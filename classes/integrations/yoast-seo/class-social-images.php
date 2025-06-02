<?php
/**
 * Yoast SEO Social Images integration functionality.
 *
 * Handles integration with Yoast SEO's social image functionality.
 * This integration:
 * - Transforms social media images
 * - Manages image optimization
 * - Handles OpenGraph images
 * - Supports Twitter cards
 * - Maintains social sharing
 * - Ensures proper scaling
 * - Integrates with WordPress hooks
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\{Integration, Helpers, Integrations, Settings, Features\Cache};

class Social_Images extends Integration {

	/**
	 * Cache group for social image processing.
	 *
	 * @since 5.2.14
	 * @var string
	 */
	private const SOCIAL_CACHE_GROUP = 'edge_images_social';

	/**
	 * The default width for social images.
	 *
	 * Standard width for social media images.
	 * This value ensures optimal display across platforms.
	 *
	 * @since      4.5.0
	 * @var        int
	 */
	private const SOCIAL_WIDTH = 1200;

	/**
	 * The default height for social images.
	 *
	 * Standard height for social media images.
	 * This value ensures optimal display across platforms.
	 *
	 * @since      4.5.0
	 * @var        int
	 */
	private const SOCIAL_HEIGHT = 675;

	/**
	 * Add integration-specific filters.
	 *
	 * Sets up required filters for Yoast SEO social integration.
	 * This method:
	 * - Hooks into social filters
	 * - Manages image transformation
	 * - Handles multiple platforms
	 * - Ensures proper integration
	 *
	 * @since      4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {

		// Bail if we shouldn't be filtering
		if (!$this->should_filter()) {
			return;
		}
		
		add_filter('wpseo_opengraph_image', [$this, 'transform_social_image']);
		add_filter('wpseo_twitter_image', [$this, 'transform_social_image']);
		add_filter('wpseo_opengraph_image_width', [$this, 'transform_social_image_width']);
		add_filter('wpseo_opengraph_image_height', [$this, 'transform_social_image_height']);
	}

	/**
	 * Set the width for the social image.
	 *
	 * @since      4.5.0
	 * 
	 * @param integer|string $width
	 * @return integer
	 */
	public function transform_social_image_width($width): int {
		return (int)self::SOCIAL_WIDTH;
	}

	/**
	 * Set the height for the social image.
	 *
	 * @since      4.5.0
	 * 
	 * @param integer|string $height
	 * @return integer
	 */
	public function transform_social_image_height($height): int {
		return (int)self::SOCIAL_HEIGHT;
	}

	/**
	 * Transform social media image.
	 *
	 * Processes and transforms social media image URLs.
	 * This method:
	 * - Transforms image URLs
	 * - Handles image dimensions
	 * - Ensures optimization
	 * - Maintains quality
	 * - Supports multiple formats
	 * - Preserves aspect ratios
	 *
	 * @since      4.5.0
	 * 
	 * @param  string $image_url The original image URL.
	 * @return string           The transformed image URL.
	 */
	public function transform_social_image(string $image_url): string {
		
		// Skip if empty or not local
		if (empty($image_url) || !Helpers::is_local_url($image_url)) {
			return $image_url;
		}

		// Check cache first
		$cache_key = 'social_' . md5($image_url);
		$cached_result = wp_cache_get($cache_key, Cache::CACHE_GROUP);
		if ($cached_result !== false) {
			return $cached_result;
		}

		// Try to get the image ID from Yoast's meta
		$image_id = null;
		$meta = \YoastSEO()->meta->for_current_page();
		
		// Check if this is the OpenGraph image
		if ($meta && $meta->open_graph_image_id) {
			$image_id = $meta->open_graph_image_id;
		}
		// Check if this is the Twitter image
		elseif ($meta && $meta->twitter_image_id) {
			$image_id = $meta->twitter_image_id;
		}
		// Fallback to getting ID from URL if needed
		else {
			$image_id = Helpers::get_attachment_id_from_url($image_url);
		}

		// If we couldn't get an ID, return original URL
		if (!$image_id) {
			wp_cache_set($cache_key, $image_url, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return $image_url;
		}

		// Get the image dimensions.
		$dimensions = Helpers::get_image_dimensions($image_id);

		// If we didn't get the dimensions, don't try to transform.
		if (!$dimensions) {
			wp_cache_set($cache_key, $image_url, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return $image_url;
		}

		// Set default args
		$args = [
			'width' => self::SOCIAL_WIDTH,
			'height' => self::SOCIAL_HEIGHT,
			'fit' => 'cover',
			'sharpen' => 1,
			'quality' => 85,
		];

		// Adjust behavior for small images that need upscaling
		if ((int) $dimensions['width'] < self::SOCIAL_WIDTH || (int) $dimensions['height'] < self::SOCIAL_HEIGHT) {
			$args['fit'] = 'pad';
			$args['sharpen'] = 2;
		}

		// Transform the URL
		$transformed_url = Helpers::edge_src($image_url, $args);

		// Cache the result
		wp_cache_set($cache_key, $transformed_url, Cache::CACHE_GROUP, HOUR_IN_SECONDS);

		return $transformed_url;
	}

	/**
	 * Get default settings for this integration.
	 *
	 * Provides default configuration settings for the social integration.
	 * This method:
	 * - Sets feature defaults
	 * - Configures options
	 * - Ensures consistency
	 * - Supports customization
	 *
	 * @since      4.5.0
	 * 
	 * @return array<string,mixed> Array of default feature settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_integration_yoast_social' => true,
		];
	}

	/**
	 * Check if this integration should filter.
	 *
	 * Determines if social integration should be active.
	 * This method:
	 * - Checks feature status
	 * - Validates settings
	 * - Ensures requirements
	 * - Controls processing
	 *
	 * @since      4.5.0
	 * 
	 * @return bool True if integration should be active, false otherwise.
	 */
	protected function should_filter(): bool {

		// Check if Yoast SEO is installed and active
		if (!Integrations::is_enabled('yoast-seo')) {
			return false;
		}

		// Check if image transformation is enabled
		if (!Helpers::should_transform_images()) {
			return false;
		}

		// Check if this specific integration is enabled in settings
		return Settings::get_option('edge_images_integration_yoast_social', true);
	}
}
