<?php
/**
 * Base integration class.
 *
 * Provides common functionality for all plugin integrations.
 * This abstract class handles:
 * - Integration registration and initialization
 * - Filter management and caching
 * - Integration configuration retrieval
 * - Default settings management
 * - Integration state tracking
 * - Namespace and class name handling
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

abstract class Integration {

	/**
	 * Whether the integration has been registered.
	 *
	 * Tracks registration status of integrations to prevent duplicate registration.
	 * Uses the integration class name as the array key and a boolean value
	 * to indicate registration status.
	 *
	 * @since      4.5.0
	 * @var array<string,bool>
	 */
	private static array $registered_integrations = [];

	/**
	 * Cached result of should_filter check.
	 *
	 * Stores the results of integration filter checks to avoid repeated processing.
	 * Uses the integration class name as the array key and a boolean or null value
	 * to indicate the filter status.
	 *
	 * @since      4.5.0
	 * @var array<string,bool|null>
	 */
	private static array $should_filter_cache = [];

	/**
	 * Register the integration.
	 *
	 * Initializes and registers an integration instance.
	 * This method:
	 * - Creates a new instance of the integration
	 * - Calls the integration's add_filters method
	 * - Handles static registration
	 * - Is called by the Integration Manager
	 * - Is the main entry point for integration setup
	 *
	 * @since      4.5.0
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
	 * Abstract method that must be implemented by each integration.
	 * This method should:
	 * - Add all necessary WordPress filters
	 * - Set up integration-specific hooks
	 * - Configure integration behavior
	 * - Initialize integration features
	 * - Handle integration-specific setup
	 *
	 * @since      4.5.0
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
	 * Get integration configuration.
	 *
	 * Gets the integration configuration for this class.
	 * This method:
	 * - Gets the class name
	 * - Retrieves configuration from Integrations
	 * - Returns configuration array
	 * - Handles missing configurations
	 * - Supports inheritance
	 * - Maintains consistency
	 *
	 * @since      4.5.0
	 * 
	 * @return array<string,mixed>|null The integration configuration or null if not found.
	 */
	protected function get_integration_config(): ?array {
		$class_name = basename(str_replace('\\', '/', get_class($this)));
		$integrations = Integrations::get_integrations();

		foreach ($integrations as $integration => $config) {
			if (in_array($class_name, $config['classes'], true)) {
				return $config;
			}
		}

		return null;
	}

	/**
	 * Get default settings for this integration.
	 *
	 * Returns the default settings array for the integration.
	 * This method:
	 * - Can be overridden by child classes
	 * - Returns an empty array by default
	 * - Should return key-value pairs of settings
	 * - Is used during plugin activation
	 * - Helps initialize integration options
	 *
	 * @since      4.5.0
	 * 
	 * @return array<string,mixed> Array of default settings for the integration.
	 */
	public static function get_default_settings(): array {
		return [];
	}
} 