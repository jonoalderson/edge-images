<?php
/**
 * Settings management functionality.
 *
 * Handles the management of plugin settings, including:
 * - Settings registration and initialization
 * - Settings retrieval and caching
 * - Option value validation
 * - Default value management
 * - Cache invalidation
 * - WordPress Settings API integration
 * - Provider configuration
 * - Image dimension settings
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

class Settings {

	/**
	 * Option group name for settings.
	 *
	 * Defines the group name used when registering settings
	 * with the WordPress Settings API. All plugin settings
	 * are registered under this group for organization.
	 *
	 * @since      4.5.0
	 * @var string
	 */
	public const OPTION_GROUP = 'edge_images_settings';

	/**
	 * Provider option name.
	 *
	 * The option name used to store the selected edge provider.
	 * This setting determines which provider is used for
	 * image transformation and optimization.
	 *
	 * @since      4.5.0
	 * @var string
	 */
	public const PROVIDER_OPTION = 'edge_images_provider';

	/**
	 * Max width option name.
	 *
	 * The option name used to store the maximum image width.
	 * This setting controls the maximum width allowed for
	 * transformed images when content width is not set.
	 *
	 * @since      4.5.0
	 * @var string
	 */
	public const MAX_WIDTH_OPTION = 'edge_images_max_width';

	/**
	 * Cache for settings values.
	 *
	 * Stores retrieved option values to minimize database queries.
	 * The cache is cleared when settings are updated or when
	 * explicitly reset via reset_cache().
	 *
	 * @since      4.5.0
	 * @var array<string,mixed>
	 */
	private static array $cache = [];

	/**
	 * Get the max width setting.
	 *
	 * Retrieves the maximum width setting for image transformations.
	 * This method:
	 * - Returns the configured max width value
	 * - Provides a default of 800 pixels
	 * - Ensures integer return type
	 * - Uses cached values when available
	 * - Integrates with the WordPress options API
	 * - Supports filterable values
	 *
	 * @since      4.5.0
	 * 
	 * @return int The maximum width for images in pixels.
	 */
	public static function get_max_width(): int {
		return (int) self::get_option(self::MAX_WIDTH_OPTION, 800);
	}

	/**
	 * Get the current provider.
	 *
	 * Retrieves the configured edge provider identifier.
	 * This method:
	 * - Returns the current provider setting
	 * - Provides a default of 'none'
	 * - Ensures string return type
	 * - Uses cached values when available
	 * - Integrates with the WordPress options API
	 * - Supports filterable values
	 *
	 * @since      4.5.0
	 * 
	 * @return string The current provider identifier.
	 */
	public static function get_provider(): string {
		$provider = self::get_option(self::PROVIDER_OPTION, 'none');
		return (string) $provider;
	}

	/**
	 * Get a plugin option with caching.
	 *
	 * Retrieves and caches plugin option values.
	 * This method:
	 * - Checks the cache before querying the database
	 * - Stores retrieved values in memory
	 * - Supports default values
	 * - Integrates with WordPress options API
	 * - Minimizes database queries
	 * - Handles any option type
	 * - Maintains consistent return types
	 *
	 * @since      4.5.0
	 * 
	 * @param string $option  The option name to retrieve.
	 * @param mixed  $default Optional. Default value to return if option doesn't exist.
	 * @return mixed The option value or default if not found.
	 */
	public static function get_option(string $option, $default = false) {
		if (!isset(self::$cache[$option])) {
			self::$cache[$option] = get_option($option, $default);
		}
		return self::$cache[$option];
	}

	/**
	 * Reset the settings cache.
	 *
	 * Clears the internal settings cache.
	 * This method:
	 * - Empties the entire settings cache
	 * - Forces fresh option retrieval
	 * - Is called after settings updates
	 * - Ensures data consistency
	 * - Supports cache invalidation
	 * - Maintains memory efficiency
	 *
	 * @since      4.5.0
	 * 
	 * @return void
	 */
	public static function reset_cache(): void {
		self::$cache = [];
	}

	/**
	 * Register core settings.
	 *
	 * Registers all core plugin settings with WordPress.
	 * This method:
	 * - Registers the provider setting
	 * - Registers the max width setting
	 * - Sets up sanitization callbacks
	 * - Configures default values
	 * - Adds setting descriptions
	 * - Integrates with Settings API
	 * - Handles setting updates
	 * - Manages cache invalidation
	 *
	 * @since      4.5.0
	 * 
	 * @return void
	 */
	public static function register_settings(): void {
		// Register provider setting
		register_setting(
			self::OPTION_GROUP,
			self::PROVIDER_OPTION,
			[
				'type' => 'string',
				'description' => __('The edge provider to use for image optimization', 'edge-images'),
				'sanitize_callback' => [Provider_Registry::class, 'is_valid_provider'],
				'default' => 'none',
			]
		);

		// Register max width setting
		register_setting(
			self::OPTION_GROUP,
			self::MAX_WIDTH_OPTION,
			[
				'type' => 'integer',
				'description' => __('The maximum width for images when content width is not set', 'edge-images'),
				'sanitize_callback' => 'absint',
				'default' => 800,
				'update_callback' => [self::class, 'reset_cache'],
			]
		);
	}
} 