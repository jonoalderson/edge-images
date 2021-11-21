<?php

namespace Yoast_CF_Images;

use Yoast_CF_Images\Handler;

/**
 * Provides helper methods.
 */
class Helpers {

	/**
	 * The plugin styles URL
	 *
	 * @var string
	 */
	const STYLES_URL = YOAST_CF_IMAGES_PLUGIN_PLUGIN_URL . 'assets/css';

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
	 * Replace a SRC string with a Cloudflared version
	 *
	 * @param  string $src The src.
	 * @param  array  $args The args.
	 *
	 * @return string      The modified SRC attr.
	 */
	public static function cf_src( string $src, array $args ) : string {

		$cf_properties = array(
			'width'    => ( isset( $args['width'] ) ) ? $args['width'] : self::get_content_width(),
			'fit'      => ( isset( $args['fit'] ) ) ? $args['fit'] : 'cover',
			'f'        => ( isset( $args['f'] ) ) ? $args['f'] : 'auto',
			'gravity'  => ( isset( $args['gravity'] ) ) ? $args['gravity'] : 'auto',
			'onerror'  => ( isset( $args['onerror'] ) ) ? $args['onerror'] : 'redirect',
			'metadata' => ( isset( $args['metadata'] ) ) ? $args['metadata'] : 'none',
		);

		// OPTIONAL: Height.
		if ( isset( $args['height'] ) ) {
			$cf_properties['height'] = $args['height'];
		}

		// OPTIONAL: Blur.
		if ( isset( $args['blur'] ) ) {
			$cf_properties['blur'] = $args['blur'];
		}

		// Sort our properties alphabetically by key.
		ksort( $cf_properties );

		// Hard-code the yoast.com domain (for now).
		$cf_prefix = self::get_rewrite_domain() . '/cdn-cgi/image/';
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
	 * Normaize a size attribute to a string
	 *
	 * @param  mixed $size The size.
	 *
	 * @return string      The normalized size
	 */
	public static function normalize_size_attr( $size ) : string {
		if ( is_array( $size ) ) {
			return implode( 'x', $size );
		}
		return $size;
	}

	/**
	 * Creates an srcset val from a src and dimensions
	 *
	 * @param string $src  The image src attr.
	 * @param array  $args    The args.
	 *
	 * @return string   The srcset value
	 */
	public static function create_srcset_val( string $src, $args ) : string {
		return sprintf(
			'%s %dw',
			self::cf_src(
				$src,
				$args
			),
			$args['width']
		);
	}

	/**
	 * Get the content width value
	 *
	 * @return int The content width value
	 */
	public static function get_content_width() : int {
		// See if there's a filtered width.
		$filtered_width = apply_filters( 'cf_images_content_width', 0 );
		if ( $filtered_width ) {
			return $filtered_width;
		}

		// Fall back to the WP content_width var, or our default.
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

	/**
	 * Determins if images should be transformed
	 *
	 * @return bool
	 */
	public static function should_transform_images() : bool {

		// Bail if we're in the admin.
		if ( is_admin() ) {
			return false;
		}

		// Bail if the functionality has been disabled via a filter.
		$disabled = apply_filters( 'cf_images_disable', false );
		if ( $disabled ) {
			return false;
		}

		return true;
	}

	/**
	 * Decide if an image should be transformed
	 *
	 * @param int $id The image ID.
	 *
	 * @return bool
	 */
	public static function should_transform_image( int $id ) : bool {

		// Bail if functionality has been disabled via a filter.
		if ( ! self::should_transform_images() ) {
			return false;
		}

		// Bail if this image ID has been filtered.
		$excluded_images = apply_filters( 'cf_images_exclude', array() );
		if ( $id && in_array( $id, $excluded_images, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the domain to use as the CF rewrite base
	 *
	 * @return string The domain
	 */
	public static function get_rewrite_domain() : string {
		$domain = apply_filters( 'cf_images_domain', false );
		if ( ! $domain ) {
			$domain = get_site_url();
		}
		return $domain;
	}

	/**
	 * Get the permitted <img> attributes
	 *
	 * @return array The attributes
	 */
	public static function allowed_img_attrs() : array {
		return array(
			'src'      => array(),
			'width'    => array(),
			'height'   => array(),
			'srcset'   => array(),
			'sizes'    => array(),
			'loading'  => array(),
			'decoding' => array(),
			'class'    => array(),
			'alt'      => array(),
		);
	}

	/**
	 * Get the permitted <picture> attributes
	 *
	 * @return array The attributes
	 */
	public static function allowed_picture_attrs() : array {
		return array(
			'style' => array(),
			'class' => array(),
		);
	}

}