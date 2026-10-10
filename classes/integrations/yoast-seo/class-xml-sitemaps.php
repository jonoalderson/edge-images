<?php
/**
 * Yoast SEO XML sitemap image integration.
 *
 * @package Edge_Images
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\{Integration, Helpers, Integrations, Settings};

/**
 * Rewrites Yoast sitemap image URLs through the edge provider.
 */
class XML_Sitemaps extends Integration {

	/**
	 * Add filters.
	 *
	 * @return void
	 */
	protected function add_filters(): void {

		if ( ! $this->should_filter() ) {
			return;
		}

		\add_filter( 'wpseo_xml_sitemap_img_src', [ $this, 'transform_sitemap_image' ] );
	}

	/**
	 * Transform a sitemap image URL.
	 *
	 * @param string $image_url URL.
	 * @return string
	 */
	public function transform_sitemap_image( string $image_url ): string {

		return $this->transform_seo_image( $image_url );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {

		return [
			'edge_images_integration_yoast_xml' => true,
		];
	}

	/**
	 * Whether to filter.
	 *
	 * @return bool
	 */
	protected function should_filter(): bool {

		if ( ! Integrations::is_enabled( 'yoast-seo' ) || ! Helpers::should_transform_images() ) {
			return false;
		}

		return (bool) Settings::get_option( 'edge_images_integration_yoast_xml', true );
	}
}
