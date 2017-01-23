<?php
/*
Plugin Name: Polylang Add-on: Translate existing media
Plugin URI: 
Version: 0.2.0
Author: Aucor Oy
Author URI: https://github.com/aucor
Description: Translate and replace media in existing posts, pages and cpts
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: polylang-translate-existing-media
*/

defined( 'ABSPATH' ) or die( 'Get out of here!' );

class PolylangTranslateExistingMedia {

	private $updated;
	private $images_translated;
	private $ignored;
	private $admin_notice;
	private $custom_fields;

	/**
	 * Constructor
	 */

	public function __construct() {

		if (defined('DOING_AJAX') && DOING_AJAX) {
			return; // don't mess up ajax calls
		}

		// Check that Polylang is active
		global $polylang;
		
		if (isset($polylang)) {

			$this->updated = 0;
			$this->images_translated = 0;
			$this->ignored = 0;

			add_action('admin_init', array(&$this, 'translate_existing_media'));
			add_filter('wp_generate_attachment_metadata', array(&$this, 'wp_generate_attachment_metadata'), 10, 2);
			add_action('admin_notices',  array(&$this, 'admin_notice'));
		}
	}

	/**
	 * Main
	 */

	function translate_existing_media() {

		// include custom fields that have images saved as IDs
		$this->custom_fields = apply_filters('polylang-translate-existing-media-custom-fields-with-image-id', array());

		if(!PLL()->model->options['media_support']) {

			$this->admin_notice = 'Enable media support to start. Remember to then "set all content to default language"';

		} elseif( isset($_GET["polylang-translate-existing-media-library"]) ) {

			// translate whole media library

			$paged = ($_GET["polylang-translate-existing-media-library-p"]) ? $_GET["polylang-translate-existing-media-library-p"] : 1; // Paged because running too many posts at a time causes memory overflow

			$args = array( 
				'post_type' => 'attachment',
				'posts_per_page' => 50, // Make me less if PHP can't handle it
				'paged' => $paged,
				'orderby' => 'title',
				'order' => 'DESC',
				'post_status' => true,
				'lang' => pll_default_language(), // Assumes you did set all content to default language as instructed, if not, there's nothing to query
			);

			$attachment_query = new WP_Query( $args );
			$max_num_pages = $attachment_query->max_num_pages;
			$languages = pll_languages_list();

			// remove default language from language list
			if(($key = array_search(pll_default_language(), $languages)) !== false) {
				unset($languages[$key]);
			}
			
			while ( $attachment_query->have_posts() ) : $attachment_query->the_post();

				$lang_slug = pll_default_language();
				$post_parent = wp_get_post_parent_id($attachment_query->post->ID);

				// Go through all languages (except the default language)
				foreach ($languages as $new_lang) {

					$post_parent_new = ($post_parent !== 0) ? pll_get_post($post_parent, $new_lang) : 0; // Translate post parent
					$translated_attachment_id = $this->translate_attachment($attachment_query->post->ID, $new_lang, $post_parent_new);

					if($translated_attachment_id !== $attachment_query->post->ID) {
						$this->updated++;
					}

				}

			endwhile;
			wp_reset_query();

			
			$this->admin_notice = $this->updated . ' attachment translations created';
			$next_page = ($paged < $max_num_pages) ? $paged + 1 : null;

			if(!empty($next_page)) {
				$full_url = "//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
				$full_url = str_replace('polylang-translate-existing-media-library-p=' . $paged, 'polylang-translate-existing-media-library-p=' . $next_page, $full_url);
				$this->admin_notice .= '<a class="button" style="margin:-.15rem 0 .25rem .25rem" href="' . $full_url . '">Continue media library translation (step ' . $next_page . ' / ' . $max_num_pages . ')</a>';
			} else {

				$full_url = "//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
				$full_url = str_replace('polylang-translate-existing-media-library=start&polylang-translate-existing-media-library-p=' . $paged, '', $full_url);
				$full_url .= (strpos($full_url, '?') === false) ? '?' : '&';
				$replace_full_url = $full_url . 'polylang-translate-existing-media=start&polylang-translate-existing-media-p=1';

				$this->admin_notice .= '<br /><br />Done! Media library is translated. Next, replace all existing images from content. <a class="button" style="margin:-.15rem 0 .25rem 0" href="' . $replace_full_url . '">2. Translate existing images in content</a>';
			}


		} elseif( isset($_GET["polylang-translate-existing-media"]) ) {

			// replace in existing content

			global $wpdb;

			$post_types = get_post_types();
			$skip_post_types = apply_filters( 'polylang-translate-existing-media-skip-post-types', array('attachment', 'revision', 'acf-field', 'acf-field-group', 'nav_menu_item', 'polylang_mo' ));
			$post_types = array_diff($post_types, $skip_post_types);

			// remove non translated post types from the mix
			foreach ($post_types as $key => $pt) {
				if (!PLL()->model->is_translated_post_type($pt)) {
					unset($post_types[$key]);
				}
			}

			$paged = ($_GET["polylang-translate-existing-media-p"]) ? $_GET["polylang-translate-existing-media-p"] : 1; // Paged because running too many posts at a time causes memory overflow

			$args = array( 
				'post_type' => $post_types,
				'posts_per_page' => 50, // Make me less if PHP can't handle it
				'paged' => $paged,
				'orderby' => 'title',
				'order' => 'DESC',
				'post_status' => true,
				'lang' => ''
			);

			$query = new WP_Query( $args );
			$max_num_pages = $query->max_num_pages;
			

			while ( $query->have_posts() ) : $query->the_post();

				$lang_slug = pll_get_post_language($query->post->ID);
				$this_post_was_updated = false;

				// deal with content
				$the_content = get_the_content();
				$altered_content = $this->replace_media_in_content($the_content, $query->post, $lang_slug);

				// deal with custom fields
				foreach ($this->custom_fields as $custom_field) {
					$updated_custom_field = $this->replace_media_in_custom_field_with_image_id($custom_field, $query->post, $lang_slug);

					if($this_post_was_updated !== true && $updated_custom_field === true) {
						$this_post_was_updated = true;
					}

				}
		

				if($the_content !== $altered_content) {

					// update with wpdb to avoid revisions and changes to modified dates etc

					$wpdb->update(
						$wpdb->posts,
						array(
							'post_content' => $altered_content // data
						),
						array(
							'ID' => $query->post->ID // where
						),
						array(
							'%s' // data format
						),
						array(
						'%d' // where format
						)
					);

					$this_post_was_updated = true;

				}
				
				// deal with metadata
				$metadata = get_metadata('post', $query->post->ID);
				foreach ($metadata as $key => $value) {

					// skip hidden fields "_meta_key"
					if(mb_substr($key, 0, 1) == '_') {
						continue;
					}

					// skip serialized data
					if(mb_strlen($value[0]) > 2 && mb_substr($value[0], 0, 2) == 'a:') {
						continue;
					}

					// skip empty values
					if(empty($value[0])) {
						continue;
					}

					// if you have pure image ID meta fields, this won't work
					if( mb_strpos($value[0], '<img ') !== false || mb_strpos($value[0], '[gallery ') !== false ) {

						$value_altered = $this->replace_media_in_content($value[0], $query->post, $lang_slug);

						// update if there are changes
						if($value[0] !== $value_altered) {
							update_post_meta( $query->post->ID, $key, $value_altered, $value[0] );
							$this_post_was_updated = true;
						}
					}
				}

				// deal with featured image
				$post_thumbnail_id = get_post_thumbnail_id();
				if(!empty($post_thumbnail_id)) {
					$new_post_thumbnail_id = $this->translate_attachment($post_thumbnail_id, $lang_slug, $query->post->ID);
					if($new_post_thumbnail_id !== $post_thumbnail_id) {
						set_post_thumbnail($query->post, $new_post_thumbnail_id);
					}
				}

				if($this_post_was_updated) {
					$this->updated++;
				} else {
					$this->ignored++;
				}

			endwhile;
			wp_reset_query();

			
			$this->admin_notice = $this->updated . ' posts updated, ' . $this->images_translated . ' images translated, ' . $this->ignored . ' posts ignored';


			$next_page = ($paged < $max_num_pages) ? $paged + 1 : null;

			if(!empty($next_page)) {
				$full_url = "//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
				$full_url = str_replace('polylang-translate-existing-media-p=' . $paged, 'polylang-translate-existing-media-p=' . $next_page, $full_url);
				$this->admin_notice .= '<a class="button" style="margin:-.15rem 0 .25rem .25rem" href="' . $full_url . '">Continue replacing existing images in content (step ' . $next_page . ' / ' . $max_num_pages . ')</a>';
			} else {
				$this->admin_notice .= '<br /><br />Done! You can (and should) deactivate this plugin now!';
			}

		} else {

			$full_url = "//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
			$full_url .= (strpos($full_url, '?') === false) ? '?' : '&';

			$replace_full_url = $full_url . 'polylang-translate-existing-media=start&polylang-translate-existing-media-p=1';
			$translate_full_url = $full_url . 'polylang-translate-existing-media-library=start&polylang-translate-existing-media-library-p=1';
			
			$this->admin_notice = 'Start bulk translating. Remember to first set all content without language to default language from Languages. <b>Take a backup for your database!</b> There is no return. ';

			if(!empty($this->custom_fields)) {
				$this->admin_notice .= '<br /><br />You have added following custom fields that have image IDs: <span style="display:block;background: #f1f1f1;padding:.5rem;margin-bottom:1rem;">' . esc_attr( implode(', ', $this->custom_fields)) . '</span>';
			}

			$this->admin_notice .= '<a class="button" style="margin:-.15rem .5rem .25rem 0" href="' . $translate_full_url . '">1. Translate the whole media library</a><a class="button" style="margin:-.15rem 0 .25rem 0" href="' . $replace_full_url . '">2. Translate existing images in content</a>';

		}
	}

	/**
	 * Admin message displayer
	 */

	function admin_notice() {
			?>
			<div class="updated">
				<p><b>Polylang Add-on: Translate existing media</b><br />
				<?php echo $this->admin_notice; ?>
				</p>
			</div>
		<?php
	}

	/**
	 * Replace media in content
	 *
	 * @param obj post the new translated post
	 * @param string content
	 * @param string lang slug
	 *
	 */
	function replace_media_in_content($content, $post, $lang_slug) {

		$content = $this->replace_content_img($content, $post, $lang_slug);
		$content = $this->replace_content_caption($content, $post, $lang_slug);
		$content = $this->replace_content_gallery($content, $post, $lang_slug);

		return $content;

	}

	/**
	 * Replace images in content with translated versions
	 *
	 * @param string $content html post content
	 * @param obj $post current post object
	 * @param string $new_lang_slug slug of translated language
	 *
	 * @return string filtered content
	 */

	function replace_content_img($content, $post, $new_lang_slug) {

		// get all images in content (full <img> tags)
		preg_match_all('/<img[^>]+>/i', $content, $img_array);

		// no images in content
		if(empty($img_array))
			return $content;

		// prepare nicer array structure
		$img_and_meta = array();
		for ($i=0; $i < count($img_array[0]); $i++) { 
			$img_and_meta[$i] = array('tag' => $img_array[0][$i]);
		}

		foreach($img_and_meta as $i=>$arr) {

			// get classes
			preg_match('/ class="([^"]*)"/i', $img_array[0][$i], $class_temp);
			$img_and_meta[$i]['class'] = !empty($class_temp) ? $class_temp[1] : '';

			// only proceed if image is created by WordPress (has wp-image-{ID} class)
			if(!strstr($img_and_meta[$i]['class'], 'wp-image-'))
				continue;

			// get the attachment id
			preg_match('/wp-image-(\d+)/i', $img_array[0][$i], $id_temp);

			if(empty($id_temp))
				continue;

			$img_and_meta[$i]['id'] = (int) $id_temp[1];

			$attachment = get_post($img_and_meta[$i]['id']);

			// check if given ID is really attachment (or copied from some other WordPress)
			if(empty($attachment) || $attachment->post_type !== 'attachment')
				continue;
			
			$img_and_meta[$i]['new_id'] = $this->translate_attachment($img_and_meta[$i]['id'], $new_lang_slug, $post->ID);

			// check if already in right language
			if($img_and_meta[$i]['new_id'] == $img_and_meta[$i]['id']) {
				continue;
			}

			// create new class clause (don't want to risk replacing something else that has "wp-image-")
			$img_and_meta[$i]['new_class'] = preg_replace('/wp-image-(\d+)/i', 'wp-image-' . $img_and_meta[$i]['new_id'], $img_and_meta[$i]['class']);
			
			// create new tag that is ready to replace the original
			$img_and_meta[$i]['new_tag'] = preg_replace('/class="([^"]*)"/i', ' class="' . $img_and_meta[$i]['new_class'] . '"', $img_and_meta[$i]['tag']);

			// replace image inside content
			$content = str_replace($img_and_meta[$i]['tag'], $img_and_meta[$i]['new_tag'], $content);

			// replace links to attachment page
			$attachment_permalink = get_permalink( $attachment->ID );
			if(strpos($content, $attachment_permalink) !== false) {
				$new_attachment_permalink = get_permalink( $img_and_meta[$i]['new_id'] );
				$content = str_replace($attachment_permalink, $new_attachment_permalink, $content);

				// replace rel part as well
				$content = str_replace('rel="attachment wp-att-' . $attachment->ID . '"', 'rel="attachment wp-att-' . $img_and_meta[$i]['new_id'] . '"', $content);
			}
		}

		return $content;

	}

	/**
	 * Replace caption shortcodes in content by setting correct attachment information
	 * 
	 * The <img> tags inside shortcode are replaced already by replace_content_img function
	 *
	 * @param string $content html post content
	 * @param string $new_lang_slug slug of translated language
	 *
	 * @return string filtered content
	 */

	function replace_content_caption($content, $post, $new_lang_slug) {

		preg_match_all('/\[caption(.*?)\](.*?)\[\/caption\]/i', $content, $caption_array);
		
		// no captions in content
		if(empty($caption_array))
			return $content;

		// prepare nicer array structure
		$caption_and_meta = array();

		for ($i=0; $i < count($caption_array[0]); $i++) { 
			$caption_and_meta[$i] = array('shortcode' => $caption_array[0][$i]);
		}

		foreach($caption_and_meta as $i=>$arr) {

			// get ids (comma separated list)
			preg_match('/ id="([^"]*)"/i', $caption_and_meta[$i]['shortcode'], $ids_temp);
			$caption_and_meta[$i]['id'] = !empty($ids_temp) ? $ids_temp[1] : '';


			// only proceed if id is in right format (attachment_{ID})
			if(!strstr($caption_and_meta[$i]['id'], 'attachment_'))
				continue;

			// get the attachment id
			preg_match('/attachment_(\d+)/i', $caption_and_meta[$i]['id'], $attachment_id_temp);

			if(empty($attachment_id_temp))
				continue;

			$caption_and_meta[$i]['attachment_id'] = (int) $attachment_id_temp[1];

			$attachment = get_post($caption_and_meta[$i]['attachment_id']);

			// check if given ID is really attachment (or copied from some other WordPress)
			if(empty($attachment) || $attachment->post_type !== 'attachment')
				continue;
			
			$caption_and_meta[$i]['new_attachment_id'] = $this->translate_attachment($caption_and_meta[$i]['attachment_id'], $new_lang_slug, $post->ID);

			// create new id clause (don't want to risk replacing something else that has "attachment_")
			$caption_and_meta[$i]['new_id'] = preg_replace('/attachment_(\d+)/i', 'attachment_' . $caption_and_meta[$i]['new_attachment_id'], $caption_and_meta[$i]['id']);

			// create new shortcode that is ready to replace the original
			$caption_and_meta[$i]['new_shortcode'] = preg_replace('/ id="([^"]*)"/i', ' id="' . $caption_and_meta[$i]['new_id'] . '"', $caption_and_meta[$i]['shortcode']);

			// add translation mark in caption by removing original and replacing it with the new attachment's caption
			preg_match('/ \/>(.*?)\[\/caption\]/i', $caption_and_meta[$i]['new_shortcode'], $txt_temp);
			$caption_and_meta[$i]['txt'] = !empty($txt_temp) ? $txt_temp[1] : '';

			if(!empty($caption_and_meta[$i]['txt'])) {

				$new_attachment = get_post($caption_and_meta[$i]['new_attachment_id']);

				$new_caption = !empty($new_attachment->post_excerpt) ? $new_attachment->post_excerpt : '';

				$caption_and_meta[$i]['new_txt'] = apply_filters( 'polylang_addon_copy_content_filter_caption_txt', $new_caption, $new_attachment, $new_lang_slug );

				if(!empty($caption_and_meta[$i]['new_txt'])) {

					// replace the caption in the embedded caption
					$caption_and_meta[$i]['new_shortcode'] = preg_replace('/ \/>(.*?)\[\/caption\]/i', '/>' . $caption_and_meta[$i]['new_txt'] . '[/caption]', $caption_and_meta[$i]['new_shortcode']);
				}

			}

			// replace image inside content
			$content = str_replace($caption_and_meta[$i]['shortcode'], $caption_and_meta[$i]['new_shortcode'], $content);

		}

		return $content;

	}

	/**
	 * Replace gallery shortcodes in content by translating attachments
	 *
	 * @param string $content html post content
	 * @param string $new_lang_slug slug of translated language
	 *
	 * @return string filtered content
	 */

	function replace_content_gallery($content, $post, $new_lang_slug) {

		preg_match_all('/\[gallery (.*?)\]/i', $content, $gallery_array);
		
		// no galleries in content
		if(empty($gallery_array))
			return $content;

		// prepare nicer array structure
		$gallery_and_meta = array();
		for ($i=0; $i < count($gallery_array[0]); $i++) { 
			$gallery_and_meta[$i] = array('shortcode' => $gallery_array[0][$i]);
		}

		foreach($gallery_and_meta as $i=>$arr) {

			// get ids (comma separated list)
			preg_match('/ ids="([^"]*)"/i', $gallery_and_meta[$i]['shortcode'], $ids_temp);
			$gallery_and_meta[$i]['ids'] = !empty($ids_temp) ? $ids_temp[1] : '';

			// ids empty, skip this shortcode
			if(empty($gallery_and_meta[$i]['ids']))
				continue;

			// make id list into array for easier handeling
			$gallery_ids_array = explode(',', str_replace(' ', '', $gallery_and_meta[$i]['ids']));

			$gallery_ids_new_array = array();

			// go through all images and get ids of translated attachments
			foreach ($gallery_ids_array as $id) {
				array_push($gallery_ids_new_array, $this->translate_attachment($id, $new_lang_slug, $post->ID));
			}

			$gallery_and_meta[$i]['ids_new'] = implode(',', $gallery_ids_new_array);

			$gallery_and_meta[$i]['shortcode_new'] = preg_replace('/ ids="([^"]*)"/i', ' ids="' . $gallery_and_meta[$i]['ids_new'] . '"', $gallery_and_meta[$i]['shortcode']);

			// replace galleries in content
			$content = str_replace($gallery_and_meta[$i]['shortcode'], $gallery_and_meta[$i]['shortcode_new'], $content);

		}

		return $content;

	}


	/**
	 * Translate featured image
	 *
	 * @param obj post new post object
	 * @param int ID of the post we copy from
	 * @param string slug of the new translation language
	 */

	function copy_featured_image($post, $from_post_id, $new_lang_slug) {
		if(has_post_thumbnail( $post->ID )) {
			$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
			$post_thumbnail_id = $this->translate_attachment($post_thumbnail_id, $new_lang_slug, $post->ID);
			set_post_thumbnail( $post, $post_thumbnail_id );
		}
	}

	/**
	 * Replace media in custom fields with image ID
	 *
	 * These custom fields are given with filter and not found automatically.
	 *
	 * @param string custom field key
	 * @param object post
	 * @param string slug of the new translation language
	 */

	function replace_media_in_custom_field_with_image_id($custom_field, $post_object, $new_lang) {
		
		$did_translate_something = false;

		if(empty($custom_field)) {
			return false;
		}

		$custom_field_values = get_post_meta($post_object->ID, $custom_field, false);

		if(empty($custom_field_values)) {
			return false;
		}

		foreach ($custom_field_values as $custom_field_value) {
			$translated_value = $this->translate_attachment($custom_field_value, $new_lang, $post_object->ID);
			if($custom_field_value == $translated_value) {
				continue; // it was correct already
			}
			update_post_meta($post_object->ID, $custom_field, $translated_value, $custom_field_value);
			$did_translate_something = true;
		}

		return $did_translate_something;

	}

	/**
	 * Translate attachment
	 *
	 * @param int $attachment_id id of the attachment in original language
	 * @param string $new_lang new language slug
	 * @param int $parent_id id of the parent of the translated attachments (post ID)
	 *
	 * @return int translated id
	 */
	function translate_attachment($attachment_id, $new_lang, $parent_id) {

		$post = get_post($attachment_id);
		$post_id = $post->ID;

		// if there's existing translation, use it
		$existing_translation = pll_get_post($post_id, $new_lang);
		if(!empty($existing_translation)) {
			return $existing_translation; // existing translated attachment
		}

		$post->ID = null; // will force the creation
		$post->post_parent = $parent_id ? $parent_id : 0;

		$tr_id = wp_insert_attachment($post);
		add_post_meta($tr_id, '_wp_attachment_metadata', get_post_meta($post_id, '_wp_attachment_metadata', true));
		add_post_meta($tr_id, '_wp_attached_file', get_post_meta($post_id, '_wp_attached_file', true));

		// copy alternative text to be consistent with title, caption and description copied when cloning the post
		if ($meta = get_post_meta($post_id, '_wp_attachment_image_alt', true)) {
			add_post_meta($tr_id, '_wp_attachment_image_alt', $meta);
		}
		
		// set language of the attachment
		PLL()->model->post->set_language($tr_id, $new_lang);
		
		$translations = PLL()->model->post->get_translations($post_id);
		if (!$translations && $lang = PLL()->model->post->get_language($post_id)) {
			$translations[$lang->slug] = $post_id;
		}

		$translations[$new_lang] = $tr_id;
		PLL()->model->post->save_translations($tr_id, $translations);

		$this->images_translated++;

		return $tr_id; // newly translated attachment

	}

	/**
	 * Generate attachment metadata
	 *
	 * @param int $metadata metadata of the attachment from which we copy informations
	 * @param int $attachment_id id of the attachment to copy the metadata to
	 */
	function wp_generate_attachment_metadata( $metadata, $attachment_id ) {

		$attachment_lang = PLL()->model->post->get_language($attachment_id);
		$translations = PLL()->model->post->get_translations($attachment_id);

		foreach ($translations as $lang => $tr_id) {
			if (!$tr_id)
				continue;
			
			if ($attachment_lang->slug !== $lang) {
				update_post_meta($tr_id, '_wp_attachment_metadata', $metadata);
			}
		}

		return $metadata;

	}

}

add_action('plugins_loaded', create_function('', 'global $polylang_translate_existing_media; $polylang_translate_existing_media = new PolylangTranslateExistingMedia();'));

