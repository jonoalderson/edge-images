<?php
/**
 * Base integration class.
 *
 * Provides common functionality for all plugin integrations.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.0
 */

namespace Edge_Images;

/**
 * Abstract base class for plugin integrations.
 *
 * @since 4.5.0
 */
abstract class Integration {

	/**
	 * Whether the integration has been registered.
	 *
	 * @since 4.5.0
	 * @var array<string,bool>
	 */
	private static array $registered_integrations = [];

	/**
	 * Cached result of should_filter check.
	 *
	 * @since 4.5.0
	 * @var array<string,bool|null>
	 */
	private static array $should_filter_cache = [];

	/**
	 * Register the integration.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	public static function register(): void {
		$instance = new static();
		$instance->add_filters();
	}

	/**
	 * Add integration-specific filters.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	abstract protected function add_filters(): void;

	/**
	 * Check if this integration should filter.
	 *
	 * @since 4.5.0
	 * 
	 * @return bool Whether the integration should filter.
	 */
	protected function should_filter(): bool {
		// Get the integration config.
		$integration_config = $this->get_integration_config();

		// Bail if we don't have a valid integration config.
		if (!$integration_config) {
			return false;
		}

		// Get the type and check value.
		$type = $integration_config['type'] ?? 'constant';
		$check = $integration_config['check'] ?? '';

		// Check if the integration is active.
		switch ($type) {
			case 'constant':
				$is_active = defined($check);
				break;
			case 'class':
				$is_active = class_exists($check);
				break;
			case 'function':
				$is_active = function_exists($check);
				break;
			case 'callback':
				$is_active = is_callable($check) && call_user_func($check);
				break;
			default:
				$is_active = false;
		}

		// Return true if the integration is active and the provider is configured.
		return $is_active && Helpers::is_provider_configured();
	}

	/**
	 * Get integration configuration from Integration_Manager.
	 *
	 * @since 4.5.0
	 * 
	 * @return array|null The integration configuration or null if not found.
	 */
	private function get_integration_config(): ?array {

		// Get the class name.
		$class_name = get_class($this);
		$namespace = 'Edge_Images\\Integrations\\';
		
		// Remove namespace prefix
		if (str_starts_with($class_name, $namespace)) {
			$class_name = substr($class_name, strlen($namespace));
		}

		// Convert class name to integration key
		$parts = explode('\\', $class_name);
		// Use the first two parts for Yoast_SEO namespace
		$integration_key = strtolower(str_replace('_', '-', $parts[0]));
		
		// Special handling for Yoast SEO classes
		if ($parts[0] === 'Yoast_SEO') {
			$integration_key = 'yoast-seo';
		}

		// Get the integrations.
		$integrations = Integration_Manager::get_integrations();

		// Return the integration config.
		return $integrations[$integration_key] ?? null;
	}

	/**
	 * Get default settings for this integration.
	 *
	 * @since 4.5.0
	 * 
	 * @return array<string,mixed> Default settings.
	 */
	public static function get_default_settings(): array {
		return [];
	}
} 