<?php

class SmartCrop_Image_Size
{
    public $filename;
    public $dimensions;
    public $name_of_file;
    public $image_url;
    public $meta = array();

    public function __construct($filename = null, $dimensions = null, $url = null)
    {
        $this->filename = $filename;
        $this->dimensions = $dimensions;
        $this->url = $url;
        $this->get_name_of_file();
    }

    public function get_name_of_file()
    {
        /* Do not use pathinfo for getting the filename.
        It doesn't work when the filename starts with a special character. */
        $path_parts = explode('/', $this->filename);
        $this->name_of_file = end($path_parts);
    }
}
