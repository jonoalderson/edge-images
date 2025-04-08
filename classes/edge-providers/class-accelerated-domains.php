<?php
/**
 * Accelerated Domains edge provider implementation.
 *
 * Handles image transformation through Accelerated Domains' image resizing service.
 * This provider:
 * - Integrates with Accelerated Domains' image processing service
 * - Transforms image URLs to use Accelerated Domains' endpoint
 * - Supports dynamic image resizing and optimization
 * - Maps standard parameters to Accelerated Domains' format
 * - Provides efficient image delivery
 * - Ensures secure image transformation
 *
 * Documentation: https://accelerateddomains.com/docs/image-optimization/
 * 
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.0.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

class Accelerated_Domains extends Edge_Provider {

	/**
	 * Cache of transformed URLs.
	 *
	 * @since 4.5.0
	 * @var array<string,string>
	 */
	private static array $url_cache = [];

	/**
	 * The root of the Accelerated Domains edge URL.
	 *
	 * This path identifies Accelerated Domains' image transformation endpoint.
	 * Used as a prefix for all transformed image URLs.
	 * Format: /acd-cgi/img/v1
	 *
	 * @since      4.0.0
	 * @var        string
	 */
	public const EDGE_ROOT = '/acd-cgi/img/v1';

	/**
	 * Get the edge URL for an image.
	 *
	 * @since 4.0.0
	 * @return string The transformed edge URL.
	 */
	public function get_edge_url(): string {

		// Bail early if no path
		if (empty($this->path)) {
			return '';
		}

		// Generate cache key from path and args
		$cache_key = md5(serialize([
			'path' => $this->path,
			'args' => $this->args,
			'domain' => Helpers::get_rewrite_domain()
		]));
		
		// Check cache first
		if (isset(self::$url_cache[$cache_key])) {
			return self::$url_cache[$cache_key];
		}

		// If this is already a transformed URL, extract the original path
		if (strpos($this->path, self::EDGE_ROOT) !== false) {
			if (preg_match('#' . preg_quote(self::EDGE_ROOT, '#') . '(/[^?]+)#', $this->path, $matches)) {
				$this->path = $matches[1];
			} else {
				return '';
			}
		}

		// Clean the URL to get just the path
		$image_path = Helpers::clean_url($this->path);
		
		// If no valid path found, return empty
		if (empty($image_path)) {
			return '';
		}

		// Get transform arguments
		$transform_args = $this->get_full_transform_args();
		
		// If no transform args, return empty
		if (empty($transform_args)) {
			return '';
		}

		// Build the URL with query parameters
		$edge_url = sprintf(
			'%s%s%s?%s',
			rtrim(Helpers::get_rewrite_domain(), '/'),
			self::EDGE_ROOT,
			$image_path,
			\http_build_query($transform_args)
		);

		// Cache the result
		$final_url = esc_attr($edge_url);
		self::$url_cache[$cache_key] = $final_url;
		
		return $final_url;
	}

	/**
	 * Get the URL pattern used to identify transformed images.
	 *
	 * Used to detect if an image has already been transformed by Accelerated Domains.
	 * This method:
	 * - Returns the Accelerated Domains-specific URL pattern
	 * - Enables detection of transformed images
	 * - Prevents duplicate transformations
	 * - Supports URL validation
	 *
	 * @since      4.0.0
	 * 
	 * @return string The Accelerated Domains URL pattern for transformed images.
	 */
	public static function get_url_pattern(): string {
		return self::EDGE_ROOT;
	}

	/**
	 * Get the pattern to identify transformed URLs.
	 * 
	 * Returns a regex pattern that matches Accelerated Domains' URL structure.
	 * This method:
	 * - Provides regex for URL matching
	 * - Captures transformation parameters
	 * - Supports URL validation
	 * - Ensures proper pattern detection
	 * 
	 * @since      4.5.0
	 * 
	 * @return string The regex pattern to match Accelerated Domains-transformed URLs.
	 */
	public static function get_transform_pattern(): string {
		return self::EDGE_ROOT . '/[^?]+(?:\?|$)';
	}

	/**
	 * Get full transformation arguments with full parameter names.
	 *
	 * @since 4.0.0
	 * @return array<string,mixed> Array of formatted Accelerated Domains parameters.
	 */
	private function get_full_transform_args(): array {
		$args = $this->get_transform_args();

		// Map short args to full names
		$full_args = [];
		foreach ($args as $key => $value) {
			switch ($key) {
				case 'w':
					$full_args['width'] = $value;
					break;
				case 'h':
					$full_args['height'] = $value;
					break;
				default:
					// Preserve all other args unchanged
					$full_args[$key] = $value;
					break;
			}
		}

		return $full_args;
	}

	/**
	 * Get the transformation arguments.
	 *
	 * @since 4.0.0
	 * @return array The transformation arguments.
	 */
	protected function get_transform_args(): array {
		// Get parent args - these already include defaults properly merged
		$args = parent::get_transform_args();
		
		// Return parent args without modification
		return $args;
	}
}
