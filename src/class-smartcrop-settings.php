<?php

class SmartCrop_Settings extends SmartCrop_WP_Base
{

    public function __construct()
    {
        parent::__construct();
    }

    public function admin_menu()
    {
        global $smartcrop_settings_page;
        $smartcrop_settings_page = add_management_page(
            __('Smart Image Crop AI Settings'),
            esc_html__('Smart Image Crop'),
            'manage_options',
            'smartcropai',
            array($this, 'smartcropai_settings_do_page')
        );
    }

    public function smartcropai_settings_do_page()
    {
?>
        <div id="smart_image_crop_settings"></div>
        <div id="smart_image_crop_dashboard"></div>
<?php
    }
}
