<?php
/**
 * Avatar transformation functionality.
 *
 * Handles the transformation of avatar images across the site.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.0
 */

namespace Edge_Images\Features;

use Edge_Images\{Helpers, Integration, Feature_Manager};

/**
 * Handles avatar transformations.
 *
 * @since 4.5.0
 */
class Avatars extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		add_filter('get_avatar_url', [$this, 'transform_avatar_url'], 10, 3);
		add_filter('get_avatar', [$this, 'transform_avatar_html'], 10, 6);
	}

	/**
	 * Transform avatar URLs.
	 *
	 * @since 4.2.0
	 * 
	 * @param string $url         The URL of the avatar.
	 * @param mixed  $id_or_email The Gravatar to retrieve.
	 * @param array  $args        Arguments passed to get_avatar_data().
	 * @return string The transformed URL.
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
	 * @since 4.2.0
	 * 
	 * @param string $avatar      HTML for the user's avatar.
	 * @param mixed  $id_or_email The Gravatar to retrieve.
	 * @param int    $size        Square avatar width and height in pixels.
	 * @param string $default     URL for the default image.
	 * @param string $alt         Alternative text.
	 * @param array  $args        Arguments passed to get_avatar_data().
	 * @return string The transformed avatar HTML.
	 */
	public function transform_avatar_html( string $avatar, $id_or_email, int $size, string $default, string $alt, array $args ): string {
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

		// Add our classes
		$classes = $processor->get_attribute( 'class' ) ?? '';
		$processor->set_attribute( 'class', trim( $classes . ' edge-images-img edge-images-processed' ) );

		// Set dimensions
		$processor->set_attribute( 'width', (string) $size );
		$processor->set_attribute( 'height', (string) $size );

		// Check if picture wrapping is enabled
		if ( Feature_Manager::is_enabled( 'picture_wrap' ) ) {
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
	 * @since 4.5.0
	 * 
	 * @return array<string,mixed> Default settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_feature_avatars' => true,
		];
	}

	/**
	 * Check if this integration should filter.
	 *
	 * @since 4.5.0
	 * 
	 * @return bool Whether the integration should filter.
	 */
	protected function should_filter(): bool {
		return Feature_Manager::is_enabled( 'avatars' ) && Helpers::should_transform_images();
	}
} 