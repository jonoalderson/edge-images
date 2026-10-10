<?php
/**
 * Rank Math schema image integration.
 *
 * @package Edge_Images
 */

namespace Edge_Images\Integrations\Rank_Math;

use Edge_Images\{Integration, Helpers, Integrations, Settings};

/**
 * Rewrites Rank Math JSON-LD image URLs through the edge provider.
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

		\add_filter( 'rank_math/json_ld', [ $this, 'transform_schema' ], 1000, 2 );
	}

	/**
	 * Transform schema data.
	 *
	 * @param array<string, mixed> $data   Schema data.
	 * @param mixed                $jsonld JSON-LD manager.
	 * @return array<string, mixed>
	 */
	public function transform_schema( array $data, $jsonld ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		foreach ( $data as &$schema ) {
			if ( is_array( $schema ) ) {
				$schema = $this->transform_schema_node( $schema );
			}
		}

		return $data;
	}

	/**
	 * Recursively transform image fields in a schema node.
	 *
	 * @param array<string, mixed> $schema Schema node.
	 * @return array<string, mixed>
	 */
	private function transform_schema_node( array $schema ): array {

		foreach ( [ 'image', 'logo', 'thumbnailUrl', 'url' ] as $key ) {
			if ( ! isset( $schema[ $key ] ) ) {
				continue;
			}

			if ( is_string( $schema[ $key ] ) && $this->looks_like_image_url( $schema[ $key ] ) ) {
				$schema[ $key ] = $this->transform_seo_image( $schema[ $key ] );
			} elseif ( is_array( $schema[ $key ] ) ) {
				if ( isset( $schema[ $key ]['url'] ) && is_string( $schema[ $key ]['url'] ) ) {
					$schema[ $key ]['url']    = $this->transform_seo_image( $schema[ $key ]['url'] );
					$schema[ $key ]['width']  = 1200;
					$schema[ $key ]['height'] = 675;
				} else {
					foreach ( $schema[ $key ] as $index => $item ) {
						if ( is_string( $item ) && $this->looks_like_image_url( $item ) ) {
							$schema[ $key ][ $index ] = $this->transform_seo_image( $item );
						} elseif ( is_array( $item ) && isset( $item['url'] ) && is_string( $item['url'] ) ) {
							$schema[ $key ][ $index ]['url']    = $this->transform_seo_image( $item['url'] );
							$schema[ $key ][ $index ]['width']  = 1200;
							$schema[ $key ][ $index ]['height'] = 675;
						}
					}
				}
			}
		}

		return $schema;
	}

	/**
	 * Whether a string looks like an image URL.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private function looks_like_image_url( string $value ): bool {

		return (bool) preg_match( '/\.(jpe?g|png|gif|webp)(\?|$)/i', $value );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {

		return [
			'edge_images_integration_rank_math_schema' => true,
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

		return (bool) Settings::get_option( 'edge_images_integration_rank_math_schema', true );
	}
}
