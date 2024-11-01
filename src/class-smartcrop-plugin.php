<?php

class SmartCrop_Plugin extends SmartCrop_WP_Base
{
    const VERSION = '0.9';
    const DATETIME_FORMAT = 'Y-m-d G:i:s';

    private $settings;

    public static function version()
    {
        /* Avoid using get_plugin_data() because it is not loaded early enough
        in xmlrpc.php. */
        return self::VERSION;
    }

    public function __construct()
    {
        parent::__construct();
        $this->settings = new SmartCrop_Settings();
    }

    public function init()
    {
        // Add off-center crop thumbnail for the purposes of development/testing
        if (get_site_url() == 'https://smart-image-ai.lndo.site') {
            add_image_size('two by one', 400, 200, array('right', 'bottom'));
        }

        add_filter('attachment_fields_to_edit', array($this, 'add_smartcrop_button_to_edit_media_modal_fields_area'), 99, 2);

        load_plugin_textdomain(
            self::NAME,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );

        add_action('rest_api_init', $this->get_method('add_smartcrop_meta_to_media_api'));

        add_action('rest_api_init', $this->get_method('add_smartcrop_api_routes'));

        add_filter('wp_generate_attachment_metadata', $this->get_method('delete_smartcrop_meta'), 10, 2);
    }

    public function admin_init()
    {

        // Add a regenerate button to the non-modal edit media page.
        add_action('attachment_submitbox_misc_actions', array($this, 'add_smartcrop_button_to_media_edit_page'), 99);

        // Add a regenerate link to actions list in the media list view.
        add_filter('media_row_actions', array($this, 'add_smartcrop_link_to_media_list_view'), 10, 2);

        add_action(
            'admin_enqueue_scripts',
            $this->get_method('enqueue_scripts')
        );

        $plugin = plugin_basename(
            dirname(dirname(__FILE__)) . '/smart-crop-image-ai.php'
        );

        add_filter(
            "plugin_action_links_$plugin",
            $this->get_method('add_plugin_links')
        );
    }

    /**
     * Helper function to create a URL to smartcrop a single image.
     *
     * @param int $id The attachment ID that should be smart cropped.
     *
     * @return string The URL to the admin page.
     */
    public function create_page_url($id)
    {
        return add_query_arg('page', 'smartcropai', admin_url('tools.php')) . '&attachmentId=' . $id;
    }

    /**
     * Add a smartcrop button to the submit box on the non-modal "Edit Media" screen for an image attachment.
     */
    public function add_smartcrop_button_to_media_edit_page()
    {
        global $post;

        echo '<div class="misc-pub-section">';
        echo '<a href="' . esc_url($this->create_page_url($post->ID)) . '" class="button-secondary button-large" title="' . esc_attr(__('Smartcrop the thumbnails for this single image', 'smartcropai')) . '">' . _x('Smartcrop Thumbnails', 'action for a single image', 'smartcropai') . '</a>';
        echo '</div>';
    }

    public function add_smartcrop_button_to_edit_media_modal_fields_area($form_fields, $post)
    {

        $form_fields['smartcropai'] = array(
            'label' => '',
            'input' => 'html',
            'html' => '<a href="' . esc_url($this->create_page_url($post->ID)) . '" class="button-secondary button-large" title="' . esc_attr(__('Smartcrop the thumbnails for this single image', 'smartcropai')) . '">' . _x('Smartcrop Thumbnails', 'action for a single image', 'smartcropai') . '</a>',
            'show_in_modal' => true,
            'show_in_edit' => false,
        );

        return $form_fields;
    }

    public function add_smartcrop_link_to_media_list_view($actions, $post)
    {

        $actions['smartcrop_thumbnails'] = '<a href="' . esc_url($this->create_page_url($post->ID)) . '" title="' . esc_attr(__('Smartcrop the thumbnails for this single image', 'smartcropai')) . '">' . _x('Smartcrop', 'action for a single image', 'smartcropai') . '</a>';

        return $actions;
    }

    public function add_plugin_links($current_links)
    {
        $additional = array(
            'smartcropai' => sprintf(
                '<a href="tools.php?page=smartcropai">%s</a>',
                esc_html__('Get Started', 'smartcropai')
            ),
        );
        return array_merge($additional, $current_links);
    }

    public function add_smartcrop_meta_to_media_api()
    {
        register_rest_field(
            'attachment',
            'smartcropai',
            array(
                'get_callback' => $this->get_method('get_smartcrop_custom_meta'),
                'update_callback' => null,
                'schema' => null,
            )
        );
    }

    public function get_smartcrop_custom_meta($object)
    {

        $the_meta = get_post_meta($object['id'], 'smartcropai', true);

        if (is_null($the_meta) || empty($the_meta)) {
            return null;
        }
        return $the_meta;
    }

    public function delete_smartcrop_meta($metadata, $attachment_id)
    {
        if (is_array(get_post_meta($attachment_id, 'smartcropai'))) {
            update_post_meta($attachment_id, 'smartcropai', null);
        }
        $sizes = wp_get_registered_image_subsizes();
        foreach ($sizes as $key => $size) {
            $transient = 'smartcrop_' . $attachment_id . '_' . $key;
            delete_transient($transient);
        }

        return $metadata;
    }

    public function add_smartcrop_api_routes()
    {
        register_rest_route('smart-image-crop/v1', '/proxy', array(
            // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
            'methods' => WP_REST_Server::READABLE,
            // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
            'callback' => $this->get_method('smartcrop_via_api'),
            'permission_callback' => $this->get_method('smartcrop_proxy_permissions_check'),
        ));
        register_rest_route('smart-image-crop/v1', '/settings', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => $this->get_method('smartcrop_api_get_settings'),
            'permission_callback' => $this->get_method('smartcrop_settings_permissions_check'),
        ));
        register_rest_route('smart-image-crop/v1', '/settings', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => $this->get_method('smartcrop_api_update_settings'),
            'permission_callback' => $this->get_method('smartcrop_settings_permissions_check'),
        ));
    }

    // get saved settings from WP DB
    public function smartcrop_api_get_settings($request)
    {
        $api_key = get_option('smartcropai_api_key');
        $response = new WP_REST_RESPONSE(array(
            'success' => true,
            'value' => array(
                'apiKey' => !$api_key ? '' : $api_key,
            ),
        ), 200);
        $response->set_headers(array('Cache-Control' => 'no-cache'));
        return $response;
    }

    // save settings to WP DB
    public function smartcrop_api_update_settings($request)
    {
        $json = $request->get_json_params();
        // store the values in wp_options table
        $updated_api_key = update_option('smartcropai_api_key', $json['apiKey']);
        $response = new WP_REST_RESPONSE(array(
            'success' => $updated_api_key,
            'value' => $json,
        ), 200);
        $response->set_headers(array('Cache-Control' => 'no-cache'));
        return $response;
    }

    // check permissions
    public function smartcrop_settings_permissions_check()
    {
        // Restrict endpoint to only users who have the capability to manage options.
        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error('rest_forbidden', esc_html__('You do not have permissions to view this data.', 'smartcropai'), array('status' => 401));
    }

    public function smartcrop_proxy_permissions_check()
    {
        // Restrict endpoint to only users who have the capability to manage options.
        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error('rest_forbidden', esc_html__('You do not have permission to use this.', 'smart-image-crop'), array('status' => 401));
    }

    public function smartcrop_via_api($request)
    {
        // $this::write_log($request);

        if (!$request->get_query_params()) {
            return new WP_REST_RESPONSE(array(
                'success' => false,
                'error' => 'No query parameters',
            ), 500);
        }

        $params = $request->get_query_params();

        if (!isset($params['size']) || !isset($params['attachment'])) {
            return new WP_REST_RESPONSE(array(
                'success' => false,
                'error' => 'No size or attachment parameters',
            ), 500);
        }

        $size_name = sanitize_text_field($params['size']);
        $attachment_id = sanitize_text_field($params['attachment']);
        $is_preview = $params['pre'] == 0 ? false : true;

        $metadata = wp_get_attachment_metadata($attachment_id);

        $smartcrop_image = new SmartCrop_Image($this->settings, $attachment_id, $metadata);

        if (!$smartcrop_image->get_image_size($size_name)) {
            return new WP_Error('no_file', esc_html__('No image for that size.', 'smartcropai'), array('status' => 500));
        }

        $result = $smartcrop_image->get_smartcrop($size_name, $is_preview);

        if (is_wp_error($result)) {
            return $result;
        }

        // The wp_update_attachment_metadata call is thrown because the
        // dimensions of the original image can change. This will then
        // trigger other plugins and can result in unexpected behaviour and
        // further changes to the image. This may require another approach.
        // Note that as of WP 5.3 it is advised to not hook into this filter
        // anymore, so other plugins are less likely to be triggered.
        wp_update_attachment_metadata($attachment_id, $smartcrop_image->get_wp_metadata());

        // $this::write_log($result);

        $response = new WP_REST_RESPONSE(array(
            'success' => true,
            'body' => array(
                'smartcropai' => $result,
            ),
        ), 200);

        $response->set_headers(array('Cache-Control' => 'no-cache'));

        return $response;
    }

    public function enqueue_scripts($hook)
    {

        // only load scripts on dashboard and settings page
        global $smartcrop_settings_page;
        if ($hook != 'index.php' && $hook != $smartcrop_settings_page) {
            return;
        }

        if (in_array($_SERVER['REMOTE_ADDR'], array('172.23.0.8', '::1'))) {
            // DEV React dynamic loading
            $js_to_load = 'http://localhost:3000/static/js/bundle.js';
        } else {
            $react_app_manifest = file_get_contents(__DIR__ . '/react-frontend/build/asset-manifest.json');
            if ($react_app_manifest !== false) {
                $manifest_json = json_decode($react_app_manifest, true);
                $main_css = $manifest_json['files']['main.css'];
                $main_js = $manifest_json['files']['main.js'];
                $js_to_load = plugin_dir_url(__FILE__) . '/react-frontend/build' . $main_js;

                $css_to_load = plugin_dir_url(__FILE__) . '/react-frontend/build' . $main_css;
                wp_enqueue_style('smart_image_crop_styles', $css_to_load);
            }
        }

        wp_enqueue_script('smart_image_crop_react', $js_to_load, '', mt_rand(10, 1000), true);
        wp_localize_script('smart_image_crop_react', 'smart_image_crop_ajax', array(
            'urls' => array(
                'proxy' => rest_url('smart-image-crop/v1/proxy'),
                'settings' => rest_url('smart-image-crop/v1/settings'),
                'previews_url' => SMART_PREVIEWS_URL,
                'media' => rest_url('wp/v2/media'),
            ),
            'nonce' => wp_create_nonce('wp_rest'),
            'imageSizes' => wp_get_registered_image_subsizes(),
        ));
    }

    public static function write_log($log)
    {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}
