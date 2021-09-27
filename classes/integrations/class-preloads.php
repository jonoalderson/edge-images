<?php

namespace Yoast_CF_Images\Integrations;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;

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
		add_action( 'wp_head', array( $instance, 'preload_hero_image_on_single_posts' ) );
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

		$this->preload_image( $thumbnail_id, 'banner' );
	}

	/**
	 * Add a rel preload tag for an image
	 *
	 * @param  int   $id   The image ID.
	 * @param  mixed $size The image size.
	 *
	 * @return void
	 */
	private function preload_image( int $id, $size ) : void {

		$image = get_cf_image_object( $id, array(), $size );
		if ( ! $image ) {
			return;
		}

		echo sprintf(
			'<link rel="preload" as="image" imagesrcset="%s" imagesizes="%s">',
			implode( ' ', $image->attrs['srcset'] ),
			$image->attrs['sizes'],
		);

	}

}
