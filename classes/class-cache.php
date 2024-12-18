<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Handles caching of transformed images.
 *
 * @since 4.5.0
 */
class Cache {

	/**
	 * Cache group for transformed image HTML.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	private const CACHE_GROUP = 'edge_images';

	/**
	 * Cache expiration time in seconds (24 hours).
	 *
	 * @since 4.5.0
	 * @var int 
	 */
	private const CACHE_EXPIRATION = DAY_IN_SECONDS;

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
	 * Purge cache for a specific attachment.
	 *
	 * @since 4.5.0
	 * 
	 * @param int $attachment_id The attachment ID.
	 * @return void
	 */
	public static function purge_attachment(int $attachment_id): void {
		// Get all cached keys for this attachment
		$keys_cache_key = self::get_keys_cache_key($attachment_id);
		$cached_keys = wp_cache_get($keys_cache_key, self::CACHE_GROUP) ?: [];

		// Delete each cached variation
		foreach ($cached_keys as $key) {
			wp_cache_delete($key, self::CACHE_GROUP);
		}

		// Delete the keys cache itself
		wp_cache_delete($keys_cache_key, self::CACHE_GROUP);
	}

	/**
	 * Purge cache for all images in a post.
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

} 