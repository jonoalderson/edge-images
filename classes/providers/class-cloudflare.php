<?php

namespace Edge_Images\Providers;

use Edge_Images\{Helpers, Image};

/**
 * Describes the Cloudflare edge provider.
 */
class Cloudflare {

	/**
	 * Create the provider
	 *
	 * @param string $path The path to the image.
	 * @param array  $args The arguments.
	 */
	public function __construct( string $path, array $args = array() ) {
		$this->path = $path;
		$this->args = $args;
		$this->init();
	}

	/**
	 * Get the properties
	 *
	 * @return array The properties.
	 */
	private function get_properties() : array {

		$properties = array(
			'width'    => ( isset( $args['width'] ) ) ? $args['width'] : Helpers::get_content_width(),
			'fit'      => ( isset( $args['fit'] ) ) ? $args['fit'] : 'cover',
			'f'        => ( isset( $args['format'] ) ) ? $args['format'] : 'auto',
			'q'        => ( isset( $args['quality'] ) ) ? $args['quality'] : Helpers::get_image_quality_high(),
			'gravity'  => ( isset( $args['gravity'] ) ) ? $args['gravity'] : 'auto',
			'onerror'  => ( isset( $args['onerror'] ) ) ? $args['onerror'] : 'redirect',
			'metadata' => ( isset( $args['metadata'] ) ) ? $args['metadata'] : 'none',
		);

		// Optional properties.
		if ( isset( $args['height'] ) ) {
			$properties['height'] = $args['height'];
		}
		if ( isset( $args['blur'] ) ) {
			$properties['blur'] = $args['blur'];
		}

		ksort( $properties );

		return $properties;
	}

	/**
	 * Get the edge URL
	 * E.g., https://staging.prosettings.net/cdn-cgi/image/f=auto%2Cfit=cover[...]path-to-image.jpg
	 *
	 * @return string The edge URL.
	 */
	public function get_edge_url() : string {
		$edge_prefix = Helpers::get_rewrite_domain() . '/cdn-cgi/image/';

		$edge_url = $edge_prefix . http_build_query(
			$this->get_properties(),
			'',
			'%2C'
		);

		return $edge_url . $this->path;
	}

}
