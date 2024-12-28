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
	 * Transform an image tag.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html The image HTML to transform.
	 * @param string $context The transformation context.
	 * @return string The transformed image HTML.
	 */
	protected function transform_image(string $img_html, string $context): string {
		// Skip if already processed
		if (Helpers::is_image_processed($img_html)) {
			return $img_html;
		}

		// Create processor for transformation
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return $img_html;
		}

		error_log('Original HTML: ' . $img_html);
		error_log('Width attribute: ' . $processor->get_attribute('width'));
		error_log('Height attribute: ' . $processor->get_attribute('height'));

		// Get the attachment ID if available
		$attachment_id = Helpers::get_attachment_id_from_classes($processor);
		error_log('Attachment ID: ' . var_export($attachment_id, true));

		// Get and constrain dimensions
		$dimensions = Image_Dimensions::from_html($processor);
		error_log('Dimensions from HTML: ' . var_export($dimensions, true));

		if (!$dimensions && $attachment_id) {
			$dimensions = Image_Dimensions::from_attachment($attachment_id);
			error_log('Dimensions from attachment: ' . var_export($dimensions, true));
		}

		if ($dimensions) {
			$dimensions = Image_Dimensions::constrain_to_content_width($dimensions);
			error_log('Constrained dimensions: ' . var_export($dimensions, true));
			$processor->set_attribute('width', $dimensions['width']);
			$processor->set_attribute('height', $dimensions['height']);
		}

		// Transform the image
		$processor = Images::transform_image_tag($processor, $attachment_id, $img_html, $context);
		$result = $processor->get_updated_html();
		error_log('Final HTML: ' . $result);
		return $result;
	}

}