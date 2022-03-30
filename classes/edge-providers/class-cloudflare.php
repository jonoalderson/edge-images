<?php

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * Describes the Cloudflare edge provider.
 */
class Cloudflare extends Edge_Provider {

	/**
	 * Get the properties
	 *
	 * @return array The properties.
	 */
	private function get_properties() : array {

		$properties = array(
			'width'    => ( isset( $this->args['width'] ) ) ? $this->args['width'] : Helpers::get_content_width(),
			'fit'      => ( isset( $this->args['fit'] ) ) ? $this->args['fit'] : 'cover',
			'f'        => ( isset( $this->args['format'] ) ) ? $this->args['format'] : 'auto',
			'q'        => ( isset( $this->args['quality'] ) ) ? $this->args['quality'] : Helpers::get_image_quality_high(),
			'onerror'  => ( isset( $this->args['onerror'] ) ) ? $this->args['onerror'] : 'redirect',
			'metadata' => ( isset( $this->args['metadata'] ) ) ? $this->args['metadata'] : 'none',
			'dpr'      => ( isset( $this->args['dpr'] ) ) ? $this->args['dpr'] : 1,
		);

		// Optional properties.
		if ( isset( $this->args['height'] ) ) {
			$properties['height'] = $this->args['height'];
		}
		if ( isset( $this->args['blur'] ) ) {
			$properties['blur'] = $this->args['blur'];
		}
		if ( isset( $this->args['sharpen'] ) ) {
			$properties['sharpen'] = $this->args['sharpen'];
		}
		if ( isset( $this->args['gravity'] ) ) {
			$properties['gravity'] = $this->args['gravity'];
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

		return esc_attr( $edge_url . $this->path );
	}

}
