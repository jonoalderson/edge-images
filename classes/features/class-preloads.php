<?php

namespace Edge_Images\Features;

use Edge_Images\{Helpers, Image};

/**
 * Configures hero image preload headers (using the edge rewriter).
 */
class Preloads {

	/**
	 * Register the Integration
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
		add_action( 'wp_head', array( $instance, 'preload_filtered_images' ), 1 );
	}

	/**
	 * Preload any filtered images.
	 *
	 * @return void
	 */
	public function preload_filtered_images() : void {
		$images = apply_filters( 'Edge_Images\preloads', array() );

		// Bail if $images isn't an array.
		if ( ! is_array( $images ) || empty( $images ) ) {
			return;
		}

		// Bail if there aren't any images.
		if ( empty( $images ) ) {
			return;
		}

		// Remove any duplicate entries.
		$images = array_unique( $images, SORT_REGULAR );

		// Iterate through the images.
		foreach ( $images as $image ) {
			// Bail if we don't have an ID and a size.
			if ( ! isset( $image['id'] ) || ! isset( $image['size'] ) ) {
				continue;
			}
			$this->preload_image( $image['id'], $image['size'] );
		}
	}

	/**
	 * Echoes a rel preload tag for an image
	 *
	 * @param  int   $id   The image ID.
	 * @param  mixed $size The image size.
	 *
	 * @return void
	 */
	private function preload_image( int $id, $size ) : void {

		$image = \Edge_Images\get_edge_image_object( $id, array(), $size );

		// Bail if there's no image, or if it's malformed.
		if ( ! $image || ! $this->is_valid( $image ) ) {
			return;
		}

		echo sprintf(
			'<link rel="preload" as="image" imagesrcset="%s" imagesizes="%s">',
			implode( ', ', $image->attrs['srcset'] ),
			$image->attrs['sizes'],
		) . PHP_EOL;

	}

	/**
	 * Checks if an image is valid for preloading
	 *
	 * @param  mixed $image The image.
	 *
	 * @return bool
	 */
	private function is_valid( $image ) : bool {

		// Bail if this isn't an Image.
		if ( ! is_a( $image, 'Edge_Images\Image' ) ) {
			return false;
		}

		// Bail if we're missing key properties.
		if (
			! isset( $image->attrs['srcset'] ) ||
			! isset( $image->attrs['sizes'] )
		) {
			return false;
		}

		return true;
	}

}
