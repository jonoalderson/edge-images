/**
 * Bricks Builder integration.
 *
 * Handles integration with the Bricks Builder theme system.
 * Specifically:
 * - Disables SVG transformation when Bricks is active
 * - Prevents SVG wrapping in picture elements
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      5.2.14
 */

namespace Edge_Images\Integrations\Bricks;

use Edge_Images\Integration;
use Edge_Images\Helpers;

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
		
		// Disable transformation for SVGs.
		add_filter('edge_images_should_transform', [$this, 'maybe_disable_svg_transform'], 10, 2);
		add_filter('edge_images_should_wrap', [$this, 'maybe_disable_svg_wrap'], 10, 2);
	}

	/**
	 * Disable transformation for SVGs when Bricks is active.
	 *
	 * @since 5.2.14
	 * 
	 * @param bool   $should_transform Whether the image should be transformed.
	 * @param string $html            The image HTML.
	 * @return bool Whether the image should be transformed.
	 */
	public function maybe_disable_svg_transform(bool $should_transform, string $html): bool {
		// If transformation is already disabled, return early.
		if (!$should_transform) {
			return $should_transform;
		}

		// If this is an SVG, disable transformation.
		if (Helpers::is_svg($html)) {
			return false;
		}

		return $should_transform;
	}

	/**
	 * Disable wrapping for SVGs when Bricks is active.
	 *
	 * @since 5.2.14
	 * 
	 * @param bool   $should_wrap Whether the image should be wrapped.
	 * @param string $html       The image HTML.
	 * @return bool Whether the image should be wrapped.
	 */
	public function maybe_disable_svg_wrap(bool $should_wrap, string $html): bool {
		// If wrapping is already disabled, return early.
		if (!$should_wrap) {
			return $should_wrap;
		}

		// If this is an SVG, disable wrapping.
		if (Helpers::is_svg($html)) {
			return false;
		}

		return $should_wrap;
	}
} 