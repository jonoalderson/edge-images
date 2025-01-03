<?php
/**
 * Image manipulation functionality.
 *
 * Handles all image manipulation and transformation, including:
 * - Image attribute transformation
 * - Image HTML cleanup
 * - Image dimension handling
 * - Image URL transformation
 * - Image class management
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

use Edge_Images\Features\Cache;

class Images {

	/**
	 * The cached edge provider instance.
	 *
	 * @since 4.5.0
	 * @var Edge_Provider|null
	 */
	private static ?Edge_Provider $provider_instance = null;

	/**
	 * Transform an image tag using the HTML Tag Processor.
	 *
	 * @since 4.5.0
	 * 
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * @param int|null               $image_id  Optional. The image attachment ID.
	 * @param string                 $html      Optional. The original HTML.
	 * @param string                 $context   Optional. The transformation context.
	 * @param array                  $args      Optional. Additional transformation arguments.
	 * @return \WP_HTML_Tag_Processor The transformed processor.
	 */
	public static function transform_image_tag(
		\WP_HTML_Tag_Processor $processor,
		?int $image_id = null,
		string $html = '',
		string $context = '',
		array $args = []
	): \WP_HTML_Tag_Processor {
		
		// Check cache first if we have an image ID
		if ($image_id) {
			// Get dimensions from processor
			$width = $processor->get_attribute('width');
			$height = $processor->get_attribute('height');
			
			if ($width && $height) {
				$size = [(int)$width, (int)$height];
				$cached_html = Cache::get_image_html($image_id, $size, $args);
				
				if ($cached_html !== false) {
					return new \WP_HTML_Tag_Processor($cached_html);
				}
			}
		}

		// Get src
		$src = $processor->get_attribute('src');
		if (!$src) {
			return $processor;
		}

		// Get dimensions from args if they exist
		$dimensions = null;
		if (isset($args['w'], $args['h'])) {
			$dimensions = [
				'width' => (string) $args['w'],
				'height' => (string) $args['h']
			];
		}

		// If no dimensions in args, try width/height attributes
		if (!$dimensions) {
			$width = $processor->get_attribute('width');
			$height = $processor->get_attribute('height');
			
			if ($width && $height) {
				$dimensions = [
					'width' => (string) $width,
					'height' => (string) $height
				];
			}
		}

		// If still no dimensions, try Image_Dimensions::get
		if (!$dimensions) {
			$dimensions = Image_Dimensions::get($processor, $image_id);
		}

		// Transform the URL if we have dimensions
		if ($dimensions) {
			// Create a new processor to preserve the original state
			$new_processor = new \WP_HTML_Tag_Processor($processor->get_updated_html());
			$new_processor->next_tag('img');

			// Check if this is an SVG
			if (Helpers::is_svg($src)) {
				// For SVGs, just set the dimensions without transforming the URL
				$new_processor->set_attribute('width', $dimensions['width']);
				$new_processor->set_attribute('height', $dimensions['height']);
				$new_processor->set_attribute('class', trim($new_processor->get_attribute('class') . ' edge-images-processed'));
				return $new_processor;
			}

			// Use transform_image_urls for consistent behavior
			self::transform_image_urls($new_processor, $dimensions, $html, $context, $args);

			// Always add the processed class
			$new_processor->set_attribute('class', trim($new_processor->get_attribute('class') . ' edge-images-processed'));

			// Cache the result if we have an image ID
			if ($image_id) {
				$size = [(int)$dimensions['width'], (int)$dimensions['height']];
				Cache::set_image_html($image_id, $size, $args, $new_processor->get_updated_html());
			}

			// Clean transformation attributes before returning
			return self::clean_transform_attributes($new_processor);
		}

		return $processor;
	}

	/**
	 * Transform image URLs in an image tag.
	 *
	 * @since 4.0.0
	 * 
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * @param array                  $dimensions The image dimensions.
	 * @param string                 $original_html The original HTML.
	 * @param string                 $context The transformation context.
	 * @param array                  $transform_args Optional. Additional transformation arguments.
	 */
	public static function transform_image_urls(
		\WP_HTML_Tag_Processor $processor,
		array $dimensions,
		string $original_html,
		string $context,
		array $transform_args = []
	): void {
		// Get the src
		$src = $processor->get_attribute('src');
		if (!$src) {
			return;
		}

		// Get attachment ID if available
		$attachment_id = null;
		
		// First try to get ID from classes
		$classes = $processor->get_attribute('class') ?? '';
		if (preg_match('/wp-image-(\d+)/', $classes, $matches)) {
			$attachment_id = (int) $matches[1];
		}

		// Calculate aspect ratio and validate dimensions
		$width = (int) $dimensions['width'];
		$height = (int) $dimensions['height'];
		
		if ($width <= 0 || $height <= 0) {
			return;
		}

		$ratio = $height / $width;

		// Determine if we should constrain the image
		$should_constrain = self::should_constrain_image($processor, $original_html, $context);

		// Get content width if we're constraining
		if ($should_constrain) {
			$max_width = min($width, Settings::get_max_width());
			$max_height = round($max_width * $ratio);
			
			$dimensions = [
				'width' => (string) $max_width,
				'height' => (string) $max_height
			];
		}

		// Check if this is an SVG
		if (Helpers::is_svg($src)) {
			// For SVGs, just set the dimensions without transforming the URL
			$processor->set_attribute('width', $dimensions['width']);
			$processor->set_attribute('height', $dimensions['height']);
			return;
		}

		// Get a provider instance to access default args
		$provider = self::get_provider_instance();

		// Bail if we don't have a provider
		if (!$provider) {
			return;
		}
		
		// Transform src with dimensions - merge in this order to preserve transform_args values
		$default_args = $provider->get_default_args();

		$edge_args = array_merge(
			$default_args,
			$transform_args,
			[
				'width' => $dimensions['width'],
				'height' => $dimensions['height'],
			]
		);

		// Get full size URL
		$full_src = self::get_full_size_url($src, $attachment_id);
		
		$transformed_src = Helpers::edge_src($full_src, $edge_args);
		$processor->set_attribute('src', $transformed_src);
		
		// Update width and height attributes
		$processor->set_attribute('width', $dimensions['width']);
		$processor->set_attribute('height', $dimensions['height']);
		
		// Get sizes attribute
		$sizes = $processor->get_attribute('sizes') ?? 
			"(max-width: {$dimensions['width']}px) calc(100vw - 2.5rem), {$dimensions['width']}px";
		
		// Generate srcset using the constrained dimensions and original transform args
		$srcset = Srcset_Transformer::transform(
			$full_src, 
			$dimensions,
			$sizes,
			$transform_args
		);

		if ($srcset) {
			$processor->set_attribute('srcset', $srcset);
			$processor->set_attribute('sizes', $sizes);
		}
	}

	/**
	 * Clean transformation attributes from an image tag.
	 *
	 * @since 4.5.0
	 * 
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * @return \WP_HTML_Tag_Processor The cleaned processor.
	 */
	public static function clean_transform_attributes(\WP_HTML_Tag_Processor $processor): \WP_HTML_Tag_Processor {
		// Get all valid transformation parameters
		$valid_args = Edge_Provider::get_valid_args();
		$all_params = [];

		// Include both short forms and aliases
		foreach ($valid_args as $short => $aliases) {
			// Skip width and height as they're valid HTML attributes
			if ($short === 'w' || $short === 'h') {
				continue;
			}
			
			$all_params[] = $short;
			if (is_array($aliases)) {
				$all_params = array_merge($all_params, $aliases);
			}
		}

		// Remove transformation attributes
		foreach ($all_params as $param) {
			$processor->remove_attribute($param);
		}

		return $processor;
	}

	/**
	 * Check if image should be constrained by content width
	 *
	 * @since 4.5.0
	 * 
	 * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
	 * @param string                 $original_html Original HTML string.
	 * @param string                 $context       The context (content, header, etc).
	 * @return bool Whether the image should be constrained
	 */
	private static function should_constrain_image(\WP_HTML_Tag_Processor $processor, string $original_html, string $context = ''): bool {
		// Only constrain images in the main content area
		if (!in_array($context, ['content', 'block', 'post', 'page'], true)) {
			return false;
		}

		// Check for alignment classes that indicate full-width
		$classes = $processor->get_attribute('class') ?? '';
		
		if (preg_match('/(alignfull|alignwide|full-width|width-full)/i', $classes)) {
			return false;
		}

		// Check parent figure for alignment classes
		if (strpos($original_html, '<figure') !== false) {
			if (preg_match('/<figure[^>]*class=["\']([^"\']*)["\']/', $original_html, $matches)) {
				if (preg_match('/(alignfull|alignwide|full-width|width-full)/i', $matches[1])) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Get the full size URL for an image.
	 *
	 * @since 4.0.0
	 * 
	 * @param string   $src           The source URL.
	 * @param int|null $attachment_id Optional. The attachment ID.
	 * @return string The full size URL.
	 */
	public static function get_full_size_url(string $src, ?int $attachment_id = null): string {
		// If URL is already transformed, get the original URL first
		if (Helpers::is_transformed_url($src)) {
			$src = Helpers::get_original_url($src);
		}

		// If we have an attachment ID, try to get the full size URL
		if ($attachment_id) {
			$full_src = wp_get_attachment_url($attachment_id);
			if ($full_src) {
				return $full_src;
			}
		}

		// Try to get the original filename by removing dimensions
		$src = preg_replace('/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $src);
		
		return $src;
	}

	/**
	 * Get the edge provider instance.
	 * 
	 * @since 4.5.0
	 * @return Edge_Provider|null The provider instance, or null if none configured.
	 */
	private static function get_provider_instance(): ?Edge_Provider {
		if (self::$provider_instance === null) {
			self::$provider_instance = Helpers::get_edge_provider();
		}
		return self::$provider_instance;
	}

	/**
	 * Clean transformation attributes from attachment image attributes.
	 *
	 * @since 4.5.0
	 * 
	 * @param array $attr Image attributes.
	 * @return array Cleaned attributes.
	 */
	public static function clean_attachment_image_attributes(array $attr): array {
		// Get all valid transformation parameters
		$valid_args = Edge_Provider::get_valid_args();
		$all_params = [];

		// Include both short forms and aliases
		foreach ($valid_args as $short => $aliases) {
			// Skip width and height as they're valid HTML attributes
			if ($short === 'w' || $short === 'h') {
				continue;
			}
			
			$all_params[] = $short;
			if (is_array($aliases)) {
				$all_params = array_merge($all_params, $aliases);
			}
		}

		// Remove transformation attributes
		foreach ($all_params as $param) {
			unset($attr[$param]);
		}

		return $attr;
	}
} 