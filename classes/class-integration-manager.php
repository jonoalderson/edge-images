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
			'classes' => [
				'Integrations\Yoast_SEO\Schema_Images',
				'Integrations\Yoast_SEO\Social_Images',
				'Integrations\Yoast_SEO\XML_Sitemaps',
			],
		],
		// Add other integrations here as needed
	];

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
		foreach ( self::$integrations as $integration => $config ) {
			self::maybe_register_integration( $integration, $config );
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
		// Check if the required plugin/constant is present
		if ( ! defined( $config['check'] ) ) {
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
				'active'   => defined( $config['check'] ),
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
	public static function is_integration_active( string $integration ): bool {
		if ( ! isset( self::$integrations[$integration] ) ) {
			return false;
		}

		return defined( self::$integrations[$integration]['check'] );
	}
} 