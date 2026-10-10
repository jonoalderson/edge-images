<?php
/**
 * Edge provider registry.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Registers and resolves edge providers.
 */
class Providers {

	/**
	 * Default provider.
	 */
	public const DEFAULT_PROVIDER = 'none';

	/**
	 * Provider class cache.
	 *
	 * @var array<string, string>
	 */
	private static array $provider_class_cache = [];

	/**
	 * Registered providers.
	 *
	 * @return array<string, string>
	 */
	public static function get_providers(): array {

		return [
			'none'                => __( 'None (Disabled)', 'edge-images' ),
			'cloudflare'          => __( 'Cloudflare', 'edge-images' ),
			'accelerated_domains' => __( 'Accelerated Domains', 'edge-images' ),
			'bunny'               => __( 'Bunny CDN', 'edge-images' ),
			'imgix'               => __( 'Imgix', 'edge-images' ),
			'imgproxy'            => __( 'Imgproxy', 'edge-images' ),
		];
	}

	/**
	 * Provider slugs.
	 *
	 * @return array<int, string>
	 */
	public static function get_provider_slugs(): array {

		return array_keys( self::get_providers() );
	}

	/**
	 * Whether a provider slug is valid.
	 *
	 * @param string $provider_name Provider.
	 * @return bool
	 */
	public static function is_valid_provider( string $provider_name ): bool {

		return in_array( strtolower( $provider_name ), array_map( 'strtolower', self::get_provider_slugs() ), true );
	}

	/**
	 * Resolve a provider class name.
	 *
	 * @param string $provider_name Provider.
	 * @return string
	 */
	public static function get_provider_class( string $provider_name ): string {

		$cache_key = strtolower( $provider_name );

		if ( isset( self::$provider_class_cache[ $cache_key ] ) ) {
			return self::$provider_class_cache[ $cache_key ];
		}

		if ( ! self::is_valid_provider( $provider_name ) ) {
			$class = Edge_Providers\None::class;
		} else {
			$parts      = explode( '_', $cache_key );
			$parts      = array_map( 'ucfirst', $parts );
			$class_name = implode( '_', $parts );
			$class      = 'Edge_Images\\Edge_Providers\\' . $class_name;
		}

		self::$provider_class_cache[ $cache_key ] = $class;

		return $class;
	}
}
