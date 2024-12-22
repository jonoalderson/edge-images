<?php
/**
 * Htaccess caching functionality.
 *
 * Handles the creation and management of .htaccess rules
 * for image caching.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.0
 */

namespace Edge_Images\Features;

use Edge_Images\{Integration, Feature_Manager};

/**
 * Handles htaccess caching configuration.
 *
 * @since 4.5.0
 */
class Htaccess_Cache extends Integration {

	/**
	 * The htaccess rules we want to add.
	 *
	 * @var string
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
	 * Register the integration
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
	protected function add_filters(): void {
		// Get the correct option name
		$option_name = 'edge_images_feature_htaccess_caching';
		
		// Hook into option updates
		add_action("update_option_{$option_name}", [$this, 'handle_option_update'], 10, 2);
		add_action("add_option_{$option_name}", [$this, 'handle_option_update'], 10, 2);

		// Add admin notices
		add_action('admin_notices', [$this, 'display_admin_notices']);

		// If the feature is enabled, ensure htaccess exists
		if (Feature_Manager::is_enabled('htaccess_caching')) {
			$upload_dir = wp_upload_dir();
			$htaccess_path = $upload_dir['basedir'] . '/.htaccess';
			
			if (!file_exists($htaccess_path) || !$this->has_our_rules($htaccess_path)) {
				$result = $this->create_htaccess();
				$this->store_admin_notice($result);
			}
		}
	}

	/**
	 * Check if htaccess file has our rules.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $htaccess_path Path to htaccess file.
	 * @return bool Whether the file has our rules.
	 */
	private function has_our_rules(string $htaccess_path): bool {
		if (!file_exists($htaccess_path)) {
			return false;
		}

		$content = file_get_contents($htaccess_path);
		return $content !== false && strpos($content, '# BEGIN Edge Images Cache Rules') !== false;
	}

	/**
	 * Handle the option being updated.
	 *
	 * @since 4.5.0
	 * 
	 * @param mixed $old_value The old option value.
	 * @param mixed $new_value The new option value.
	 * @return void
	 */
	public function handle_option_update($old_value, $new_value): void {
		// Only create/update htaccess when enabling the feature
		if ($new_value && (!$old_value || $old_value !== $new_value)) {
			$result = $this->create_htaccess();
			$this->store_admin_notice($result);
		} elseif (!$new_value && $old_value) {
			$result = $this->remove_htaccess();
			$this->store_admin_notice($result);
		}
	}

	/**
	 * Store an admin notice for later display.
	 *
	 * @since 4.5.0
	 * 
	 * @param array{success: bool, message: string} $result Operation result.
	 * @return void
	 */
	private function store_admin_notice(array $result): void {
		$notices = get_option('edge_images_admin_notices', []);
		$notices[] = [
			'type' => $result['success'] ? 'success' : 'error',
			'message' => $result['message'],
			'time' => time(),
		];
		update_option('edge_images_admin_notices', $notices);
	}

	/**
	 * Display admin notices.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	public function display_admin_notices(): void {
		$notices = get_option('edge_images_admin_notices', []);
		if (empty($notices)) {
			return;
		}

		foreach ($notices as $notice) {
			// Only show notices that are less than 5 minutes old
			if (time() - $notice['time'] > 300) {
				continue;
			}

			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr($notice['type']),
				esc_html($notice['message'])
			);
		}

		// Clear notices
		delete_option('edge_images_admin_notices');
	}

	/**
	 * Create or update the .htaccess file.
	 *
	 * @since 4.5.0
	 * 
	 * @return array{success: bool, message: string} Result of the operation.
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
	 * @since 4.5.0
	 * 
	 * @return array{success: bool, message: string} Result of the operation.
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

		// If content is empty after removal, delete the file
		if (trim($new_content) === '') {
			if (!unlink($htaccess_path)) {
				return [
					'success' => false,
					'message' => __('Failed to delete empty .htaccess file.', 'edge-images'),
				];
			}
			return [
				'success' => true,
				'message' => __('Removed .htaccess file as it was empty.', 'edge-images'),
			];
		}

		// Write the modified content back
		$result = file_put_contents($htaccess_path, $new_content);
		if ($result === false) {
			return [
				'success' => false,
				'message' => __('Failed to update .htaccess file.', 'edge-images'),
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
	 * @since 4.5.0
	 * 
	 * @return array<string,mixed> Default settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_feature_htaccess_caching' => false,
		];
	}
} 