<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Registry of valid edge providers.
 */
class Provider_Registry {

	/**
	 * The default provider.
	 *
	 * @var string
	 */
	public const DEFAULT_PROVIDER = 'cloudflare';

	/**
	 * Get all registered providers
	 *
	 * @return array<string,string> Array of provider slugs and display names
	 */
	public static function get_providers(): array {
		return [
			'none'                => __( 'None (Disabled)', 'edge-images' ),
			'cloudflare'          => __( 'Cloudflare', 'edge-images' ),
			'accelerated_domains' => __( 'Accelerated Domains', 'edge-images' ),
		];
	}

	/**
	 * Get provider slugs
	 *
	 * @return array<string> Array of provider slugs
	 */
	public static function get_provider_slugs(): array {
		return array_keys( self::get_providers() );
	}

	/**
	 * Check if a provider is valid
	 *
	 * @param string $provider_name The provider name to check.
	 * @return bool Whether the provider is valid
	 */
	public static function is_valid_provider( string $provider_name ): bool {
		return in_array( $provider_name, self::get_provider_slugs(), true );
	}

	/**
	 * Get the provider class name
	 *
	 * @param string $provider_name The provider name.
	 * @return string The fully qualified class name
	 */
	public static function get_provider_class( string $provider_name ): string {
		if ( ! self::is_valid_provider( $provider_name ) ) {
			return Edge_Provider::class;
		}

		if ( $provider_name === 'none' ) {
			return Edge_Provider::class;
		}

		return 'Edge_Images\Edge_Providers\\' . ucfirst( $provider_name );
	}
} 