<?php 
/**
 * Relevanssi Live Ajax Search integration functionality.
 *
 * Handles integration with the Relevanssi Live Ajax Search plugin.
 * This integration:
 * - Transforms search result images
 * - Manages image optimization
 * - Handles AJAX responses
 * - Supports responsive images
 * - Maintains search performance
 * - Ensures proper image scaling
 * - Integrates with WordPress hooks
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Integrations\Relevanssi;

use Edge_Images\{Integration, Helpers, Features};
use Edge_Images\Features\Picture;

class Live_Ajax_Search extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * Sets up required filters for Relevanssi Live Ajax Search integration.
	 * This method:
	 * - Hooks into search result filters
	 * - Manages image transformation
	 * - Handles response modification
	 * - Ensures proper integration
	 *
	 * @since      4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		
		// Bail if we shouldn't be filtering
		if (!$this->should_filter()) {
			return;
		}

		add_filter('relevanssi_live_search_post_content', [$this, 'transform_search_content'], 10, 2);
	}

	/**
	 * Transform search result content.
	 *
	 * Processes and transforms images in search results.
	 * This method:
	 * - Processes HTML content
	 * - Transforms image URLs
	 * - Handles responsive images
	 * - Maintains aspect ratios
	 * - Supports picture elements
	 * - Ensures proper scaling
	 *
	 * @since      4.5.0
	 * 
	 * @param  string $content The search result content to transform.
	 * @param  array  $post    The post object data.
	 * @return string         The transformed content.
	 */
	public function transform_search_content(string $content, array $post): string {
		// Skip if content is empty
		if (empty($content)) {
			return $content;
		}

		// Create HTML processor
		$processor = new \WP_HTML_Tag_Processor($content);

		// Track if we made any changes
		$made_changes = false;

		// Process all img tags
		while ($processor->next_tag('img')) {
			// Get current src
			$src = $processor->get_attribute('src');
			if (!$src || !Helpers::is_local_url($src)) {
				continue;
			}

			// Get dimensions
			$width = $processor->get_attribute('width');
			$height = $processor->get_attribute('height');

			// Skip if we don't have both dimensions
			if (!$width || !$height) {
				continue;
			}

			// Transform the URL
			$transformed_url = Helpers::edge_src($src, [
				'width' => $width,
				'height' => $height,
				'fit' => 'cover',
				'quality' => 85,
			]);

			// Set the transformed URL
			$processor->set_attribute('src', $transformed_url);

			// Add our classes
			$classes = $processor->get_attribute('class') ?? '';
			$processor->set_attribute('class', trim($classes . ' edge-images-img edge-images-processed'));

			$made_changes = true;
		}

		// If we made changes, get the updated HTML
		if ($made_changes) {
			$content = $processor->get_updated_html();
		}

		return $content;
	}

	/**
	 * Get default settings for this integration.
	 *
	 * Provides default configuration settings for the Live Ajax Search integration.
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
			'edge_images_feature_relevanssi_live_search' => true,
		];
	}

	/**
	 * Check if this integration should filter.
	 *
	 * Determines if Live Ajax Search integration should be active.
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
	protected function should_filter(): bool {
		return Features::is_enabled('relevanssi_live_search') && Helpers::should_transform_images();
	}
} 