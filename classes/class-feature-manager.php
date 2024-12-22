<?php
/**
 * Feature management functionality.
 *
 * Handles the registration and management of plugin features.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.0
 */

namespace Edge_Images;

/**
 * Manages plugin features.
 *
 * @since 4.5.0
 */
class Feature_Manager {

	/**
	 * Available features.
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
			'option' => 'edge_images_enable_picture_wrap',
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

    public static function is_disabled(string $feature_id): bool {
        return !self::is_feature_enabled($feature_id);
    }

    public static function is_enabled(string $feature_id): bool {
        return self::is_feature_enabled($feature_id);
    }

	/**
	 * Get all available features.
	 *
	 * @since 4.5.0
	 * 
	 * @return array<string,array> Array of features and their configurations.
	 */
	public static function get_features(): array {
		return self::$features;
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $feature_id The feature identifier.
	 * @return bool Whether the feature is enabled.
	 */
	public static function is_feature_enabled(string $feature_id): bool {
		if (!isset(self::$features[$feature_id])) {
			return false;
		}

		$feature = self::$features[$feature_id];
		$option_name = $feature['option'] ?? "edge_images_feature_{$feature_id}";
		$value = get_option($option_name, $feature['default']);
		
		return $value;
	}

	/**
	 * Get default settings for all features.
	 *
	 * @since 4.5.0
	 * 
	 * @return array<string,bool> Array of feature settings.
	 */
	public static function get_default_settings(): array {
		$settings = [];
		foreach (self::$features as $id => $feature) {
			$settings["edge_images_feature_{$id}"] = $feature['default'];
		}
		return $settings;
	}

	/**
	 * Register enabled features.
	 *
	 * @since 4.5.0
	 * 
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