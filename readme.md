# Polylang Add-on: Translate existing media

**Contributors:** [Teemu Suoranta](https://github.com/TeemuSuoranta)

**Tags:** polylang, media, attachments, translations

**License:** GPLv2 or later

## Description

Polylang Translate existing media is an add-on for the multilingual WordPress plugin [Polylang](https://wordpress.org/plugins/polylang/). This add-on let's you bulk translate and replace all existing media in content, featured image and meta fields you enable translations in media. This plugin is based on my other Polylang plugin [polylang-copy-content](https://github.com/aucor/polylang-copy-content).


Basic feature list:

 * Copy title, content and attachments for new translation
 * Choose the language you want to copy from (make translation from the translated version's editor)
 * Get useful translation markup for captions and title like (es translation) to be overwritten
 * Media translation works for images, captions, galleries and featured image (if you use media translations)
 * Use various filters to modify copied content in code (to be documented and expanded)
 * Translations are done with Polylang's functions, no messing around

**The plugin is still in test phase and I'd like to get feedback and tackle possible issues. Please, report issues and contribute!**


## Installation

Basic feature list:

 * Take a backup of your database
 * Download plugin and activate (you will need Polylang active)
 * Enable Media translations in Polylang Settings
 * Set all content without language to default language from "Languages" admin page
 * Click "Translate and replace" from the admin notice
 * Click through steps (50 posts at a time). Each step will take some time.
 * Deactivate and delete plugin when you have gone through all the steps


## Issues and feature whishlist

**Issues:**

(No known issues, yet)

 **Feature whishlist:**

 * Create API / Filter to translate meta fields (like ACF image fields) where image is saved as ID

## Changelog

### 0.1.0 - Github launch
 * It's working