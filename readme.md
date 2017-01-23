# Polylang Add-on: Translate existing media

**Contributors:** [Teemu Suoranta](https://github.com/TeemuSuoranta)

**Tags:** polylang, media, attachments, translations

**License:** GPLv2 or later

## Description

Polylang Translate existing media is an add-on for the multilingual WordPress plugin [Polylang](https://wordpress.org/plugins/polylang/). This add-on let's you bulk translate and replace all existing media in content, featured image and meta fields you enable translations in media. This plugin is based on my other Polylang plugin [polylang-copy-content](https://github.com/aucor/polylang-copy-content).


Basic feature list:

 * Translate all media (images and galleries) that are inside content, meta fields and featured image
 * Takes care all post types (that are not 'attachment', 'revision', 'acf-field', 'acf-field-group', 'nav_menu_item', 'polylang_mo')
 * Image translations are linked automatically
 * Uses Polylang's functions, no messing around

**The plugin is still in test phase and I'd like to get feedback and tackle possible issues. Please, report issues and contribute!**


## Installation

How-to use:

 * Take a backup of your database
 * Download plugin and activate (you will need Polylang active)
 * Enable Media translations in Polylang Settings
 * Set all content without language to default language from "Languages" admin page
 * Click "Translate and replace" from the admin notice
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
