<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images\Integrations\Enable_Media_Replace;

use Edge_Images\Handler;
use Edge_Images\Cache;

/**
 * Integration with Enable Media Replace plugin.
 */
class Enable_Media_Replace {

	/**
	 * Whether the integration has been registered.
	 *
	 * @since 4.5.0
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the integration.
	 *
	 * @return void
	 */
	public static function register(): void {
		
		// Prevent double registration.
		if (self::$registered) {
			return;
		}

		add_filter( 'enable-media-replace-upload-done', [ __CLASS__, 'purge_cache_after_replace' ], 10, 3 );

		self::$registered = true;
	}

	/**
	 * Purge image cache after media replacement.
	 *
	 * @param mixed $target  Unknown parameter from EMR.
	 * @param mixed $source  Unknown parameter from EMR.
	 * @param int   $post_id The attachment post ID.
	 * @return mixed The unmodified target parameter.
	 */
	public static function purge_cache_after_replace( $target, $source, int $post_id ): mixed {
		// Purge cache for this attachment
		Cache::purge_attachment( $post_id );

		// Return the target parameter unmodified
		return $target;
	}
} 