<?php

abstract class SmartCrop_WP_Base
{
    const NAME = 'smartcrop-images';
    const PREFIX = 'smartcropai';

    private static $wp_version;

    public static function wp_version()
    {
        if (is_null(self::$wp_version)) {
            // Try to use unmodified version
            include ABSPATH . WPINC . '/version.php';
            if (isset($wp_version)) {
                self::$wp_version = $wp_version;
            } else {
                self::$wp_version = $GLOBALS['wp_version'];
            }
        }
        return self::$wp_version;
    }

    public static function check_wp_version($version)
    {
        return floatval(self::wp_version()) >= $version;
    }

    protected static function get_prefixed_name($name)
    {
        return self::PREFIX . '_' . $name;
    }

    public function __construct()
    {
        add_action('init', $this->get_method('init'));
        if (is_admin()) {
            add_action('admin_init', $this->get_method('admin_init'));
            add_action('admin_menu', $this->get_method('admin_menu'));
        }
    }

    protected function get_method($name)
    {
        return array($this, $name);
    }

    protected function get_static_method($name)
    {
        return array(get_class($this), $name);
    }

    protected function get_user_id()
    {
        return get_current_user_id();
    }

    public function init()
    {
    }

    public function xmlrpc_init()
    {
    }

    public function ajax_init()
    {
    }

    public function admin_init()
    {
    }

    public function admin_menu()
    {
    }
}
