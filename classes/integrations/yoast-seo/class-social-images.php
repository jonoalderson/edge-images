<?php

namespace Edge_Images\Integrations\Yoast_SEO;

use Yoast\WP\SEO\Presenters\Open_Graph\Image_Presenter;
use Edge_Images\Helpers;

/**
 * Configures the og:image to use the edge.
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
		add_filter( 'wpseo_opengraph_image', array( $instance, 'route_image_through_edge' ), 10, 2 );
		add_filter( 'wpseo_twitter_image', array( $instance, 'route_image_through_edge' ), 10, 2 );
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
	 * @param string          $output    The tag value.
	 * @param Image_Presenter $presenter The presenter.
	 *
	 * @return string The modified string
	 */
	public function route_image_through_edge( $output, $presenter ) : string {

		// Bail if $output isn't a string.
		if ( ! is_string( $output ) ) {
			return $output;
		}

		// Baul if $presenter isn't an Image_Presenter.
		if ( ! is_a( $presenter, 'Image_Presenter' ) ) {
			return $output;
		}

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
		$args = array(
			'width'  => self::OG_WIDTH,
			'height' => self::OG_HEIGHT,
		);
		$src  = Helpers::edge_src( $image[0], $args );

		return ( $src ) ? $src : $output;
	}

}
