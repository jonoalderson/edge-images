<?php
/**
 * Integration manager functionality.
 *
 * Handles the registration and management of all plugin integrations.
 * Provides a central point for managing third-party plugin integrations.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.1.0
 */

namespace Edge_Images;

/**
 * Manages plugin integrations.
 *
 * @since 4.1.0
 */
class Integration_Manager {

	/**
	 * Available integrations.
	 *
	 * @since 4.1.0
	 * @var array<string,array>
	 */
	private static array $integrations = [
		'yoast-seo' => [
			'check'  => 'WPSEO_VERSION',
			'type'   => 'constant',
			'name'   => 'Yoast SEO',
			'classes' => [
				'Integrations\Yoast_SEO\Schema_Images',
				'Integrations\Yoast_SEO\Social_Images',
				'Integrations\Yoast_SEO\XML_Sitemaps',
			],
		],
		'relevanssi-live-search' => [
			'check'   => 'Relevanssi_Live_Search',
			'type'    => 'class',
			'name'    => 'Relevanssi Live Ajax Search',
			'classes' => [
				'Integrations\Relevanssi\Live_Ajax_Search',
			],
		],
		'enable-media-replace' => [
			'class' => Integrations\Enable_Media_Replace\Enable_Media_Replace::class,
			'check' => 'EMR_VERSION',
			'type' => 'constant',
			'name' => 'Enable Media Replace',
			'classes' => [
				'Integrations\Enable_Media_Replace\Enable_Media_Replace',
			],
		],
	];

	/**
	 * Get all available integrations.
	 *
	 * @since 4.1.0
	 * 
	 * @return array<string,array>
	 */
	public static function get_integrations() : array {
		return self::$integrations;
	}

	/**
	 * Optimize integration loading.
	 */
	private static $loaded_integrations = [];

	/**
	 * Register all available integrations.
	 *
	 * Checks for active plugins and registers their integrations
	 * if the required plugin is present.
	 *
	 * @since 4.1.0
	 * 
	 * @return void
	 */
	public static function register(): void {

		// Skip if no integrations needed
		if (!Helpers::should_transform_images()) {
			return;
		}

		// Load integrations (only once)
		foreach (self::$integrations as $integration => $config) {
			if (!isset(self::$loaded_integrations[$integration])) {
				self::maybe_register_integration($integration, $config);
				self::$loaded_integrations[$integration] = true;
			}
		}
	}

	/**
	 * Register a specific integration if requirements are met.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $integration The integration identifier.
	 * @param array  $config      The integration configuration.
	 * @return void
	 */
	private static function maybe_register_integration( string $integration, array $config ): void {
		// Check if the integration requirements are met
		if ( ! self::check_integration_requirements( $config ) ) {
			return;
		}

		// Register each integration class
		foreach ( $config['classes'] as $class ) {
			$full_class = __NAMESPACE__ . '\\' . $class;
			if ( class_exists( $full_class ) ) {
				$full_class::register();
			}
		}
	}

	/**
	 * Check if integration requirements are met.
	 *
	 * @since 4.1.0
	 * 
	 * @param array $config The integration configuration.
	 * @return bool Whether requirements are met.
	 */
	private static function check_integration_requirements( array $config ): bool {
		$type = $config['type'] ?? 'constant';
		$check = $config['check'] ?? '';

		switch ( $type ) {
			case 'constant':
				return defined( $check );
			case 'class':
				return class_exists( $check );
			case 'function':
				return function_exists( $check );
			case 'callback':
				return is_callable( $check ) && call_user_func( $check );
			default:
				return false;
		}
	}

	/**
	 * Get all registered integrations.
	 *
	 * Returns an array of all available integrations and their status.
	 * Useful for admin interfaces or debugging.
	 *
	 * @since 4.1.0
	 * 
	 * @return array<string,array> Array of integrations and their status.
	 */
	public static function get_registered_integrations(): array {
		$registered = [];

		foreach ( self::$integrations as $integration => $config ) {
			$registered[$integration] = [
				'active'   => self::check_integration_requirements( $config ),
				'classes'  => $config['classes'],
			];
		}

		return $registered;
	}

	/**
	 * Check if a specific integration is active.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $integration The integration identifier.
	 * @return bool Whether the integration is active.
	 */
	public static function is_enabled( string $integration ): bool {
		if ( ! isset( self::$integrations[$integration] ) ) {
			return false;
		}

		return self::check_integration_requirements( self::$integrations[$integration] );
	}

	/**
	 * Get human-readable integration name.
	 *
	 * @since 4.2.0
	 * 
	 * @param string $integration_id The integration identifier.
	 * @return string The formatted integration name.
	 */
	public static function get_name( string $integration_id ): string {
		return self::$integrations[$integration_id]['name'] ?? ucwords( str_replace( '-', ' ', $integration_id ) );
	}
} 