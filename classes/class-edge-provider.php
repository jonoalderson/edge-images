<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

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
	public array $args = array();

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

		$this->args = wp_parse_args(
			$args,
			array(
				'width'    => Helpers::get_content_width(),
				'height'   => null,
				'fit'      => 'cover',
				'f'        => null,
				'q'        => Helpers::get_image_quality_default(),
				'dpr'      => 1,
				'sharpen'  => null,
				'blur'     => null,
				'gravity'  => null,
				'onerror'  => null,
				'metadata' => null,
			)
		);

		$this->normalize_args();
	}

	/**
	 * Get the args
	 *
	 * @return array The args.
	 */
	protected function get_transform_args() : array {

		$args = array(
			'width'   => ( isset( $this->args['width'] ) ) ? $this->args['width'] : null,
			'height'  => ( isset( $this->args['height'] ) ) ? $this->args['height'] : null,
			'fit'     => ( isset( $this->args['fit'] ) ) ? $this->args['fit'] : null,
			'f'       => ( isset( $this->args['f'] ) ) ? $this->args['f'] : 'auto',
			'q'       => ( isset( $this->args['q'] ) ) ? $this->args['q'] : null,
			'dpr'     => ( isset( $this->args['dpr'] ) ) ? $this->args['dpr'] : null,
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
				( is_string( $v ) && $v === '' )
				) {
					unset( $args[ $k ] );
			}
		}

		// Remove empty values and sort our array.
		$args = array_filter( $args );
		ksort( $args );

		return $args;
	}

	/**
	 * Normalize our argument values.
	 *
	 * @return void
	 */
	private function normalize_args() : void {

		// Convert 'format' to 'f'.
		if ( isset( $this->args['format'] ) ) {
			$this->args['f'] = $this->args['format'];
			unset( $this->args['format'] );
		}

		// Convert 'gravity' to 'g'.
		if ( isset( $this->args['gravity'] ) ) {
			$this->args['g'] = $this->args['gravity'];
			unset( $this->args['gravity'] );
		}

		// Convert 'quality' to 'q'.
		if ( isset( $this->args['quality'] ) ) {
			$this->args['q'] = $this->args['quality'];
			unset( $this->args['quality'] );
		}

		// Align loading and fetchpriority values.
		$this->align_loading_and_fetchpriority();

		// Tidy up our array.
		$this->args = array_filter( $this->args );
	}

	/**
	 * If loading is set to eager, set fetchpriority to high
	 *
	 * @return void
	 */
	private function align_loading_and_fetchpriority() : void {
		if ( isset( $this->$args['loading'] ) && ( $this->$args['loading'] === 'eager' ) ) {
			$this->args['fetchpriority'] = 'high';
		}
	}


}
