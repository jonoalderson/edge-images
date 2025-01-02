<?php
/**
 * Avatar transformation functionality.
 *
 * Handles the transformation of avatar images across the site.
 * This feature:
 * - Transforms avatar URLs to use edge providers
 * - Manages avatar image dimensions
 * - Provides responsive image support
 * - Handles srcset generation
 * - Supports picture element wrapping
 * - Ensures proper image scaling
 * - Maintains avatar quality
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Features;

use Edge_Images\{Helpers, Integration, Features};

class Avatars extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * Sets up filters for avatar transformation.
	 * This method:
	 * - Adds URL transformation filter
	 * - Adds HTML transformation filter
	 * - Configures processing order
	 * - Manages integration points
	 *
	 * @since      4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {

		// Only add filters if we should be filtering
		if (!$this->should_filter()) {
			return;
		}

		add_filter('get_avatar_url', [$this, 'transform_avatar_url'], 10, 3);
		add_filter('get_avatar', [$this, 'transform_avatar_html'], 10, 6);
	}

	/**
	 * Transform avatar URLs.
	 *
	 * Processes and transforms avatar image URLs.
	 * This method:
	 * - Validates input URLs
	 * - Checks for local URLs
	 * - Applies size constraints
	 * - Transforms using edge provider
	 * - Maintains image quality
	 *
	 * @since      4.2.0
	 * 
	 * @param  string $url         The URL of the avatar to transform.
	 * @param  mixed  $id_or_email The Gravatar identifier (user ID, email, or object).
	 * @param  array  $args        Arguments passed to get_avatar_data().
	 * @return string             The transformed avatar URL.
	 */
	public function transform_avatar_url( string $url, $id_or_email, array $args ): string {

		// Skip if URL is empty or remote.
		if ( empty( $url ) || ! Helpers::is_local_url( $url ) ) {
			return $url;
		}

		// Get size from args.
		$size = $args['size'] ?? 96;

		// Transform URL using edge provider.
		return Helpers::edge_src( $url, [
			'width'   => $size,
			'height'  => $size,
			'fit'     => 'cover',
			'sharpen' => 1,
		]);
	}

	/**
	 * Transform avatar HTML.
	 *
	 * Processes and transforms complete avatar HTML markup.
	 * This method:
	 * - Processes HTML tags
	 * - Transforms image URLs
	 * - Generates srcset values
	 * - Sets image dimensions
	 * - Adds CSS classes
	 * - Supports picture wrapping
	 * - Maintains accessibility
	 *
	 * @since      4.2.0
	 * 
	 * @param  string $avatar      HTML for the user's avatar.
	 * @param  mixed  $id_or_email The Gravatar identifier (user ID, email, or object).
	 * @param  int    $size        Square avatar width and height in pixels.
	 * @param  string $default     URL for the default image or 'mystery' (see get_avatar_url).
	 * @param  string $alt         Alternative text for the avatar image.
	 * @param  array  $args        Arguments passed to get_avatar_data().
	 * @return string             The transformed avatar HTML.
	 */
	public function transform_avatar_html( string $avatar, $id_or_email, int $size, string $default, string $alt, array $args ): string {
		// Skip if in admin
		if (is_admin()) {
			return $avatar;
		}

		// Skip if avatar is empty.
		if ( empty( $avatar ) ) {
			return $avatar;
		}

		// Create HTML processor.
		$processor = new \WP_HTML_Tag_Processor( $avatar );
		if ( ! $processor->next_tag( 'img' ) ) {
			return $avatar;
		}

		// Skip if remote URL.
		$src = $processor->get_attribute( 'src' );
		if ( ! $src || ! Helpers::is_local_url( $src ) ) {
			return $avatar;
		}

		// Transform the URL
		$transformed_url = $this->transform_avatar_url( $src, $id_or_email, $args );
		$processor->set_attribute( 'src', $transformed_url );

		// Generate srcset using our transformer
		$dimensions = ['width' => $size, 'height' => $size];
		$sizes = "(max-width: {$size}px) 100vw, {$size}px";
		
		$srcset = \Edge_Images\Srcset_Transformer::transform(
			$src,
			$dimensions,
			$sizes,
			[
				'fit' => 'cover',
				'sharpen' => 1,
			]
		);
		
		if ( $srcset ) {
			$processor->set_attribute( 'srcset', $srcset );
			$processor->set_attribute( 'sizes', $sizes );
		}

		// Add our classes
		$classes = $processor->get_attribute( 'class' ) ?? '';
		$processor->set_attribute( 'class', trim( $classes . ' edge-images-img edge-images-processed' ) );

		// Set dimensions
		$processor->set_attribute( 'width', (string) $size );
		$processor->set_attribute( 'height', (string) $size );

		// Check if picture wrapping is enabled
		if ( Features::is_enabled( 'picture_wrap' ) ) {
			return Picture::create(
				$processor->get_updated_html(),
				['width' => $size, 'height' => $size],
				'avatar-picture'
			);
		}

		return $processor->get_updated_html();
	}

	/**
	 * Get default settings for this integration.
	 *
	 * Provides default configuration settings for the avatars feature.
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
			'edge_images_feature_avatars' => true,
		];
	}

	/**
	 * Check if this integration should filter.
	 *
	 * Determines if avatar transformation should be active.
	 * This method:
	 * - Checks feature status
	 * - Validates settings
	 * - Ensures requirements
	 * - Controls processing
	 *
	 * @since      4.5.0
	 * 
	 * @return bool True if avatar transformation should be active, false otherwise.
	 */
	protected function should_filter(): bool {
		return Features::is_enabled( 'avatars' ) && Helpers::should_transform_images();
	}
} 