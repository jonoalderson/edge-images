<?php

namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;

/**
 * Configures the og:image to use the CF rewriter.
 */
class Social_Images {

	/**
	 * The og:width value
	 *
	 * @var integer
	 */
	const OG_WIDTH = 1200;

	/**
	 * The og:height value
	 *
	 * @var integer
	 */
	const OG_HEIGHT = 675;

	/**
	 * Register the Integration
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
		add_filter( 'wpseo_opengraph_image_size', array( $instance, 'set_full_size_og_image' ) );
		add_filter( 'wpseo_opengraph_image', array( $instance, 'route_image_through_cf' ), 10, 2 );
		add_filter( 'wpseo_twitter_image', array( $instance, 'route_image_through_cf' ), 10, 2 );
		add_filter( 'wpseo_frontend_presentation', array( $instance, 'set_image_dimensions' ), 30, 1 );
	}

	/**
	 * Overwrite the og:image:width and og:image:height
	 *
	 * @param array $presentation The presentation.
	 *
	 * @return array The modified presentation
	 */
	public function set_image_dimensions( $presentation ) {

		if ( ! $presentation->open_graph_images ) {
			return $presentation; // Bail if there's nothing here.
		}

		$key = array_key_first( $presentation->open_graph_images );
		if ( ! isset( $presentation->open_graph_images[ $key ] ) ) {
			return $presentation; // Bail if there's no key.
		}

		$presentation->open_graph_images[ $key ]['width']  = self::OG_WIDTH;
		$presentation->open_graph_images[ $key ]['height'] = self::OG_HEIGHT;

		return $presentation;
	}

	/**
	 * Set the size of the og:image
	 *
	 * @return string The image size to use
	 */
	public function set_full_size_og_image() : string {
		return 'full';
	}

	/**
	 * Sets the og:image to the max size
	 *
	 * @param string $output    The tag value.
	 * @param object $presenter The presenter.
	 *
	 * @return string The modified string
	 */
	public function route_image_through_cf( string $output, object $presenter ) : string {

		// Get the image ID.
		$image_id = $presenter->model->open_graph_image_id;
		if ( ! $image_id ) {
			return $output; // Bail if there's no image ID.
		}

		// Get the image.
		$image = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! $image || ! isset( $image ) || ! isset( $image[0] ) ) {
			return $output; // Bail if there's no image.
		}

		// Convert the image src to a Cloudflare string.
		$src = Helpers::cf_src( $image[0], self::OG_WIDTH, self::OG_HEIGHT );

		return ( $src ) ? $src : $output;
	}

}
