<?php
/**
 * Rank Math Schema Images integration functionality.
 *
 * Handles integration with Rank Math's schema/structured data functionality.
 * This integration:
 * - Transforms schema image URLs
 * - Manages image optimization
 * - Handles multiple schema types
 * - Ensures proper dimensions
 * - Maintains schema compliance
 * - Integrates with WordPress hooks
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      5.2.14
 */

namespace Edge_Images\Integrations\Rank_Math;

use Edge_Images\{Integration, Helpers, Integrations, Settings, Features\Cache};

/**
 * Class Schema_Images
 */
class Schema_Images extends Integration {

	/**
	 * The default width for schema images.
	 *
	 * Standard width for schema images.
	 * This value ensures optimal display and schema compliance.
	 *
	 * @since      5.2.14
	 * @var        int
	 */
	private const SCHEMA_WIDTH = 1200;

	/**	
	 * The default height for schema images.
	 *
	 * Standard height for schema images.
	 * This value ensures optimal display and schema compliance.
	 *
	 * @since      5.2.14
	 * @var        int
	 */
	private const SCHEMA_HEIGHT = 675;

	/**
	 * Add integration-specific filters.
	 *
	 * Sets up required filters for Rank Math schema integration.
	 * This method:
	 * - Hooks into schema filters
	 * - Manages image transformation
	 * - Handles multiple schema types
	 * - Ensures proper integration
	 *
	 * @since      5.2.14
	 * 
	 * @return void
	 */
	protected function add_filters(): void {

		// Bail if we shouldn't be filtering
		if (!$this->should_filter()) {
			return;
		}

		// Transform schema images in JSON-LD output
		add_filter('rank_math/json_ld', [$this, 'transform_schema'], 1000, 2);
	}

	/**
	 * Transform schema data.
	 *
	 * Processes and transforms image URLs in schema data.
	 * This method:
	 * - Transforms image URLs
	 * - Handles multiple schema types
	 * - Ensures optimization
	 * - Maintains schema compliance
	 *
	 * @since      5.2.14
	 * 
	 * @param  array $data   The schema data.
	 * @param  object $jsonld The JSON-LD manager instance.
	 * @return array         The transformed schema data.
	 */
	public function transform_schema(array $data, $jsonld): array {

		// Bail if no data
		if (empty($data)) {
			return $data;
		}
		
		// Process each piece of schema data
		foreach ($data as $key => &$schema) {
			if (!is_array($schema)) {
				continue;
			}

			// Handle image URLs in various schema properties
			$this->process_schema_images($schema);
		}

		return $data;
	}

	/**
	 * Process schema images recursively.
	 *
	 * @since 5.2.14
	 * 
	 * @param array &$schema Schema data to process.
	 * @return void
	 */
	private function process_schema_images(array &$schema): void {
		foreach ($schema as $key => &$value) {
			// Handle nested arrays
			if (is_array($value)) {
				$this->process_schema_images($value);
				continue;
			}

			// Check if this is an image URL
			if (is_string($value) && $this->is_image_url($value)) {
				$value = $this->transform_schema_image($value);
			}
		}
	}

	/**
	 * Check if a URL is an image URL.
	 *
	 * @since 5.2.14
	 * 
	 * @param string $url URL to check.
	 * @return bool Whether this is an image URL.
	 */
	private function is_image_url(string $url): bool {
		$image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		$extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
		return in_array($extension, $image_extensions, true);
	}

	/**
	 * Transform schema image URL.
	 *
	 * Processes and transforms individual image URLs.
	 * This method:
	 * - Transforms image URLs
	 * - Handles image dimensions
	 * - Ensures optimization
	 * - Maintains quality
	 * - Supports multiple formats
	 *
	 * @since      5.2.14
	 * 
	 * @param  string $image_url The original image URL.
	 * @return string           The transformed image URL.
	 */
	private function transform_schema_image(string $image_url): string {
		// Skip if empty or not local
		if (empty($image_url) || !Helpers::is_local_url($image_url)) {
			return $image_url;
		}

		// Check cache first
		$cache_key = 'schema_' . md5($image_url);
		$cached_result = wp_cache_get($cache_key, Cache::CACHE_GROUP);
		if ($cached_result !== false) {
			return $cached_result;
		}

		// Get image ID from URL
		$image_id = Helpers::get_attachment_id_from_url($image_url);
		if (!$image_id) {
			wp_cache_set($cache_key, $image_url, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return $image_url;
		}

		// Get dimensions
		$dimensions = Helpers::get_image_dimensions($image_id);
		if (!$dimensions) {
			wp_cache_set($cache_key, $image_url, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return $image_url;
		}

		// Set default args
		$args = [
			'width' => self::SCHEMA_WIDTH,
			'height' => self::SCHEMA_HEIGHT,
			'fit' => 'cover',
			'quality' => 85,
		];

		// Transform the URL
		$transformed_url = Helpers::edge_src($image_url, $args);

		// Cache the result
		wp_cache_set($cache_key, $transformed_url, Cache::CACHE_GROUP, HOUR_IN_SECONDS);

		return $transformed_url;
	}

	/**
	 * Get default settings for this integration.
	 *
	 * Provides default configuration settings for the schema integration.
	 * This method:
	 * - Sets feature defaults
	 * - Configures options
	 * - Ensures consistency
	 * - Supports customization
	 *
	 * @since      5.2.14
	 * 
	 * @return array<string,mixed> Array of default feature settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_integration_rank_math_schema' => true,
		];
	}

	/**
	 * Check if this integration should filter.
	 *
	 * Determines if schema integration should be active.
	 * This method:
	 * - Checks feature status
	 * - Validates settings
	 * - Ensures requirements
	 * - Controls processing
	 *
	 * @since      5.2.14
	 * 
	 * @return bool True if integration should be active, false otherwise.
	 */
	protected function should_filter(): bool {
		// Check if Rank Math is installed and active
		if (!Integrations::is_enabled('rank-math')) {
			return false;
		}

		// Check if image transformation is enabled
		if (!Helpers::should_transform_images()) {
			return false;
		}

		// Check if this specific integration is enabled in settings
		return Settings::get_option('edge_images_integration_rank_math_schema', true);
	}

	/**
	 * Render integration settings.
	 *
	 * @since 5.3.0
	 * @return void
	 */
	public static function render_settings(): void {
		// Bail if user doesn't have sufficient permissions.
		if (!current_user_can('manage_options')) {
			return;
		}

		$schema_enabled = Settings::get_option('edge_images_integration_rank_math_schema', true);
		$social_enabled = Settings::get_option('edge_images_integration_rank_math_social', true);
		$sitemap_enabled = Settings::get_option('edge_images_integration_rank_math_xml', true);
		?>
		<fieldset>
			<p>
				<label>
					<input type="checkbox" 
						name="edge_images_integration_rank_math_schema" 
						value="1" 
						<?php checked($schema_enabled); ?>
					>
					<?php esc_html_e('Enable schema.org image optimization', 'edge-images'); ?>
				</label>
			</p>

			<p>
				<label>
					<input type="checkbox" 
						name="edge_images_integration_rank_math_social" 
						value="1" 
						<?php checked($social_enabled); ?>
					>
					<?php esc_html_e('Enable social media image optimization', 'edge-images'); ?>
				</label>
			</p>

			<p>
				<label>
					<input type="checkbox" 
						name="edge_images_integration_rank_math_xml" 
						value="1" 
						<?php checked($sitemap_enabled); ?>
					>
					<?php esc_html_e('Enable XML sitemap image optimization', 'edge-images'); ?>
				</label>
			</p>

			<p class="description">
				<?php esc_html_e('Edge Images can optimize images in Rank Math\'s schema.org output, social media tags, and XML sitemaps. Enable or disable these features as needed.', 'edge-images'); ?>
			</p>
		</fieldset>
		<?php
	}
} 