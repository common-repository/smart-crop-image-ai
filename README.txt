=== Smart Image Crop AI ===
Contributors: bcupham
Tags: resize images, crop images, images
Requires at least: 5.1.0
Tested up to: 5.8.2
Requires PHP: 5.4
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use the power of machine learning to crop images automatically. 

== Description ==

If your WordPress website crops certain image sizes, you may find this plugin useful. This plugin uses machine learning to automatically find the main focus of an image, and crop to that, regardless of the crop ratio.   

== Features ==

* Automagically crop to the "main focus" of an image, whether a face or object. 
* Preview smart crops before saving them.
* Select multiple images to smart crop or select all displayed.
* Load as many images as you want with infinite scroll loading (like Media library).
* Smart crop a specific attachment's image sizes with a smart crop button on the attachment page.
* Compatible with thumbnail regeneration and image optimization plugins (but see below).

== Installation ==

1. Install this plugin from the directory or by downloading it directly from here and uploading it.
2. Activate the plugin. 
3. All smart crop functionality can be found in Tools -> Smart Image Crop.
4. You'll need your Google Cloud Vision API key to get started, see link in settings. 

== Screenshots == 

1. The SmartCrop user interface. 
2. Image sizes selected for smart cropping. 
3. Images smart cropped. Note the smart cropped has been previewed, but not saved yet. 

== How Does It Work? ==

This plugin uses the Google Cloud Vision API to guess the perfect crop for an image. It requires a Google Cloud Vision API key, which is free and can be acquired [here](https://cloud.google.com/vision/docs/setup). As of 2022, the first 1000 requests per month are free. If you need more that is between you and our Google overlords (i.e. you'll need to give them your credit card).

== Can I automatically smart crop all images at once? ==

Yes and no. You can load all of the eligible images in the tool by scrolling down until they're all on the page, then selecting all and clicking "save and overwrite". This long-running operation may time out, or you may run out of credits with Google. If so, just refresh the page and only the un-smart cropped images will be displayed. Good luck!

== Is it Compatible With Image Optimization Plugins? ==

Yes, but you may need to recompress any smart cropped images. If a smart cropped image is derived from an image that is already compressed, it is likely to be fairly small anyway. TinyPNG keeps track of what images have changed after compression so you can recompress them. Smush and Shortpixel do not. 

== Is it Compatible with WebP? ==

Not yet. I plan to include automatic smart cropping of webp images in a future version. 

== Is it Compatible with Retina? ==

Not yet. I plan to include automatic smart cropping of retina images in a future version. 

== Can it automatically smart crop on image upload? == 

I have deliberately left out this feature to avoid conflict with other image plugins. I may add it later. 
