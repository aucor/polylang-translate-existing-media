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

## Issues and feature whishlist

**Issues:**

(No known issues, yet)

 **Feature whishlist:**

 * Create API / Filter to translate meta fields (like ACF image fields) where image is saved as ID
