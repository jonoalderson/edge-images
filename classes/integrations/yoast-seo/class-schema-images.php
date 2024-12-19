<?php
/**
 * Yoast SEO schema integration.
 *
 * Handles the transformation of images in Yoast SEO's schema output.
 * Ensures that schema images are optimized and properly sized.
 *
 * @package    Edge_Images\Integrations
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.0.0
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\{Helpers, Integration, Cache, Settings, Integration_Manager};

/**
 * Configures Yoast SEO schema output to use the image rewriter.
 *
 * @since 4.0.0
 */
class Schema_Images extends Integration {

	/**
	 * Cache group for schema image processing.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	private const SCHEMA_CACHE_GROUP = 'edge_images_schema';

	/**
	 * The image width value for schema images.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const SCHEMA_WIDTH = 1200;

	/**
	 * The image height value for schema images.
	 *
	 * @since 4.0.0
	 * @var int
	 */
	public const SCHEMA_HEIGHT = 675;

	/**
	 * Add integration-specific filters.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		// Schema filters
		add_filter('wpseo_schema_imageobject', [$this, 'edge_image']);
		add_filter('wpseo_schema_organization', [$this, 'edge_organization_logo']);
		add_filter('wpseo_schema_webpage', [$this, 'edge_thumbnail']);
		add_filter('wpseo_schema_article', [$this, 'edge_thumbnail']);

		// Cache busting hooks
		add_action('save_post', [$this, 'bust_schema_cache']);
		add_action('deleted_post', [$this, 'bust_schema_cache']);
		add_action('attachment_updated', [$this, 'bust_attachment_schema_cache'], 10, 3);
		add_action('delete_attachment', [$this, 'bust_attachment_schema_cache']);
		add_action('wpseo_save_indexable', [$this, 'bust_indexable_schema_cache']);
	}

	/**
	 * Bust schema cache for a post.
	 *
	 * @since 4.5.0
	 * 
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function bust_schema_cache(int $post_id): void {
		if (!$post_id || wp_is_post_revision($post_id)) {
			return;
		}

		$cache_key = 'schema_' . $post_id;
		wp_cache_delete($cache_key, self::SCHEMA_CACHE_GROUP);

		// Also bust cache for any images associated with this post
		$images = $this->get_post_schema_images($post_id);
		foreach ($images as $image_id) {
			$this->bust_attachment_schema_cache($image_id);
		}
	}

	/**
	 * Bust schema cache for an attachment.
	 *
	 * @since 4.5.0
	 * 
	 * @param int   $attachment_id The attachment ID.
	 * @param array $data         Optional. New attachment data.
	 * @param array $old_data     Optional. Old attachment data.
	 * @return void
	 */
	public function bust_attachment_schema_cache(int $attachment_id, array $data = [], array $old_data = []): void {
		if (!$attachment_id) {
			return;
		}

		$cache_key = 'schema_attachment_' . $attachment_id;
		wp_cache_delete($cache_key, self::SCHEMA_CACHE_GROUP);

		// Also bust cache for the parent post if this is an attachment
		$parent_id = wp_get_post_parent_id($attachment_id);
		if ($parent_id) {
			$this->bust_schema_cache($parent_id);
		}
	}

	/**
	 * Bust schema cache when a Yoast indexable is updated.
	 *
	 * @since 4.5.0
	 * 
	 * @param \Yoast\WP\SEO\Models\Indexable $indexable The indexable that was saved.
	 * @return void
	 */
	public function bust_indexable_schema_cache($indexable): void {
		if (!$indexable || !isset($indexable->object_id)) {
			return;
		}

		$this->bust_schema_cache($indexable->object_id);
	}

	/**
	 * Get all images that might be used in schema for a post.
	 *
	 * @since 4.5.0
	 * 
	 * @param int $post_id The post ID.
	 * @return array Array of image IDs.
	 */
	private function get_post_schema_images(int $post_id): array {
		$images = [];

		// Featured image
		if (has_post_thumbnail($post_id)) {
			$images[] = get_post_thumbnail_id($post_id);
		}

		// Organization logo
		$company_logo_id = YoastSEO()->meta->for_current_page()->company_logo_id;
		if ($company_logo_id) {
			$images[] = $company_logo_id;
		}

		return array_unique(array_filter($images));
	}

	/**
	 * Process an image for schema output.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $image_url The original image URL.
	 * @param array  $custom_args Optional. Custom transformation arguments.
	 * @return array|false Array of edge URL and dimensions, or false on failure.
	 */
	private function process_schema_image( string $image_url, array $custom_args = [] ) {
		// Check cache first
		$cache_key = 'schema_' . md5($image_url . serialize($custom_args));
		$cached_result = wp_cache_get($cache_key, Cache::CACHE_GROUP);
		if ($cached_result !== false) {
			return $cached_result;
		}

		$image_id = Helpers::get_attachment_id( $image_url );
		if ( ! $image_id ) {
			wp_cache_set($cache_key, false, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return false;
		}

		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			wp_cache_set($cache_key, false, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return false;
		}

		// Set default args
		$args = [
			'width'   => self::SCHEMA_WIDTH,
			'height'  => self::SCHEMA_HEIGHT,
			'fit'     => 'cover',
			'sharpen' => (int) $dimensions['width'] < self::SCHEMA_WIDTH ? 3 : 2,
		];

		// Merge with custom args
		$args = array_merge( $args, $custom_args );

		// Tweak the behaviour for small images
		if ( (int) $dimensions['width'] < self::SCHEMA_WIDTH || (int) $dimensions['height'] < self::SCHEMA_HEIGHT ) {
			$args['fit']     = 'pad';
			$args['sharpen'] = 2;
		}

		$edge_url = Helpers::edge_src( $image_url, $args );
		if ( ! $edge_url ) {
			wp_cache_set($cache_key, false, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return false;
		}

		$result = [
			'url'     => $edge_url,
			'width'   => $args['width'],
			'height'  => $args['height'],
		];

		// Cache the result
		wp_cache_set($cache_key, $result, Cache::CACHE_GROUP, HOUR_IN_SECONDS);

		return $result;
	}

	/**
	 * Edit the thumbnailUrl property of the WebPage to use the edge.
	 *
	 * @since 4.1.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_thumbnail( array $data ): array {
		if ( ! isset( $data['thumbnailUrl'] ) ) {
			return $data;
		}

		$processed = $this->process_schema_image( $data['thumbnailUrl'] );
		if ( $processed ) {
			$data['thumbnailUrl'] = $processed['url'];
		}

		return $data;
	}

	/**
	 * Transform the primary image to use the edge.
	 *
	 * @since 4.1.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_image( array $data ): array {
		if (!isset($data['url'])) {
			return $data;
		}

		$processed = $this->process_schema_image($data['url']);

		if ($processed) {
			$data['url'] = $processed['url'];
			$data['contentUrl'] = $processed['url'];
			$data['width'] = $processed['width'];
			$data['height'] = $processed['height'];
		}

		return $data;
	}

	/**
	 * Alter the Organization's logo property to use the edge.
	 *
	 * @since 4.0.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_organization_logo( array $data ): array {
		// Get the image ID from Yoast SEO.
		$image_id = YoastSEO()->meta->for_current_page()->company_logo_id;
		if ( ! $image_id ) {
			return $data;
		}

		$image = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! $image ) {
			return $data;
		}

		// Get dimensions from the image.
		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			return $data;
		}

		$processed = $this->process_schema_image(
			$image[0],
			[
				'fit'    => 'contain',
				'width'  => min( (int) $dimensions['width'], self::SCHEMA_WIDTH ),
				'height' => min( (int) $dimensions['height'], self::SCHEMA_HEIGHT ),
			]
		);

		if ( $processed ) {
			$data['logo'] = [
				'url'         => $processed['url'],
				'contentUrl'  => $processed['url'],
				'width'       => $processed['width'],
				'height'      => $processed['height'],
				'@type'       => 'ImageObject',
			];
		}

		return $data;
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
			'edge_images_yoast_schema_images' => true,
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

		return Settings::get_option('edge_images_yoast_schema_images');
	}

}


