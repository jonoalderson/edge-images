<?php
/**
 * Helper functions for Edge Images.
 *
 * Provides utility functions for URL transformation, provider management,
 * and general plugin functionality.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      1.0.0
 */

namespace Edge_Images;

use Edge_Images\Edge_Provider;

/**
 * Static helper class for common functionality.
 *
 * @since 4.0.0
 */
class Helpers {

	/**
	 * The plugin styles URL.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public const STYLES_URL = EDGE_IMAGES_PLUGIN_URL . 'assets/css';

	/**
	 * The plugin styles path.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public const STYLES_PATH = EDGE_IMAGES_PLUGIN_DIR . '/assets/css';

	/**
	 * The plugin scripts path.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public const SCRIPTS_PATH = EDGE_IMAGES_PLUGIN_DIR . '/assets/js';

	/**
	 * Valid HTML image attributes.
	 * 
	 * @since 4.0.0
	 * @var array<string>
	 */
	public static array $valid_html_attrs = [
		'alt',
		'class',
		'container-class',
		'decoding',
		'height',
		'id',
		'loading',
		'sizes',
		'src',
		'srcset',
		'style',
		'title',
		'width',
		'data-attachment-id',
		'data-original-width',
		'data-original-height',
		'data-wrap-in-picture',
		'fetchpriority'
	];

	/**
	 * Cache for provider URL patterns.
	 *
	 * @var array<string,string>
	 */
	private static array $url_pattern_cache = [];

	/**
	 * Cache group for all operations in the plugin.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	public const CACHE_GROUP = 'edge_images';

	/**
	 * Get the configured edge provider name.
	 *
	 * Retrieves the provider name from options and validates it.
	 * Falls back to the default provider if the configured one is invalid.
	 *
	 * @since 4.0.0
	 * 
	 * @return string The provider name.
	 */
	private static function get_provider_name(): string {
		// Get the provider from options.
		$provider = get_option( 'edge_images_provider', Provider_Registry::DEFAULT_PROVIDER );
		
		// Allow filtering.
		$provider = apply_filters( 'edge_images_provider', $provider );
		
		// Validate provider name.
		if ( ! Provider_Registry::is_valid_provider( $provider ) ) {
			return Provider_Registry::DEFAULT_PROVIDER;
		}
		
		return $provider;
	}

	/**
	 * Replace a SRC string with an edge version.
	 *
	 * Takes an image URL and transformation arguments and returns
	 * a URL that will be processed by the edge provider.
	 *
	 * @since 4.0.0
	 * 
	 * @param  string $src  The source URL.
	 * @param  array  $args The transformation arguments.
	 * @return string      The modified URL.
	 */
	public static function edge_src( string $src, array $args ): string {
		// Skip SVGs and AVIFs
		if (preg_match('/\.(svg|avif)$/i', $src)) {
			return $src;
		}

		// Bail if we shouldn't transform the src.
		if (!self::should_transform_url($src)) {
			return $src;
		}

		// Get the provider name.
		$provider = self::get_provider_name();
		
		// If provider is 'none', return original src.
		if ( $provider === 'none' ) {
			return $src;
		}

		// Get the provider class.
		$provider_class = Provider_Registry::get_provider_class( $provider );

		// Bail if we can't find one.
		if ( ! class_exists( $provider_class ) ) {
			return $src;
		}

		// If URL is already transformed, extract the original path.
		if ( strpos( $src, $provider_class::get_url_pattern() ) !== false ) {
			$upload_dir = wp_get_upload_dir();
			$upload_path = str_replace( site_url('/'), '', $upload_dir['baseurl'] );
			
			// Extract everything after the upload path.
			if ( preg_match( '#' . preg_quote( $upload_path ) . '/.*$#', $src, $matches ) ) {
				$src = $matches[0];
			}
		}

		// Get the image path from the URL.
		$url  = wp_parse_url( $src );
		$path = ( isset( $url['path'] ) ) ? $url['path'] : '';

		// Create our provider instance.
		$provider_instance = new $provider_class( $path, $args );

		// Get the edge URL.
		return $provider_instance->get_edge_url();
	}

	/**
	 * Determines if images should be transformed.
	 *
	 * Checks various conditions to determine if image transformation
	 * should be performed in the current context.
	 *
	 * @since 4.0.0
	 * 
	 * @return bool Whether images should be transformed.
	 */
	public static function should_transform_images(): bool {
		// Never transform in admin
		if ( is_admin() && !wp_doing_ajax() ) {  // Allow AJAX requests
			return false;
		}

		// Never transform in REST API requests
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( wp_is_json_request() ) {
			return false;
		}

		// Allow AJAX requests for Relevanssi
		if ( wp_doing_ajax() && isset($_REQUEST['action']) && $_REQUEST['action'] === 'relevanssi_live_search' ) {
			return true;
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

		// Check if Imgix is selected but not configured
		$provider = get_option( 'edge_images_provider', Provider_Registry::DEFAULT_PROVIDER );
		if ( $provider === 'imgix' ) {
			$subdomain = get_option( 'edge_images_imgix_subdomain', '' );
			if ( empty( $subdomain ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if an image is an SVG.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $src The image src value.
	 * @return bool Whether the image is an SVG.
	 */
	public static function is_svg( string $src ): bool {
		return strpos( $src, '.svg' ) !== false;
	}

	/**
	 * Get an edge provider instance.
	 *
	 * Creates and returns a new instance of the configured edge provider.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $path Optional path to the image.
	 * @param array  $args Optional transformation arguments.
	 * @return Edge_Provider The provider instance.
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
	 * Get the domain to use as the edge rewrite base.
	 *
	 * @since 4.0.0
	 * 
	 * @return string The domain to use for edge URLs.
	 */
	public static function get_rewrite_domain(): string {
		return apply_filters( 'edge_images_domain', get_site_url() );
	}

	/**
	 * Check if the current provider is properly configured.
	 *
	 * Validates that the selected provider has all required configuration.
	 * For example, checks if Imgix has a subdomain configured.
	 *
	 * @since 4.1.0
	 * 
	 * @return bool Whether the provider is properly configured.
	 */
	public static function is_provider_configured(): bool {
		$provider = get_option( 'edge_images_provider', 'none' );

		// Check provider-specific requirements
		switch ( $provider ) {
			case 'imgix':
				$subdomain = get_option( 'edge_images_imgix_subdomain', '' );
				if ( empty( $subdomain ) ) {
					return false;
				}
				break;
			// Add other provider-specific checks here as needed
		}

		return true;
	}

	/**
	 * Get URL pattern for a provider with caching.
	 *
	 * @param string $provider_name The provider name.
	 * @return string The URL pattern.
	 */
	private static function get_provider_url_pattern(string $provider_name): string {
		if (!isset(self::$url_pattern_cache[$provider_name])) {
			$provider_class = Provider_Registry::get_provider_class($provider_name);
			self::$url_pattern_cache[$provider_name] = $provider_class::get_url_pattern();
		}
		return self::$url_pattern_cache[$provider_name];
	}

	/**
	 * Check if we should transform an image URL.
	 *
	 * @param string $url The image URL to check.
	 * @return bool Whether we should transform the image.
	 */
	public static function should_transform_url(string $url): bool {
		// Skip if no URL
		if (!$url) {
			return false;
		}

		// Skip if already transformed
		if (self::is_transformed_url($url)) {
			return false;
		}

		// Skip external URLs
		if (!self::is_local_url($url)) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a URL has already been transformed by an edge provider.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $url The URL to check.
	 * @return bool Whether the URL has been transformed.
	 */
	public static function is_transformed_url(string $url): bool {
		// Get current provider
		$provider = self::get_provider_name();
		
		// If no provider selected, URL can't be transformed
		if ($provider === 'none') {
			return false;
		}

		// Get the provider's URL pattern
		$pattern = self::get_provider_url_pattern($provider);
		
		// Check if URL contains the provider's pattern
		return strpos($url, $pattern) !== false;
	}

	/**
	 * Check if a URL is local to the current site.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $url The URL to check.
	 * @return bool Whether the URL is local.
	 */
	public static function is_local_url(string $url): bool {
		$site_url = site_url();
		$upload_url = wp_get_upload_dir()['baseurl'];
		
		// Check if URL starts with site URL or uploads URL
		return strpos($url, $site_url) === 0 || strpos($url, $upload_url) === 0;
	}

	/**
	 * Get the attachment ID from a URL, with caching.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $url The image URL.
	 * @return int|null The attachment ID or null if not found.
	 */
	public static function get_attachment_id( string $url ): ?int {
		// Remove query string for attachment lookup
		$clean_url = preg_replace('/\?.*/', '', $url);

		// Try to get cached attachment ID
		$cache_key = 'attachment_' . md5($clean_url);
		$image_id = wp_cache_get($cache_key, self::CACHE_GROUP);

		if (false === $image_id) {
			// Cache miss - look up the attachment ID
			$image_id = attachment_url_to_postid($clean_url);
			
			// Cache the result (even if it's 0)
			wp_cache_set($cache_key, $image_id, self::CACHE_GROUP, 3600);
		}

		return $image_id ?: null;
	}

	/**
	 * Get image dimensions with caching.
	 *
	 * @since 4.1.0
	 * 
	 * @param int $image_id The attachment ID.
	 * @return array|null The dimensions or null if not found.
	 */
	public static function get_image_dimensions( int $image_id ): ?array {
		static $dimension_cache = [];

		// Get dimensions from static cache first
		if (!isset($dimension_cache[$image_id])) {
			$dimension_cache[$image_id] = Image_Dimensions::from_attachment($image_id);
		}

		return $dimension_cache[$image_id] ?: null;
	}
}
