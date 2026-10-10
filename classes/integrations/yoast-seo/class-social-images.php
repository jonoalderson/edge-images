<?php
/**
 * Yoast SEO social image integration.
 *
 * @package Edge_Images
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\{Integration, Helpers, Integrations, Settings};

/**
 * Rewrites Yoast OpenGraph and Twitter images through the edge provider.
 */
class Social_Images extends Integration {

	/**
	 * Add filters.
	 *
	 * @return void
	 */
	protected function add_filters(): void {

		if ( ! $this->should_filter() ) {
			return;
		}

		\add_filter( 'wpseo_opengraph_image', [ $this, 'transform_social_image' ] );
		\add_filter( 'wpseo_twitter_image', [ $this, 'transform_social_image' ] );
		\add_filter( 'wpseo_opengraph_image_width', [ $this, 'transform_social_image_width' ] );
		\add_filter( 'wpseo_opengraph_image_height', [ $this, 'transform_social_image_height' ] );
	}

	/**
	 * Social image width.
	 *
	 * @param mixed $width Width.
	 * @return int
	 */
	public function transform_social_image_width( $width ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		return 1200;
	}

	/**
	 * Social image height.
	 *
	 * @param mixed $height Height.
	 * @return int
	 */
	public function transform_social_image_height( $height ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		return 675;
	}

	/**
	 * Transform a social image URL.
	 *
	 * @param string $image_url URL.
	 * @return string
	 */
	public function transform_social_image( string $image_url ): string {

		return $this->transform_seo_image( $image_url, [ 'sharpen' => 1 ] );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {

		return [
			'edge_images_integration_yoast_social' => true,
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

		return (bool) Settings::get_option( 'edge_images_integration_yoast_social', true );
	}
}
