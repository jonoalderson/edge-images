<?php
/**
 * Gallery block functionality.
 *
 * We don't want to transform gallery blocks, as it'll cause considerable headaches with themes and plugins.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Blocks;

use Edge_Images\{Block, Features, Helpers, Images, Image_Dimensions};

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
		// Bail if this isn't a gallery block.
		if ($block['blockName'] !== 'core/gallery') {
			return $block_content;
		}

		// Find all images in the gallery
		if (preg_match_all('/<img[^>]+>/', $block_content, $matches)) {

			// Bail if no images found
			if (!isset($matches[0])) {
				return $block_content;
			}

			// Transform each image
			foreach ($matches[0] as $img_html) {
				// Transform the image
				$transformed = $this->transform_image($img_html, 'gallery');

				// Replace the original image with the transformed one
				$block_content = str_replace($img_html, $transformed, $block_content);
			}
		}

		return $block_content;
	}
	
}