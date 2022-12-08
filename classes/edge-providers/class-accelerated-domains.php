<?php

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * Describes the Accelerated Domains edge provider.
 */
class Accelerated_Domains extends Edge_Provider {

	/**
	 * Get the edge URL
	 * E.g., https://www.example.com/acd-cgi/img/v1/path-to-image.jpg?width=200&height=200
	 *
	 * @return string The edge URL.
	 */
	public function get_edge_url() : string {
		$edge_prefix = Helpers::get_rewrite_domain() . '/acd-cgi/img/v1';

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

}
