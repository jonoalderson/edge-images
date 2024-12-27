<?php
/**
 * Feature functionality.
 *
 * Manages plugin features and their settings.
 * This class handles:
 * - Feature registration and management
 * - Feature activation and deactivation
 * - Feature configuration
 * - Feature status tracking
 * - Feature settings management
 * - Feature option handling
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

class Features {

	/**
	 * Available features configuration.
	 *
	 * Defines all available plugin features and their settings:
	 * - name: Display name of the feature
	 * - description: User-friendly description
	 * - class: PHP class that implements the feature
	 * - option: Optional custom option name for the feature
	 * - default: Default enabled/disabled state
	 *
	 * @since 4.5.0
	 * @var array<string,array>
	 */
	private static array $features = [
		'avatars' => [
			'name' => 'Avatars',
			'description' => 'Transform avatar images in comments and user profiles.',
			'class' => 'Features\Avatars',
			'default' => true,
		],
		'picture_wrap' => [
			'name' => 'Picture Element Wrapping',
			'description' => 'Wrap images in picture elements for better responsive behavior and aspect ratio handling.',
			'class' => 'Features\Picture',
			'default' => false,
		],
		'htaccess_caching' => [
			'name' => 'Browser Caching',
			'description' => 'Create .htaccess rules to enable long-term browser caching for images.',
			'class' => 'Features\Htaccess_Cache',
			'default' => false,
		],
	];

	/**
	 * Check if a feature is disabled.
	 *
	 * Convenience method that inverts is_feature_enabled().
	 *
	 * @since 4.5.0
	 * @param string $feature_id The feature identifier.
	 * @return bool Whether the feature is disabled.
	 */
	public static function is_disabled(string $feature_id): bool {
		return !self::is_feature_enabled($feature_id);
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * Alias for is_feature_enabled() for more natural reading.
	 *
	 * @since 4.5.0
	 * @param string $feature_id The feature identifier.
	 * @return bool Whether the feature is enabled.
	 */
	public static function is_enabled(string $feature_id): bool {
		return self::is_feature_enabled($feature_id);
	}

	/**
	 * Get all available features.
	 *
	 * Returns the complete list of registered features and their
	 * configuration settings.
	 *
	 * @since 4.5.0
	 * @return array<string,array> Array of feature configurations.
	 */
	public static function get_features(): array {
		return self::$features;
	}

	/**
	 * Check if a specific feature is enabled.
	 *
	 * Checks the feature's option in WordPress settings to determine
	 * if it's enabled. Falls back to the feature's default setting
	 * if no option is set.
	 *
	 * @since 4.5.0
	 * @param string $feature_id The feature identifier.
	 * @return bool Whether the feature is enabled.
	 */
	public static function is_feature_enabled(string $feature_id): bool {
		if (!isset(self::$features[$feature_id])) {
			return false;
		}

		$feature = self::$features[$feature_id];
		$option_name = $feature['option'] ?? "edge_images_feature_{$feature_id}";
		
		return (bool) get_option($option_name, $feature['default']);
	}

	/**
	 * Get default settings for all features.
	 *
	 * Returns an array of all feature options with their default values.
	 * Used during plugin activation to set initial feature states.
	 *
	 * @since 4.5.0
	 * @return array<string,bool> Array of feature defaults.
	 */
	public static function get_default_settings(): array {
		$defaults = [];
		foreach (self::$features as $id => $feature) {
			$option_name = $feature['option'] ?? "edge_images_feature_{$id}";
			$defaults[$option_name] = $feature['default'];
		}
		return $defaults;
	}

	/**
	 * Register and initialize enabled features.
	 *
	 * Loads and initializes all enabled feature classes. Each feature
	 * class is responsible for registering its own hooks and filters.
	 *
	 * @since 4.5.0
	 * @return void
	 */
	public static function register(): void {
		foreach (self::$features as $id => $feature) {
			if (self::is_feature_enabled($id)) {
				$class = __NAMESPACE__ . '\\' . $feature['class'];
				if (class_exists($class)) {
					$class::register();
				}
			}
		}
	}
} 