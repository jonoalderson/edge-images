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
 * @license    GPL-2.0-or-later
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
		'decoding',
		'height',
		'id',
		'loading',
		'sizes',
		'src',
		'srcset',
		'style',
		'width',
		'data-attachment-id',
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
	 * Determines if image transformation should be disabled.
	 *
	 * @since 5.3.0
	 * 
	 * @param string $html The image HTML to check.
	 * @return bool Whether image transformation should be disabled.
	 */
	public static function should_disable_transform(string $html = ''): bool {
		return apply_filters('edge_images_disable_transform', false, $html);
	}

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
		
		// Skip non-transformable formats (empty URLs, data: URLs, SVG, AVIF)
		if (self::is_non_transformable_format($src)) {
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

		// Clean the URL to get just the path
		$cleaned_path = self::clean_url($src);
		if (empty($cleaned_path)) {
			return $src;
		}

		// Create our provider instance and get edge URL
		$provider_instance = new $provider_class();
		$provider_instance->set_path($cleaned_path);
		$provider_instance->set_args($args);
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
		
		// Never transform if plugin is disabled
		if (apply_filters('edge_images_disable', false)) {
			return false;
		}

		// Never transform in admin unless it's an AJAX request
		if (is_admin() && !wp_doing_ajax()) {
			return false;
		}

		// Get the provider name
		$provider = self::get_provider_name();
		
		// Bail if no provider or provider is 'none'
		if (!$provider || $provider === 'none') {
			return false;
		}

		// Get the provider class
		$provider_class = Providers::get_provider_class($provider);
		if (!class_exists($provider_class)) {
			return false;
		}

		// Bail if provider isn't properly configured
		if (!$provider_class::is_configured()) {
			return false;
		}

		// Allow AJAX requests for Relevanssi
		if (wp_doing_ajax() && isset($_REQUEST['action'])) {
			$action = sanitize_key($_REQUEST['action']);
			if ($action === 'relevanssi_live_search') {
				return true;
			}
		}

		// Check if this is a valid page request
		if (!self::request_is_for_page()) {
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
	 * Determines if an image is an AVIF.
	 *
	 * @since 5.3.0
	 * 
	 * @param string $src The image src value.
	 * @return bool Whether the image is an AVIF.
	 */
	public static function is_avif( string $src ): bool {
		return strpos( $src, '.avif' ) !== false;
	}

	/**
	 * Determines if an image format should not be transformed.
	 *
		* Checks if the image URL is:
		* - Empty
		* - A data: URL
		* - An SVG file
		* - An AVIF file
		* - Not a string
		* - Doesn't begin with 'http' or '//'
		*
		* These formats should never be transformed as they are either
		* already optimized or cannot/should not be processed.
		*
		* @since 5.3.0
		* 
		* @param string|null $src The image src value.
	 * @return bool Whether the image format should not be transformed.
	 */
	public static function is_non_transformable_format( ?string $src ): bool {
		
		// Skip empty URLs
		if (empty($src)) {
			return true;
		}

		// Skip data URLs
		if (strpos($src, 'data:') === 0) {
			return true;
		}

		// Skip if the $src isn't a string.
		if (!is_string($src)) {
			return true;
		}

		// Skip if the $src doesn't begin with 'http' or '//'
		if (strpos($src, 'http') !== 0 && strpos($src, '//') !== 0) {
			return true;
		}

		// Skip if it's an SVG or AVIF file
		if (self::is_svg($src) || self::is_avif($src)) {
			return true;
		}

		// If we've made it this far, the URL is transformable
		return false;
	}

	/**
	 * Get the domain to use as the edge rewrite base.
	 *
	 * @since 4.0.0
	 * 
	 * @return string The domain to use for edge URLs.
	 */
	public static function get_rewrite_domain(): string {
		// Get custom domain from settings
		$domain = Settings::get_domain();
		
		// If no custom domain, use site URL
		if (empty($domain)) {
			$domain = get_site_url();
		}
		
		// Allow filtering
		return (string) apply_filters('edge_images_domain', $domain);
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

		// Skip non-transformable formats
		if (self::is_non_transformable_format($url)) {
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
		// If we can't parse the URL, assume it's not local
		$url_parts = wp_parse_url($url);
		if (!$url_parts || empty($url_parts['host'])) {
			return false;
		}

		// Get the list of internal hosts
		$internal_hosts = wp_internal_hosts();
		$url_host = strtolower($url_parts['host']);
		
		// Check if the URL's host matches any internal host
		$is_local = in_array($url_host, $internal_hosts, true);

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
	 * A shortcut to get the provider instance.
	 *
	 * @since 4.0.0
	 * 
	 * @return Edge_Provider|null The provider instance or null if none configured.
	 */
	public static function get_provider(): ?Edge_Provider {
        return self::get_edge_provider();
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
		$request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
		
		// Clean the path for comparison
		$clean_path = trim(wp_parse_url($request_uri, PHP_URL_PATH) ?? '', '/');

		// Check system files that should always be excluded
		$system_files = [
			'favicon.ico',      // Favicon
			'robots.txt',      // Robots
		];

		// Direct path matches for system files
		if (in_array($clean_path, $system_files, true)) {
			return false;
		}

		// Check WordPress system paths (these should match full paths or have specific positions)
		$wp_paths = [
			'^feed$',           // Main feed (exact match)
			'^feed/',           // Feed with subpath
			'^wp-json/',        // REST API (must start with)
			'/feed$',           // Category/taxonomy feed (must end with)
			'/feed/',           // Nested feed
		];

		foreach ($wp_paths as $path) {
			if (preg_match('~' . $path . '~', $clean_path)) {
				return false;
			}
		}
		
		// Check content type for JSON/JSONP requests
		$content_type = isset($_SERVER['CONTENT_TYPE']) ? sanitize_text_field(wp_unslash($_SERVER['CONTENT_TYPE'])) : '';
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

		return true;
	}

	/**
	 * Clean a URL to get just the path component.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $url The URL to clean.
	 * @return string The cleaned path.
	 */
	public static function clean_url(string $url): string {
		// If empty URL, return empty string
		if (empty($url)) {
			return '';
		}

		// Parse the URL
		$parsed = wp_parse_url($url);

		// If no path component, return empty string
		if (!isset($parsed['path'])) {
			return '';
		}

		// Extract just the path component
		$path = $parsed['path'];

		// Remove any double slashes
		$path = preg_replace('#/+#', '/', $path);

		// Ensure path starts with a single slash
		$path = '/' . ltrim($path, '/');

		return $path;
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
	 * Check if an image has been processed or skipped.
	 *
	 * @since 4.5.0
	 * 
	 * @param string|array $input Either a class string or array of attributes.
	 * @return bool Whether the image has been processed or skipped.
	 */
	public static function is_image_processed($input): bool {
		// Get the class string
		$classes = is_array($input) ? ($input['class'] ?? '') : $input;

		// Check for either processed or skipped class
		return strpos($classes, 'edge-images-processed') !== false || 
			   strpos($classes, 'edge-images-skipped') !== false;
	}

	/**
	 * Check if an image should be skipped from transformation.
	 *
	 * @since 5.4.0
	 * 
	 * @param string $src The image source URL.
	 * @param string $html Optional. The complete HTML tag.
	 * @return bool Whether the image should be skipped.
	 */
	public static function should_skip_transform(string $src, string $html = ''): bool {

		// Skip if transformation is disabled for this image
		if (!empty($html) && self::should_disable_transform($html)) {
			return true;
		}
		
		// Skip if it's a non-transformable format
		if (self::is_non_transformable_format($src)) {
			return true;
		}

		// Skip if already processed or marked as skipped
		if (!empty($html) && self::is_image_processed($html)) {
			return true;
		}

		return false;
	}

	/**
	 * Extract img tag from HTML.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $html The HTML containing the img tag.
	 * @return string|null The img tag HTML or null if not found.
	 */
	public static function extract_img_tag(string $html): ?string {
		$processor = new \WP_HTML_Tag_Processor($html);
		
		if (!$processor->next_tag('img')) {
			return null;
		}

		// Create a new processor for the output img tag
		$tag_html = new \WP_HTML_Tag_Processor('<img>');
		$tag_html->next_tag();

		// Copy all attributes from the source to the new tag
		foreach ($processor->get_attribute_names_with_prefix('') as $name) {
			$tag_html->set_attribute($name, $processor->get_attribute($name));
		}

		return $tag_html->get_updated_html();
	}

	/**
	 * Convert WP_HTML_Tag_Processor to attributes array.
	 *
	 * @since 4.5.0
	 * 
	 * @param \WP_HTML_Tag_Processor $processor The processor.
	 * @return array The attributes array.
	 */
	public static function processor_to_attributes(\WP_HTML_Tag_Processor $processor): array {
		$attributes = ['class' => ''];
		foreach (self::$valid_html_attrs as $attr) {
			$value = $processor->get_attribute($attr);
			if ($value !== null) {
				$attributes[$attr] = $value;
			}
		}
		return $attributes;
	}

	/**
	 * Convert attributes array to string.
	 *
	 * @since 4.5.0
	 * 
	 * @param array $attributes The attributes array.
	 * @return string The attributes string.
	 */
	public static function attributes_to_string(array $attributes): string {

		// Initialize the pairs array
		$pairs = [];

		// Loop through the attributes
		foreach ($attributes as $name => $value) {

			// Skip null values
			if ($value === null) {
				continue;
			}

			// Special handling for alt attribute - always include it if it exists
			if ($name === 'alt' || $value !== '') {
				$pairs[] = sprintf('%s="%s"', $name, esc_attr($value));
			}
		}
		
		return implode(' ', $pairs);
	}

	/**
	 * Get the original URL from a potentially resized image URL.
	 *
	 * Takes a URL that might be a resized version (e.g., image-150x150.jpg)
	 * and returns the URL to the original image (e.g., image.jpg).
	 * Also handles getting the full URL from relative paths.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $src The source URL to process.
	 * @return string The original image URL, or empty string if invalid.
	 */
	public static function get_original_url(string $src): string {
		if (empty($src)) {
			return '';
		}

		// Remove any existing size suffixes (e.g., -150x150)
		$original_src = preg_replace('/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $src);

		// Get the upload directory info
		$upload_dir = wp_get_upload_dir();
		$upload_path = str_replace(site_url('/'), '', $upload_dir['baseurl']);

		// If this is a relative path from the uploads directory, make it absolute
		if (preg_match('#' . preg_quote($upload_path) . '/.*$#', $original_src, $matches)) {
			$original_src = site_url($matches[0]);
		}

		return $original_src;
	}

	/**
	 * Extract figure classes from HTML.
	 *
	 * @since 4.5.0
	 * @param string $html The HTML containing the figure tag.
	 * @return string Space-separated list of classes or empty string if none found.
	 */
	public static function extract_figure_classes(string $html): string {
		if (preg_match('/<figure[^>]*class=["\']([^"\']*)["\']/', $html, $matches)) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * Extract transform arguments from a transformed URL.
	 *
	 * @since 5.0.0
	 * @param string $url The transformed URL to extract arguments from.
	 * @return array The extracted transform arguments.
	 */
	public static function extract_transform_args_from_url(string $url): array {
		$args = [];
		
		// Parse the URL
		$parsed = parse_url($url);
		if (!isset($parsed['query'])) {
			return $args;
		}

		// Parse the query string
		parse_str($parsed['query'], $query_args);

		// Get valid transform args
		$valid_args = \Edge_Images\Edge_Provider::get_valid_args();

		// Extract only valid transform args
		foreach ($query_args as $key => $value) {
			if (isset($valid_args[$key]) || in_array($key, array_merge(...array_values($valid_args)), true)) {
				$args[$key] = $value;
			}
		}

		return $args;
	}

	/**
	 * Check if the server is running Apache.
	 *
	 * @since 5.4.0
	 * @return bool Whether the server is running Apache.
	 */
	public static function is_apache(): bool {
		if (!isset($_SERVER['SERVER_SOFTWARE'])) {
			return false;
		}

		$server_software = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']));
		return stripos($server_software, 'apache') !== false;
	}

	/**
	 * Check if the server is running NGINX.
	 *
	 * @since 5.4.0
	 * @return bool Whether the server is running NGINX.
	 */
	public static function is_nginx(): bool {
		if (!isset($_SERVER['SERVER_SOFTWARE'])) {
			return false;
		}

		$server_software = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']));
		return stripos($server_software, 'nginx') !== false;
	}

}
