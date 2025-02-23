<?php
/**
 * Rank Math XML Sitemaps integration functionality.
 *
 * Handles integration with Rank Math's XML sitemap functionality.
 * This integration:
 * - Transforms sitemap image URLs
 * - Maintains image optimization
 * - Preserves sitemap structure
 * - Ensures proper image dimensions
 * - Integrates with WordPress hooks
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      5.2.14
 */

namespace Edge_Images\Integrations\Rank_Math;

use Edge_Images\{Integration, Helpers, Integrations, Settings};
use Edge_Images\Features\Cache;

/**
 * Class XML_Sitemaps
 */
class XML_Sitemaps extends Integration {

	/**
	 * The default width for sitemap images.
	 *
	 * Standard width for sitemap images.
	 * This value ensures optimal indexing and display.
	 *
	 * @since      5.2.14
	 * @var        int
	 */
	private const SITEMAP_WIDTH = 1200;

	/**
	 * The default height for sitemap images.
	 *
	 * Standard height for sitemap images.
	 * This value ensures optimal indexing and display.
	 *
	 * @since      5.2.14
	 * @var        int
	 */
	private const SITEMAP_HEIGHT = 675;
	/**
	 * Add integration-specific filters.
	 *
	 * Sets up required filters for Rank Math sitemap integration.
	 * This method:
	 * - Hooks into sitemap filters
	 * - Manages image transformation
	 * - Ensures proper integration
	 *
	 * @since      5.2.14
	 * 
	 * @return void
	 */
	protected function add_filters(): void {

		// Bail if we shouldn't be filtering
		if (!$this->should_filter()) {
			return;
		}

		// Transform sitemap image URLs
		add_filter('rank_math/sitemap/entry', [$this, 'transform_sitemap_entry'], 10, 3);
	
    
        // Disable Rank Math's native XML sitemap caching, otherwise we can't filter early enough
        add_filter( 'rank_math/sitemap/enable_caching', [$this, 'use_default_caching']);

    }

    /**
     * Use default caching
     *
     * @return boolean
     */
    public function use_default_caching(): bool {
        return false;
    }

	/**
	 * Transform images in a sitemap entry.
	 *
	 * @since      5.2.14
	 * 
	 * @param  array  $url    Array of URL parts.
	 * @param  string $type   Entry type.
	 * @param  object $object Entry object.
	 * @return array          Modified URL parts.
	 */
	public function transform_sitemap_entry(array $url, string $type, $object): array {
		// Only process entries with images
		if (!isset($url['images']) || empty($url['images'])) {
			return $url;
		}

		// Transform each image URL
		foreach ($url['images'] as &$image) {
			if (isset($image['src'])) {
				$image['src'] = $this->transform_sitemap_image($image['src'], $object->ID ?? 0);
			}
			if (isset($image['image:loc'])) {
				$image['image:loc'] = $this->transform_sitemap_image($image['image:loc'], $object->ID ?? 0);
			}
		}

		return $url;
	}

	/**
	 * Transform sitemap image URL.
	 *
	 * Processes and transforms image URLs in the XML sitemap.
	 * This method:
	 * - Transforms image URLs
	 * - Handles image dimensions
	 * - Ensures optimization
	 * - Maintains quality
	 * - Supports multiple formats
	 *
	 * @since      5.2.14
	 * 
	 * @param  string $image_url The original image URL.
	 * @param  int    $post_id   The post ID.
	 * @return string           The transformed image URL.
	 */
	private function transform_sitemap_image(string $image_url, int $post_id): string {
		// Skip if empty or not local
		if (empty($image_url) || !Helpers::is_local_url($image_url)) {
			return $image_url;
		}

		// Check cache first
		$cache_key = 'sitemap_' . md5($image_url);
		$cached_result = wp_cache_get($cache_key, Cache::CACHE_GROUP);
		if ($cached_result !== false) {
			return $cached_result;
		}

		// Get image ID from URL
		$image_id = Helpers::get_attachment_id_from_url($image_url);
		if (!$image_id) {
			wp_cache_set($cache_key, $image_url, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return $image_url;
		}

		// Get dimensions
		$dimensions = Helpers::get_image_dimensions($image_id);
		if (!$dimensions) {
			wp_cache_set($cache_key, $image_url, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return $image_url;
		}

		// Set default args
		$args = [
			'width' => self::SITEMAP_WIDTH,
			'height' => self::SITEMAP_HEIGHT,
			'fit' => 'cover',
			'quality' => 85,
		];

		// Transform the URL
		$transformed_url = Helpers::edge_src($image_url, $args);

		// Cache the result
		wp_cache_set($cache_key, $transformed_url, Cache::CACHE_GROUP, HOUR_IN_SECONDS);

		return $transformed_url;
	}

	/**
	 * Get default settings for this integration.
	 *
	 * Provides default configuration settings for the sitemap integration.
	 * This method:
	 * - Sets feature defaults
	 * - Configures options
	 * - Ensures consistency
	 * - Supports customization
	 *
	 * @since      5.2.14
	 * 
	 * @return array<string,mixed> Array of default feature settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_integration_rank_math_sitemap' => true,
		];
	}

	/**
	 * Check if this integration should filter.
	 *
	 * Determines if sitemap integration should be active.
	 * This method:
	 * - Checks feature status
	 * - Validates settings
	 * - Ensures requirements
	 * - Controls processing
	 *
	 * @since      5.2.14
	 * 
	 * @return bool True if integration should be active, false otherwise.
	 */
	protected function should_filter(): bool {
		// Check if Rank Math is installed and active
		if (!Integrations::is_enabled('rank-math')) {
			return false;
		}

		// Check if image transformation is enabled
		if (!Helpers::should_transform_images()) {
			return false;
		}

		// Check if this specific integration is enabled in settings
		return Settings::get_option('edge_images_integration_rank_math_xml', true);
	}
} 