<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

use Edge_Images\Edge_Provider;

/**
 * Provides helper methods.
 */
class Helpers {

	/**
	 * The plugin styles URL
	 *
	 * @var string
	 */
	public const STYLES_URL = EDGE_IMAGES_PLUGIN_URL . 'assets/css';

	/**
	 * The plugin styles path
	 *
	 * @var string
	 */
	public const STYLES_PATH = EDGE_IMAGES_PLUGIN_DIR . '/assets/css';

	/**
	 * The plugin scripts path
	 *
	 * @var string
	 */
	public const SCRIPTS_PATH = EDGE_IMAGES_PLUGIN_DIR . '/assets/js';

	/**
	 * Get the configured edge provider name
	 *
	 * @return string The provider name
	 */
	private static function get_provider_name(): string {
		// Get the provider from options
		$provider = get_option( 'edge_images_provider', Provider_Registry::DEFAULT_PROVIDER );
		
		// Allow filtering
		$provider = apply_filters( 'edge_images_provider', $provider );
		
		// Validate provider name
		if ( ! Provider_Registry::is_valid_provider( $provider ) ) {
			return Provider_Registry::DEFAULT_PROVIDER;
		}
		
		return $provider;
	}

	/**
	 * Replace a SRC string with an edge version
	 *
	 * @param  string $src The src.
	 * @param  array  $args The args.
	 *
	 * @return string      The modified SRC attr.
	 */
	public static function edge_src( string $src, array $args ): string {
		// Don't transform SVGs
		if ( self::is_svg( $src ) ) {
			return $src;
		}

		// Get the provider name
		$provider = self::get_provider_name();
		
		// If provider is 'none', return original src
		if ( $provider === 'none' ) {
			return $src;
		}

		// Get the provider class
		$provider_class = Provider_Registry::get_provider_class( $provider );

		// Bail if we can't find one
		if ( ! class_exists( $provider_class ) ) {
			return $src;
		}

		// If URL is already transformed, extract the original path
		if ( strpos( $src, $provider_class::get_url_pattern() ) !== false ) {
			$upload_dir = wp_get_upload_dir();
			$upload_path = str_replace( site_url('/'), '', $upload_dir['baseurl'] );
			
			// Extract everything after the upload path
			if ( preg_match( '#' . preg_quote( $upload_path ) . '/.*$#', $src, $matches ) ) {
				$src = $matches[0];
			}
		}

		// Get the image path from the URL
		$url  = wp_parse_url( $src );
		$path = ( isset( $url['path'] ) ) ? $url['path'] : '';

		// Create our provider
		$provider_instance = new $provider_class( $path, $args );

		// Get the edge URL
		return $provider_instance->get_edge_url();
	}

	/**
	 * Determines if images should be transformed
	 *
	 * @return bool
	 */
	public static function should_transform_images(): bool {
		// Never transform in admin
		if ( is_admin() ) {
			return false;
		}

		// Never transform in REST API or AJAX requests
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( wp_is_json_request() ) {
			return false;
		}

		if ( wp_doing_ajax() ) {
			return false;
		}

		// If we're debugging, always return true.
		if ( defined( 'EDGE_IMAGES_DEBUG_MODE' ) && EDGE_IMAGES_DEBUG_MODE === true ) {
			return true;
		}

		// Bail if the functionality has been disabled via a filter.
		$disabled = apply_filters( 'edge_images_disable', false );
		if ( $disabled === true ) {
			return false;
		}

		return true;
	}

	/**
	 * Determines if an image is an SVG.
	 *
	 * @param string $src The image src value.
	 *
	 * @return bool
	 */
	public static function is_svg( string $src ): bool {
		return strpos( $src, '.svg' ) !== false;
	}

	/**
	 * Get an edge provider instance
	 *
	 * @param string $path Optional path.
	 * @param array  $args Optional args.
	 * 
	 * @return Edge_Provider The provider instance
	 */
	public static function get_edge_provider( string $path = '', array $args = [] ): Edge_Provider {
		$provider = self::get_provider_name();
		$provider_class = Provider_Registry::get_provider_class( $provider );

		if ( ! class_exists( $provider_class ) ) {
			$provider_class = Edge_Provider::class;
		}

		return new $provider_class( $path, $args );
	}

	/**
	 * Get the domain to use as the edge rewrite base
	 *
	 * @return string The domain
	 */
	public static function get_rewrite_domain(): string {
		return apply_filters( 'edge_images_domain', get_site_url() );
	}
}
