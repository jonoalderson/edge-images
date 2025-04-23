<?php
/**
 * Block transformation management.
 *
 * Manages block-specific transformation logic in Edge Images.
 * This class:
 * - Provides block-specific handlers
 * - Manages block registration
 * - Routes content to appropriate handlers
 * - Maintains transformation rules
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

class Blocks {

	/**
	 * Block handlers.
	 *
	 * @since 4.5.0
	 * @var array<string,Block>
	 */
	private static array $handlers = [];

	/**
	 * Block patterns for identifying specific block types.
	 *
	 * @since 4.5.0
	 * @var array<string,string>
	 */
	private static array $block_patterns = [];

	/**
	 * Register block handlers.
	 *
	 * @since 4.5.0
	 * @return void
	 */
	public static function register(): void {
		// Register block handlers
		self::$handlers = [
			'gallery' => new Blocks\Gallery(),
			'block_image' => new Blocks\Image(),
			'block_image_with_link' => new Blocks\Image(),
		];

		// Register block patterns
		self::$block_patterns = [
			// Gallery pattern - matches the outer gallery wrapper and all nested figures
			'gallery'		         => '/<figure[^>]*\bwp-block-gallery\b[^>]*>(?:[^<]*|<(?!figure[^>]*>|\/figure>)[^<]*|<figure[^>]*>(?:[^<]*|<(?!figure[^>]*>|\/figure>)[^<]*)*<\/figure>)*<\/figure>/s',
			// Image patterns
			'block_image' 		     => '/<div[^>]*class="[^"]*\bwp-block-image\b[^"]*"[^>]*>\s*<figure[^>]*>.*?<\/figure>\s*<\/div>/s',
			'block_image_with_link'  => '/<div[^>]*class="[^"]*\bwp-block-image\b[^"]*"[^>]*>\s*<figure[^>]*>\s*<a[^>]*>.*?<\/a>\s*<\/figure>\s*<\/div>/s'
		];


	}

	/**
	 * Get a block handler by type.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $type The block type (e.g., 'gallery', 'image').
	 * @return Block|null The block handler or null if not found.
	 */
	public static function get_handler(string $type): ?Block {
		return self::$handlers[$type] ?? null;
	}

	/**
	 * Check if we have a handler for a block type.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $type The block type to check.
	 * @return bool Whether we have a handler for this block type.
	 */
	public static function has_handler(string $type): bool {
		return isset(self::$handlers[$type]);
	}

	/**
	 * Get all registered handlers.
	 *
	 * @since 4.5.0
	 * @return array<string,Block> Array of block handlers.
	 */
	public static function get_handlers(): array {
		return self::$handlers;
	}

	/**
	 * Get the pattern for a specific block type.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $type The block type.
	 * @return string|null The pattern or null if not found.
	 */
	public static function get_block_pattern(string $type): ?string {
		return self::$block_patterns[$type] ?? null;
	}

	/**
	 * Get all block patterns.
	 *
	 * @since 4.5.0
	 * @return array<string,string> Array of block patterns.
	 */
	public static function get_block_patterns(): array {
		return self::$block_patterns;
	}
}