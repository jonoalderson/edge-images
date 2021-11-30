<?php

namespace Edge_Images\Integrations;

use Edge_Images\{Helpers, Cloudflare_Image};

/**
 * Configures hero image preload headers (using the CF rewriter).
 */
class Preloads {

	/**
	 * Register the Integration
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
		add_action( 'wp_head', array( $instance, 'preload_filtered_images' ), 10 );
	}

	/**
	 * Preload any filtered images.
	 *
	 * @return void
	 */
	public function preload_filtered_images() : void {
		$images = apply_filters( 'preload_cf_images', array() );

		// Bail if there aren't any images.
		if ( empty( $images ) ) {
			return;
		}

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

		$image = get_cf_image_object( $id, array(), $size );

		// Bail if there's no image, or if it's malformed.
		if ( ! $image || ! $this->is_valid( $image ) ) {
			return;
		}

		echo sprintf(
			'<link rel="preload" as="image" imagesrcset="%s" imagesizes="%s">',
			esc_attr( implode( ', ', $image->attrs['srcset'] ) ),
			esc_attr( $image->attrs['sizes'] ),
		);

	}

	/**
	 * Checks if an image is valid for preloading
	 *
	 * @param  mixed $image The image.
	 *
	 * @return bool
	 */
	private function is_valid( $image ) : bool {

		// Bail if this isn't a Cloudflare Image.
		if ( ! is_a( $image, 'Edge_Images\Cloudflare_Image' ) ) {
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
