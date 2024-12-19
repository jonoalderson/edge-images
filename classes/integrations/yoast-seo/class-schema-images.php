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

use Edge_Images\{Helpers, Integration, Cache};

/**
 * Configures Yoast SEO schema output to use the image rewriter.
 *
 * @since 4.0.0
 */
class Schema_Images extends Integration {

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
		add_filter('wpseo_schema_imageobject', [$this, 'edge_image']);
		add_filter('wpseo_schema_organization', [$this, 'edge_organization_logo']);
		add_filter('wpseo_schema_webpage', [$this, 'edge_thumbnail']);
		add_filter('wpseo_schema_article', [$this, 'edge_thumbnail']);
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

}


