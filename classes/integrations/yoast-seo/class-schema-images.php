<?php
/**
 * Yoast SEO schema image integration.
 *
 * @package Edge_Images
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\{Integration, Helpers, Integrations, Settings};

/**
 * Rewrites Yoast schema image URLs through the edge provider.
 */
class Schema_Images extends Integration {

	/**
	 * Add filters.
	 *
	 * @return void
	 */
	protected function add_filters(): void {

		if ( ! $this->should_filter() ) {
			return;
		}

		\add_filter( 'wpseo_schema_imageobject', [ $this, 'transform_schema_image' ], 10, 1 );
		\add_filter( 'wpseo_schema_organization', [ $this, 'edge_organization_logo' ], PHP_INT_MAX - 10 );
		\add_filter( 'wpseo_schema_webpage', [ $this, 'edge_images' ], PHP_INT_MAX - 10 );
		\add_filter( 'wpseo_schema_article', [ $this, 'edge_images' ], PHP_INT_MAX - 10 );
		\add_filter( 'wpseo_schema_webpage', [ $this, 'edge_thumbnail' ], PHP_INT_MAX - 10 );
		\add_filter( 'wpseo_schema_article', [ $this, 'edge_thumbnail' ], PHP_INT_MAX - 10 );
	}

	/**
	 * Transform a schema ImageObject piece.
	 *
	 * @param array<string, mixed> $data Schema data.
	 * @return array<string, mixed>
	 */
	public function transform_schema_image( array $data ): array {

		if ( empty( $data['url'] ) || ! is_string( $data['url'] ) ) {
			return $data;
		}

		$data['url']    = $this->transform_seo_image( $data['url'] );
		$data['width']  = 1200;
		$data['height'] = 675;
		$data['contentUrl'] = $data['url'];

		return $data;
	}

	/**
	 * Transform organization logo.
	 *
	 * @param array<string, mixed> $data Schema data.
	 * @return array<string, mixed>
	 */
	public function edge_organization_logo( array $data ): array {

		if ( isset( $data['logo']['url'] ) && is_string( $data['logo']['url'] ) ) {
			$data['logo']['url']    = $this->transform_seo_image( $data['logo']['url'] );
			$data['logo']['width']  = 1200;
			$data['logo']['height'] = 675;
		}

		return $data;
	}

	/**
	 * Transform thumbnailUrl.
	 *
	 * @param array<string, mixed> $data Schema data.
	 * @return array<string, mixed>
	 */
	public function edge_thumbnail( array $data ): array {

		if ( isset( $data['thumbnailUrl'] ) && is_string( $data['thumbnailUrl'] ) ) {
			$data['thumbnailUrl'] = $this->transform_seo_image( $data['thumbnailUrl'] );
		}

		return $data;
	}

	/**
	 * Transform image properties on schema pieces.
	 *
	 * @param array<string, mixed> $data Schema data.
	 * @return array<string, mixed>
	 */
	public function edge_images( array $data ): array {

		if ( ! isset( $data['image'] ) ) {
			return $data;
		}

		if ( is_string( $data['image'] ) ) {
			$data['image'] = $this->transform_seo_image( $data['image'] );
			return $data;
		}

		if ( ! is_array( $data['image'] ) ) {
			return $data;
		}

		foreach ( $data['image'] as $key => $image ) {
			if ( is_string( $image ) ) {
				$data['image'][ $key ] = $this->transform_seo_image( $image );
			} elseif ( is_array( $image ) && isset( $image['url'] ) && is_string( $image['url'] ) ) {
				$data['image'][ $key ]['url']    = $this->transform_seo_image( $image['url'] );
				$data['image'][ $key ]['width']  = 1200;
				$data['image'][ $key ]['height'] = 675;
			}
		}

		return $data;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {

		return [
			'edge_images_integration_yoast_schema' => true,
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

		return (bool) Settings::get_option( 'edge_images_integration_yoast_schema', true );
	}
}
