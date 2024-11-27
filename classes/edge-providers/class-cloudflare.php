<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images\Edge_Providers
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * Describes the Cloudflare edge provider.
 */
class Cloudflare extends Edge_Provider {

	/**
	 * The root of the Cloudflare edge URL
	 *
	 * @var string
	 */
	const EDGE_ROOT = '/cdn-cgi/image/';

	/**
	 * Get the edge URL
	 *
	 * @return string The edge URL.
	 */
	public function get_edge_url(): string {
		$edge_prefix = Helpers::get_rewrite_domain() . self::EDGE_ROOT;
		
		// Get transform args and ensure they're properly formatted
		$transform_args = $this->get_transform_args();

		$edge_url = $edge_prefix . http_build_query(
			$transform_args,
			'',
			'%2C' // comma
		);
		
		return $edge_url . $this->path;
	}

	/**
	 * Get the URL pattern used to identify transformed images
	 *
	 * @return string The URL pattern
	 */
	public static function get_url_pattern(): string {
		return self::EDGE_ROOT;
	}

}
