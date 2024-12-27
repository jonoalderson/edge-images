<?php
/**
 * Base block class.
 *
 * Provides base functionality for handling blocks in Edge Images.
 * This class:
 * - Defines common block transformation methods
 * - Handles block content processing
 * - Manages block attributes
 * - Provides utility methods
 * - Ensures consistent block handling
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

use Edge_Images\Features\Picture;

abstract class Block {

	/**
	 * Transform block content.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * @return string The transformed block content.
	 */
	abstract public function transform(string $block_content, array $block): string;

	/**
	 * Extract classes from block content and attributes.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $block_content The block content.
	 * @param array  $block         Optional block data.
	 * @return array The combined classes.
	 */
	protected function extract_classes(string $block_content, array $block = []): array {
		$classes = [];

		// Create a processor for the element
		$processor = new \WP_HTML_Tag_Processor($block_content);
		if ($processor->next_tag()) {
			$element_classes = $processor->get_attribute('class');
			if ($element_classes) {
				$classes = array_filter(explode(' ', $element_classes));
			}
		}

		// Return unique classes
		return array_unique($classes);
	}

	/**
	 * Check if an image should be transformed.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html The image HTML.
	 * @return bool Whether the image should be transformed.
	 */
	protected function should_transform_image(string $img_html): bool {
		return !Helpers::is_image_processed($img_html);
	}

	/**
	 * Extract link wrapping an image.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $html     The HTML content.
	 * @param string $img_html The image HTML.
	 * @return array{link: string, img: string} The link HTML and possibly updated image HTML.
	 */
	protected function extract_link(string $html, string $img_html): array {
		$link_html = '';
		if (preg_match('/<a[^>]*>.*?' . preg_quote($img_html, '/') . '.*?<\/a>/s', $html, $link_matches)) {
			$link_html = $link_matches[0];
			$img_html = Helpers::extract_img_tag($link_html) ?? $img_html;
		}
		return ['link' => $link_html, 'img' => $img_html];
	}

	/**
	 * Extract dimensions from an image.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html The image HTML.
	 * @return array|null The dimensions array or null if not found.
	 */
	protected function extract_dimensions(string $img_html): ?array {
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return null;
		}

		$width = $processor->get_attribute('width');
		$height = $processor->get_attribute('height');

		if (!$width || !$height) {
			return null;
		}

		return [
			'width' => $width,
			'height' => $height
		];
	}

	/**
	 * Create a picture element.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html  The image HTML.
	 * @param array  $dimensions The image dimensions.
	 * @param array  $classes    The classes to add.
	 * @return string The picture element HTML.
	 */
	protected function create_picture(string $img_html, array $dimensions, array $classes = []): string {
		return Picture::create(
			$img_html,
			$dimensions,
			implode(' ', array_merge($classes, ['edge-images-container']))
		);
	}

	/**
	 * Extract caption from HTML.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $html The HTML content.
	 * @return string The caption HTML or empty string if not found.
	 */
	protected function extract_caption(string $html): string {
		if (preg_match('/<figcaption.*?>(.*?)<\/figcaption>/s', $html, $caption_matches)) {
			return $caption_matches[0];
		}
		return '';
	}

	/**
	 * Transform an image using the Handler.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html The image HTML.
	 * @return string The transformed image HTML.
	 */
	protected function transform_image(string $img_html): string {
		$handler = new Handler();
		return $handler->transform_image(true, $img_html, 'block', 0);
	}
}