<?php
/**
 * Rank Math XML sitemap image integration.
 *
 * @package Edge_Images
 */

namespace Edge_Images\Integrations\Rank_Math;

use Edge_Images\{Integration, Helpers, Integrations, Settings};

/**
 * Rewrites Rank Math sitemap image URLs through the edge provider.
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

		\add_filter( 'rank_math/sitemap/entry', [ $this, 'transform_sitemap_entry' ], 10, 3 );
		\add_filter( 'rank_math/sitemap/enable_caching', '__return_false' );
	}

	/**
	 * Transform images in a sitemap entry.
	 *
	 * @param array<string, mixed> $url    URL parts.
	 * @param string               $type   Entry type.
	 * @param mixed                $object Entry object.
	 * @return array<string, mixed>
	 */
	public function transform_sitemap_entry( array $url, string $type, $object ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		if ( empty( $url['images'] ) || ! is_array( $url['images'] ) ) {
			return $url;
		}

		foreach ( $url['images'] as &$image ) {
			if ( isset( $image['src'] ) && is_string( $image['src'] ) ) {
				$image['src'] = $this->transform_seo_image( $image['src'] );
			}
			if ( isset( $image['image:loc'] ) && is_string( $image['image:loc'] ) ) {
				$image['image:loc'] = $this->transform_seo_image( $image['image:loc'] );
			}
		}

		return $url;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {

		return [
			'edge_images_integration_rank_math_xml' => true,
		];
	}

	/**
	 * Whether to filter.
	 *
	 * @return bool
	 */
	protected function should_filter(): bool {

		if ( ! Integrations::is_enabled( 'rank-math' ) || ! Helpers::should_transform_images() ) {
			return false;
		}

		return (bool) Settings::get_option( 'edge_images_integration_rank_math_xml', true );
	}
}
