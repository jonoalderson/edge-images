<?php
/**
 * Picture element creation functionality.
 *
 * Handles the creation and configuration of picture elements for responsive images.
 * This feature:
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

use Edge_Images\{Integration, Features, Helpers};
use Edge_Images\Settings;

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
	 * Process srcset attribute.
	 *
	 * Processes and validates srcset attribute values.
	 * This method:
	 * - Parses srcset strings
	 * - Cleans image URLs
	 * - Preserves descriptors
	 * - Handles empty values
	 * - Maintains URL formatting
	 *
	 * @since      4.5.0
	 * 
	 * @param  string $srcset The srcset attribute value to process.
	 * @return string         The processed srcset attribute value.
	 */
	private static function process_srcset(string $srcset): string {
		// If the srcset is empty, return it as is.
		if (empty($srcset)) {
			return $srcset;
		}

		// Split srcset into individual sources.
		$sources = explode(',', $srcset);
		$processed_sources = [];

		foreach ($sources as $source) {
			// Split into URL and descriptor.
			if (preg_match('/^(.+?)(\s+\d+[wx])$/i', trim($source), $matches)) {
				$url = trim($matches[1]);
				$descriptor = $matches[2];

				// Only clean URL if it hasn't been transformed already.
				if (!Helpers::is_transformed_url($url)) {
					$url = Helpers::clean_url($url);
				}

				$processed_sources[] = $url . $descriptor;
			}
		}

		return implode(', ', $processed_sources);
	}

	/**
	 * Create a picture element.
	 *
	 * Generates a complete picture element with responsive image support.
	 * This method:
	 * - Handles link wrapping
	 * - Processes image tags
	 * - Manages dimensions
	 * - Applies transformations
	 * - Sets CSS classes
	 * - Adds inline styles
	 * - Ensures proper markup
	 *
	 * @since      4.5.0
	 * 
	 * @param  string $img_html  The original image HTML to transform.
	 * @param  array  $dimensions The image dimensions array with width and height.
	 * @param  string $class     Optional CSS class for the picture element.
	 * @return string           The complete picture element HTML.
	 */
	public static function create(string $img_html, array $dimensions, string $class = ''): string {
		
		// Extract any wrapping anchor tag.
		$link_open = '';
		$link_close = '';
		if (preg_match('/<a[^>]*>(.*?)<\/a>/s', $img_html, $matches)) {
			$link_open = substr($img_html, 0, strpos($img_html, '>') + 1);
			$link_close = '</a>';
			$img_html = $matches[1]; // Get just the img tag.
		}

		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return $img_html;
		}

		// Get sizes attribute before any modifications.
		$sizes = $processor->get_attribute('sizes');

		// Skip if picture wrapping is disabled.
		if (!Features::is_feature_enabled('picture_wrap')) {
			return $link_open . $img_html . $link_close;
		}

		// Get the max width, respecting the global setting.
		$max_width = min($dimensions['width'], Settings::get_max_width());

		// Transform the image URLs while preserving sizes.
		$img_html = self::transform_image_urls($img_html, [
			'width' => $max_width,
			'height' => round($max_width * ($dimensions['height'] / $dimensions['width']))
		], $sizes);
		
		// Build classes array.
		$classes = ['edge-images-container'];
		if ($class) {
			// Split the class string and merge with existing classes.
			$additional_classes = array_filter(explode(' ', $class));
			$classes = array_merge($classes, $additional_classes);
		}

		// Build inline styles.
		$style_array = [
			'--max-width' => $max_width . 'px',
		];

		$style_string = self::build_style_string($style_array);
		
		// Create picture element with the link if it exists.
		$picture_html = sprintf(
			'<picture class="%s" style="%s">%s%s%s</picture>',
			esc_attr(implode(' ', array_unique($classes))),
			esc_attr($style_string),
			$link_open,
			$img_html,
			$link_close
		);

		return $picture_html;
	}

	/**
	 * Transform image URLs in HTML.
	 *
	 * Processes and transforms image URLs within HTML markup.
	 * This method:
	 * - Processes image tags
	 * - Calculates dimensions
	 * - Transforms source URLs
	 * - Generates srcset values
	 * - Updates attributes
	 * - Maintains aspect ratios
	 * - Ensures proper scaling
	 *
	 * @since      4.5.0
	 * 
	 * @param  string $img_html   The image HTML to transform.
	 * @param  array  $dimensions The target dimensions array with width and height.
	 * @param  string $sizes     Optional sizes attribute value.
	 * @return string           The transformed image HTML.
	 */
	private static function transform_image_urls(string $img_html, array $dimensions, ?string $sizes = null): string {
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return $img_html;
		}

		// Validate dimensions
		if (empty($dimensions['width']) || empty($dimensions['height'])) {
			return $img_html;
		}

		// Create a Handler instance to use its transform_image_urls method
		$handler = new \Edge_Images\Handler();
		
		// Call the handler's transform_image_urls method
		$handler->transform_image_urls(
			$processor,
			$dimensions,
			$img_html,
			'picture',
			[
				'fit' => 'cover',
			]
		);

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