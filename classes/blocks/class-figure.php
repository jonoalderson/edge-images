<?php
/**
 * Figure block functionality.
 *
 * Handles the transformation of figure blocks.
 * This class:
 * - Transforms figure content
 * - Handles image processing
 * - Manages figure attributes
 * - Supports picture wrapping
 * - Preserves captions
 * - Maintains link wrapping
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Blocks;

use Edge_Images\{Block, Features, Helpers};
use Edge_Images\Features\Picture;

class Figure extends Block {

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
		// Skip if no figure or already processed
		if (!str_contains($block_content, '<figure') || str_contains($block_content, '<picture')) {
			return $block_content;
		}

		// Extract the image and any wrapping link
		$img_html = Helpers::extract_img_tag($block_content);
		if (!$img_html) {
			return $block_content;
		}

		// Extract any link wrapping the image
		$link_data = $this->extract_link($block_content, $img_html);
		$link_html = $link_data['link'];
		$img_html = $link_data['img'];

		// Transform the image
		$transformed_img = $this->transform_image($img_html);

		// If Picture wrap is disabled, just replace the original image with the transformed one
		if (!Features::is_feature_enabled('picture_wrap')) {
			$new_html = $transformed_img;
			if ($link_html) {
				$new_html = str_replace($img_html, $transformed_img, $link_html);
			}
			return str_replace($link_html ?: $img_html, $new_html, $block_content);
		}

		// Get dimensions from the image
		$dimensions = $this->extract_dimensions($transformed_img);
		if (!$dimensions) {
			return $block_content;
		}

		// Extract figure classes
		$figure_classes = $this->extract_classes($block_content, $block);

		// If we have a link, wrap the transformed image in it
		if ($link_html) {
			// Extract the link opening tag
			if (preg_match('/<a[^>]*>/', $link_html, $link_open_matches)) {
				$transformed_img = $link_open_matches[0] . $transformed_img . '</a>';
			}
		}

		// Create picture element with figure classes
		$picture_html = $this->create_picture($transformed_img, $dimensions, $figure_classes);

		// Extract any caption from the figure
		$caption = $this->extract_caption($block_content);
		if ($caption) {
			$picture_html .= $caption;
		}

		return $picture_html;
	}
} 