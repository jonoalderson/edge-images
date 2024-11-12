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
	 * E.g., https://staging.prosettings.net/cdn-cgi/image/f=auto%2Cfit=cover[...]path-to-image.jpg
	 *
	 * @return string The edge URL.
	 */
	public function get_edge_url() : string {
		$edge_prefix = Helpers::get_rewrite_domain() . self::EDGE_ROOT;

		$edge_url = $edge_prefix . http_build_query(
			$this->get_transform_args(),
			'',
			'%2C' // comma.
		);
		return $edge_url . $this->path;
	}

	/**
	 * Get the URL pattern used to identify transformed images
	 *
	 * @return string The URL pattern
	 */
	public static function get_url_pattern(): string {
		return '/cdn-cgi/image/';
	}

}
