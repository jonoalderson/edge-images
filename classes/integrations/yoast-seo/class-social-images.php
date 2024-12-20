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

use Edge_Images\{Helpers, Image_Dimensions, Integration, Cache, Settings, Integration_Manager};

/**
 * Configures the og:image to use the edge provider.
 *
 * @since 4.0.0
 */
class Social_Images extends Integration {

	/**
	 * Cache group for social image processing.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	private const SOCIAL_CACHE_GROUP = 'edge_images_social';

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

		// Cache busting hooks
		add_action('save_post', [$this, 'bust_social_cache']);
		add_action('deleted_post', [$this, 'bust_social_cache']);
		add_action('attachment_updated', [$this, 'bust_attachment_social_cache'], 10, 3);
		add_action('delete_attachment', [$this, 'bust_attachment_social_cache']);
		add_action('wpseo_save_indexable', [$this, 'bust_indexable_social_cache']);
	}

	/**
	 * Bust social image cache for a post.
	 *
	 * @since 4.5.0
	 * 
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function bust_social_cache(int $post_id): void {
		if (!$post_id || wp_is_post_revision($post_id)) {
			return;
		}

		$cache_key = 'social_' . $post_id;
		wp_cache_delete($cache_key, self::SOCIAL_CACHE_GROUP);

		// Also bust cache for any images associated with this post
		$images = $this->get_post_social_images($post_id);
		foreach ($images as $image_id) {
			$this->bust_attachment_social_cache($image_id);
		}
	}

	/**
	 * Bust social image cache for an attachment.
	 *
	 * @since 4.5.0
	 * 
	 * @param int   $attachment_id The attachment ID.
	 * @param array $data         Optional. New attachment data.
	 * @param array $old_data     Optional. Old attachment data.
	 * @return void
	 */
	public function bust_attachment_social_cache(int $attachment_id, array $data = [], array $old_data = []): void {
		if (!$attachment_id) {
			return;
		}

		$cache_key = 'social_attachment_' . $attachment_id;
		wp_cache_delete($cache_key, self::SOCIAL_CACHE_GROUP);

		// Also bust cache for the parent post if this is an attachment
		$parent_id = wp_get_post_parent_id($attachment_id);
		if ($parent_id) {
			$this->bust_social_cache($parent_id);
		}
	}

	/**
	 * Bust social image cache when a Yoast indexable is updated.
	 *
	 * @since 4.5.0
	 * 
	 * @param \Yoast\WP\SEO\Models\Indexable $indexable The indexable that was saved.
	 * @return void
	 */
	public function bust_indexable_social_cache($indexable): void {
		if (!$indexable || !isset($indexable->object_id)) {
			return;
		}

		$this->bust_social_cache($indexable->object_id);
	}

	/**
	 * Get all images that might be used in social meta for a post.
	 *
	 * @since 4.5.0
	 * 
	 * @param int $post_id The post ID.
	 * @return array Array of image IDs.
	 */
	private function get_post_social_images(int $post_id): array {
		$images = [];

		// Get Yoast SEO specific social images
		$yoast_meta = YoastSEO()->meta->for_post($post_id);
		
		// Facebook image
		$fb_image_id = $yoast_meta->facebook_image_id;
		if ($fb_image_id) {
			$images[] = $fb_image_id;
		}

		// Twitter image
		$twitter_image_id = $yoast_meta->twitter_image_id;
		if ($twitter_image_id) {
			$images[] = $twitter_image_id;
		}

		// Featured image as fallback
		if (has_post_thumbnail($post_id)) {
			$images[] = get_post_thumbnail_id($post_id);
		}

		return array_unique(array_filter($images));
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

		// Check object cache
		$cache_key = 'social_' . md5($url);
		$cached_url = wp_cache_get($cache_key, \Edge_Images\Cache::CACHE_GROUP);
		if ($cached_url !== false) {
			self::$processed_urls[$url] = $cached_url;
			return $cached_url;
		}

		// Skip if URL is already transformed.
		if ( Helpers::is_transformed_url( $url ) ) {
			self::$processed_urls[$url] = $url;
			wp_cache_set($cache_key, $url, \Edge_Images\Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return $url;
		}

		// Get the image ID from the URL.
		$image_id = Helpers::get_attachment_id_from_url( $url );
		if ( ! $image_id ) {
			self::$processed_urls[$url] = $url;
			wp_cache_set($cache_key, $url, \Edge_Images\Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return $url;
		}

		// Get dimensions from the image.
		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			self::$processed_urls[$url] = $url;
			wp_cache_set($cache_key, $url, \Edge_Images\Cache::CACHE_GROUP, HOUR_IN_SECONDS);
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

		// Cache the transformed URL
		wp_cache_set($cache_key, $transformed_url, \Edge_Images\Cache::CACHE_GROUP, HOUR_IN_SECONDS);

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

	/**
	 * Check if this integration should filter.
	 *
	 * @since 4.5.0
	 * 
	 * @return bool Whether the integration should filter.
	 */
	protected function should_filter(): bool {

		// Bail if the Yoast SEO integration is disabled
		if ( ! Integration_Manager::is_enabled('yoast-seo') ) {
			return false;
		}

		return Settings::get_option('edge_images_yoast_social_images');
	}


}
