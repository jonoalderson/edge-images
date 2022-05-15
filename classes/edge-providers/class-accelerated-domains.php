<?php

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * Describes the Accelerated Domains edge provider.
 */
class Accelerated_Domains extends Edge_Provider {

	/**
	 * Get the properties
	 *
	 * @return array The properties.
	 */
	private function get_properties() : array {

		$properties = array(
			'width' => ( isset( $this->args['width'] && $this->args['width'] ) ) ? $this->args['width'] : Helpers::get_content_width(),
			'fit'   => ( isset( $this->args['fit'] ) ) ? $this->args['fit'] : 'cover',
			'f'     => ( isset( $this->args['format'] ) ) ? $this->args['format'] : 'webp',
			'q'     => ( isset( $this->args['quality'] ) ) ? $this->args['quality'] : Helpers::get_image_quality_default(),
			'dpr'   => ( isset( $this->args['dpr'] ) ) ? $this->args['dpr'] : 1,
		);

		// Height.
		if ( isset( $this->args['height'] ) && $this->args['height'] ) {
			$properties['height'] = $this->args['height'];
		}

		// Blue.
		if ( isset( $this->args['blur'] ) && $this->args['blur'] ) {
			$properties['blur'] = $this->args['blur'];
		}

		// Sharpen.
		if ( isset( $this->args['sharpen'] ) && $this->args['sharpen'] ) {
			$properties['sharpen'] = $this->args['sharpen'];
		}

		// Gravity.
		if ( isset( $this->args['gravity'] ) && $this->args['gravity'] ) {
			$properties['gravity'] = $this->args['gravity'];
		}

		ksort( $properties );

		return $properties;
	}

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
				$this->get_properties(),
				'',
				'|'
			)
		);

		return esc_attr( $edge_url ); // Escape the ampersands to match WP's image handling.
	}

}
