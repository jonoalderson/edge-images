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
	 * Cache for image dimensions to avoid repeated lookups.
	 *
	 * @since 4.1.0
	 * @var array<int,array>
	 */
	private static array $dimension_cache = [];

	/**
	 * Cache for post images to avoid repeated meta queries.
	 *
	 * @since 4.1.0
	 * @var array<int,array>
	 */
	private static array $post_images_cache = [];

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

		// Add filters with higher priority to ensure they run before Yoast's internal filters
		add_filter( 'wpseo_opengraph_image', [ $instance, 'route_image_through_edge' ], 5, 2 );
		add_filter( 'wpseo_opengraph_image_url', [ $instance, 'route_image_through_edge' ], 5, 2 );
		add_filter( 'wpseo_twitter_image', [ $instance, 'route_image_through_edge' ], 5, 2 );
		add_filter( 'wpseo_twitter_image_url', [ $instance, 'route_image_through_edge' ], 5, 2 );
		add_filter( 'wpseo_opengraph_image_size', [ $instance, 'set_full_size_og_image' ], 5 );
		add_filter( 'wpseo_frontend_presentation', [ $instance, 'set_image_dimensions' ], 5, 1 );

		// Add cache busting hooks
		add_action( 'save_post', [ $instance, 'bust_cache' ], 10, 3 );
		add_action( 'deleted_post', [ $instance, 'bust_cache' ] );
		add_action( 'attachment_updated', [ $instance, 'bust_attachment_cache' ], 10, 3 );
	}

	/**
	 * Checks if these filters should run.
	 *
	 * @since 4.0.0
	 * 
	 * @return bool Whether the filters should run.
	 */
	private function should_filter(): bool {
		// Bail if the Yoast SEO integration is disabled
		$disable_integration = apply_filters( 'edge_images_yoast_disable', false );
		if ( $disable_integration ) {
			return false;
		}

		// Bail if social image filtering is disabled
		$enabled = get_option( 'edge_images_yoast_social_images', true );
		if ( ! $enabled ) {
			return false;
		}

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
		static $processed_urls = [];

		// Use static cache for already processed URLs in this request
		if (isset($processed_urls[$url])) {
			return $processed_urls[$url];
		}

		// Bail if URL isn't a string or is empty
		if ( ! is_string( $url ) || empty( $url ) ) {
			$processed_urls[$url] = $url;
			return $url;
		}

		// Skip if URL is already transformed
		if ( Helpers::is_transformed_url( $url ) ) {
			$processed_urls[$url] = $url;
			return $url;
		}

		// Get the image ID from the URL
		$image_id = Helpers::get_attachment_id( $url );
		if ( ! $image_id ) {
			$processed_urls[$url] = $url;
			return $url;
		}

		// Get dimensions from the image
		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			$processed_urls[$url] = $url;
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
		$transformed_url = Helpers::edge_src( $url, $args );
		$processed_urls[$url] = $transformed_url;

		return $transformed_url;
	}

	/**
	 * Busts the cache for a post's images.
	 *
	 * @since 4.1.0
	 * 
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function bust_cache( int $post_id, \WP_Post $post = null, bool $update = false ): void {
		// Skip revisions and autosaves
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Clear static caches
		unset(self::$post_images_cache[$post_id]);
		
		// Get all images associated with the post
		$images = $this->get_post_images( $post_id );

		// Bust cache for each image
		foreach ( $images as $url ) {
			$clean_url = preg_replace('/\?.*/', '', $url);
			$cache_key = 'attachment_' . md5($clean_url);
			wp_cache_delete($cache_key, Helpers::CACHE_GROUP);
		}
	}

	/**
	 * Busts the cache for an updated attachment.
	 *
	 * @since 4.1.0
	 * 
	 * @param int      $attachment_id Attachment ID.
	 * @param array    $data          Attachment data.
	 * @param array    $old_data      Old attachment data.
	 * @return void
	 */
	public function bust_attachment_cache( int $attachment_id, array $data, array $old_data ): void {
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return;
		}

		$clean_url = preg_replace('/\?.*/', '', $url);
		$cache_key = 'attachment_' . md5($clean_url);
		wp_cache_delete($cache_key, Helpers::CACHE_GROUP);
	}

	/**
	 * Gets all images associated with a post.
	 *
	 * @since 4.1.0
	 * 
	 * @param int $post_id Post ID.
	 * @return array Array of image URLs.
	 */
	private function get_post_images( int $post_id ): array {
		// Return cached result if available
		if (isset(self::$post_images_cache[$post_id])) {
			return self::$post_images_cache[$post_id];
		}

		$images = [];

		// Get featured image
		if ( has_post_thumbnail( $post_id ) ) {
			$images[] = get_the_post_thumbnail_url( $post_id, 'full' );
		}

		// Get all Yoast meta in one query instead of multiple
		$yoast_meta = get_post_meta( $post_id, '', true );
		
		// Get Yoast SEO image
		if ( !empty($yoast_meta['_yoast_wpseo_opengraph-image'][0]) ) {
			$images[] = $yoast_meta['_yoast_wpseo_opengraph-image'][0];
		}

		// Get Twitter image
		if ( !empty($yoast_meta['_yoast_wpseo_twitter-image'][0]) ) {
			$images[] = $yoast_meta['_yoast_wpseo_twitter-image'][0];
		}

		$images = array_unique( array_filter( $images ) );
		
		// Cache the result
		self::$post_images_cache[$post_id] = $images;

		return $images;
	}
}
