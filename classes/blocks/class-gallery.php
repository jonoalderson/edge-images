<?php
/**
 * Gallery block functionality.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Blocks;

use Edge_Images\{Block, Features, Helpers, Images};

class Gallery extends Block {

	/**
	 * Transform block content.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * @return string The transformed block content.
	 */
	public function transform(string $block_content, array $block): string {

		// Only add no-picture class if picture wrapping is enabled
		if (!Features::is_feature_enabled('picture_wrap')) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor($block_content);

		// Skip the outer wrapper figure tag.
		$processor->next_tag('figure');

		// Process all nested figure tags.
		while ($processor->next_tag('figure')) {
			$existing_class = $processor->get_attribute('class') ?? '';
			$new_class = trim($existing_class . ' edge-images-no-picture');
			$processor->set_attribute('class', $new_class);
		
		}

		return $processor->get_updated_html();
	}
}