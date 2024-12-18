<?php
/**
 * Picture element creation functionality.
 *
 * Handles the creation and configuration of picture elements
 * for responsive images.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.0
 */

namespace Edge_Images\Features;

use Edge_Images\{Integration, Feature_Manager, Helpers};
use Edge_Images\Settings;

/**
 * Handles picture element creation.
 *
 * @since 4.5.0
 */
class Picture extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		// No filters needed - this is a utility class
	}

	/**
	 * Process srcset attribute.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $srcset The srcset attribute value.
	 * @return string The processed srcset.
	 */
	private static function process_srcset(string $srcset): string {
		// Split srcset into individual sources
		$sources = explode(',', $srcset);
		$processed_sources = [];

		foreach ($sources as $source) {
			// Split into URL and descriptor
			if (preg_match('/^(.+?)(\s+\d+[wx])$/i', trim($source), $matches)) {
				$url = $matches[1];
				$descriptor = $matches[2];

				// Clean transformation parameters from URL
				$processed_sources[] = $url . $descriptor;
			}
		}

		return implode(', ', $processed_sources);
	}

	/**
	 * Create a picture element.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html  The image HTML.
	 * @param array  $dimensions Image dimensions.
	 * @param string $class     Optional. CSS class for the picture element.
	 * @return string The picture element HTML.
	 */
	public static function create(string $img_html, array $dimensions, string $class = ''): string {
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return $img_html;
		}

		// Process srcset if it exists
		$srcset = $processor->get_attribute('srcset');
		if ($srcset) {
			$processor->set_attribute('srcset', self::process_srcset($srcset));
		}

		// Skip if picture wrapping is disabled
		if (!Feature_Manager::is_feature_enabled('picture_wrap')) {
			return $img_html;
		}

		// Transform the image URLs
		$img_html = self::transform_image_urls($img_html, $dimensions);

		$aspect_ratio = $dimensions['height'] / $dimensions['width'];
		
		// Get the max width, respecting the global setting
		$max_width = min($dimensions['width'], Settings::get_max_width());
		
		$classes = ['edge-images-container'];
		if ($class) {
			$classes[] = $class;
		}

		// Build inline styles
		$style_array = [
			'--aspect-ratio' => $aspect_ratio,
			'--max-width' => $max_width . 'px',
		];

		$style_string = self::build_style_string($style_array);
		
		return sprintf(
			'<picture class="%s" style="%s">%s</picture>',
			esc_attr(implode(' ', $classes)),
			esc_attr($style_string),
			$img_html
		);
	}

	/**
	 * Transform image URLs in HTML.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html   The image HTML.
	 * @param array  $dimensions The image dimensions.
	 * @return string The transformed HTML.
	 */
	private static function transform_image_urls(string $img_html, array $dimensions): string {
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return $img_html;
		}

		// Get the max width for transformations
		$max_width = min($dimensions['width'], Settings::get_max_width());

		// Calculate proportional height
		$ratio = $dimensions['height'] / $dimensions['width'];
		$max_height = round($max_width * $ratio);

		// Get constrained dimensions
		$constrained_dimensions = [
			'width' => (string) $max_width,
			'height' => (string) $max_height
		];

		// Transform src
		$src = $processor->get_attribute('src');
		if ($src) {
			$transformed_src = Helpers::edge_src($src, [
				'width' => $constrained_dimensions['width'],
				'height' => $constrained_dimensions['height'],
			]);
			$processor->set_attribute('src', $transformed_src);
		}

		// Generate srcset using the Srcset_Transformer
		$srcset = \Edge_Images\Srcset_Transformer::transform(
			$src,
			$dimensions, // Use original dimensions for srcset generation
			$processor->get_attribute('sizes') ?? ''
		);

		if ($srcset) {
			$processor->set_attribute('srcset', $srcset);
		}

		return $processor->get_updated_html();
	}

	/**
	 * Build a CSS style string from an array of properties.
	 *
	 * @since 4.5.0
	 * 
	 * @param array $styles Array of CSS properties and values.
	 * @return string The compiled style string.
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
	 * @since 4.5.0
	 * 
	 * @return array<string,mixed> Default settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_disable_picture_wrap' => false,
		];
	}
} 