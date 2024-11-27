<?php
/**
 * Yoast SEO social images integration.
 *
 * Handles the transformation of images in Yoast SEO's social media tags.
 * Ensures that og:image and twitter:image tags use optimized edge versions.
 *
 * @package    Edge_Images\Integrations
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.0.0
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Yoast\WP\SEO\Presenters\Open_Graph\Image_Presenter;
use Edge_Images\{Helpers, Image_Dimensions};

/**
 * Configures the og:image to use the edge provider.
 *
 * @since 4.0.0
 */
class Social_Images {

	/**
	 * The og:width value.
	 *
	 * @since 4.0.0
	 * @var int
	 */
	public const OG_WIDTH = 1200;

	/**
	 * The og:height value.
	 *
	 * @since 4.0.0
	 * @var int
	 */
	public const OG_HEIGHT = 675;

	/**
	 * Register the integration.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	public static function register(): void {
		$instance = new self();

		// Bail if these filters shouldn't run.
		if ( ! $instance->should_filter() ) {
			return;
		}

		// Add filters for all possible hooks
		add_filter( 'wpseo_opengraph_image_url', [ $instance, 'route_image_through_edge' ], 10, 2 );
		add_filter( 'wpseo_twitter_image_url', [ $instance, 'route_image_through_edge' ], 10, 2 );
		add_filter( 'wpseo_opengraph_image_size', [ $instance, 'set_full_size_og_image' ] );
		add_filter( 'wpseo_frontend_presentation', [ $instance, 'set_image_dimensions' ], 30, 1 );
	}

	/**
	 * Checks if these filters should run.
	 *
	 * @since 4.0.0
	 * 
	 * @return bool Whether the filters should run.
	 */
	private function should_filter(): bool {

		// Check if the provider is properly configured
		if ( ! Helpers::is_provider_configured() ) {
			return false;
		}

		return true;
	}

	/**
	 * Manage the og:image:width and og:image:height.
	 *
	 * @since 4.0.0
	 * 
	 * @param object $presentation The presentation object.
	 * @return object The modified presentation.
	 */
	public function set_image_dimensions( $presentation ) {
		// Bail if there's no open graph image info.
		if ( ! $presentation->open_graph_images ) {
			return $presentation;
		}

		// Bail if there's no OG images key.
		$key = array_key_first( $presentation->open_graph_images );
		if ( ! isset( $presentation->open_graph_images[ $key ] ) ) {
			return $presentation;
		}

		// Remove the og:image:type (we don't know what it'll be if it's transformed).
		if ( isset( $presentation->open_graph_images[ $key ]['type'] ) ) {
			unset( $presentation->open_graph_images[ $key ]['type'] );
		}

		// Get the image ID.
		$image_id = $presentation->model->open_graph_image_id;
		if ( ! $image_id ) {
			return $presentation;
		}

		// Get dimensions from the image.
		$dimensions = Image_Dimensions::from_attachment( $image_id );
		if ( ! $dimensions ) {
			return $presentation;
		}

		// Set the width and height based on the image's max dimensions.
		$presentation->open_graph_images[ $key ]['width']  = self::OG_WIDTH;
		$presentation->open_graph_images[ $key ]['height'] = self::OG_HEIGHT;

		return $presentation;
	}

	/**
	 * Set the size of the og:image.
	 *
	 * @since 4.0.0
	 * 
	 * @return string The image size to use.
	 */
	public function set_full_size_og_image(): string {
		return 'full';
	}

	/**
	 * Sets the og:image to the max size.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $url       The image URL.
	 * @param mixed  $presenter The presenter (unused).
	 * @return string The modified URL.
	 */
	public function route_image_through_edge( $url, $presenter = null ): string {
		// Bail if URL isn't a string.
		if ( ! is_string( $url ) || empty( $url ) ) {
			return $url;
		}

		// Get the image ID from the URL
		$image_id = attachment_url_to_postid( $url );
		if ( ! $image_id ) {
			return $url;
		}

		// Get dimensions from the image
		$dimensions = Image_Dimensions::from_attachment( $image_id );
		if ( ! $dimensions ) {
			return $url;
		}

		// Set our default args
		$args = [
			'width'  => self::OG_WIDTH,
			'height' => self::OG_HEIGHT,
			'fit'    => 'cover',
		];

		// Tweak the behaviour for small images
		if ( (int) $dimensions['width'] < self::OG_WIDTH || (int) $dimensions['height'] < self::OG_HEIGHT ) {
			$args['fit']     = 'pad';
			$args['sharpen'] = 2;
		}

		// Allow for filtering the args
		$args = apply_filters( 'edge_images_yoast_social_image_args', $args );

		// Convert the image src to an edge SRC
		return Helpers::edge_src( $url, $args );
	}
}
