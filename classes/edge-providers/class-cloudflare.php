<?php
/**
 * Cloudflare edge provider implementation.
 *
 * Handles image transformation through Cloudflare's image resizing service.
 * This provider:
 * - Integrates with Cloudflare's Image Resizing service
 * - Transforms image URLs to use Cloudflare's CDN endpoint
 * - Supports dynamic image resizing and optimization
 * - Implements Cloudflare's URL structure and parameters
 * - Provides caching and performance optimization
 * - Ensures secure and efficient image delivery
 *
 * Documentation: https://developers.cloudflare.com/images/image-resizing/
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

class Cloudflare extends Edge_Provider {

	/**
	 * The root of the Cloudflare edge URL.
	 *
	 * This path identifies Cloudflare's image transformation endpoint.
	 * Used as a prefix for all transformed image URLs.
	 * Format: /cdn-cgi/image/
	 * 
	 * @since      4.0.0
	 * @var        string
	 */
	public const EDGE_ROOT = '/cdn-cgi/image/';

	/**
	 * Get the edge URL for an image.
	 *
	 * Transforms the image URL into a Cloudflare-compatible format with
	 * transformation parameters. This method:
	 * - Combines the rewrite domain with Cloudflare's endpoint
	 * - Formats transformation parameters
	 * - Ensures proper URL encoding
	 * - Maintains path integrity
	 *
	 * Format: /cdn-cgi/image/param1,param2/path-to-image.jpg
	 *
	 * @since      4.0.0
	 * 
	 * @return string The transformed edge URL with Cloudflare parameters.
	 */
	public function get_edge_url(): string {
		$edge_prefix = Helpers::get_rewrite_domain() . self::EDGE_ROOT;
		
		// Get transform args and ensure they're properly formatted.
		$transform_args = $this->get_transform_args();

		// Build the URL with comma-separated parameters.
		$edge_url = $edge_prefix . \http_build_query(
			$transform_args,
			'',
			'%2C' // comma
		);
		
		// The path is already cleaned in the constructor
		return $edge_url . $this->path;
	}

	/**
	 * Get the URL pattern used to identify transformed images.
	 *
	 * Used to detect if an image has already been transformed by Cloudflare.
	 * This method:
	 * - Returns the Cloudflare-specific URL pattern
	 * - Enables detection of transformed images
	 * - Prevents duplicate transformations
	 * - Supports URL validation
	 *
	 * @since      4.0.0
	 * 
	 * @return string The Cloudflare URL pattern for transformed images.
	 */
	public static function get_url_pattern(): string {
		return self::EDGE_ROOT;
	}

	/**
	 * Get the pattern to identify transformed URLs.
	 * 
	 * Returns a regex pattern that matches Cloudflare's URL structure.
	 * This method:
	 * - Provides a regex pattern for URL matching
	 * - Captures transformation parameters
	 * - Supports URL validation
	 * - Ensures proper pattern detection
	 * 
	 * @since      4.5.0
	 * 
	 * @return string The regex pattern to match Cloudflare-transformed URLs.
	 */
	public static function get_transform_pattern(): string {
		return '/cdn-cgi/image/[^/]+/';
	}
}
