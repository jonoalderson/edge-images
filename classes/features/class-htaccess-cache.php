<?php
/**
 * Htaccess caching functionality.
 *
 * Handles the creation and management of .htaccess rules for image caching.
 * This feature:
 * - Creates and manages .htaccess rules
 * - Configures browser caching for images
 * - Handles file permissions
 * - Manages cache settings
 * - Provides admin notifications
 * - Ensures proper file handling
 * - Supports multiple image types
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Features;

use Edge_Images\{Integration, Features};

class Htaccess_Cache extends Integration {

	/**
	 * The htaccess rules we want to add.
	 *
	 * Apache configuration rules for image caching.
	 * These rules:
	 * - Enable browser caching
	 * - Set cache duration
	 * - Support multiple formats
	 * - Use mod_expires
	 *
	 * @since      4.5.0
	 * @var        string
	 */
	private const HTACCESS_RULES = '
# BEGIN Edge Images Cache Rules
<IfModule mod_expires.c>
	ExpiresActive On
	ExpiresByType image/jpeg "access plus 1 year"
	ExpiresByType image/gif "access plus 1 year"
	ExpiresByType image/png "access plus 1 year"
	ExpiresByType image/webp "access plus 1 year"
</IfModule>
# END Edge Images Cache Rules';

	/**
	 * Add integration-specific filters.
	 *
	 * Sets up required filters and actions for htaccess caching.
	 * This method:
	 * - Adds option update handlers
	 * - Sets up admin notices
	 * - Checks file existence
	 * - Validates configuration
	 * - Ensures proper setup
	 *
	 * @since      4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		// Get the correct option name
		$option_name = 'edge_images_feature_htaccess_caching';
		
		// Always hook into option updates
		add_action("update_option_{$option_name}", [$this, 'handle_option_update'], 10, 2);
		add_action("add_option_{$option_name}", [$this, 'handle_option_update'], 10, 2);

		// Only check htaccess on initial load if the feature is enabled
		if (Features::is_enabled('htaccess_caching')) {
			$upload_dir = wp_upload_dir();
			$htaccess_path = $upload_dir['basedir'] . '/.htaccess';
			
			// Only create htaccess if it doesn't exist or doesn't have our rules
			if (!file_exists($htaccess_path)) {
				$result = $this->create_htaccess();
				$this->store_admin_notice($result);
			} elseif (!$this->has_our_rules($htaccess_path)) {
				$result = $this->create_htaccess();
				$this->store_admin_notice($result);
			}
		}
	}

	/**
	 * Check if this integration should filter.
	 *
	 * Determines if htaccess caching should be active.
	 * This method:
	 * - Checks feature status
	 * - Validates settings
	 * - Ensures requirements
	 * - Controls processing
	 *
	 * @since      4.5.0
	 * 
	 * @return bool True if integration should be active, false otherwise.
	 */
	protected function should_filter() : bool {
		return Features::is_enabled('htaccess_caching');
	}

	/**
	 * Check if htaccess file has our rules.
	 *
	 * Validates the presence of caching rules in .htaccess.
	 * This method:
	 * - Checks file existence
	 * - Reads file contents
	 * - Searches for rules
	 * - Validates configuration
	 *
	 * @since      4.5.0
	 * 
	 * @param  string $htaccess_path Full path to the .htaccess file.
	 * @return bool                 True if rules exist, false otherwise.
	 */
	private function has_our_rules(string $htaccess_path): bool {

		// Bail if the file doesn't exist
		if (!file_exists($htaccess_path)) {
			return false;
		}

		// Get the file contents
		$content = file_get_contents($htaccess_path);

		// Check if the rules are in the file
		return $content !== false && strpos($content, '# BEGIN Edge Images Cache Rules') !== false;
	}

	/**
	 * Handle the option being updated.
	 *
	 * Processes changes to the htaccess caching option.
	 * This method:
	 * - Handles option changes
	 * - Creates/removes rules
	 * - Updates configuration
	 * - Provides feedback
	 * - Ensures proper state
	 *
	 * @since      4.5.0
	 * 
	 * @param  mixed $old_value Previous option value.
	 * @param  mixed $new_value New option value.
	 * @return void
	 */
	public function handle_option_update($old_value, $new_value): void {
		// Convert values to boolean for comparison.
		$old_enabled = filter_var($old_value, FILTER_VALIDATE_BOOLEAN);
		$new_enabled = filter_var($new_value, FILTER_VALIDATE_BOOLEAN);

		// Only act if there's a change.
		if ($old_enabled === $new_enabled) {
			return;
		}

		// If enabling.
		if ($new_enabled) {
			$result = $this->create_htaccess();
			$this->store_admin_notice($result);
			return;
		}

		// If disabling.
		$result = $this->remove_htaccess();
		$this->store_admin_notice($result);
	}

	/**
	 * Store an admin notice for later display.
	 *
	 * @since      4.5.0
	 * 
	 * @param  array{success: bool, message: string} $result Operation result array.
	 * @return void
	 */
	private function store_admin_notice(array $result): void {
		add_action('admin_notices', function() use ($result) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr($result['success'] ? 'success' : 'error'),
				esc_html($result['message'])
			);
		});
	}

	/**
	 * Create or update the .htaccess file.
	 *
	 * Manages the creation and updating of .htaccess rules.
	 * This method:
	 * - Checks permissions
	 * - Validates content
	 * - Writes rules
	 * - Handles errors
	 * - Provides feedback
	 * - Ensures integrity
	 *
	 * @since      4.5.0
	 * 
	 * @return array{success: bool, message: string} Operation result array.
	 */
	private function create_htaccess(): array {
		$upload_dir = wp_upload_dir();
		$htaccess_path = $upload_dir['basedir'] . '/.htaccess';

		// Check if we have write permissions
		if (!is_writable($upload_dir['basedir'])) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Directory path */
					__('The uploads directory %s is not writable.', 'edge-images'),
					$upload_dir['basedir']
				),
			];
		}

		// If file exists, read it
		$current_content = '';
		if (file_exists($htaccess_path)) {
			$current_content = file_get_contents($htaccess_path);
			
			// Check if our rules are already there
			if (strpos($current_content, '# BEGIN Edge Images Cache Rules') !== false) {
				return [
					'success' => true,
					'message' => __('Cache rules already exist in .htaccess file.', 'edge-images'),
				];
			}

			// Append our rules
			$new_content = $current_content . "\n" . self::HTACCESS_RULES;
		} else {
			$new_content = self::HTACCESS_RULES;
		}

		// Write the file
		$result = file_put_contents($htaccess_path, $new_content);
		if ($result === false) {
			return [
				'success' => false,
				'message' => __('Failed to write .htaccess file.', 'edge-images'),
			];
		}

		return [
			'success' => true,
			'message' => __('Successfully added cache rules to .htaccess file.', 'edge-images'),
		];
	}

	/**
	 * Remove our rules from the .htaccess file.
	 *
	 * Manages the removal of caching rules from .htaccess.
	 * This method:
	 * - Validates file
	 * - Reads content
	 * - Removes rules
	 * - Updates file
	 * - Handles errors
	 * - Provides feedback
	 *
	 * @since      4.5.0
	 * 
	 * @return array{success: bool, message: string} Operation result array.
	 */
	private function remove_htaccess(): array {
		$upload_dir = wp_upload_dir();
		$htaccess_path = $upload_dir['basedir'] . '/.htaccess';

		// If file doesn't exist, nothing to do
		if (!file_exists($htaccess_path)) {
			return [
				'success' => true,
				'message' => __('.htaccess file does not exist.', 'edge-images'),
			];
		}

		// Read the current content
		$current_content = file_get_contents($htaccess_path);
		if ($current_content === false) {
			return [
				'success' => false,
				'message' => __('Failed to read .htaccess file.', 'edge-images'),
			];
		}

		// Remove our rules using regex
		$pattern = '/\s*# BEGIN Edge Images Cache Rules.*# END Edge Images Cache Rules\s*/s';
		$new_content = preg_replace($pattern, '', $current_content);

		// Write the file
		$result = file_put_contents($htaccess_path, $new_content);
		if ($result === false) {
			return [
				'success' => false,
				'message' => __('Failed to write .htaccess file.', 'edge-images'),
			];
		}

		return [
			'success' => true,
			'message' => __('Successfully removed cache rules from .htaccess file.', 'edge-images'),
		];
	}

	/**
	 * Get default settings for this integration.
	 *
	 * Provides default configuration settings for htaccess caching.
	 * This method:
	 * - Sets feature defaults
	 * - Configures options
	 * - Ensures consistency
	 * - Supports customization
	 *
	 * @since      4.5.0
	 * 
	 * @return array<string,mixed> Array of default feature settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_feature_htaccess_caching' => false,
		];
	}
} 