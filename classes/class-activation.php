<?php
/**
 * Activation functionality.
 *
 * This class is responsible for initializing the plugin's default settings,
 * including core options, integration settings, and feature configurations.
 * It handles three types of defaults:
 * - Core plugin defaults (provider, settings, etc.)
 * - Integration-specific defaults
 * - Feature-specific defaults
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

class Activation {

	/**
	 * Core default option values.
	 *
	 * Default settings for the core plugin functionality.
	 * These values are used when the plugin is activated
	 * for the first time or if settings are missing.
	 *
	 * @since 4.5.0
	 * @var array<string,mixed>
	 */
	private static array $core_defaults = [
		'edge_images_provider'             => Providers::DEFAULT_PROVIDER,
		'edge_images_disable_picture_wrap' => false,
		'edge_images_max_width'           => 800,
		'edge_images_imgix_subdomain'     => '',
	];

	/**
	 * Run activation tasks.
	 *
	 * This method is called when the plugin is activated and
	 * triggers the initialization of default options.
	 *
	 * @since 4.5.0
	 * @return void
	 */
	public static function activate(): void {
		self::set_default_options();
	}

	/**
	 * Set default options if they don't exist.
	 *
	 * Initializes all plugin options with default values if they haven't
	 * been set already. This includes core options, integration options,
	 * and feature settings.
	 *
	 * @since 4.5.0
	 * @return void
	 */
	private static function set_default_options(): void {
		// Set core defaults
		foreach (self::$core_defaults as $option => $default) {
			if (get_option($option) === false) {
				update_option($option, $default);
			}
		}

		// Get and set integration defaults
		$integration_defaults = self::get_integration_defaults();
		foreach ($integration_defaults as $option => $default) {
			if (get_option($option) === false) {
				update_option($option, $default);
			}
		}

		// Set feature defaults
		$feature_defaults = Features::get_default_settings();
		foreach ($feature_defaults as $option => $default) {
			if (get_option($option) === false) {
				update_option($option, $default);
			}
		}
	}

	/**
	 * Get default settings from all integrations.
	 *
	 * Collects and combines default settings from all registered
	 * integrations that provide default settings through their
	 * get_default_settings() method.
	 *
	 * @since 4.5.0
	 * @return array<string,mixed> Combined default settings from all integrations.
	 */
	private static function get_integration_defaults(): array {
		$defaults = [];
		$integrations = Integrations::get_integrations();

		foreach ($integrations as $integration => $config) {
			foreach ($config['classes'] as $class) {
				$full_class = __NAMESPACE__ . '\\' . $class;
				if (class_exists($full_class) && method_exists($full_class, 'get_default_settings')) {
					$defaults = array_merge($defaults, $full_class::get_default_settings());
				}
			}
		}

		return $defaults;
	}

	/**
	 * Get default settings.
	 *
	 * @since 4.5.0
	 * 
	 * @return array<string,mixed> Default settings.
	 */
	public static function get_default_settings(): array {
		return array_merge(
			[
				'edge_images_provider' => '',
				'edge_images_imgix_subdomain' => '',
				'edge_images_feature_picture_wrap' => false,
			],
			Features::get_default_settings()
		);
	}

} 