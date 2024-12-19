<?php
/**
 * Settings management functionality.
 *
 * Handles retrieval and caching of plugin settings.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.0
 */

namespace Edge_Images;

/**
 * Manages plugin settings.
 *
 * @since 4.5.0
 */
class Settings {

	/**
	 * Option group name for settings.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	public const OPTION_GROUP = 'edge_images_settings';

	/**
	 * Provider option name.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	public const PROVIDER_OPTION = 'edge_images_provider';

	/**
	 * Max width option name.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	public const MAX_WIDTH_OPTION = 'edge_images_max_width';

	/**
	 * Cache for settings values.
	 *
	 * @since 4.5.0
	 * @var array<string,mixed>
	 */
	private static array $cache = [];

	/**
	 * Get the max width setting.
	 *
	 * @since 4.5.0
	 * 
	 * @return int The maximum width for images.
	 */
	public static function get_max_width(): int {
		return (int) self::get_option(self::MAX_WIDTH_OPTION, 800);
	}

	/**
	 * Get the current provider.
	 *
	 * @since 4.5.0
	 * 
	 * @return string The current provider ID.
	 */
	public static function get_provider(): string {
		$provider = self::get_option(self::PROVIDER_OPTION, 'none');
		return (string) $provider;
	}

	/**
	 * Get a plugin option with caching.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $option  The option name.
	 * @param mixed  $default The default value.
	 * @return mixed The option value.
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
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	public static function reset_cache(): void {
		self::$cache = [];
	}

	/**
	 * Register core settings.
	 *
	 * @since 4.5.0
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