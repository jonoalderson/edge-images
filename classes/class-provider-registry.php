<?php
/**
 * Provider registry functionality.
 *
 * Manages the registration and validation of edge providers.
 * Acts as a central registry for all available edge provider implementations.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      1.0.0
 */

namespace Edge_Images;

/**
 * Registry of valid edge providers.
 *
 * @since 4.0.0
 */
class Provider_Registry {

	/**
	 * The default provider to use if none is configured.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public const DEFAULT_PROVIDER = 'cloudflare';

	/**
	 * Cache for provider class names.
	 *
	 * @var array<string,string>
	 */
	private static array $provider_class_cache = [];

	/**
	 * Get all registered providers.
	 *
	 * Returns an array of all available providers and their display names.
	 * Used primarily in the admin interface for provider selection.
	 *
	 * @since 4.0.0
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
	 * Used for validation and internal provider management.
	 *
	 * @since 4.0.0
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
	 *
	 * @since 4.0.0
	 * 
	 * @param string $provider_name The provider name to check.
	 * @return bool Whether the provider is valid.
	 */
	public static function is_valid_provider( string $provider_name ): bool {
		return in_array( $provider_name, self::get_provider_slugs(), true );
	}

	/**
	 * Get the provider class name with caching.
	 *
	 * @param string $provider_name The provider name.
	 * @return string The fully qualified class name.
	 */
	public static function get_provider_class( string $provider_name ): string {
		if (isset(self::$provider_class_cache[$provider_name])) {
			return self::$provider_class_cache[$provider_name];
		}

		if (!self::is_valid_provider($provider_name) || $provider_name === 'none') {
			$class = Edge_Provider::class;
		} else {
			$class = 'Edge_Images\Edge_Providers\\' . ucfirst($provider_name);
		}

		self::$provider_class_cache[$provider_name] = $class;
		return $class;
	}
} 