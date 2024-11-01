<?php

class GCV_Client
{

    public function get_crop_hint($original_file, $size)
    {

        $width = $size->dimensions['width'];
        $height = $size->dimensions['height'];
        $aspect_ratio = $width / $height;
        $img = file_get_contents($original_file);
        $data = base64_encode($img);
        $baseurl = 'https://vision.googleapis.com/v1/images:annotate';
        $apikey = get_option('smartcropai_api_key');
        $body = array(
            'requests' => array(
                array(
                    'features' => array(
                        array(
                            'type' => 'CROP_HINTS',
                        ),
                    ),
                    'image' => array(
                        'content' => $data,
                        // 'source' => array(
                        //   'imageUri' => $data
                        //   )
                    ),
                    'imageContext' => array(
                        'cropHintsParams' => array(
                            'aspectRatios' => array(
                                $aspect_ratio,
                            ),
                        ),
                    ),
                ),
            ),
        );

        $request = wp_remote_post($baseurl . '?key=' . $apikey, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($body),
            'method' => 'POST',
            'data_format' => 'body',
        ));

        if (is_wp_error($request)) {
            return $request;
        }

        $data = json_decode(wp_remote_retrieve_body($request));
        if (isset($data->error)) {
            $response_code = $data->error->code;
            $response_message = $data->error->message;
        } else {
            $response_code = wp_remote_retrieve_response_code($request);
            $response_message = wp_remote_retrieve_response_message($request);
        }

        if (200 != $response_code && !empty($response_message)) {
            return new WP_Error($response_code, $response_message, $data);
        } elseif (200 != $response_code) {
            return new WP_Error($response_code, "Uknown error", $data);
        } else {

            $vertices = $data->responses[0]->cropHintsAnnotation->cropHints[0]->boundingPoly->vertices;
            return $vertices;
        }
    }
}
