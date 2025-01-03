<?php
/**
 * Enable Media Replace integration functionality.
 *
 * Handles integration with the Enable Media Replace plugin.
 * This integration:
 * - Manages cache purging on media replacement
 * - Ensures image transformations stay current
 * - Maintains cache consistency
 * - Handles attachment updates
 * - Supports media library operations
 * - Integrates with WordPress hooks
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Integrations\Enable_Media_Replace;

use Edge_Images\{Integration, Cache};

class Enable_Media_Replace extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * Sets up required filters for Enable Media Replace integration.
	 * This method:
	 * - Hooks into media replacement events
	 * - Triggers cache purging
	 * - Maintains data consistency
	 * - Ensures proper cleanup
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

		add_action('enable-media-replace-upload-done', [Cache::class, 'purge_attachment'], 10, 3);
	}
} 