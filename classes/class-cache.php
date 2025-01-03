<?php
/**
 * Cache management functionality.
 *
 * Provides a caching system for transformed image HTML and metadata.
 * This class manages:
 * - HTML caching for transformed images
 * - Cache key generation and management
 * - Cache invalidation and purging
 * - Integration with WordPress cache system
 * - Post and attachment cache relationships
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

class Cache {

	/**
	 * Cache group for transformed image HTML.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	public const CACHE_GROUP = 'edge_images';

	/**
	 * Cache expiration time
	 *
	 * @since 4.5.0
	 * @var int 
	 */
	public const CACHE_EXPIRATION = DAY_IN_SECONDS * 30;

	/**
	 * Whether hooks have been registered.
	 *
	 * @since 4.5.0
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register cache busting hooks.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	public static function register(): void {
		if (self::$registered) {
			return;
		}

		$instance = new self();
		$instance->add_filters();

		self::$registered = true;
	}

	/**
	 * Add filters.
	 *
	 * @return void
	 */
	private function add_filters() : void {

		// Core post-related hooks
		add_action('save_post', [self::class, 'purge_post_images']);
		add_action('deleted_post', [self::class, 'purge_post_images']);
		add_action('attachment_updated', [self::class, 'purge_attachment'], 10, 3);
		add_action('delete_attachment', [self::class, 'purge_attachment']);

		// Settings update hook
		add_action('update_option', [self::class, 'maybe_purge_all'], 10, 3);

		// Allow integrations to register their own cache hooks
		do_action('edge_images_register_cache_hooks');
	}

	/**
	 * Get cached transformed image HTML.
	 *
	 * @since 4.5.0
	 * 
	 * @param int          $attachment_id The attachment ID.
	 * @param string|array $size         The image size.
	 * @param array        $attr         Optional. Additional attributes.
	 * @return string|false The cached HTML or false if not found.
	 */
	public static function get_image_html(int $attachment_id, $size, array $attr = []) {
		$cache_key = self::generate_cache_key($attachment_id, $size, $attr);
		return wp_cache_get($cache_key, self::CACHE_GROUP);
	}

	/**
	 * Cache transformed image HTML.
	 *
	 * @since 4.5.0
	 * 
	 * @param int          $attachment_id The attachment ID.
	 * @param string|array $size         The image size.
	 * @param array        $attr         Optional. Additional attributes.
	 * @param string       $html         The HTML to cache.
	 * @return bool Whether the value was cached.
	 */
	public static function set_image_html(int $attachment_id, $size, array $attr, string $html): bool {
		$cache_key = self::generate_cache_key($attachment_id, $size, $attr);
		
		// Store this cache key in the list of keys for this attachment
		$keys_cache_key = self::get_keys_cache_key($attachment_id);
		$cached_keys = wp_cache_get($keys_cache_key, self::CACHE_GROUP) ?: [];
		$cached_keys[] = $cache_key;
		wp_cache_set($keys_cache_key, array_unique($cached_keys), self::CACHE_GROUP, self::CACHE_EXPIRATION);
		
		return wp_cache_set($cache_key, $html, self::CACHE_GROUP, self::CACHE_EXPIRATION);
	}

	/**
	 * Purge all caches for a specific post.
	 *
	 * @since 4.5.0
	 * 
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public static function purge_post_images(int $post_id): void {
		// Skip revisions and autosaves
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}

		// Get all images associated with the post
		$images = self::get_post_images($post_id);
		
		// Purge cache for each image
		foreach ($images as $image_id) {
			self::purge_attachment($image_id);
		}

		// Allow integrations to purge their specific caches
		do_action('edge_images_purge_post_cache', $post_id);
	}

	/**
	 * Purge cache for a specific attachment.
	 *
	 * @since 4.5.0
	 * 
	 * @param int      $attachment_id The attachment ID.
	 * @param array    $data         Optional. New attachment data.
	 * @param array    $old_data     Optional. Old attachment data.
	 * @return void
	 */
	public static function purge_attachment(int $attachment_id, array $data = [], array $old_data = []): void {
		// Get all cached keys for this attachment
		$keys_cache_key = self::get_keys_cache_key($attachment_id);
		$cached_keys = wp_cache_get($keys_cache_key, self::CACHE_GROUP) ?: [];

		// Delete each cached variation
		foreach ($cached_keys as $key) {
			wp_cache_delete($key, self::CACHE_GROUP);
		}

		// Delete the keys cache itself
		wp_cache_delete($keys_cache_key, self::CACHE_GROUP);

		// Allow integrations to purge their specific caches
		do_action('edge_images_purge_attachment_cache', $attachment_id, $data, $old_data);
	}

	/**
	 * Generate cache key for an image.
	 *
	 * @since 4.5.0
	 * 
	 * @param int          $attachment_id The attachment ID.
	 * @param string|array $size         The image size.
	 * @param array        $attr         Optional. Additional attributes.
	 * @return string The cache key.
	 */
	private static function generate_cache_key(int $attachment_id, $size, array $attr = []): string {
		$key_parts = [
			'image',
			$attachment_id,
			is_array($size) ? implode('x', $size) : $size,
			md5(serialize($attr))
		];
		
		return implode('_', $key_parts);
	}

	/**
	 * Get the cache key for storing attachment keys.
	 *
	 * @since 4.5.0
	 * 
	 * @param int $attachment_id The attachment ID.
	 * @return string The cache key.
	 */
	private static function get_keys_cache_key(int $attachment_id): string {
		return 'keys_' . $attachment_id;
	}

	/**
	 * Get all image IDs associated with a post.
	 *
	 * @since 4.5.0
	 * 
	 * @param int $post_id The post ID.
	 * @return array Array of attachment IDs.
	 */
	private static function get_post_images(int $post_id): array {
		$images = [];

		// Get featured image
		if (has_post_thumbnail($post_id)) {
			$images[] = get_post_thumbnail_id($post_id);
		}

		// Get images from content
		$post = get_post($post_id);
		if ($post && !empty($post->post_content)) {
			$images = array_merge(
				$images,
				self::extract_image_ids_from_content($post->post_content)
			);
		}

		return array_unique(array_filter($images));
	}

	/**
	 * Extract image IDs from content.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $content The post content.
	 * @return array Array of attachment IDs.
	 */
	private static function extract_image_ids_from_content(string $content): array {
		$images = [];
		
		// Match wp-image-{ID} class
		if (preg_match_all('/wp-image-(\d+)/', $content, $matches)) {
			$images = array_merge($images, $matches[1]);
		}
		
		// Match image src URLs
		if (preg_match_all('/<img[^>]+src=([\'"])(.*?)\1/', $content, $matches)) {
			foreach ($matches[2] as $url) {
				$id = attachment_url_to_postid($url);
				if ($id) {
					$images[] = $id;
				}
			}
		}
		
		return array_map('intval', $images);
	}

	/**
	 * Purge all caches when relevant options are updated.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $option    Name of the updated option.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 * @return void
	 */
	public static function maybe_purge_all(string $option, $old_value, $value): void {
		
		// List of options that should trigger a cache purge
		$trigger_options = [
			'edge_images_provider',
			'edge_images_imgix_subdomain',
			'edge_images_bunny_subdomain',
			'edge_images_max_width',
			'edge_images_disable_picture_wrap',
		];

		if (in_array($option, $trigger_options, true)) {
			self::purge_all();
		}
	}

	/**
	 * Purge all Edge Images caches.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	public static function purge_all(): void {
		wp_cache_delete_group(self::CACHE_GROUP);

		// Allow integrations to purge their specific caches
		do_action('edge_images_purge_all_cache');
	}

} 