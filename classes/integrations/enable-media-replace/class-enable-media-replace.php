<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images\Integrations\Enable_Media_Replace;

use Edge_Images\{Integration, Cache};

/**
 * Integration with Enable Media Replace plugin.
 */
class Enable_Media_Replace extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		add_action('enable-media-replace-upload-done', [Cache::class, 'purge_attachment'], 10, 3);
	}
} 