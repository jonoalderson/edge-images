<?php
/**
 * Provider functionality.
 *
 * Manages the registration and validation of edge providers.
 * This class handles:
 * - Provider registration and management
 * - Provider validation and verification
 * - Provider class name resolution
 * - Provider caching and optimization
 * - Provider configuration validation
 * - Default provider handling
 * - Provider slug management
 * - Provider display name localization
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

namespace Edge_Images;

class Providers {

	/**
	 * The default provider to use if none is configured.
	 *
	 * This constant defines the fallback provider when no specific
	 * provider is set or when the configured provider is invalid.
	 * The 'none' provider disables image transformation functionality.
	 *
	 * @since      4.0.0
	 * @var string
	 */
	public const DEFAULT_PROVIDER = 'none';

	/**
	 * Cache for provider class names.
	 *
	 * Stores resolved provider class names to avoid repeated string
	 * operations and class name resolution. Uses provider slugs as
	 * keys and fully qualified class names as values.
	 *
	 * @since      4.0.0
	 * @var array<string,string>
	 */
	private static array $provider_class_cache = [];

	/**
	 * Get all registered providers.
	 *
	 * Returns an array of all available providers and their display names.
	 * This method:
	 * - Returns all registered edge providers
	 * - Provides localized display names
	 * - Includes the 'none' provider option
	 * - Is used in admin interfaces
	 * - Supports provider selection
	 * - Maintains consistent provider order
	 * - Ensures all providers are properly registered
	 *
	 * @since      4.0.0
	 * 
	 * @return array<string,string> Array of provider slugs and display names.
	 */
	public static function get_providers(): array {
		return [
			'none'                => __( 'None (Disabled)', 'edge-images' ),
			'cloudflare'          => __( 'Cloudflare', 'edge-images' ),
			'accelerated_domains' => __( 'Accelerated Domains', 'edge-images' ),
			'bunny'              => __( 'Bunny CDN', 'edge-images' ),
			'imgix'              => __( 'Imgix', 'edge-images' ),
		];
	}

	/**
	 * Get provider slugs.
	 *
	 * Returns an array of all registered provider slugs.
	 * This method:
	 * - Returns only provider identifiers
	 * - Excludes display names
	 * - Includes all available providers
	 * - Is used for validation
	 * - Supports provider management
	 * - Maintains consistent order
	 * - Ensures provider availability
	 *
	 * @since      4.0.0
	 * 
	 * @return array<string> Array of provider slugs.
	 */
	public static function get_provider_slugs(): array {
		return array_keys( self::get_providers() );
	}

	/**
	 * Check if a provider is valid.
	 *
	 * Validates whether a given provider name is registered and available.
	 * This method:
	 * - Performs case-insensitive validation
	 * - Checks against registered providers
	 * - Handles provider name normalization
	 * - Supports provider validation
	 * - Ensures provider availability
	 * - Prevents invalid provider usage
	 * - Returns boolean validation result
	 *
	 * @since      4.0.0
	 * 
	 * @param string $provider_name The provider name to check.
	 * @return bool Whether the provider is valid.
	 */
	public static function is_valid_provider( string $provider_name ): bool {
		// Convert provider name and valid providers to lowercase for case-insensitive comparison
		$provider_name = strtolower($provider_name);
		$valid_providers = array_map('strtolower', self::get_provider_slugs());
		return in_array($provider_name, $valid_providers, true);
	}

	/**
	 * Get the provider class name with caching.
	 *
	 * Resolves and caches the fully qualified class name for a provider.
	 * This method:
	 * - Uses cached class names when available
	 * - Handles case-insensitive provider names
	 * - Validates provider existence
	 * - Constructs namespaced class names
	 * - Supports provider class resolution
	 * - Handles the 'none' provider specially
	 * - Maintains consistent class naming
	 * - Optimizes class name resolution
	 *
	 * @since      4.0.0
	 * 
	 * @param string $provider_name The provider name to resolve.
	 * @return string The fully qualified provider class name.
	 */
	public static function get_provider_class( string $provider_name ): string {
		// Convert to lowercase for cache key
		$cache_key = strtolower($provider_name);
		
		if (isset(self::$provider_class_cache[$cache_key])) {
			return self::$provider_class_cache[$cache_key];
		}

		if (!self::is_valid_provider($provider_name) || strtolower($provider_name) === 'none') {
			$class = Edge_Provider::class;
		} else {
			// Always use the lowercase version for consistency
			$provider_name = strtolower($provider_name);
			$parts = explode('_', $provider_name);
			$parts = array_map('ucfirst', $parts);
			$class_name = implode('_', $parts);
			
			$class = 'Edge_Images\Edge_Providers\\' . $class_name;
		}

		self::$provider_class_cache[$cache_key] = $class;
		return $class;
	}
} 