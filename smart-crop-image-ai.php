<?php

/**
 * Plugin Name: Smart Crop Image AI
 * Description: Use the power of machine learning to crop your WordPress images perfectly.
 * Version: 1.0
 * Author: Ben Upham
 * Text Domain: smartcropai
 * License: GPLv2 or later
 */

require dirname(__FILE__) . '/src/class-smartcrop-wp-base.php';
require dirname(__FILE__) . '/src/class-smartcrop-image-size.php';
require dirname(__FILE__) . '/src/class-smartcrop-image.php';
require dirname(__FILE__) . '/src/class-smartcrop-settings.php';
require dirname(__FILE__) . '/src/class-smartcrop-plugin.php';
require dirname(__FILE__) . '/src/class-gcv-client.php';

if (!defined('SMART_PREVIEWS_URL')) {
    define('SMART_PREVIEWS_URL', plugin_dir_url(__FILE__) . 'preview-images');
}
if (!defined('SMART_PREVIEWS_PATH')) {
    define('SMART_PREVIEWS_PATH', plugin_dir_path(__FILE__) . 'preview-images');
}

$smartcrop_plugin = new SmartCrop_Plugin();
