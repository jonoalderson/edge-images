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
		// If the srcset is empty, return it as is
		if (empty($srcset)) {
			return $srcset;
		}

		// Split srcset into individual sources
		$sources = explode(',', $srcset);
		$processed_sources = [];

		foreach ($sources as $source) {
			// Split into URL and descriptor
			if (preg_match('/^(.+?)(\s+\d+[wx])$/i', trim($source), $matches)) {
				$url = trim($matches[1]);
				$descriptor = $matches[2];

				// Only clean URL if it hasn't been transformed already
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
	 * @since 4.5.0
	 * 
	 * @param string $img_html  The image HTML.
	 * @param array  $dimensions Image dimensions.
	 * @param string $class     Optional. CSS class for the picture element.
	 * @return string The picture element HTML.
	 */
	public static function create(string $img_html, array $dimensions, string $class = ''): string {
		error_log('PICTURE::CREATE - Starting');
		error_log('Input image HTML: ' . $img_html);
		error_log('Input dimensions: ' . print_r($dimensions, true));
		error_log('Input class: ' . $class);

		// Extract any wrapping anchor tag
		$link_open = '';
		$link_close = '';
		if (preg_match('/<a[^>]*>(.*?)<\/a>/s', $img_html, $matches)) {
			error_log('Found link wrapper');
			$link_open = substr($img_html, 0, strpos($img_html, '>') + 1);
			$link_close = '</a>';
			$img_html = $matches[1]; // Get just the img tag
			error_log('Extracted image from link: ' . $img_html);
		}

		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			error_log('Could not process image tag');
			return $img_html;
		}

		// Get sizes attribute before any modifications
		$sizes = $processor->get_attribute('sizes');
		error_log('Original sizes attribute: ' . $sizes);

		// Skip if picture wrapping is disabled
		if (!Feature_Manager::is_feature_enabled('picture_wrap')) {
			error_log('Picture wrapping is disabled');
			return $link_open . $img_html . $link_close;
		}

		// Get the max width, respecting the global setting
		$max_width = min($dimensions['width'], Settings::get_max_width());
		error_log('Max width (from dimensions and settings): ' . $max_width);

		// Transform the image URLs while preserving sizes
		$img_html = self::transform_image_urls($img_html, [
			'width' => $max_width,
			'height' => round($max_width * ($dimensions['height'] / $dimensions['width']))
		], $sizes);
		error_log('Transformed image HTML: ' . $img_html);
		
		// Build classes array
		$classes = ['edge-images-container'];
		if ($class) {
			// Split the class string and merge with existing classes
			$additional_classes = array_filter(explode(' ', $class));
			$classes = array_merge($classes, $additional_classes);
		}
		error_log('Final classes: ' . print_r($classes, true));

		// Build inline styles
		$style_array = [
			'--max-width' => $max_width . 'px',
		];
		error_log('Style array: ' . print_r($style_array, true));

		$style_string = self::build_style_string($style_array);
		error_log('Style string: ' . $style_string);
		
		// Create picture element with the link if it exists
		$picture_html = sprintf(
			'<picture class="%s" style="%s">%s%s%s</picture>',
			esc_attr(implode(' ', array_unique($classes))),
			esc_attr($style_string),
			$link_open,
			$img_html,
			$link_close
		);
		error_log('Final picture HTML: ' . $picture_html);

		return $picture_html;
	}

	/**
	 * Transform image URLs in HTML.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $img_html   The image HTML.
	 * @param array  $dimensions Image dimensions.
	 * @param string $sizes     Optional sizes attribute value.
	 * @return string Modified HTML.
	 */
	private static function transform_image_urls(string $img_html, array $dimensions, ?string $sizes = null): string {
		error_log('TRANSFORM_IMAGE_URLS - Starting');
		error_log('Input image HTML: ' . $img_html);
		error_log('Input dimensions: ' . print_r($dimensions, true));
		error_log('Input sizes: ' . $sizes);

		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			error_log('Could not process image tag');
			return $img_html;
		}

		// Get the max width for transformations
		$max_width = min($dimensions['width'], Settings::get_max_width());
		error_log('Max width for transformations: ' . $max_width);

		// Calculate proportional height
		$ratio = $dimensions['height'] / $dimensions['width'];
		$max_height = round($max_width * $ratio);
		error_log('Calculated max height: ' . $max_height);

		// Get constrained dimensions for src and srcset
		$constrained_dimensions = [
			'width' => (string) $max_width,
			'height' => (string) $max_height
		];
		error_log('Constrained dimensions: ' . print_r($constrained_dimensions, true));

		// Set width and height attributes using constrained dimensions
		$processor->set_attribute('width', $constrained_dimensions['width']);
		$processor->set_attribute('height', $constrained_dimensions['height']);

		// Transform src with constrained dimensions
		$src = $processor->get_attribute('src');
		error_log('Original src: ' . $src);
		
		if ($src) {
			$transformed_src = Helpers::edge_src($src, [
				'width' => $constrained_dimensions['width'],
				'height' => $constrained_dimensions['height'],
				'fit' => 'cover',
				'quality' => 85,
			]);
			$processor->set_attribute('src', $transformed_src);
			error_log('Transformed src: ' . $transformed_src);
		}

		// Generate srcset using the constrained dimensions
		$srcset = \Edge_Images\Srcset_Transformer::transform(
			$src,
			$constrained_dimensions,  // Use constrained dimensions for srcset
			$sizes ?? "(max-width: {$constrained_dimensions['width']}px) 100vw, {$constrained_dimensions['width']}px",
			[
				'height' => $constrained_dimensions['height'],  // Force height to match constrained
				'fit' => 'cover',
				'quality' => 85,
			]
		);
		error_log('Generated srcset: ' . $srcset);

		if ($srcset) {
			$processor->set_attribute('srcset', $srcset);
			// Update sizes attribute to use constrained width
			$new_sizes = "(max-width: {$constrained_dimensions['width']}px) 100vw, {$constrained_dimensions['width']}px";
			$processor->set_attribute('sizes', $new_sizes);
			error_log('Set new sizes attribute: ' . $new_sizes);
		}

		$result = $processor->get_updated_html();
		error_log('Final transformed HTML: ' . $result);
		return $result;
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