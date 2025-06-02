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
 * @license    GPL-2.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Features;

use Edge_Images\{Integration, Features, Images, Image_Dimensions, Helpers};

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
	 * @param string     $img_html   The image HTML to wrap.
	 * @param array|null $dimensions The image dimensions.
	 * @param string     $class      Optional additional class.
	 * @return string The wrapped HTML.
	 */
	public static function create(string $img_html, ?array $dimensions, string $class = ''): string {

		// Bail if already wrapped
		if (strpos($img_html, '<picture') !== false) {
			return $img_html;
		}

		// Try to get dimensions from the image tag if not provided
		if (!$dimensions) {
			$processor = new \WP_HTML_Tag_Processor($img_html);
			if ($processor->next_tag('img')) {
				$dimensions = Image_Dimensions::from_html($processor);
			}
			if (!$dimensions) {
				return $img_html;
			}
		}

		// Update the image dimensions
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if ($processor->next_tag('img')) {
			$processor->set_attribute('width', $dimensions['width']);
			$processor->set_attribute('height', $dimensions['height']);
			$img_html = $processor->get_updated_html();
		}

		// Build classes array
		$classes = [];
		if ($class) {
			// Split the class string and merge with existing classes
			$additional_classes = array_filter(explode(' ', $class));
			$classes = array_merge($classes, $additional_classes);
		}
		$classes[] = 'edge-images-container';

		// Build inline styles
		$style_array = [
			'--max-width' => $dimensions['width'] . 'px', // Utility variable for max-width
			'--content-visibility' => 'auto', // Only load the image when it nears the viewport
			'--width' => $dimensions['width'] . 'px',
			'--height' => $dimensions['height'] . 'px',
		];

		// Organize the properties alphabetically.
		ksort($style_array);

		// Build the style string.
		$style_string = self::build_style_string($style_array);

		// Create the picture element
		$picture_html = sprintf(
			'<picture class="%s" style="%s">',
			esc_attr(implode(' ', array_unique($classes))),
			esc_attr($style_string)
		);

		// Add the original img tag
		$picture_html .= $img_html;

		// Close the picture element
		$picture_html .= '</picture>';

		return $picture_html;
	}

	/**
	 * Transform a figure element into a picture element.
	 *
	 * @since 4.5.0
	 * 
	 * @param string     $html The HTML containing a figure element.
	 * @param string     $img_html The image HTML within the figure.
	 * @param array|null $dimensions The image dimensions.
	 * @return string|null The transformed HTML, or null if transformation not possible/needed.
	 */
	public static function transform_figure(string $html, string $img_html, ?array $dimensions): ?string {
		// Skip if picture wrapping is disabled
		if (!Features::is_feature_enabled('picture_wrap')) {
			return null;
		}

		// Skip if already wrapped
		if (strpos($html, '<picture') !== false) {
			return null;
		}

		// Extract just the img tag from the transformed HTML
		$img_tag = Helpers::extract_img_tag($img_html);
		if (!$img_tag) {
			return null;
		}

		// Extract the figure classes
		$figure_classes = Helpers::extract_figure_classes($html);

		// Try to get dimensions from the image tag if not provided
		if (!$dimensions) {
			$processor = new \WP_HTML_Tag_Processor($img_tag);
			if ($processor->next_tag('img')) {
				$dimensions = Image_Dimensions::from_html($processor);
			}
			if (!$dimensions) {
				return null;
			}
		}

		// Create picture element with figure classes
		$picture = self::create($img_tag, $dimensions, $figure_classes);

		// Replace the entire figure with the picture
		return str_replace($html, $picture, $html);
	}

	/**
	 * Check if an image should be wrapped in a picture element.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $html The HTML to check.
	 * @param string $context Optional context (e.g., 'block', 'content', 'attachment').
	 * @return bool Whether the image should be wrapped.
	 */
	public static function should_wrap(string $html, string $context = ''): bool {
		// Skip if picture wrapping is disabled
		if (!Features::is_feature_enabled('picture_wrap')) {
			return false;
		}

		// Skip if already wrapped
		if (strpos($html, '<picture') !== false) {
			return false;
		}

		// Skip featured images
		if (strpos($html, 'attachment-post-thumbnail') !== false) {
			return false;
		}

		// Skip if in gallery context
		if (in_array($context, ['gallery'], true)) {
			return false;
		}

		return true;
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

		// Extract transform args from the image
		$transform_args = [];
		$valid_args = \Edge_Images\Edge_Provider::get_valid_args();
		
		// Get the src
		$src = $processor->get_attribute('src');
		if (!$src) {
			return $img_html;
		}

		// Check if already transformed
		if (Helpers::is_transformed_url($src)) {
			// Extract existing transform args from URL
			$url_parts = wp_parse_url($src);
			if (isset($url_parts['query'])) {
				parse_str($url_parts['query'], $transform_args);
			}
		}
		
		// Check attributes which might override URL parameters
		foreach ($valid_args as $short => $aliases) {
			// Check short form
			if ($processor->get_attribute($short)) {
				$transform_args[$short] = $processor->get_attribute($short);
				continue;
			}
			
			// Check aliases
			if ($aliases) {
				foreach ($aliases as $alias) {
					if ($processor->get_attribute($alias)) {
						$transform_args[$short] = $processor->get_attribute($alias);
						break;
					}
				}
			}
		}

		// Add dimensions to transform args
		$transform_args['w'] = $dimensions['width'];
		$transform_args['h'] = $dimensions['height'];

		// Transform the image URLs
		Images::transform_image_urls($processor, $dimensions, $img_html, 'picture', $transform_args);

		return $processor->get_updated_html();
	}

	/**
	 * Build a CSS style string from an array of properties.
	 *
	 * @since 4.5.0
	 * 
	 * @param array $styles Array of style properties.
	 * @return string The formatted style string.
	 */
	private static function build_style_string(array $styles): string {
		$style_parts = [];
		foreach ($styles as $property => $value) {
			$style_parts[] = $property . ': ' . $value;
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