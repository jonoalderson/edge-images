<?php
/**
 * Base class for third-party integrations.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Shared registration helpers for SEO integrations.
 */
abstract class Integration {

	/**
	 * Register the integration.
	 *
	 * @return void
	 */
	public static function register(): void {

		$instance = new static();
		$instance->add_filters();
	}

	/**
	 * Add integration-specific filters.
	 *
	 * @return void
	 */
	abstract protected function add_filters(): void;

	/**
	 * Whether this integration should run.
	 *
	 * @return bool
	 */
	abstract protected function should_filter(): bool;

	/**
	 * Transform a local image URL to a 1200×675 edge URL.
	 *
	 * @param string               $image_url Source URL.
	 * @param array<string, mixed> $args      Extra transform args.
	 * @return string
	 */
	protected function transform_seo_image( string $image_url, array $args = [] ): string {

		if ( '' === $image_url || ! Helpers::is_local_url( $image_url ) ) {
			return $image_url;
		}

		if ( Helpers::is_non_transformable_format( $image_url ) ) {
			return $image_url;
		}

		$src = Helpers::strip_dimensions_from_url( $image_url );

		return Transformer::url(
			$src,
			1200,
			675,
			array_merge(
				[
					'fit'     => 'cover',
					'quality' => 85,
				],
				$args
			)
		);
	}

	/**
	 * Default settings for the integration.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {

		return [];
	}
}
