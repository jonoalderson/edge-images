<?php

namespace Yoast_CF_Images\Integrations;

use Yoast_CF_Images\{Helpers, Cloudflare_Image};

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
		add_action( 'wp_head', array( $instance, 'preload_hero_image_on_single_posts' ), 1 );
	}

	/**
	 * Preload the hero image for singular posts
	 *
	 * @return void
	 */
	public function preload_hero_image_on_single_posts() : void {
		if ( ! is_singular() ) {
			return;
		}

		$thumbnail_id = get_post_thumbnail_id();
		if ( ! $thumbnail_id ) {
			return;
		}

		self::preload_image( $thumbnail_id, 'banner' );
	}

	/**
	 * Echoes a rel preload tag for an image
	 *
	 * @param  int   $id   The image ID.
	 * @param  mixed $size The image size.
	 *
	 * @return void
	 */
	public static function preload_image( int $id, $size ) : void {

		$image = get_cf_image_object( $id, array(), $size );

		// Bail if there's no image, or if it's malformed.
		if ( ! $image || ! self::is_valid_image( $image ) ) {
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
	 * @param  Cloudflare_Image $image The image.
	 *
	 * @return bool
	 */
	private static function is_valid_image( Cloudflare_Image $image ) : bool {
		if (
			! isset( $image->attrs['srcset'] ) ||
			! isset( $image->attrs['sizes'] ) ) {
				return false;
		}
		return true;
	}

}
