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

use Edge_Images\{Helpers, Image_Dimensions, Integration};

/**
 * Configures the og:image to use the edge provider.
 *
 * @since 4.0.0
 */
class Social_Images extends Integration {

	/**
	 * Cache for processed URLs to avoid repeated transformations.
	 *
	 * @since 4.1.0
	 * @var array<string,string>
	 */
	private static $processed_urls = [];

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
	 * Add integration-specific filters.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		add_filter('wpseo_opengraph_image', [$this, 'route_image_through_edge'], 5, 2);
		add_filter('wpseo_opengraph_image_url', [$this, 'route_image_through_edge'], 5, 2);
		add_filter('wpseo_twitter_image', [$this, 'route_image_through_edge'], 5, 2);
		add_filter('wpseo_twitter_image_url', [$this, 'route_image_through_edge'], 5, 2);
		add_filter('wpseo_opengraph_image_size', [$this, 'set_full_size_og_image'], 5);
		add_filter('wpseo_frontend_presentation', [$this, 'set_image_dimensions'], 5, 1);
	}

	/**
	 * Process an image for social media output.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $url The original image URL.
	 * @return string The processed image URL.
	 */
	private function process_social_image( string $url ): string {

		// Use static cache for already processed URLs in this request.
		if ( isset( self::$processed_urls[$url] ) ) {
			return self::$processed_urls[$url];
		}

		// Skip if URL is already transformed.
		if ( Helpers::is_transformed_url( $url ) ) {
			self::$processed_urls[$url] = $url;
			return $url;
		}

		// Get the image ID from the URL.
		$image_id = Helpers::get_attachment_id( $url );
		if ( ! $image_id ) {
			self::$processed_urls[$url] = $url;
			return $url;
		}

		// Get dimensions from the image.
		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			self::$processed_urls[$url] = $url;
			return $url;
		}

		// Set our default args.
		$args = [
			'width'  => self::OG_WIDTH,
			'height' => self::OG_HEIGHT,
			'fit'    => 'cover',
		];

		// Tweak the behaviour for small images.
		if ( (int) $dimensions['width'] < self::OG_WIDTH || (int) $dimensions['height'] < self::OG_HEIGHT ) {
			$args['fit']     = 'pad';
			$args['sharpen'] = 2;
		}

		// Allow for filtering the args.
		$args = apply_filters( 'edge_images_yoast_social_image_args', $args );

		// Convert the image src to an edge SRC.
		$transformed_url = Helpers::edge_src( $url, $args );
		self::$processed_urls[$url] = $transformed_url;

		return $transformed_url;
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
		// Bail if URL isn't a string or is empty.
		if ( ! is_string( $url ) || empty( $url ) ) {
			return $url;
		}

		return $this->process_social_image( $url );
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

		// Set the width and height based on our constants.
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
	 * Get default settings for this integration.
	 *
	 * @since 4.5.0
	 * 
	 * @return array<string,mixed> Default settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_yoast_social_images' => true,
		];
	}

}
