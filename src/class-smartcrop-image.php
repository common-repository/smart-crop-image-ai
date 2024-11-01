<?php

class SmartCrop_Image
{
    const ORIGINAL = 0;

    private $settings;
    private $id;
    private $name;
    private $wp_metadata;
    private $sizes = array();
    private $statistics = array();

    public function __construct(
        $settings,
        $id,
        $wp_metadata = null
    ) {
        $this->settings = $settings;
        $this->id = $id;
        $this->original_filename = null;
        $this->wp_metadata = $wp_metadata;
        $this->parse_wp_metadata();
    }

    private function parse_wp_metadata()
    {
        if (!is_array($this->wp_metadata)) {
            $this->wp_metadata = wp_get_attachment_metadata($this->id);
        }
        if (!is_array($this->wp_metadata)) {
            return;
        }
        if (!isset($this->wp_metadata['file'])) {
            /* No file metadata found, this might be another plugin messing with
            metadata. Simply ignore this! */
            return;
        }

        $upload_dir = wp_upload_dir();
        $path_prefix = $upload_dir['basedir'] . '/';
        $path_info = pathinfo($this->wp_metadata['file']);
        if (isset($path_info['dirname'])) {
            $path_prefix .= $path_info['dirname'] . '/';
        }

        /* Do not use pathinfo for getting the filename.
        It doesn't work when the filename starts with a special character. */
        $path_parts = explode('/', $this->wp_metadata['file']);
        $this->name = end($path_parts);
        $filename = $path_prefix . $this->name;
        $this->original_filename = $filename;

        $this->sizes[self::ORIGINAL] = new SmartCrop_Image_Size($filename);

        if (isset($this->wp_metadata['sizes']) && is_array($this->wp_metadata['sizes'])) {
            foreach ($this->wp_metadata['sizes'] as $size_name => $info) {
                // Adding an array of width/height
                $dimensions = array(
                    'width' => $info['width'],
                    'height' => $info['height'],
                );

                $url = wp_get_attachment_image_src($this->id, $size_name)[0];

                $this->sizes[$size_name] = new SmartCrop_Image_Size($path_prefix . $info['file'], $dimensions, $url);
            }
        }
    }

    public function get_id()
    {
        return $this->id;
    }

    public function get_name()
    {
        return $this->name;
    }

    public function get_original_filename()
    {
        return $this->original_filename;
    }

    public function get_wp_metadata()
    {
        return $this->wp_metadata;
    }

    public function file_type_allowed()
    {
        return in_array($this->get_mime_type(), array('image/jpeg', 'image/png'));
    }

    public function get_mime_type()
    {
        return get_post_mime_type($this->id);
    }

    public function check_for_preview_image($size_name)
    {

        $size = $this->get_image_size($size_name);
        if (file_exists(SMART_PREVIEWS_PATH . '/' . $size->name_of_file)) {
            error_log('preview image exists for ' . $this->wp_metadata['file']);
            return true;
        }
        return false;
    }

    public function get_smartcrop($size_name, $is_preview)
    {
        $size = $this->get_image_size($size_name);

        $gcv_credit = 0;

        $response = array(
            'is_preview' => $is_preview,
            'gcv_api' => $gcv_credit,
        );

        // If there's already a generated preview image, return that
        if ($this->check_for_preview_image($size_name)) {
            if ($is_preview) {

                $response['image_url'] = SMART_PREVIEWS_URL . '/' . $size->name_of_file;
            } else {

                $this->replace_size_with_preview($size_name);
                $this->update_smartcrop_meta($size_name);
                $response['image_url'] = $size->url;
            }

            return $response;
        }

        // If there's transient cache data for the size, get that
        $transient = 'smartcrop_' . $this->id . '_' . $size_name;
        $vertices = null;

        if (false !== ($value = get_transient($transient))) {
            $vertices = $value;
        } else {
            // We have no existing preview and no existing vertices cache, so go to Google
            $gcv_client = new GCV_Client();
            $vertices = $gcv_client->get_crop_hint($this->original_filename, $size);

            if (is_wp_error($vertices)) {
                return $vertices;
            }

            set_transient($transient, $vertices, MONTH_IN_SECONDS);
            $response['gcv_api'] = 1;
        }

        $crop_data = $this->create_smart_crop_image($this->original_filename, $vertices, $size_name, $is_preview);

        if (is_wp_error($crop_data)) {
            return $crop_data;
        }

        $response['image_url'] = $crop_data;

        return $response;
    }

    private function create_smart_crop_image($original_file, $crop_vertices, $size_name, $is_preview = true)
    {

        $size = $this->get_image_size($size_name);

        $cropped_file_path = $is_preview ? SMART_PREVIEWS_PATH . '/' . $size->name_of_file : $size->filename;

        $image_editor = wp_get_image_editor($original_file);

        if (is_wp_error($image_editor)) {
            return $image_editor;
        }

        $src_x = isset($crop_vertices[0]->x) ? $crop_vertices[0]->x : 0;
        $src_y = isset($crop_vertices[0]->y) ? $crop_vertices[0]->y : 0;
        $src_w = $crop_vertices[1]->x - $src_x;
        $src_h = $crop_vertices[3]->y - $src_y;

        $image_editor->crop($src_x, $src_y, $src_w, $src_h);
        $image_editor->resize($size->dimensions['width'], $size->dimensions['height'], false);

        $cropped_image_data = $image_editor->save($cropped_file_path);

        if (is_wp_error($cropped_image_data)) {
            return $cropped_image_data;
        }

        if (false === $is_preview) {
            $this->update_smartcrop_meta($size_name);
        }


        $image_url = $is_preview ? plugins_url($size->name_of_file, $cropped_file_path)
            : $size->url;

        return $image_url;
    }

    public function replace_size_with_preview($size_name)
    {

        $size = $this->get_image_size($size_name);
        $original_file_path = $size->filename;
        $preview_file = $size->name_of_file;
        $preview_file_path = SMART_PREVIEWS_PATH . '/' . $preview_file;

        if (file_exists($preview_file_path)) {

            $smartcropped_image = file_get_contents($preview_file_path);
            $result = file_put_contents($original_file_path, $smartcropped_image);

            if (is_wp_error($result) || $result == false) {
                return $result;
            }

            unlink($preview_file_path);

            return true;
        }

        return false;
    }

    public function update_smartcrop_meta($size_name)
    {

        $smartcrop_meta = get_post_meta($this->id, 'smartcropai', true);
        SmartCrop_Plugin::write_log($size_name . ' metadata is ');
        SmartCrop_Plugin::write_log($smartcrop_meta);

        if (!is_array($smartcrop_meta)) {

            $smartcrop_meta = array(
                $size_name => time(),
            );
        } else {

            $smartcrop_meta[$size_name] = time();
        }

        $result = update_post_meta($this->id, 'smartcropai', $smartcrop_meta);

        /*
        This action is being used by WPML:
        https://gist.github.com/srdjan-jcc/5c47685cda4da471dff5757ba3ce5ab1
         */
        do_action('update_smartcrop_meta', $this->id, 'smartcropai', $this->wp_metadata);
    }

    public function get_image_size($size = self::ORIGINAL, $create = false)
    {
        if (isset($this->sizes[$size])) {
            return $this->sizes[$size];
        } elseif ($create) {
            return new SmartCrop_Image_Size();
        } else {
            return null;
        }
    }

    public static function is_original($size)
    {
        return self::ORIGINAL === $size;
    }
}
