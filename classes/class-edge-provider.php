<?php

namespace Edge_Images;

use Edge_Images\Helpers;

/**
 * Describes an edge provider.
 */
class Edge_Provider {

	/**
	 * The args to set for images.
	 *
	 * @var array
	 */
	public array $args = array(
		'width'    => null,
		'height'   => null,
		'fit'      => null,
		'f'        => null,
		'q'        => null,
		'dpr'      => null,
		'sharpen'  => null,
		'blur'     => null,
		'gravity'  => null,
		'onerror'  => null,
		'metadata' => null,
	);

	/**
	 * The image path
	 *
	 * @var string
	 */
	public string $path;

	/**
	 * Create the provider
	 *
	 * @param string $path The path to the image.
	 * @param array  $args The arguments.
	 */
	public function __construct( string $path, array $args = array() ) {
		$this->path = $path;
		$this->args = $args;
		$this->normalize_args();
	}

	/**
	 * Get the args
	 *
	 * @return array The args.
	 */
	protected function get_transform_args() : array {

		$args = array(
			'width'   => ( isset( $this->args['width'] ) ) ? $this->args['width'] : Helpers::get_content_width(),
			'height'  => ( isset( $this->args['height'] ) ) ? $this->args['height'] : null,
			'fit'     => ( isset( $this->args['fit'] ) ) ? $this->args['fit'] : 'cover',
			'f'       => ( isset( $this->args['f'] ) ) ? $this->args['f'] : 'webp',
			'q'       => ( isset( $this->args['q'] ) ) ? $this->args['q'] : Helpers::get_image_quality_default(),
			'dpr'     => ( isset( $this->args['dpr'] ) ) ? $this->args['dpr'] : 1,
			'sharpen' => ( isset( $this->args['sharpen'] ) ) ? $this->args['sharpen'] : null,
			'blur'    => ( isset( $this->args['blur'] ) ) ? $this->args['blur'] : null,
			'gravity' => ( isset( $this->args['gravity'] ) ) ? $this->args['gravity'] : null,
		);

		// Unset any empty/null properties.
		foreach ( $args as $k => $v ) {
			if (
				! $v ||
				is_null( $v ) ||
				( is_array( $v ) && empty( $v ) ) ||
				( is_string( $v ) && $v !== '' )
				) {
					unset( $args[ $k ] );
			}
		}

		// Remove empty values and sort our array.
		ksort( array_filter( $args ) );

		return $args;
	}

	/**
	 * Normalize our argument values.
	 *
	 * @return void
	 */
	private function normalize_args() : void {
		$args = $this->args;

		// Convert 'format' to 'f'.
		if ( isset( $args['format'] ) ) {
			$args['f'] = $args['format'];
			unset( $args['format'] );
		}

		// Convert 'gravity' to 'g'.
		if ( isset( $args['gravity'] ) ) {
			$args['g'] = $args['gravity'];
			unset( $args['gravity'] );
		}

		// Convert 'quality' to 'q'.
		if ( isset( $args['quality'] ) ) {
			$args['q'] = $args['quality'];
			unset( $args['q'] );
		}

		$this->args = array_filter( $args );
	}


}
