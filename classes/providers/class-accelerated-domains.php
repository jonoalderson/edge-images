<?php

namespace Edge_Images\Providers;

use Edge_Images\Helpers;

/**
 * Describes the Accelerated Domains edge provider.
 */
class Accelerated_Domains {

	/**
	 * Create the provider
	 *
	 * @param string $path The path to the image.
	 * @param array  $args The arguments.
	 */
	public function __construct( string $path, array $args = array() ) {
		$this->path = $path;
		$this->args = $args;
	}

	/**
	 * Get the properties
	 *
	 * @return array The properties.
	 */
	private function get_properties() : array {

		$properties = array(
			'width'   => ( isset( $this->args['width'] ) ) ? $this->args['width'] : Helpers::get_content_width(),
			'fit'     => ( isset( $this->args['fit'] ) ) ? $this->args['fit'] : 'cover',
			'format'  => ( isset( $this->args['format'] ) ) ? $this->args['format'] : 'webp',
			'quality' => ( isset( $this->args['quality'] ) ) ? $this->args['quality'] : Helpers::get_image_quality_high(),
			'gravity' => ( isset( $this->args['gravity'] ) ) ? $this->args['gravity'] : 'auto',
		);

		// Optional properties.
		if ( isset( $this->args['height'] ) ) {
			$properties['height'] = $this->args['height'];
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
				'&'
			)
		);

		return $edge_url;
	}






}
