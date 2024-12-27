<?php
/**
 * Gallery block functionality.
 *
 * Handles the transformation of gallery blocks.
 * This class:
 * - Transforms gallery content
 * - Processes multiple images
 * - Manages gallery attributes
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
		// Skip if no images or already processed
		if (!str_contains($block_content, '<img') || str_contains($block_content, 'edge-images-processed')) {
			return $block_content;
		}

		// Find all inner figures in the gallery (those with wp-block-image class)
		if (!preg_match_all('/<figure[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>.*?<\/figure>/s', $block_content, $matches, PREG_OFFSET_CAPTURE)) {
			return $block_content;
		}

		$offset_adjustment = 0;

		// Process each figure
		foreach ($matches[0] as $match) {
			$figure_html = $match[0];
			$position = $match[1];

			// Extract the image and any wrapping link
			$img_html = Helpers::extract_img_tag($figure_html);
			if (!$img_html || !$this->should_transform_image($img_html)) {
				continue;
			}

			// Check for a link wrapping the image
			$link_html = '';
			if (preg_match('/<a[^>]*>.*?' . preg_quote($img_html, '/') . '.*?<\/a>/s', $figure_html, $link_matches)) {
				$link_html = $link_matches[0];
				$img_html = Helpers::extract_img_tag($link_html);
			}

			// Transform the image
			$transformed_img = $this->transform_image($img_html);

			// If we have a link, wrap the transformed image in it
			if ($link_html) {
				// Extract the link opening tag
				if (preg_match('/<a[^>]*>/', $link_html, $link_open_matches)) {
					$transformed_img = $link_open_matches[0] . $transformed_img . '</a>';
				}
			}

			// Replace just the image/link in the figure
			$new_figure_html = str_replace(
				$link_html ?: $img_html,
				$transformed_img,
				$figure_html
			);

			// Replace the figure with our new markup
			$block_content = substr_replace(
				$block_content,
				$new_figure_html,
				$position + $offset_adjustment,
				strlen($figure_html)
			);
			
			// Adjust the offset
			$offset_adjustment += strlen($new_figure_html) - strlen($figure_html);
		}

		return $block_content;
	}
}