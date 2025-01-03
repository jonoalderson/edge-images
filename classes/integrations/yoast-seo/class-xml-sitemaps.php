<?php
/**
 * Yoast SEO XML Sitemaps integration functionality.
 *
 * Handles integration with Yoast SEO's XML sitemap functionality.
 * This integration:
 * - Transforms sitemap image URLs
 * - Manages image optimization
 * - Handles sitemap entries
 * - Supports image sitemaps
 * - Maintains SEO integrity
 * - Ensures proper scaling
 * - Integrates with WordPress hooks
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\{Integration, Helpers, Features};

class XML_Sitemaps extends Integration {

	/**
	 * The default width for sitemap images.
	 *
	 * Standard width for sitemap images.
	 * This value ensures optimal indexing and display.
	 *
	 * @since      4.5.0
	 * @var        int
	 */
	private const SITEMAP_WIDTH = 1200;

	/**
	 * The default height for sitemap images.
	 *
	 * Standard height for sitemap images.
	 * This value ensures optimal indexing and display.
	 *
	 * @since      4.5.0
	 * @var        int
	 */
	private const SITEMAP_HEIGHT = 675;

	/**
	 * Add integration-specific filters.
	 *
	 * Sets up required filters for Yoast SEO sitemap integration.
	 * This method:
	 * - Hooks into sitemap filters
	 * - Manages image transformation
	 * - Handles sitemap entries
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

		add_filter('wpseo_xml_sitemap_img_src', [$this, 'transform_sitemap_image']);
	}

	/**
	 * Transform sitemap image.
	 *
	 * Processes and transforms sitemap image URLs.
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
	public function transform_sitemap_image(string $image_url): string {
		// Skip if empty or not local
		if (empty($image_url) || !Helpers::is_local_url($image_url)) {
			return $image_url;
		}

		// Transform the URL
		return Helpers::edge_src($image_url, [
			'width' => self::SITEMAP_WIDTH,
			'height' => self::SITEMAP_HEIGHT,
			'fit' => 'cover',
			'quality' => 85,
		]);
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
	 * @since      4.5.0
	 * 
	 * @return array<string,mixed> Array of default feature settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_integration_yoast_xml' => true,
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
	 * @since      4.5.0
	 * 
	 * @return bool True if integration should be active, false otherwise.
	 */
	protected function should_filter(): bool {
		return Features::is_enabled('yoast_xml_sitemap_images') && Helpers::should_transform_images();
	}
}
