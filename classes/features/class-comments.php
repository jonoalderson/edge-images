<?php
/**
 * Comment avatar transformation functionality.
 *
 * Handles the transformation of avatar images in comments.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.0
 */

namespace Edge_Images\Features;

use Edge_Images\{Helpers, Integration, Features\Picture};

/**
 * Handles comment avatar transformations.
 *
 * @since 4.5.0
 */
class Comments extends Integration {

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

	/**
	 * Transform avatar URLs.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $url         The URL of the avatar.
	 * @param mixed  $id_or_email The Gravatar to retrieve.
	 * @param array  $args        Arguments passed to get_avatar_data().
	 * @return string The transformed URL.
	 */
	public function transform_avatar_url(string $url, $id_or_email, array $args): string {
		// Skip if URL is empty or remote
		if (empty($url) || !Helpers::is_local_url($url)) {
			return $url;
		}

		// Get size from args
		$size = $args['size'] ?? 96;

		// Transform URL using edge provider
		return Helpers::edge_src($url, [
			'width' => $size,
			'height' => $size,
			'fit' => 'cover',
			'sharpen' => 1,
		]);
	}

	/**
	 * Transform avatar HTML.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $avatar      HTML for the user's avatar.
	 * @param mixed  $id_or_email The Gravatar to retrieve.
	 * @param int    $size        Square avatar width and height in pixels.
	 * @param string $default     URL for the default image.
	 * @param string $alt         Alternative text.
	 * @param array  $args        Arguments passed to get_avatar_data().
	 * @return string The transformed avatar HTML.
	 */
	public function transform_avatar_html(string $avatar, $id_or_email, int $size, string $default, string $alt, array $args): string {
		// Skip if avatar is empty
		if (empty($avatar)) {
			return $avatar;
		}

		// Create HTML processor
		$processor = new \WP_HTML_Tag_Processor($avatar);
		if (!$processor->next_tag('img')) {
			return $avatar;
		}

		// Skip if remote URL
		$src = $processor->get_attribute('src');
		if (!$src || !Helpers::is_local_url($src)) {
			return $avatar;
		}

		// Transform the image
		$transform_args = [
			'width' => $size,
			'height' => $size,
			'fit' => 'cover',
			'sharpen' => 1,
		];

		// Transform src
		$transformed_src = Helpers::edge_src($src, $transform_args);
		$processor->set_attribute('src', $transformed_src);

		// Transform srcset if it exists
		$srcset = $processor->get_attribute('srcset');
		if ($srcset) {
			// For 2x avatar, double the dimensions
			$transform_args['width'] = $size * 2;
			$transform_args['height'] = $size * 2;
			$transformed_2x = Helpers::edge_src($src, $transform_args);
			$processor->set_attribute('srcset', "$transformed_2x 2x");
		}

		// Get the transformed HTML
		$transformed = $processor->get_updated_html();

		// Create dimensions array for picture element
		$dimensions = [
			'width' => $size,
			'height' => $size,
		];

		// Create picture element using the Picture feature
		return Picture::create(
			$transformed,
			$dimensions,
			'avatar-picture'
		);
	}
} 