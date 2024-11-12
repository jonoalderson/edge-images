<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images\Edge_Providers
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * Describes the Accelerated Domains edge provider.
 */
class Accelerated_Domains extends Edge_Provider {

	/**
	 * The root of the Accelerated Domains edge URL
	 *
	 * @var string
	 */
	const EDGE_ROOT = '/acd-cgi/img/v1';

	/**
	 * Get the edge URL
	 * E.g., https://www.example.com/acd-cgi/img/v1/path-to-image.jpg?width=200&height=200
	 *
	 * @return string The edge URL.
	 */
	public function get_edge_url() : string {
		$edge_prefix = Helpers::get_rewrite_domain() . self::EDGE_ROOT;

		$edge_url = sprintf(
			'%s%s?%s',
			$edge_prefix,
			$this->path,
			http_build_query(
				$this->get_transform_args(),
				'',
				'|'
			)
		);

		return esc_attr( $edge_url ); // Escape the ampersands to match WP's image handling.
	}

	/**
	 * Get the URL pattern used to identify transformed images
	 *
	 * @return string The URL pattern
	 */
	public function get_url_pattern(): string {
		return self::EDGE_ROOT;
	}

}
