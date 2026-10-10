<?php
/**
 * Helper functions for Edge Images.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Shared helpers for URL transformation and request gating.
 */
class Helpers {

	/**
	 * Plugin styles URL.
	 *
	 * @var string
	 */
	public const STYLES_URL = EDGE_IMAGES_PLUGIN_URL . 'assets/css';

	/**
	 * Plugin styles path.
	 *
	 * @var string
	 */
	public const STYLES_PATH = EDGE_IMAGES_PLUGIN_DIR . '/assets/css';

	/**
	 * Whether transformation should be disabled for a given HTML/URL.
	 *
	 * @param string $html HTML or URL.
	 * @return bool
	 */
	public static function should_disable_transform( string $html = '' ): bool {

		return (bool) \apply_filters( 'edge_images_disable_transform', false, $html );
	}

	/**
	 * Whether images should be transformed in this request.
	 *
	 * @return bool
	 */
	public static function should_transform_images(): bool {

		if ( \apply_filters( 'edge_images_disable', false ) ) {
			return false;
		}

		if ( \is_admin() && ! \wp_doing_ajax() ) {
			// Allow the block editor content iframe.
			if ( ! self::is_block_editor_iframe() ) {
				return false;
			}
		}

		$provider = Settings::get_provider();
		$provider = (string) \apply_filters( 'edge_images_provider', $provider );

		if ( ! Providers::is_valid_provider( $provider ) || 'none' === $provider ) {
			return false;
		}

		$provider_class = Providers::get_provider_class( $provider );
		if ( ! class_exists( $provider_class ) || ! $provider_class::is_configured() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether this is the block editor content iframe.
	 *
	 * @return bool
	 */
	private static function is_block_editor_iframe(): bool {

		if ( ! \is_admin() ) {
			return false;
		}

		if ( \function_exists( 'wp_should_load_block_editor_scripts_and_styles' ) ) {
			return ! \wp_should_load_block_editor_scripts_and_styles();
		}

		return false;
	}

	/**
	 * Convert a source URL into an edge URL.
	 *
	 * @param string               $src  Source URL.
	 * @param array<string, mixed> $args Transform args.
	 * @return string
	 */
	public static function edge_src( string $src, array $args ): string {

		if ( self::is_non_transformable_format( $src ) ) {
			return $src;
		}

		$src = self::strip_dimensions_from_url( $src );

		$provider = Settings::get_provider();
		$provider = (string) \apply_filters( 'edge_images_provider', $provider );

		if ( 'none' === $provider || ! Providers::is_valid_provider( $provider ) ) {
			return $src;
		}

		$provider_class = Providers::get_provider_class( $provider );
		if ( ! class_exists( $provider_class ) ) {
			return $src;
		}

		$path = self::clean_url( $src );
		if ( '' === $path ) {
			return $src;
		}

		$instance = new $provider_class();
		$instance->set_path( $path );
		$instance->set_args( $args );

		return $instance->get_edge_url();
	}

	/**
	 * Whether a URL is an SVG.
	 *
	 * @param string $src URL.
	 * @return bool
	 */
	public static function is_svg( string $src ): bool {

		return false !== strpos( $src, '.svg' );
	}

	/**
	 * Whether a URL should never be transformed.
	 *
	 * @param string|null $src URL.
	 * @return bool
	 */
	public static function is_non_transformable_format( ?string $src ): bool {

		if ( empty( $src ) || ! is_string( $src ) ) {
			return true;
		}

		if ( 0 === strpos( $src, 'data:' ) ) {
			return true;
		}

		if ( 0 !== strpos( $src, 'http' ) && 0 !== strpos( $src, '//' ) ) {
			return true;
		}

		if ( self::is_svg( $src ) || false !== strpos( $src, '.avif' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Domain used for edge rewrites.
	 *
	 * @return string
	 */
	public static function get_rewrite_domain(): string {

		$domain = Settings::get_domain();
		if ( empty( $domain ) ) {
			$domain = \get_site_url();
		}

		return (string) \apply_filters( 'edge_images_domain', $domain );
	}

	/**
	 * Extract the path from a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function clean_url( string $url ): string {

		if ( '' === $url ) {
			return '';
		}

		// Already a path.
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return '/' . ltrim( preg_replace( '#/+#', '/', $url ) ?? $url, '/' );
		}

		$parsed = \wp_parse_url( $url );
		if ( ! isset( $parsed['path'] ) ) {
			return '';
		}

		$path = preg_replace( '#/+#', '/', $parsed['path'] );

		return '/' . ltrim( (string) $path, '/' );
	}

	/**
	 * Strip WordPress intermediate size suffixes from a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function strip_dimensions_from_url( string $url ): string {

		return (string) preg_replace( '/-\d+x\d+(\.[a-zA-Z0-9]+)(?:\?.*)?$/', '$1', $url );
	}

	/**
	 * Whether a URL is local to this site or the rewrite domain.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_local_url( string $url ): bool {

		$host = \wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$site_host = \wp_parse_url( \get_site_url(), PHP_URL_HOST );
		$edge_host = \wp_parse_url( self::get_rewrite_domain(), PHP_URL_HOST );

		return $host === $site_host || $host === $edge_host;
	}

	/**
	 * Whether a URL already looks like an edge-transformed URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_edge_url( string $url ): bool {

		$provider = Settings::get_provider();
		if ( 'none' === $provider || ! Providers::is_valid_provider( $provider ) ) {
			return false;
		}

		$provider_class = Providers::get_provider_class( $provider );
		if ( ! class_exists( $provider_class ) ) {
			return false;
		}

		$pattern = $provider_class::get_url_pattern();

		return '' !== $pattern && false !== strpos( $url, $pattern );
	}

	/**
	 * Get the active edge provider instance class name.
	 *
	 * @return string
	 */
	public static function get_edge_provider(): string {

		$provider = Settings::get_provider();
		$provider = (string) \apply_filters( 'edge_images_provider', $provider );

		if ( ! Providers::is_valid_provider( $provider ) || 'none' === $provider ) {
			return '';
		}

		return Providers::get_provider_class( $provider );
	}
}
