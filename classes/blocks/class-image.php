<?php
/**
 * Image block functionality.
 *
 * Handles the transformation of standalone image blocks.
 * This class:
 * - Transforms image content
 * - Handles image processing
 * - Manages image attributes
 * - Supports picture wrapping
 * - Preserves links
 * - Maintains aspect ratios
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Blocks;

use Edge_Images\{Block, Features, Helpers};
use Edge_Images\Features\Picture;

class Image extends Block {

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
		// Skip if no image or already processed
		if (!str_contains($block_content, '<img') || str_contains($block_content, '<picture')) {
			return $block_content;
		}

		// Extract the image
		$img_html = Helpers::extract_img_tag($block_content);
		if (!$img_html || !$this->should_transform_image($img_html)) {
			return $block_content;
		}

		// Transform the image
		$transformed_img = $this->transform_image($img_html);

		// If Picture wrap is disabled, just replace the original image
		if (!Features::is_feature_enabled('picture_wrap')) {
			return str_replace($img_html, $transformed_img, $block_content);
		}

		// Get dimensions from the image
		$dimensions = $this->extract_dimensions($transformed_img);
		if (!$dimensions) {
			return $block_content;
		}

		// Extract image classes
		$image_classes = $this->extract_classes($block_content, $block);

		// Create picture element
		$picture_html = $this->create_picture($transformed_img, $dimensions, $image_classes);

		return $picture_html;
	}
} 