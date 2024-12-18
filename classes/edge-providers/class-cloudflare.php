<?php
/**
 * Cloudflare edge provider implementation.
 *
 * Handles image transformation through Cloudflare's image resizing service.
 * Documentation: https://developers.cloudflare.com/images/image-resizing/
 *
 * @package    Edge_Images\Edge_Providers
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      1.0.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * Cloudflare edge provider class.
 *
 * @since 4.0.0
 */
class Cloudflare extends Edge_Provider {

	/**
	 * The root of the Cloudflare edge URL.
	 *
	 * This path identifies Cloudflare's image transformation endpoint.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public const EDGE_ROOT = '/cdn-cgi/image/';

	/**
	 * Get the edge URL for an image.
	 *
	 * Transforms the image URL into a Cloudflare-compatible format with
	 * transformation parameters. Format:
	 * /cdn-cgi/image/param1,param2/path-to-image.jpg
	 *
	 * @since 4.0.0
	 * 
	 * @return string The transformed edge URL.
	 */
	public function get_edge_url(): string {
		$edge_prefix = Helpers::get_rewrite_domain() . self::EDGE_ROOT;
		
		// Get transform args and ensure they're properly formatted.
		$transform_args = $this->get_transform_args();

		// Build the URL with comma-separated parameters.
		$edge_url = $edge_prefix . http_build_query(
			$transform_args,
			'',
			'%2C' // comma
		);
		
		return $edge_url . $this->path;
	}

	/**
	 * Get the URL pattern used to identify transformed images.
	 *
	 * Used to detect if an image has already been transformed by Cloudflare.
	 *
	 * @since 4.0.0
	 * 
	 * @return string The URL pattern.
	 */
	public static function get_url_pattern(): string {
		return self::EDGE_ROOT;
	}

	/**
	 * Get the pattern to identify transformed URLs.
	 * 
	 * @since 4.5.0
	 * 
	 * @return string The pattern to match in transformed URLs.
	 */
	public static function get_transform_pattern(): string {
		return '/cdn-cgi/image/[^/]+/';
	}
}
