<?php
/**
 * Block transformation management.
 *
 * Manages the transformation of blocks in Edge Images.
 * This class:
 * - Coordinates block transformations
 * - Manages block registration
 * - Routes block content
 * - Handles block types
 * - Ensures proper processing
 * - Maintains block integrity
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
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
	 * Register block handlers.
	 *
	 * @since 4.5.0
	 * @return void
	 */
	public static function register(): void {
		// Register block handlers
		self::$handlers = [
			'core/gallery' => new Blocks\Gallery(),
			'core/image' => new Blocks\Image(),
			'core/figure' => new Blocks\Figure(),
		];

		// Add filter for block content
		add_filter('render_block', [self::class, 'transform_block'], 5, 2);
	}

	/**
	 * Transform block content.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * @return string The transformed block content.
	 */
	public static function transform_block(string $block_content, array $block): string {
		// Skip if no images
		if (!str_contains($block_content, '<img')) {
			return $block_content;
		}

		// Get block name
		$block_name = $block['blockName'] ?? '';

		// If we have a specific handler for this block type, use it
		if ($block_name && isset(self::$handlers[$block_name])) {
			return self::$handlers[$block_name]->transform($block_content, $block);
		}

		// For unknown blocks with images, try each handler in order
		foreach (self::$handlers as $handler) {
			$transformed = $handler->transform($block_content, $block);
			if ($transformed !== $block_content) {
				return $transformed;
			}
		}

		return $block_content;
	}
}