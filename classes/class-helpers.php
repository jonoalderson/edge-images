<?php
/**
 * Helper functions for Edge Images.
 *
 * Provides utility functions for URL transformation, provider management,
 * and general plugin functionality. This class contains static methods that
 * are used throughout the plugin for common operations such as URL handling,
 * provider configuration, and image processing decisions.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      1.0.0
 */

namespace Edge_Images;

use Edge_Images\Edge_Provider;

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
		
		// Get the provider.
		$provider = Settings::get_provider();
		
		// Allow filtering.
		$provider = apply_filters('edge_images_provider', $provider);
		
		// Validate provider
		if (!Providers::is_valid_provider($provider)) {
			return Providers::DEFAULT_PROVIDER;
		}
		
		return $provider;
	}

	/**
	 * Replace a SRC string with an edge version.
	 *
	 * @since 4.0.0
	 * 
	 * @param  string $src  The source URL.
	 * @param  array  $args The transformation arguments.
	 * @return string      The modified URL.
	 */
	public static function edge_src(string $src, array $args): string {
		// Skip SVGs and AVIFs
		if (preg_match('/\.(svg|avif)$/i', $src)) {
			return $src;
		}

		// Get original URL if this is a WordPress resized image
		$src = preg_replace('/-\d+x\d+\.(jpg|jpeg|png|gif|webp)$/i', '.$1', $src);

		// Bail if we shouldn't transform the src
		if (!self::should_transform_url($src)) {
			return $src;
		}

		// Get the provider name
		$provider = self::get_provider_name();
		
		// If provider is 'none', return original src
		if ($provider === 'none') {
			return $src;
		}

		// Get the provider class
		$provider_class = Providers::get_provider_class($provider);

		// Bail if we can't find one
		if (!class_exists($provider_class)) {
			return $src;
		}

		// Create our provider instance and get edge URL
		$provider_instance = new $provider_class($src, $args);
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



		// Bail if we're not on a page
		if ( ! self::request_is_for_page() ) {
			return false;
		}
		
		// Never transform in admin
		if ( is_admin() && !wp_doing_ajax() ) {  // Allow AJAX requests
			return false;
		}

		// Allow AJAX requests for Relevanssi
		if ( wp_doing_ajax() && isset($_REQUEST['action']) && $_REQUEST['action'] === 'relevanssi_live_search' ) {
			return true;
		}

		// Bail if the selected provider is not properly configured.
		if (!self::is_provider_configured()) {
			return false;
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
	 *
	 * @since 4.1.0
	 * 
	 * @return bool Whether the provider is properly configured.
	 */
	public static function is_provider_configured(): bool {

		$provider = Settings::get_provider();
		
		// Bail if no provider is selected.
		if ($provider === 'none') {
			return false;
		}
		
		$provider_class = Providers::get_provider_class($provider);
		
		if (!class_exists($provider_class)) {
			return false;
		}
		
		return $provider_class::is_configured();
	}

	/**
	 * Get URL pattern for a provider with caching.
	 *
	 * @param string $provider_name The provider name.
	 * @return string The URL pattern.
	 */
	private static function get_provider_url_pattern(string $provider_name): string {
		if (!isset(self::$url_pattern_cache[$provider_name])) {
			$provider_class = Providers::get_provider_class($provider_name);
			self::$url_pattern_cache[$provider_name] = $provider_class::get_url_pattern();
		}
		return self::$url_pattern_cache[$provider_name];
	}

	/**
	 * Check if a URL should be transformed.
	 *
	 * @param string $url The URL to check.
	 * @return bool Whether the URL should be transformed.
	 */
	public static function should_transform_url(string $url): bool {
	
		// Skip empty URLs
		if (empty($url)) {
			return false;
		}

		// Skip data URLs
		if (strpos($url, 'data:') === 0) {
			return false;
		}

		// Skip SVGs
		if (self::is_svg($url)) {
			return false;
		}

		// Skip already transformed URLs
		if (self::is_transformed_url($url)) {
			return false;
		}

		// Skip external URLs
		if (!self::is_local_url($url)) {
			return false;
		}

		// Get the provider
		$provider = self::get_edge_provider();
		if (!$provider) {
			return false;
		}

		if (!$provider::is_configured()) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a URL is local.
	 *
	 * @param string $url The URL to check.
	 * @return bool Whether the URL is local.
	 */
	public static function is_local_url(string $url): bool {
		$site_url = site_url();
		$home_url = home_url();
		
		// Remove protocol and www
		$url = preg_replace('#^https?://(www\.)?#', '', $url);
		$site_url = preg_replace('#^https?://(www\.)?#', '', $site_url);
		$home_url = preg_replace('#^https?://(www\.)?#', '', $home_url);
		
		// Check if URL starts with either site_url or home_url
		$is_local = (strpos($url, $site_url) === 0) || (strpos($url, $home_url) === 0);
		
		return $is_local;
	}

	/**
	 * Get the edge provider instance.
	 *
	 * @return Edge_Provider|null The provider instance or null if none configured.
	 */
	public static function get_edge_provider(): ?Edge_Provider {
		// Get the provider name
		$provider_name = self::get_provider_name();

		// Return null for 'none' provider
		if ($provider_name === 'none') {
			return null;
		}

		// Get the provider class
		$provider_class = Providers::get_provider_class($provider_name);

		// Create a test instance with a dummy path
		$provider = new $provider_class('');

		if (!$provider) {
			return null;
		}

		return $provider;
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
	 * Get the attachment ID from a URL, with caching.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $url The image URL.
	 * @return int|null The attachment ID or null if not found.
	 */
	public static function get_attachment_id_from_url( string $url ): ?int {
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

	
	/**
	 * Checks if the request is for a 'page'
	 *
	 * @return bool
	 */
	public static function request_is_for_page(): bool {
		
		// Admin.
		if ( is_admin() && !wp_doing_ajax() ) {
			return false;
		}

		// Service workers - check if the query var exists first
		global $wp_query;
		if ( $wp_query && get_query_var( 'wp_service_worker', false ) ) {
			return false;
		}

		// Get the request URI for pattern matching
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		// Check common non-page URLs
		$non_page_patterns = [
			'/favicon.ico',      // Favicon
			'/feed/',           // Main feed
			'/feed/atom/',      // Atom feed
			'/feed/rss/',       // RSS feed
			'/robots.txt',      // Robots
			'/wp-json/',        // REST API
			'.xml',             // XML files (including sitemaps)
			'.kml',             // KML files
		];

		foreach ($non_page_patterns as $pattern) {
			if (strpos($request_uri, $pattern) !== false) {
				return false;
			}
		}

		// Check content type for JSON/JSONP requests
		$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
		if (strpos($content_type, 'application/json') !== false || 
			strpos($content_type, 'application/javascript') !== false) {
			return false;
		}

		// Bail if this is the login page.
		if ( function_exists( 'is_login' ) && is_login() ) {
			return true;
		}

		// Back-end wp-login/wp-register activity.
		if ( isset( $GLOBALS['pagenow'] ) ) {
			if ( in_array(
				$GLOBALS['pagenow'],
				array( 'wp-login.php', 'wp-register.php' ),
				true
			)
			) {
				return false;
			}
		}

		// XML sitemaps - check if the query var exists first
		if ( $wp_query && get_query_var( 'sitemap', false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Clean a URL by removing the site domain and protocol.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $url The URL to clean.
	 * @return string The cleaned path.
	 */
	public static function clean_url(string $url): string {
		
		// Get the home URL without trailing slash
		$home_url = untrailingslashit(home_url());
		
		// If the URL starts with the home URL, remove it
		if (str_starts_with($url, $home_url)) {
			return substr($url, strlen($home_url));
		}
		
		return $url;
	}

	/**
	 * Get attachment ID from image classes.
	 *
	 * @since 4.5.0
	 * 
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * @return int|null Attachment ID or null if not found.
	 */
	public static function get_attachment_id_from_classes(\WP_HTML_Tag_Processor $processor): ?int {

		// Get the classes
		$classes = $processor->get_attribute('class');

		// Bail if no classes
		if (!$classes) {
			return null;
		}

		// Look for wp-image-{ID} class
		if (preg_match('/wp-image-(\d+)/', $classes, $matches)) {
			return (int) $matches[1];
		}

		// Look for attachment-{ID} class
		if (preg_match('/attachment-(\d+)/', $classes, $matches)) {
			return (int) $matches[1];
		}

		// Also check for a data-id attribute
		$data_id = $processor->get_attribute('data-id');
		if ($data_id) {
			return (int) $data_id;
		}

		return null;
	}

	/**
	 * Check if an image has already been processed.
	 *
	 * @since 4.5.0
	 * 
	 * @param \WP_HTML_Tag_Processor|string $input Either a Tag Processor or HTML string.
	 * @return bool Whether the image has been processed.
	 */
	public static function is_image_processed($input): bool {
		if ($input instanceof \WP_HTML_Tag_Processor) {
			$class = $input->get_attribute('class') ?? '';
			return str_contains($class, 'edge-images-processed');
		}

		if (is_string($input)) {
			return str_contains($input, 'edge-images-processed');
		}

		return false;
	}

}
