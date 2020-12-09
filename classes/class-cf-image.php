<?php

namespace Yoast\Plugins\CF_Images;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes an Image component.
 */
class CF_Image extends Component {

	/**
	 * The attachment ID
	 *
	 * @var int
	 */
	public $id;

	/**
	 * The image's display ratio
	 *
	 * @var string
	 */
	public $aspect_ratio;

	/**
	 * The image's template file
	 *
	 * @var string
	 */
	public $template = 'image';

	/**
	 * The image's src attribute
	 *
	 * @var string
	 */
	public $src;

	/**
	 * The image's sources
	 *
	 * @var array
	 */
	public $sources;

	/**
	 * The height attribute
	 *
	 * @var int
	 */
	public $height;

	/**
	 * The width attribute
	 *
	 * @var int
	 */
	public $width;

	/**
	 * The alt attribute
	 *
	 * @var string
	 */
	public $alt;

	/**
	 * Classes to attach to the <picture> container
	 *
	 * @var array
	 */
	public $classes = array( 'cf_img' );

	/**
	 * The loading attribute.
	 *
	 * @var string
	 */
	public $loading = 'lazy';

	/**
	 * The decoding attribute
	 *
	 * @var string
	 */
	public $decoding = 'async';

	/**
	 * If the image should be responsive
	 *
	 * @var bool
	 */
	public $fixed = false;

	/**
	 * The srcset values
	 *
	 * @var array
	 */
	public $srcset;

	/**
	 * The sizes values
	 *
	 * @var array
	 */
	public $sizes;

	/**
	 * Construct the image
	 *
	 * @param int   $id The attachment ID.
	 * @param array $args Optional arguments.
	 * @param array $sizes Optional sizes.
	 */
	public function __construct( int $id, $args = array(), $sizes = array() ) {
		$this->set_id( $id );
		$this->init_defaults();
		$this->init_args( $args );
		if ( $this->get_fixed() ) {
			$this->init_fixed_srcset();
			$this->init_fixed_ratio();
		} else {
			$this->init_srcset();
		}
		$this->init_sizes( $sizes );
	}
	/**
	 * Init the defaults
	 *
	 * @return void
	 */
	private function init_defaults() : void {
		$this->init_sources();

		$src = $this->get_default_source();
		if ( ! $src ) {
			return;
		}
		$this->set_src( $src[0] );
		$this->set_aspect_ratio( $src[1] . '/' . $src[2] );
		$this->set_width( $src[1] );
		$this->set_height( $src[2] );
	}

	/**
	 * Init the sizes
	 *
	 * @param array $sizes The sizes values.
	 *
	 * @return self
	 */
	private function init_sizes( array $sizes ) : self {
		if ( ! $sizes ) {
			$sizes = array(
				array(
					false,
					'100w',
					false,
				),
			);
		}
		foreach ( $sizes as $k => $v ) {
			if ( ! isset( $v[1] ) ) {
				unset( $sizes[ $k ] );
			}
			if ( isset( $v[2] ) && $v[2] ) {
				unset( $sizes[ $k ][2] );
				if ( $this->get_fixed() ) {
					continue;
				}
				$this->add_srcset_members_from_size( $v[1] );
			}
		}
		$this->set_sizes( $sizes );
		return $this;
	}

	/**
	 * Add srcset members from size attributes with 'generate' set
	 *
	 * @param string $size The size.
	 */
	private function add_srcset_members_from_size( $size ) {
		if ( ! is_numeric( $size ) ) {
			if ( substr( $size, -2 ) !== 'px' ) {
				return false;
			}
		}
		$full_source = $this->get_full_source();
		if ( ! $full_source ) {
			return false;
		}
		$this->add_to_srcset(
			array(
				$full_source[0],
				$size,
			)
		);
	}

	/**
	 * Convert sizes values to a string
	 *
	 * @return false|string The size values string
	 */
	private function convert_sizes_to_string() {
		$sizes = $this->get_sizes();
		if ( ! $sizes ) {
			return false;
		}
		$values = array();
		foreach ( $sizes as $size ) {
			$values[] = implode( ' ', array_filter( $size ) );
		}
		return implode( ', ', $values );
	}

	/**
	 * Init the srcset attribute
	 *
	 * @return self
	 */
	private function init_srcset() : self {
		$sources = $this->get_sources();
		unset( $sources['full'] );
		$widths = array();
		foreach ( $sources as $src ) {
			if ( in_array( $src[1], $widths, true ) || in_array( $src[1] * 2, $widths, true ) ) {
				continue;
			}
			$this->add_to_srcset( $src );
			$widths[] = $src[1];
			$widths[] = $src[1] * 2;
		}
		return $this;
	}

	/**
	 * Init the srcset for a fixed image
	 *
	 * @return self
	 */
	private function init_fixed_srcset() : self {
		$src    = $this->get_full_source();
		$src[1] = $this->get_height();
		$src[2] = $this->get_width();
		$this->add_to_srcset( $src, ' 2x' );
		return $this;
	}

	/**
	 * Init the ratio for a fixed image
	 *
	 * @return self
	 */
	private function init_fixed_ratio() : self {
		$height = $this->get_height();
		$width  = $this->get_width();
		$this->set_aspect_ratio( $width . '/' . $height );
		return $this;
	}

	/**
	 * Add a srcset value
	 *
	 * @param array  $src An image source.
	 * @param string $format The format.
	 */
	private function add_to_srcset( $src, $format = 'w' ) {
		$srcset = $this->get_srcset();
		if ( ! $this->get_fixed() ) {
			$srcset[] = $this->convert_source_to_srcset( $src, 1, $format );
		}
		$srcset[] = $this->convert_source_to_srcset( $src, 2, $format );
		$this->set_srcset( $srcset );
	}

	/**
	 * Convert a source to a srcset member
	 *
	 * @param array  $src    An image source.
	 * @param int    $dpr    The DPR.
	 * @param string $format 'w' or '2x' (or 'nx').
	 */
	private function convert_source_to_srcset( $src, $dpr, $format ) {
		$original_src = $this->get_full_source();
		$string       = $this->convert_src_to_cf( $original_src[0], $src[1], $src[2], $dpr ) . ' ';
		if ( ! $this->is_fixed() ) {
			$string .= $src[1] * $dpr;
		}
		$string .= $format;
		return $string;
	}

	/**
	 * Set the srcset values
	 *
	 * @param array $srcset The srcset values.
	 */
	public function set_srcset( $srcset ) : self {
		$this->srcset = $srcset;
		return $this;
	}

	/**
	 * Get the srcset values
	 *
	 * @return false|array The srcset values
	 */
	protected function get_srcset() {
		if ( ! $this->has_srcset() ) {
			return false;
		}
		return $this->srcset;
	}

	/**
	 * Checks if srcset values are set
	 *
	 * @return bool
	 */
	private function has_srcset() : bool {
		if ( ! $this->srcset ) {
			return false;
		}
		return true;
	}

	/**
	 * Get the default source
	 *
	 * @return array The default source
	 */
	private function get_default_source() {
		$sources = $this->get_sources();
		if ( ! $sources || ! isset( $sources['large'] ) ) {
			return false;
		}
		return $sources['large'];
	}

	/**
	 * Get the full size source
	 *
	 * @return array The full size source
	 */
	private function get_full_source() {
		$sources = $this->get_sources();
		if ( ! $sources || ! isset( $sources['full'] ) ) {
			return false;
		}
		return $sources['full'];
	}

	/**
	 * Set values from $args
	 *
	 * @param array $args The optional construction arg.
	 */
	private function init_args( array $args ) : void {

		// Width.
		if ( isset( $args['width'] ) && is_integer( $args['width'] ) ) {
			$this->set_width( $args['width'] );
		}

		// Height.
		if ( isset( $args['height'] ) && is_integer( $args['height'] ) ) {
			$this->set_height( $args['height'] );
		}

		// Ratio.
		if ( isset( $args['ratio'] ) && is_string( $args['ratio'] ) ) {
			$this->set_aspect_ratio( $args['ratio'] );
		}

		// Classes.
		if ( isset( $args['classes'] ) ) {
			if ( ! is_array( $args['classes'] ) ) {
				$args['classes'] = array( $args['classes'] );
			}
			$this->set_classes( $args['classes'] );
		}

		// Alt.
		if ( isset( $args['alt'] ) && is_string( $args['alt'] ) ) {
			$this->set_alt( $args['alt'] );
		}

		// Loading.
		if ( isset( $args['loading'] ) && is_string( $args['loading'] ) ) {
			$this->set_loading( $args['loading'] );
		}

		// Fixed.
		if ( isset( $args['fixed'] ) && is_bool( $args['fixed'] ) ) {
			$this->set_fixed( $args['fixed'] );
		}

	}

	/**
	 * Converts a src attribute to run through Cloudflare
	 *
	 * @param string $src The src attribute.
	 * @param int    $h   The height in pixels.
	 * @param int    $w   The width in pixels.
	 * @param int    $dpr The DPR.
	 *
	 * @return string The modified src attribute.
	 */
	private function convert_src_to_cf( string $src, ?int $w, ?int $h, int $dpr = 1 ) {

		$params = array(
			'fit'     => 'cover',
			'f'       => 'auto',
			'onerror' => 'redirect',
			'dpr'     => $dpr,
		);

		// Set the width.
		if ( $w ) {
			$params['w'] = $w;
		}

		// Set the height.
		if ( $h ) {
			$params['h'] = $h;
		}

		$image_path = str_replace(
			array(
				get_site_url( null, '', 'http' ),
				get_site_url( null, '', 'https' ),
			),
			'',
			$src
		);

		$params = http_build_query( $params, '', ',' );
		$src    = '/cdn-cgi/image/' . $params . $image_path;
		return $src;
	}

	/**
	 * Set the src attribute
	 *
	 * @param string $src The src attribute.
	 *
	 * @return self
	 */
	private function set_src( string $src ) : self {
		$this->src = $src;
		return $this;
	}

	/**
	 * Set the height attribute
	 *
	 * @param int $height The height attribute.
	 *
	 * @return self
	 */
	private function set_height( int $height ) : self {
		$this->height = $height;
		return $this;
	}

	/**
	 * Set the width attribute
	 *
	 * @param int $width The width attribute.
	 *
	 * @return self
	 */
	private function set_width( int $width ) : self {
		$this->width = $width;
		return $this;
	}

	/**
	 * Get the width atttribute
	 *
	 * @return false|int The width attribute
	 */
	protected function get_width() {
		if ( ! $this->has_width() ) {
			return false;
		}
		return $this->width;
	}

	/**
	 * Checks if a width attribute is set
	 *
	 * @return bool
	 */
	private function has_width() : bool {
		if ( ! $this->width ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the container classes
	 *
	 * @param array $classes The classes.
	 *
	 * @return self
	 */
	private function set_classes( array $classes ) : self {
		$this->classes = $classes;
		return $this;
	}

	/**
	 * Get the container classes
	 *
	 * @return false|array The classes
	 */
	protected function get_classes() {
		if ( ! $this->has_classes() ) {
			return false;
		}
		return $this->classes;
	}

	/**
	 * Checks if any classes are set
	 *
	 * @return bool
	 */
	private function has_classes() : bool {
		if ( ! $this->classes || empty( $this->classes ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Add a container class
	 *
	 * @param string $class The class to add.
	 *
	 * @return self
	 */
	private function add_class( $class ) : self {
		$classes   = $this->get_classes();
		$classes[] = $class;
		$this->set_classes( $classes );
		return $this;
	}

	/**
	 * Remove a container class
	 *
	 * @param string $class The class to remove.
	 *
	 * @return self
	 */
	private function remove_class( $class ) : self {
		$classes = $this->get_classes();
		$key     = array_search( $class, $classes, true );
		if ( $key !== false ) {
			unset( $classes[ $key ] );
			$this->set_classes( $classes );
		}
		return $this;
	}

	/**
	 * Get the height atttribute
	 *
	 * @return false|int The height attribute
	 */
	protected function get_height() {
		if ( ! $this->has_height() ) {
			return false;
		}
		return $this->height;
	}

	/**
	 * Checks if a height attribute is set
	 *
	 * @return bool
	 */
	private function has_height() : bool {
		if ( ! $this->height ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the aspect ratio
	 *
	 * @param string $aspect_ratio The aspect ratio.
	 *
	 * @return self
	 */
	private function set_aspect_ratio( string $aspect_ratio ) : self {
		$this->aspect_ratio = $aspect_ratio;
		return $this;
	}

	/**
	 * Get the aspect ratio
	 *
	 * @return false|string The aspect ratio
	 */
	protected function get_aspect_ratio() {
		if ( ! $this->has_aspect_ratio() ) {
			return false;
		}
		return $this->aspect_ratio;
	}

	/**
	 * Checks if an aspect ratio is set
	 *
	 * @return bool
	 */
	private function has_aspect_ratio() : bool {
		if ( ! $this->aspect_ratio ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the loading attribute
	 *
	 * @param string $loading The loading attribute.
	 *
	 * @return self
	 */
	private function set_loading( string $loading ) : self {
		switch ( $loading ) {
			case 'lazy':
				$this->loading = 'lazy';
				$this->set_decoding( 'async' );
				break;
			case 'eager':
				$this->loading = 'eager';
				$this->set_decoding( 'sync' );
				break;
		}
		return $this;
	}

	/**
	 * Get the loading attribute
	 *
	 * @return false|string The loading attribute
	 */
	protected function get_loading() {
		if ( ! $this->has_loading() ) {
			return false;
		}
		return $this->loading;
	}

	/**
	 * Checks if a loading attribute is set
	 *
	 * @return bool
	 */
	private function has_loading() : bool {
		if ( ! $this->loading ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the decoding attribute
	 *
	 * @param string $decoding The decoding attribute.
	 *
	 * @return self
	 */
	private function set_decoding( string $decoding ) : self {
		switch ( $decoding ) {
			case 'async':
				$this->decoding = 'async';
				break;
			case 'sync':
				$this->decoding = 'sync';
				break;
		}
		return $this;
	}

	/**
	 * Get the decoding attribute
	 *
	 * @return false|string The decoding attribute
	 */
	protected function get_decoding() {
		if ( ! $this->has_decoding() ) {
			return false;
		}
		return $this->decoding;
	}

	/**
	 * Checks if an decoding attribute is set
	 *
	 * @return bool
	 */
	private function has_decoding() : bool {
		if ( ! $this->decoding ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the alt attribute
	 *
	 * @param string $alt The alt attribute.
	 *
	 * @return self
	 */
	private function set_alt( string $alt ) : self {
		$this->alt = $alt;
		return $this;
	}

	/**
	 * Get the alt attribute
	 *
	 * @return false|string The alt attribute
	 */
	protected function get_alt() {
		if ( ! $this->has_alt() ) {
			return false;
		}
		return $this->alt;
	}

	/**
	 * Checks if an alt attribute is set
	 *
	 * @return bool
	 */
	private function has_alt() : bool {
		if ( ! $this->alt ) {
			return false;
		}
		return true;
	}

	/**
	 * Set whether the image is 'fixed'
	 *
	 * @param bool $fixed Whether the image is 'fixed'.
	 *
	 * @return self
	 */
	private function set_fixed( bool $fixed ) : self {
		$this->fixed = $fixed;
		if ( $fixed ) {
			$this->set_template( 'image-fixed' );
		}
		return $this;
	}

	/**
	 * Get whether the image is 'fixed'
	 *
	 * @return bool
	 */
	protected function get_fixed() {
		return $this->fixed;
	}

	/**
	 * A shortcut to get_fixed()
	 *
	 * @return boolean
	 */
	protected function is_fixed() {
		return $this->get_fixed();
	}

	/**
	 * Get the src attribute
	 *
	 * @return false|string The src attribute
	 */
	protected function get_src() {
		if ( ! $this->has_src() ) {
			return false;
		}
		return $this->src;
	}

	/**
	 * Get a src attribute via Cloudflare
	 *
	 * @return string The src attribute
	 */
	protected function get_cf_src() {
		if ( ! $this->has_src() || ! $this->has_height() || ! $this->has_width() ) {
			return false;
		}
		$src    = $this->get_src();
		$w      = $this->get_width();
		$h      = $this->get_height();
		$cf_src = $this->convert_src_to_cf( $src, $w, $h, 1 );
		return $cf_src;
	}

	/**
	 * Checks if an src attribute is set
	 *
	 * @return bool
	 */
	private function has_src() : bool {
		if ( ! $this->src ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the attachment ID
	 *
	 * @param string $id The attachment ID.
	 *
	 * @return self
	 */
	private function set_id( string $id ) : self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Get the attachment ID
	 *
	 * @return false|int The attachment ID
	 */
	protected function get_id() {
		if ( ! $this->has_id() ) {
			return false;
		}
		return $this->id;
	}

	/**
	 * Checks if an ID is set
	 *
	 * @return bool
	 */
	private function has_id() : bool {
		if ( ! $this->id ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the sources
	 *
	 * @param array $sources The sources.
	 *
	 * @return self
	 */
	private function set_sources( array $sources ) : self {
		$this->sources = $sources;
		return $this;
	}

	/**
	 * Get the sources
	 *
	 * @return false|array The sources
	 */
	protected function get_sources() {
		if ( ! $this->has_sources() ) {
			return false;
		}
		return $this->sources;
	}

	/**
	 * Checks if any sources are set
	 *
	 * @return bool
	 */
	private function has_sources() : bool {
		if ( ! $this->sources || empty( $this->sources ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the sizes values
	 *
	 * @param array $sizes The sizes values.
	 *
	 * @return self
	 */
	private function set_sizes( array $sizes ) : self {
		$this->sizes = $sizes;
		return $this;
	}

	/**
	 * Get the sizes values
	 *
	 * @return false|array The sizes values
	 */
	protected function get_sizes() {
		if ( ! $this->has_sizes() ) {
			return false;
		}
		return $this->sizes;
	}

	/**
	 * Checks if any sizes values are set
	 *
	 * @return bool
	 */
	private function has_sizes() : bool {
		if ( ! $this->sizes ) {
			return false;
		}
		return true;
	}

	/**
	 * Get all of the possible image sizes
	 *
	 * @return array The image sizes
	 */
	private function get_image_sizes() {

		$default_image_sizes = get_intermediate_image_sizes();
		foreach ( $default_image_sizes as $size ) {
			$image_sizes[ $size ]['width']  = intval( get_option( "{$size}_size_w" ) );
			$image_sizes[ $size ]['height'] = intval( get_option( "{$size}_size_h" ) );
		}

		global $_wp_additional_image_sizes;
		if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
			foreach ( $_wp_additional_image_sizes as &$size ) {
				unset( $size['crop'] );
			}
			$image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
		}
		unset( $image_sizes['1536x1536'] );
		unset( $image_sizes['2048x2048'] );
		return $image_sizes;
	}

	/**
	 * Init the sources
	 *
	 * @return self
	 */
	private function init_sources() : self {
		$sizes   = $this->get_image_sizes();
		$id      = $this->get_id();
		$sources = array();
		foreach ( $sizes as $k => $size ) {
			$sources[ $k ] = wp_get_attachment_image_src( $id, $k, false );
		}
		$sources['1200'] = wp_get_attachment_image_src( $id, array( 1200, 0 ), true );
		$sources['full'] = wp_get_attachment_image_src( $id, 'full', false );
		$this->set_sources( $sources );
		return $this;
	}


	/**
	 * Defines template replacement variables
	 *
	 * @return array The variables and their replacements
	 */
	protected function get_replacement_variables() : array {
		$replacements                     = array();
		$replacements['{{aspect_ratio}}'] = $this->get_aspect_ratio();
		$replacements['{{src}}']          = $this->get_cf_src();
		$replacements['{{height}}']       = $this->get_height();
		$replacements['{{width}}']        = $this->get_width();
		$replacements['{{alt}}']          = $this->get_alt();
		$replacements['{{loading}}']      = $this->get_loading();
		$replacements['{{decoding}}']     = $this->get_decoding();
		$replacements['{{srcset}}']       = implode( ', ', $this->get_srcset() );
		$replacements['{{classes}}']      = implode( ' ', $this->get_classes() );

		if ( ! $this->get_fixed() ) {
			$replacements['{{sizes}}'] = $this->convert_sizes_to_string();
		}

		return $replacements;
	}

}
