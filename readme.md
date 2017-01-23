# Polylang Add-on: Translate existing media

**Contributors:** [Teemu Suoranta](https://github.com/TeemuSuoranta)

**Tags:** polylang, media, attachments, translations

**License:** GPLv2 or later

![screen shot 2017-01-23 at 17 02 03](https://cloud.githubusercontent.com/assets/9577084/22209080/d93723f6-e18d-11e6-918c-c3cf390f3c70.png)

## Description

Polylang Translate existing media is an add-on for the multilingual WordPress plugin [Polylang](https://wordpress.org/plugins/polylang/). This add-on let's you bulk translate and replace all existing media in content, featured image and meta fields you enable translations in media. This plugin is based on my other Polylang plugin [polylang-copy-content](https://github.com/aucor/polylang-copy-content).

When to use:

 * When turning existing site to multi-lingual
 * When enabling media translations when you already have media uploaded and added to content


Basic feature list:

 * Translate all media (images and galleries) that are inside content, meta fields and featured image
 * Takes care all post types (that are not 'attachment', 'revision', 'acf-field', 'acf-field-group', 'nav_menu_item', 'polylang_mo')
 * Image translations are linked automatically
 * You can add your own
 * Uses Polylang's functions, no messing around

**This is open source and I cannot give you any guarantees, though it has worked for me in many projects. Please, report issues and contribute!**


## Installation

How-to use (takes around 5 minutes):

 * Take a backup of your database
 * Download plugin and activate (you will need Polylang active)
 * Enable Media translations in Polylang Settings
 * Set all content without language to default language from "Languages" admin page
 * Click "1. Translate the whole media library" from the admin notice
 * Click through steps (50 posts at a time). Each step will take some time.
 * Click "2. Translate existing images in content" from the admin notice
 * Click through steps (50 posts at a time). Each step will take some time.
 * Deactivate and delete plugin when you have gone through all the steps

**Composer:**
```
$ composer aucor/polylang-translate-existing-media
```
**With composer.json:**
```
{
  "require": {
    "aucor/polylang-translate-existing-media": "*"
  },
  "extra": {
    "installer-paths": {
      "htdocs/wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
  }
}
```

## Filters

**Add your own custom fields that have images saved as IDs:**

```
function prefix_custom_fields_to_translate($custom_fields) {
	// return keys of your custom fields with image id
  return array(
    'my_custom_image_field',
    'other_custom_image_field'
  );
}
add_filter( 'polylang-translate-existing-media-custom-fields-with-image-id', 'prefix_custom_fields_to_translate' );
```


## Issues and feature whishlist

**Issues:**

(No known issues, yet)

 **To-do:**

 * Include terms and their custom fields
 * Add screenshot
 * Replacing count might be off, make it more informative

## Changelog

### 0.2

 * New Feature: Translate whole media library
 * New Feature: API for custom_fields that save the image as ID
 * New Feature: Add filter to excluded post types `polylang-translate-existing-media-skip-post-types`
 * Improvement: Include all post_status
 * Bugfix: Don't copy featured image, just translate the existing one
