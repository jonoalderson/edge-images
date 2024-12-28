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

use Edge_Images\{Block, Features, Helpers, Images, Image_Dimensions};
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
		
		// Bail if this isn't an image block
		if ($block['blockName'] !== 'core/image') {
			return $block_content;
		}
		
		// Skip if no image or already processed
		if (!str_contains($block_content, '<img') || str_contains($block_content, '<picture')) {
			return $block_content;
		}

		// Extract the image
		if (!preg_match('/<img[^>]+>/', $block_content, $matches)) {
			return $block_content;
		}

		// Bail if no image found
		if (!isset($matches[0])) {
			return $block_content;
		}

		// Transform the image
		$transformed = $this->transform_image($matches[0], 'image');

		// Replace the original image with the transformed one
		return str_replace($matches[0], $transformed, $block_content);
	}
} 