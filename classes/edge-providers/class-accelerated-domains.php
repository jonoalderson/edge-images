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
 * @license    GPL-3.0-or-later
 * @since      4.0.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

class Accelerated_Domains extends Edge_Provider {

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
	 * Transforms the image URL into an Accelerated Domains-compatible format with
	 * transformation parameters. This method:
	 * - Combines the rewrite domain with the endpoint
	 * - Maps standard parameters to full names
	 * - Constructs the CDN URL
	 * - Handles parameter formatting
	 * - Ensures proper URL structure
	 * - Escapes the final URL
	 *
	 * Format: /acd-cgi/img/v1/path-to-image.jpg?width=200&height=200
	 *
	 * @since      4.0.0
	 * 
	 * @return string The transformed edge URL with Accelerated Domains parameters.
	 */
	public function get_edge_url(): string {
		// Bail early if no path
		if (empty($this->path)) {
			return '';
		}

		// If this is already a transformed URL, extract the original path
		if (strpos($this->path, self::EDGE_ROOT) !== false) {
			if (preg_match('#' . self::EDGE_ROOT . '(/[^?]+)#', $this->path, $matches)) {
				$this->path = $matches[1];
			} else {
				return '';
			}
		}

		// Clean the URL to get just the path
		$image_path = Helpers::clean_url($this->path);
		
		// Debug the cleaned path
		
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
			'%s%s?%s',
			Helpers::get_rewrite_domain() . self::EDGE_ROOT,
			$image_path,
			http_build_query($transform_args)
		);
		
		return esc_attr($edge_url);
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
		return '/acd-cgi/img/v1/[^?]+\?';
	}

	/**
	 * Get full transformation arguments with full parameter names.
	 *
	 * Maps short parameter names to their full Accelerated Domains equivalents.
	 * This method:
	 * - Converts short parameter names to full names
	 * - Maintains unmapped parameters
	 * - Ensures parameter compatibility
	 * - Supports URL generation
	 *
	 * @since      4.0.0
	 * 
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
					$full_args[$key] = $value;
					break;
			}
		}

		return $full_args;
	}
}
