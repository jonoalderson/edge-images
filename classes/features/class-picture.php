<?php
/**
 * Picture element wrapper functionality.
 *
 * Handles wrapping images in picture elements for responsive images
 * and art direction support. This feature:
 * - Creates responsive picture elements
 * - Handles image transformations
 * - Manages srcset and sizes attributes
 * - Preserves image aspect ratios
 * - Supports link wrapping
 * - Provides CSS customization options
 * - Ensures proper image scaling
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Features;

use Edge_Images\{Integration, Features, Images};

class Picture extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * Initializes the picture element functionality.
	 * This method:
	 * - Sets up required filters
	 * - Configures feature behavior
	 * - Manages integration points
	 *
	 * @since      4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		// No filters needed - this is a utility class.
	}

	/**
	 * Create a picture element wrapper.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html   The image HTML to wrap.
	 * @param array  $dimensions The image dimensions.
	 * @param string $class      Optional additional class.
	 * @return string The wrapped HTML.
	 */
	public static function create(string $img_html, array $dimensions, string $class = ''): string {

		// Skip if already wrapped
		if (strpos($img_html, '<picture') !== false) {
			return $img_html;
		}

		// Transform the image URLs
		$img_html = self::transform_image_urls($img_html, $dimensions);

		// Build classes array
		$classes = ['edge-images-container'];
		if ($class) {
			// Split the class string and merge with existing classes
			$additional_classes = array_filter(explode(' ', $class));
			$classes = array_merge($classes, $additional_classes);
		}

		// Build inline styles
		$style_array = [
			'--max-width' => $dimensions['width'] . 'px',
		];

		$style_string = self::build_style_string($style_array);

		// Create the picture element
		$picture_html = sprintf(
			'<picture class="%s" style="%s">',
			esc_attr(implode(' ', array_unique($classes))),
			esc_attr($style_string)
		);

		// Add source elements for different formats
		$picture_html .= self::get_source_elements($img_html, $dimensions);

		// Add the original img tag
		$picture_html .= $img_html;

		// Close the picture element
		$picture_html .= '</picture>';

		return $picture_html;
	}

	/**
	 * Get source elements for different formats.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html   The image HTML.
	 * @param array  $dimensions The image dimensions.
	 * @return string The source elements HTML.
	 */
	private static function get_source_elements(string $img_html, array $dimensions): string {
		$source_html = '';

		// Extract src and srcset from img tag
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return $source_html;
		}

		$src = $processor->get_attribute('src');
		$srcset = $processor->get_attribute('srcset');
		$sizes = $processor->get_attribute('sizes');

		if (!$src) {
			return $source_html;
		}

		// Add WebP source if not already WebP
		if (strpos($src, '.webp') === false) {
			$source_html .= self::get_webp_source($src, $dimensions, $sizes);
		}

		// Add AVIF source if not already AVIF
		if (strpos($src, '.avif') === false) {
			$source_html .= self::get_avif_source($src, $dimensions, $sizes);
		}

		return $source_html;
	}

	/**
	 * Get WebP source element.
	 *
	 * @since 4.5.0
	 * 
	 * @param string      $src        The original image src.
	 * @param array       $dimensions The image dimensions.
	 * @param string|null $sizes      Optional sizes attribute.
	 * @return string The WebP source element.
	 */
	private static function get_webp_source(string $src, array $dimensions, ?string $sizes = null): string {
		// Transform the image URLs
		$img_html = self::transform_image_urls($img_html, [
			'width' => $dimensions['width'],
			'height' => $dimensions['height'],
			'format' => 'webp',
		]);

		// Extract the transformed src and srcset
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return '';
		}

		$webp_src = $processor->get_attribute('src');
		$webp_srcset = $processor->get_attribute('srcset');

		if (!$webp_src) {
			return '';
		}

		$source = '<source type="image/webp"';
		if ($webp_srcset) {
			$source .= ' srcset="' . esc_attr($webp_srcset) . '"';
		}
		if ($sizes) {
			$source .= ' sizes="' . esc_attr($sizes) . '"';
		}
		$source .= '>';

		return $source;
	}

	/**
	 * Get AVIF source element.
	 *
	 * @since 4.5.0
	 * 
	 * @param string      $src        The original image src.
	 * @param array       $dimensions The image dimensions.
	 * @param string|null $sizes      Optional sizes attribute.
	 * @return string The AVIF source element.
	 */
	private static function get_avif_source(string $src, array $dimensions, ?string $sizes = null): string {
		// Transform the image URLs
		$img_html = self::transform_image_urls($img_html, [
			'width' => $dimensions['width'],
			'height' => $dimensions['height'],
			'format' => 'avif',
		]);

		// Extract the transformed src and srcset
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return '';
		}

		$avif_src = $processor->get_attribute('src');
		$avif_srcset = $processor->get_attribute('srcset');

		if (!$avif_src) {
			return '';
		}

		$source = '<source type="image/avif"';
		if ($avif_srcset) {
			$source .= ' srcset="' . esc_attr($avif_srcset) . '"';
		}
		if ($sizes) {
			$source .= ' sizes="' . esc_attr($sizes) . '"';
		}
		$source .= '>';

		return $source;
	}

	/**
	 * Transform image URLs in HTML.
	 *
	 * @since 4.5.0
	 * 
	 * @param string      $img_html   The image HTML.
	 * @param array       $dimensions The image dimensions.
	 * @param string|null $sizes      Optional sizes attribute.
	 * @return string The transformed HTML.
	 */
	private static function transform_image_urls(string $img_html, array $dimensions, ?string $sizes = null): string {

		// Create a processor for the image
		$processor = new \WP_HTML_Tag_Processor($img_html);

		// Bail if no img tag
		if (!$processor->next_tag('img')) {
			return $img_html;
		}

		// Transform the image URLs
		Images::transform_image_urls($processor, $dimensions, $img_html, 'picture', []);

		return $processor->get_updated_html();
	}

	/**
	 * Build a CSS style string from an array of properties.
	 *
	 * Creates a formatted CSS style string from property-value pairs.
	 * This method:
	 * - Processes style arrays
	 * - Formats properties
	 * - Combines values
	 * - Ensures proper syntax
	 * - Maintains consistency
	 *
	 * @since      4.5.0
	 * 
	 * @param  array  $styles Array of CSS properties and their values.
	 * @return string        The compiled CSS style string.
	 */
	private static function build_style_string(array $styles): string {
		$style_parts = [];
		foreach ($styles as $property => $value) {
			$style_parts[] = sprintf('%s: %s', $property, $value);
		}
		return implode('; ', $style_parts);
	}

	/**
	 * Get default settings for this integration.
	 *
	 * Provides default configuration settings for the picture feature.
	 * This method:
	 * - Sets feature defaults
	 * - Configures options
	 * - Ensures consistency
	 * - Supports customization
	 *
	 * @since      4.5.0
	 * 
	 * @return array<string,mixed> Array of default feature settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_feature_picture_wrap' => false,
		];
	}
} 