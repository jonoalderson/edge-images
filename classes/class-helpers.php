<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

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
	 * The plugin styles path
	 *
	 * @var string
	 */
	public const STYLES_PATH = EDGE_IMAGES_PLUGIN_DIR . '/assets/css';

	/**
	 * The plugin scripts path
	 *
	 * @var string
	 */
	public const SCRIPTS_PATH = EDGE_IMAGES_PLUGIN_DIR . '/assets/js';

	/**
	 * The cache group to use
	 *
	 * @var string
	 */
	public const CACHE_GROUP = 'edge_images';

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
	private const WIDTH_STEP = 200;

	/**
	 * The default image quality.
	 *
	 * @var integer
	 */
	private const IMAGE_QUALITY_DEFAULT = 85;

	/**
	 * Replace a SRC string with an edge version
	 *
	 * @param  string $src The src.
	 * @param  array  $args The args.
	 *
	 * @return string      The modified SRC attr.
	 */
	public static function edge_src( string $src, array $args ) : string {

		// Bail if we shouldn't transform the image based on the src.
		if ( ! self::should_transform_image_src() ) {
			return $src;
		}

		// Get the provider class (default to Cloudflare).
		$provider       = apply_filters( 'edge_images_provider', 'cloudflare' );
		$provider_class = 'Edge_Images\Edge_Providers\\' . ucfirst( $provider );

		// Bail if we can't find one.
		if ( ! class_exists( $provider_class ) ) {
			return $src;
		}

		// Get the image path from the URL.
		$url  = wp_parse_url( $src );
		$path = ( isset( $url['path'] ) ) ? $url['path'] : '';

		// Create our provider.
		$provider = new $provider_class( $path, $args );

		// Get the edge URL.
		$edge_url = $provider->get_edge_url();

		return $edge_url;
	}

	/**
	 * Normalize a size attribute to a string
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
			self::edge_src(
				esc_attr( $src ),
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
		$filtered_width = (int) apply_filters( 'edge_images_content_width', 0 );
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
	 * Get the default image quality value
	 *
	 * @return int The image quality value
	 */
	public static function get_image_quality_default() : int {
		return (int) apply_filters( 'edge_images_quality', self::IMAGE_QUALITY_DEFAULT );
	}

	/**
	 * Get the image step value
	 *
	 * @return int The image step value
	 */
	public static function get_width_step() : int {
		return (int) apply_filters( 'edge_images_step_value', self::WIDTH_STEP );
	}

	/**
	 * Get the min width value
	 *
	 * @return int The image min width value
	 */
	public static function get_image_min_width() : int {
		return (int) apply_filters( 'edge_images_min_width', self::WIDTH_MIN );
	}

	/**
	 * Get the max width value
	 *
	 * @return int The image max width value
	 */
	public static function get_image_max_width() : int {
		return (int) apply_filters( 'edge_images_max_width', self::WIDTH_MAX );
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

		// If we're debugging, always return true.
		if ( defined( 'EDGE_IMAGES_DEBUG_MODE' ) && EDGE_IMAGES_DEBUG_MODE === true ) {
			return true;
		}

		// Bail if the functionality has been disabled via a filter.
		$disabled = apply_filters( 'edge_images_disable', false );
		if ( $disabled === true ) {
			return false;
		}

		// Bail if we're in the admin, but not the post editor.
		if ( self::in_admin_but_not_post_editor() ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if we're in on an admin screen, but not the post editor
	 *
	 * @return boolean
	 */
	public static function in_admin_but_not_post_editor() : bool {
		// Check that we're able to get the current screen.
		if ( ! function_exists( 'get_current_screen' ) ) {
			require_once ABSPATH . '/wp-admin/includes/screen.php';
		}
		if ( is_admin() && get_current_screen() ) {
			if ( get_current_screen()->base !== 'post' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Decide if an image should be transformed
	 *
	 * @param int $id The image ID.
	 *
	 * @return bool
	 */
	public static function should_transform_image( int $id ) : bool {

		// If we're debugging, always return true.
		if ( defined( 'EDGE_IMAGES_DEBUG_MODE' ) && EDGE_IMAGES_DEBUG_MODE === true ) {
			return true;
		}

		// Bail if functionality has been disabled via a filter.
		if ( ! self::should_transform_images() ) {
			return false;
		}

		// Bail if this image ID has been filtered.
		$excluded_images = apply_filters( 'edge_images_exclude', array() );
		if ( $id && in_array( $id, $excluded_images, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determines if the src should be transformed.
	 *
	 * @return bool
	 */
	public static function should_transform_image_src() : bool {

		// If we're debugging, always return true.
		if ( defined( 'EDGE_IMAGES_DEBUG_MODE' ) && EDGE_IMAGES_DEBUG_MODE === true ) {
			return true;
		}

		// Don't normally transform the src if this is a local or dev environment.
		switch ( wp_get_environment_type() ) {
			case 'local':
			case 'development':
				return false;
		}

		return true;
	}

	/**
	 * Determines if an image is an SVG.
	 *
	 * @param string $src The image src value.
	 *
	 * @return bool
	 */
	public static function is_svg( string $src ) : bool {
		if ( strpos( $src, '.svg' ) !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * Get the domain to use as the edge rewrite base
	 *
	 * @return string The domain
	 */
	public static function get_rewrite_domain() : string {
		return apply_filters( 'edge_images_domain', get_site_url() );
	}

	/**
	 * Get the permitted <img> attributes
	 *
	 * @return array The attributes
	 */
	public static function allowed_img_attrs() : array {
		return array(
			'src'           => array(),
			'width'         => array(),
			'height'        => array(),
			'srcset'        => array(),
			'sizes'         => array(),
			'loading'       => array(),
			'decoding'      => array(),
			'class'         => array(),
			'alt'           => array(),
			'fetchpriority' => array(),
		);
	}

	/**
	 * Get the permitted container attributes
	 *
	 * @return array The attributes
	 */
	public static function allowed_container_attrs() : array {
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

		$cache_key   = 'image_sizes';
		$image_sizes = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( ! $image_sizes ) {

			$image_sizes = array();

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

			wp_cache_set( $cache_key, $image_sizes, self::CACHE_GROUP, HOUR_IN_SECONDS );

		}

		return $image_sizes;
	}

	/**
	 * Constrain the width of the image to the max content width
	 *
	 * @param  int $w The width.
	 * @param  int $h The height.
	 *
	 * @return array The width and height values
	 */
	public static function constrain_image_to_content_width( int $w, int $h ) : array {
		$content_width = self::get_content_width();

		// Calculate the ratio and constrain the width.
		if ( $w > $content_width ) {
			$ratio = $content_width / $w;
			$w     = $content_width;
			$h     = ceil( $h * $ratio );
		}

		return array(
			'width'  => $w,
			'height' => $h,
		);
	}

	/**
	 * Attempts to get an alt attribute from <img> element HTML
	 *
	 * @param  string $html The HTML containing the <img> element.
	 *
	 * @return string        The alt attribute
	 */
	public static function get_alt_from_img_el( string $html ) : string {
		$alt = '';
		$re  = '/(alt)=("[^"]*")/';
		preg_match_all( $re, $html, $matches );
		if ( ! $matches || empty( $matches ) || ! $matches[2][0] ) {
			return $alt;
		}
		return substr( $matches[2][0], 1, -1 );
	}

	/**
	 * Attempts to get an href attribute from a linked <img> element HTML
	 *
	 * @param  string $html The HTML containing the <img> element.
	 *
	 * @return string        The href value
	 */
	public static function get_link_from_img_el( string $html ) : string {
		$alt = '';
		$re  = '/(href)=("[^"]*")/';
		preg_match_all( $re, $html, $matches );
		if ( ! $matches || empty( $matches ) || ! isset( $matches[2][0] ) ) {
			return $alt;
		}
		return substr( $matches[2][0], 1, -1 );
	}

	/**
	 * Attempts to get an href attribute from an <img> element HTML
	 *
	 * @param  string $html The HTML containing the <img> element.
	 *
	 * @return string        The href value
	 */
	public static function get_caption_from_img_el( string $html ) : string {
		$caption = '';
		$re      = '/<figcaption>(.*?)<\/figcaption>/s';
		preg_match_all( $re, $html, $matches );
		if ( ! $matches || empty( $matches ) || ! isset( $matches[1][0] ) ) {
			return $caption;
		}
		return $matches[1][0];
	}

	/**
	 * Convert a size value into a height and width array
	 *
	 * @param  string|array $size The size to use or convert.
	 *
	 * @return array       The width and height
	 */
	public static function get_sizes_from_size( $size ) {

		// Set defaults based on a 4/3 ratio constrained by the content width.
		$width           = self::get_content_width();
		$sizes['width']  = $width;
		$sizes['height'] = $width * 0.75;

		switch ( true ) {
			// If the $size is an array, just use the values provided.
			case ( is_array( $size ) ):
				$sizes['width']  = $size[0];
				$sizes['height'] = $size[1];
				break;
			// If it's a string, go fetch the values for that image size.
			case ( is_string( $size ) ):
				$vals = self::get_wp_size_vals( $size );
				if ( ! $vals ) {
					break;
				}
				$sizes['width']  = $vals['width'];
				$sizes['height'] = $vals['height'];
				break;
		}

		return $sizes;
	}

	/**
	 * Returns an array with default properties.
	 *
	 * @return array Array with default properties.
	 */
	public static function get_default_image_attrs() : array {
		$width  = self::get_content_width();
		$height = $width * 0.75;
		$attrs  = array(
			'class'           => array( 'edge-images-img' ),
			'container-class' => array( 'edge-images-container' ),
			'layout'          => 'responsive',
			'fit'             => apply_filters( 'edge_images_fit', 'cover' ),
			'loading'         => apply_filters( 'edge_images_loading', 'lazy' ),
			'decoding'        => apply_filters( 'edge_images_decoding', 'async' ),
			'fetchpriority'   => apply_filters( 'edge_images_fetchpriority', 'low' ),
			'caption'         => false,
		);

		return $attrs;
	}

	/**
	 * Sanitize the image HTML
	 *
	 * @param  string $html The image HTML.
	 *
	 * @return string       The sanitized HTML
	 */
	public static function sanitize_image_html( string $html ) : string {
		$html = wp_kses(
			$html,
			array(
				'figure'     => self::allowed_container_attrs(),
				'picture'    => self::allowed_container_attrs(),
				'img'        => self::allowed_img_attrs(),
				'a'          => array( 'href' => array() ),
				'figcaption' => array(),
			)
		);
		return $html;
	}

	/**
	 * Get an attachment ID given a URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return false|int Attachment ID, or FALSE
	 */
	public static function get_attachment_id_from_url( $url ) {

		$attachment_id = 0;

		$dir = wp_upload_dir();

		if ( false !== strpos( $url, $dir['baseurl'] . '/' ) ) { // Is URL in uploads directory?
			$file = basename( $url );

			$query_args = array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'value'   => $file,
						'compare' => 'LIKE',
						'key'     => '_wp_attachment_metadata',
					),
				),
			);

			$query = new \WP_Query( $query_args );

			if ( ! $query->have_posts() ) {
				return false;
			}

			foreach ( $query->posts as $post_id ) {

				$meta = wp_get_attachment_metadata( $post_id );

				$original_file       = basename( $meta['file'] );
				$cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );

				if ( $original_file === $file || in_array( $file, $cropped_image_files, false ) ) {
					return $post_id;
				}
			}
		}

		return false;
	}




}
