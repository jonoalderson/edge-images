<?php
/**
 * Avatar transformation functionality.
 *
 * Handles the transformation of avatar images across the site.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.0
 */

namespace Edge_Images\Features;

use Edge_Images\{Helpers, Integration, Features\Picture};

/**
 * Handles avatar transformations.
 *
 * @since 4.5.0
 */
class Avatars extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		add_filter('get_avatar_url', [$this, 'transform_avatar_url'], 10, 3);
		add_filter('get_avatar', [$this, 'transform_avatar_html'], 10, 6);
	}

	// Rest of the class remains the same...
} 