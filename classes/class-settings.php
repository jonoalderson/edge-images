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
	 * Picture-wrapping option.
	 */
	public const PICTURE_OPTION = 'edge_images_picture';

	/**
	 * Pre-6.0 picture feature option.
	 *
	 * The Features screen stored “Picture Element Wrapping” under this key.
	 */
	public const LEGACY_PICTURE_OPTION = 'edge_images_feature_picture_wrap';

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
	 * Whether picture wrapping and its stylesheet are enabled.
	 *
	 * @return bool
	 */
	public static function is_picture_enabled(): bool {

		return (bool) self::get_option( self::PICTURE_OPTION, false );
	}

	/**
	 * Copy the pre-6.0 picture toggle onto the new option once.
	 *
	 * Runs when the new option has never been saved. A missing legacy
	 * option stays off. An explicit new value is left alone.
	 *
	 * @return void
	 */
	public static function migrate_legacy_picture_option(): void {

		if ( self::option_exists( self::PICTURE_OPTION ) ) {
			return;
		}

		$enabled = false;
		if ( self::option_exists( self::LEGACY_PICTURE_OPTION ) ) {
			$enabled = self::sanitize_checkbox( \get_option( self::LEGACY_PICTURE_OPTION ) );
		}

		\update_option( self::PICTURE_OPTION, $enabled );
		self::$cache[ self::PICTURE_OPTION ] = $enabled;
	}

	/**
	 * Whether an option row exists.
	 *
	 * A stored false is different from a missing option.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private static function option_exists( string $option ): bool {

		return 'edge-images-missing' !== \get_option( $option, 'edge-images-missing' );
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
			self::PICTURE_OPTION,
			[
				'type'              => 'boolean',
				'description'       => __( 'Wrap bare images in picture elements and load the layout stylesheet.', 'edge-images' ),
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
					'sanitize_callback' => [ self::class, 'sanitize_checkbox' ],
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
