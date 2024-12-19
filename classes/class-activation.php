<?php
/**
 * Activation functionality.
 *
 * Handles plugin activation tasks like setting default options.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.0
 */

namespace Edge_Images;

/**
 * Handles plugin activation.
 *
 * @since 4.5.0
 */
class Activation {

	/**
	 * Core default option values.
	 *
	 * @since 4.5.0
	 * @var array<string,mixed>
	 */
	private static array $core_defaults = [
		'edge_images_provider'             => Provider_Registry::DEFAULT_PROVIDER,
		'edge_images_disable_picture_wrap' => false,
		'edge_images_max_width'           => 800,
		'edge_images_imgix_subdomain'     => '',
	];

	/**
	 * Run activation tasks.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	public static function activate(): void {
		self::set_default_options();
	}

	/**
	 * Set default options if they don't exist.
	 *
	 * @since 4.5.0
	 * 
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
		$feature_defaults = Feature_Manager::get_default_settings();
		foreach ($feature_defaults as $option => $default) {
			if (get_option($option) === false) {
				update_option($option, $default);
			}
		}
	}

	/**
	 * Get default settings from all integrations.
	 *
	 * @since 4.5.0
	 * 
	 * @return array<string,mixed> Combined default settings from all integrations.
	 */
	private static function get_integration_defaults(): array {
		$defaults = [];
		$integrations = Integration_Manager::get_integrations();

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

} 