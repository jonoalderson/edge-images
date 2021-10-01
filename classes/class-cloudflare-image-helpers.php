<?php

namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Handler as Handler;

/**
 * Provides helper methods.
 */
class Cloudflare_Image_Helpers {

	/**
	 * The plugin styles URL
	 *
	 * @var string
	 */
	const STYLES_URL = YOAST_CF_IMAGES_PLUGIN_PLUGIN_URL . 'assets/css';

	/**
	 * The Cloudflare host domain
	 *
	 * @var string
	 */
	const CF_HOST = 'https://yoast.com';

	/**
	 * The content width in pixels
	 *
	 * @var integer
	 */
	const CONTENT_WIDTH = 600;

	/**
	 * The min width a default srcset val should be generated at, in pixels.
	 *
	 * @var integer
	 */
	const MIN_WIDTH = 400;

	/**
	 * The max width a default srcset val should ever be generated at, in pixels.
	 *
	 * @var integer
	 */
	const WIDTH_MAX = 2400;

	/**
	 * The min width a default srcset val should be generated at, in pixels.
	 *
	 * @var integer
	 */
	const WIDTH_MIN = 400;

	/**
	 * The width to increment default srcset vals.
	 *
	 * @var integer
	 */
	const WIDTH_STEP = 100;

	/**
	 * Get the appropriate class for the image size
	 *
	 * @param  string $size The image size.
	 *
	 * @return string       The class name
	 */
	public static function get_image_class( $size ) : string {
		$image_base_class = 'Yoast_CF_Images';
		$default_class    = $image_base_class . '\\Cloudflare_Image';

		// Bail if this is a custom size.
		if ( is_array( $size ) ) {
			return $default_class;
		}

		$registered_sizes = apply_filters( 'cf_image_sizes', [] );

		var_dump($registered_sizes[$size]);

		$class = ( array_key_exists( $size, $registered_sizes ) ) ? $registered_sizes[$size] : $default_class;

		return $class;
	}

	/**
	 * Replace a SRC string with a Cloudflared version
	 *
	 * @param  string $src               The SRC attr.
	 * @param  int    $w                 The width in pixels.
	 * @param  int    $h                 The height in pixels.
	 * @param  string $fit               The fit method.
	 *
	 * @return string      The modified SRC attr.
	 */
	public static function cf_src( string $src, int $w, int $h = null, string $fit = 'contain' ) : string {
		$cf_properties = array(
			'width'   => $w,
			'fit'     => $fit,
			'f'       => 'auto',
			'gravity' => 'auto',
			'onerror' => 'redirect',
		);

		// Set a height if we have one.
		if ( $h ) {
			$cf_properties['height'] = $h;
		}

		// Sort our properties alphabetically by key.
		ksort( $cf_properties );

		// Hard-code the yoast.com domain (for now).
		$cf_prefix = 'https://yoast.com/cdn-cgi/image/';
		$cf_string = $cf_prefix . http_build_query(
			$cf_properties,
			'',
			'%2C'
		);

		// Get the path from the URL.
		$url  = wp_parse_url( $src );
		$path = ( isset( $url['path'] ) ) ? $url['path'] : '';

		return $cf_string . $path;
	}

	/**
	 * Creates an srcset val from a src and dimensions
	 *
	 * @param string $src  The image src attr.
	 * @param int    $w    The width in pixels.
	 * @param int    $h    The height in pixels.
	 *
	 * @return string   The srcset value
	 */
	public static function create_srcset_val( string $src, int $w, int $h = null ) : string {
		return sprintf(
			'%s %dw',
			self::cf_src( $src, $w, $h ),
			$w
		);
	}

	/**
	 * Get the content width value
	 *
	 * @return int The content width value
	 */
	public static function get_content_width() : int {
		global $content_width;
		if ( ! $content_width || $content_width > self::CONTENT_WIDTH ) {
			$content_width = self::CONTENT_WIDTH;
		}
		return $content_width;
	}

	/**
	 * Get the vals for a WP image size
	 *
	 * @param  string $size The size.
	 *
	 * @return false|array  The values
	 */
	public static function get_wp_size_vals( string $size ) {

		$vals = array();

		// Get our default image sizes.
		$default_image_sizes = get_intermediate_image_sizes();

		// Check the size is valid.
		if ( ! in_array( $size, $default_image_sizes, true ) ) {
			$size = 'large';
		}

		// Check if we have vlues for this size.
		$key = array_search( $size, $default_image_sizes, true );
		if ( $key === false ) {
			return false;
		}

		$vals = array(
			'width'  => intval( get_option( "{$default_image_sizes[$key]}_size_w" ) ),
			'height' => intval( get_option( "{$default_image_sizes[$key]}_size_h" ) ),
		);

		return $vals;
	}

	/**
	 * Normalize an image attr into an array of values
	 *
	 * @param  mixed $attr The attr to normalize.
	 *
	 * @return array       The array of values
	 */
	public static function normalize_attr_array( $attr ) : array {
		if ( ! $attr ) {
			return array();
		}
		if ( is_string( $attr ) ) {
			$attr = explode( ' ', $attr );
		}
		return array_unique( $attr );
	}

	/**
	 * Flatten an array of classes into a string
	 *
	 * @param  mixed $classes The classes.
	 *
	 * @return false|string The flattened classes
	 */
	public static function classes_array_to_string( $classes ) {
		if ( is_string( $classes ) ) {
			$classes = explode( ' ', $classes );
		}

		if ( is_array( $classes ) ) {
			$classes = array_map(
				function( $class ) {
					return sanitize_html_class( $class );
				},
				$classes
			);
			return implode( ' ', $classes );
		}

		return false;
	}

	/**
	 * Flatten an array of srcset values into a string
	 *
	 * @param  mixed $srcset The srcset values.
	 *
	 * @return false|string The flattened srcset string
	 */
	public static function srcset_array_to_string( $srcset ) {
		if ( is_string( $srcset ) ) {
			return $srcset;
		}

		if ( is_array( $srcset ) ) {
			return implode( ', ', $srcset );
		}

		return false;
	}


}
