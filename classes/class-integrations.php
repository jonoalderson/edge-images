<?php
/**
 * Integration functionality.
 *
 * Manages plugin integrations with third-party plugins and themes.
 * This class handles:
 * - Integration registration and management
 * - Integration activation and deactivation
 * - Integration configuration
 * - Integration status tracking
 * - Integration name localization
 * - Integration class loading
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

class Integrations {

	/**
	 * Available integrations configuration.
	 *
	 * Defines all available plugin integrations and their settings.
	 * Each integration is configured with:
	 * - check: The identifier to verify integration availability
	 * - type: The type of check (constant, class, function, callback)
	 * - name: Display name of the integration
	 * - classes: Array of integration class names to load
	 *
	 * Example structure:
	 * [
	 *     'integration-id' => [
	 *         'check' => 'CONSTANT_NAME',
	 *         'type' => 'constant',
	 *         'name' => 'Integration Name',
	 *         'classes' => ['Class_Name'],
	 *     ]
	 * ]
	 *
	 * @since      4.1.0
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
		'rank-math' => [
			'check'  => 'RANK_MATH_VERSION',
			'type'   => 'constant',
			'name'   => 'Rank Math SEO',
			'classes' => [
				'Integrations\Rank_Math\Schema_Images',
				'Integrations\Rank_Math\Social_Images',
				'Integrations\Rank_Math\XML_Sitemaps',
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
		'bricks' => [
			'check' => 'BRICKS_VERSION',
			'type' => 'constant',
			'name' => 'Bricks Builder',
			'classes' => [
				'Integrations\Bricks\Bricks',
			],
		],
	];

	/**
	 * Get all available integrations.
	 *
	 * Returns the complete list of registered integrations and their configurations.
	 * This method:
	 * - Returns all integrations regardless of their status
	 * - Includes full configuration for each integration
	 * - Does not check integration requirements
	 * - Returns raw configuration array
	 * - Is used internally by other methods
	 *
	 * @since      4.1.0
	 * 
	 * @return array<string,array> Array of integration configurations.
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
	 * Initializes and loads all configured integrations that meet their requirements.
	 * This method:
	 * - Checks if image transformation is enabled
	 * - Loads each integration only once
	 * - Verifies integration requirements
	 * - Initializes integration classes
	 * - Tracks loaded integrations
	 * - Skips already loaded integrations
	 *
	 * @since      4.1.0
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
	 * Attempts to register an individual integration after verifying requirements.
	 * This method:
	 * - Checks integration requirements
	 * - Validates integration class existence
	 * - Handles namespaced class names
	 * - Initializes integration classes
	 * - Skips invalid or unavailable integrations
	 * - Processes all classes defined for the integration
	 *
	 * @since      4.1.0
	 * 
	 * @param string $integration The integration identifier to register.
	 * @param array  $config      The integration configuration array.
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
	 * Verifies that an integration's requirements are satisfied.
	 * This method checks different types of requirements:
	 * - constant: Checks if a PHP constant is defined
	 * - class: Checks if a PHP class exists
	 * - function: Checks if a PHP function exists
	 * - callback: Executes a callback function for custom checks
	 *
	 * The type of check is determined by the 'type' key in the config:
	 * - 'constant': Checks defined('CHECK_NAME')
	 * - 'class': Checks class_exists('Class_Name')
	 * - 'function': Checks function_exists('function_name')
	 * - 'callback': Executes the callback and checks return value
	 *
	 * @since      4.1.0
	 * 
	 * @param array $config The integration configuration to check.
	 * @return bool Whether all requirements are met.
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