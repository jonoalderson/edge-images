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
	 * @param string $block_html The block content.
	 * @param array  $block      The block data.
	 * @return string The transformed block content.
	 */
	public function transform(string $block_html, array $block): string {
		
		// Create a processor for the block HTML
		$processor = new \WP_HTML_Tag_Processor($block_html);

		// First pass: Add processed class to gallery wrapper
		$first_figure = true;
		while ($processor->next_tag('figure')) {
			$class = $processor->get_attribute('class');
			if ($first_figure) {
				// Add both classes to the gallery wrapper
				$processor->set_attribute('class', trim($class . ' edge-images-processed'));
				$first_figure = false;
			}
		}

		// Get the updated HTML
		$updated_html = $processor->get_updated_html();

		// Second pass: Transform images
		$processor = new \WP_HTML_Tag_Processor($updated_html);
		while ($processor->next_tag('img')) {

			// Skip if already processed
			if (strpos($processor->get_attribute('class') ?? '', 'edge-images-processed') !== false) {
				continue;
			}

			// Get current src and dimensions
			$src = $processor->get_attribute('src');
			$width = $processor->get_attribute('width');
			$height = $processor->get_attribute('height');

			if (!$src || !$width || !$height) {
				continue;
			}

			// Transform the image URLs directly
			Images::transform_image_urls(
				$processor,
				[
					'width' => $width,
					'height' => $height
				],
				$updated_html,
				'gallery',
				[]
			);

			// Add processed class
			$class = $processor->get_attribute('class');
			$processor->set_attribute('class', trim($class . ' edge-images-processed'));
		}

		return $processor->get_updated_html();
	}
}