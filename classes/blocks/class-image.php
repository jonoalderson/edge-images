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
		// Extract the image tag
		$img_html = $this->extract_image($block_content);
		if (!$img_html) {
			return $block_content;
		}

		// Skip if already processed
		if (!$this->should_transform_image($img_html)) {
			return $block_content;
		}

		// Extract link if present
		$link_data = $this->extract_link($block_content, $img_html);
		$has_link = !empty($link_data['link']);
		$img_html = $link_data['img'];

		// Transform the image
		$transformed = $this->transform_image($img_html, 'block');

		// If picture wrapping is enabled and we have dimensions
		if (Features::is_feature_enabled('picture_wrap')) {
			$dimensions = $this->extract_dimensions($transformed);
			if ($dimensions) {
				$classes = $this->extract_classes($block_content, $block);
				$picture = $this->create_picture($transformed, $dimensions, $classes);

				// If we have a link, move it inside the picture element
				if ($has_link) {
					// Extract the anchor opening tag
					preg_match('/<a[^>]*>/', $link_data['link'], $link_open);
					$link_open = $link_open[0];

					$picture = str_replace(
						'<img',
						$link_open . '<img',
						$picture
					);
					$picture = str_replace('</picture>', '</a></picture>', $picture);
				}

				// Return just the picture element
				return $picture;
			}
		}

		// If we have a link, wrap the transformed image
		if ($has_link) {
			$transformed = str_replace($img_html, $transformed, $link_data['link']);
		}

		return $transformed;
	}
} 