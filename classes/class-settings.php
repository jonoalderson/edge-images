<?php
/**
 * Settings management.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Plugin options helpers.
 */
class Settings {

	/**
	 * Option group.
	 */
	public const OPTION_GROUP = 'edge_images_settings';

	/**
	 * Provider option.
	 */
	public const PROVIDER_OPTION = 'edge_images_provider';

	/**
	 * Domain option.
	 */
	public const DOMAIN_OPTION = 'edge_images_domain';

	/**
	 * Localization option.
	 */
	public const LOCALIZE_OPTION = 'edge_images_localize';

	/**
	 * Option cache.
	 *
	 * @var array<string, mixed>
	 */
	private static array $cache = [];

	/**
	 * Get the current provider.
	 *
	 * @return string
	 */
	public static function get_provider(): string {

		return (string) self::get_option( self::PROVIDER_OPTION, 'none' );
	}

	/**
	 * Get the transformation domain.
	 *
	 * @return string
	 */
	public static function get_domain(): string {

		$domain = (string) self::get_option( self::DOMAIN_OPTION, '' );
		if ( '' === $domain ) {
			return '';
		}

		return rtrim( $domain, '/' );
	}

	/**
	 * Whether localization is enabled.
	 *
	 * @return bool
	 */
	public static function is_localization_enabled(): bool {

		return (bool) self::get_option( self::LOCALIZE_OPTION, false );
	}

	/**
	 * Get a cached option.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get_option( string $option, $default = false ) {

		if ( ! isset( self::$cache[ $option ] ) ) {
			self::$cache[ $option ] = \get_option( $option, $default );
		}

		return self::$cache[ $option ];
	}

	/**
	 * Reset the option cache.
	 *
	 * @return void
	 */
	public static function reset_cache(): void {

		self::$cache = [];
	}

	/**
	 * Register settings with WordPress.
	 *
	 * @return void
	 */
	public static function register_settings(): void {

		\register_setting(
			self::OPTION_GROUP,
			self::PROVIDER_OPTION,
			[
				'type'              => 'string',
				'description'       => __( 'The edge provider to use for image optimization', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_provider' ],
				'default'           => 'none',
			]
		);

		\register_setting(
			self::OPTION_GROUP,
			self::DOMAIN_OPTION,
			[
				'type'              => 'string',
				'description'       => __( 'The domain to use for transformed images. If empty, the site URL will be used.', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_domain' ],
				'default'           => '',
			]
		);

		\register_setting(
			self::OPTION_GROUP,
			self::LOCALIZE_OPTION,
			[
				'type'              => 'boolean',
				'description'       => __( 'Fetch transformed images and store local copies.', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_checkbox' ],
				'default'           => false,
			]
		);

		\register_setting(
			self::OPTION_GROUP,
			'edge_images_bunny_subdomain',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		\register_setting(
			self::OPTION_GROUP,
			'edge_images_imgix_subdomain',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		\register_setting(
			self::OPTION_GROUP,
			'edge_images_imgproxy_url',
			[
				'type'              => 'string',
				'sanitize_callback' => [ self::class, 'sanitize_domain' ],
				'default'           => '',
			]
		);

		foreach ( [ 'yoast_schema', 'yoast_social', 'yoast_xml', 'rank_math_schema', 'rank_math_social', 'rank_math_xml' ] as $key ) {
			\register_setting(
				self::OPTION_GROUP,
				'edge_images_integration_' . $key,
				[
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
					'default'           => true,
				]
			);
		}
	}

	/**
	 * Sanitize a checkbox to a boolean.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function sanitize_checkbox( $value ): bool {

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize provider.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function sanitize_provider( string $value ): string {

		$value = strtolower( trim( $value ) );

		return Providers::is_valid_provider( $value ) ? $value : 'none';
	}

	/**
	 * Sanitize a domain/URL.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function sanitize_domain( string $value ): string {

		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( ! preg_match( '~^(?:f|ht)tps?://~i', $value ) ) {
			$value = 'https://' . $value;
		}

		return \untrailingslashit( \esc_url_raw( $value ) );
	}
}
