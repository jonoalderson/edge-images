<?php

namespace Edge_Images;

use Edge_Images\Handler;

/**
 * Provides helper methods.
 */
class Helpers {

	/**
	 * The plugin styles URL
	 *
	 * @var string
	 */
	public const STYLES_URL = EDGE_IMAGES_PLUGIN_URL . 'assets/css';

	/**
	 * The content width in pixels
	 *
	 * @var integer
	 */
	private const CONTENT_WIDTH = 600;

	/**
	 * The max width a default srcset val should ever be generated at, in pixels.
	 *
	 * @var integer
	 */
	private const WIDTH_MAX = 2400;

	/**
	 * The min width a default srcset val should be generated at, in pixels.
	 *
	 * @var integer
	 */
	private const WIDTH_MIN = 400;

	/**
	 * The width to increment default srcset vals.
	 *
	 * @var integer
	 */
	private const WIDTH_STEP = 100;

	/**
	 * The default image quality.
	 *
	 * @var integer
	 */
	private const IMAGE_QUALITY_HIGH = 85;

	/**
	 * The image quality for 1.5x images.
	 *
	 * @var integer
	 */
	private const IMAGE_QUALITY_MEDIUM = 75;

	/**
	 * The image quality for 2x images.
	 *
	 * @var integer
	 */
	private const IMAGE_QUALITY_LOW = 65;

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
			'f'        => ( isset( $args['format'] ) ) ? $args['format'] : 'auto',
			'q'        => ( isset( $args['quality'] ) ) ? $args['quality'] : self::get_image_quality_high(),
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
	 * Get the low image quality value
	 *
	 * @return int The image quality value
	 */
	public static function get_image_quality_low() : int {
		return apply_filters( 'cf_images_quality_low', self::IMAGE_QUALITY_LOW );
	}

	/**
	 * Get the medium image quality value
	 *
	 * @return int The image quality value
	 */
	public static function get_image_quality_medium() : int {
		return apply_filters( 'cf_images_quality_medium', self::IMAGE_QUALITY_MEDIUM );
	}


	/**
	 * Get the image step value
	 *
	 * @return int The image step value
	 */
	public static function get_width_step() : int {
		return apply_filters( 'cf_images_step_value', self::WIDTH_STEP );
	}

	/**
	 * Get the min width value
	 *
	 * @return int The image min width value
	 */
	public static function get_image_min_width() : int {
		return apply_filters( 'cf_images_min_width', self::WIDTH_MIN );
	}

	/**
	 * Get the max width value
	 *
	 * @return int The image max width value
	 */
	public static function get_image_max_width() : int {
		return apply_filters( 'cf_images_max_width', self::WIDTH_MAX );
	}

	/**
	 * Get the low image quality value
	 *
	 * @return int The image quality value
	 */
	public static function get_image_quality_high() : int {
		return apply_filters( 'cf_images_quality_high', self::IMAGE_QUALITY_HIGH );
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

		// Bail if we're in the admin or doing a REST request.
		if ( is_admin() || defined( 'REST_REQUEST' ) && REST_REQUEST ) {
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

	/**
	 * Gets the registered (and custom) system image sizes
	 *
	 * @return array The image sizes
	 */
	public static function get_wp_image_sizes() : array {
		global $_wp_additional_image_sizes;

		$default_image_sizes = get_intermediate_image_sizes();

		foreach ( $default_image_sizes as $size ) {
			$image_sizes[ $size ]['width']  = intval( get_option( "{$size}_size_w" ) );
			$image_sizes[ $size ]['height'] = intval( get_option( "{$size}_size_h" ) );
		}

		if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
			$image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
		}

		// Tidy up default WP nonsense.
		foreach ( $image_sizes as &$size ) {
			unset( $size['crop'] );
			if ( $size['height'] === 9999 ) {
				unset( $size['height'] );
			}
		}

		return $image_sizes;
	}

}
