<?php 
/**
 * Bricks Builder integration.
 *
 * Handles integration with the Bricks Builder theme system.
 * Specifically:
 * - Prevents any transformation of SVG images
 * - Disables dimension enforcement for SVGs
 * - Maintains original SVG markup
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      5.2.14
 */

namespace Edge_Images\Integrations\Bricks;

use Edge_Images\Integration;
use Edge_Images\Helpers;
use Edge_Images\Integrations;

/**
 * Class Bricks
 */
class Bricks extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * @since 5.2.14
	 * @return void
	 */
	public function add_filters(): void {
		
		// Bail if we shouldn't be filtering
		if (!$this->should_filter()) {
			return;
		}

		// Add a filter to check for SVGs before any transformation
		add_filter('edge_images_disable_transform', [$this, 'maybe_skip_svg'], 0, 2);
	}

	/**
	 * Check if we should skip transforming an SVG image.
	 *
	 * @since 5.2.14
	 * 
	 * @param bool   $should_disable Whether transformation should be disabled.
	 * @param string $html          The image HTML to check.
	 * @return bool Whether transformation should be disabled.
	 */
	public function maybe_skip_svg(bool $should_disable, string $html): bool {
	
		// If already disabled, return early
		if ($should_disable) {
			return $should_disable;
		}

		// Get the img tag
		$img_html = Helpers::extract_img_tag($html);
		if (!$img_html) {
			return $should_disable;
		}

		// The extract_img_tag helper now returns a normalized tag, so we can use it directly
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			return $should_disable;
		}

		// Get the src
		$src = $processor->get_attribute('src');
		if (!$src) {
			return $should_disable;
		}

		// Check if this is an SVG
		if (Helpers::is_svg($src)) {
			return true;
		}

		return $should_disable;
	}

	/**
	 * Check if this integration should filter.
	 *
	 * Determines if Bricks integration should be active.
	 * This method:
	 * - Checks if Bricks is active
	 * - Validates settings
	 * - Ensures requirements
	 *
	 * @since 5.2.14
	 * 
	 * @return bool True if integration should be active, false otherwise.
	 */
	protected function should_filter(): bool {

		// Check if Bricks is installed and active
		if (!Integrations::is_enabled('bricks')) {
			return false;
		}

		// Check if image transformation is enabled
		if (!Helpers::should_transform_images()) {
			return false;
		}

		return true;
	}

} 