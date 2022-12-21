<?php

namespace Edge_Images;

use Edge_Images\{Helpers, Handler};

/**
 * Generates and managers an image.
 */
class Image {

	/**
	 * The attachment ID
	 *
	 * @var int
	 */
	private $id;

	/**
	 * The attachment attributes
	 *
	 * @var array
	 */
	public $attrs = array();

	/**
	 * The attachment size
	 *
	 * @var string|array
	 */
	public $size;

	/**
	 * Construct the image object
	 *
	 * @param int    $id    The attachment ID.
	 * @param array  $attrs  The attachment attributes.
	 * @param string $size The size.
	 */
	public function __construct( int $id, array $attrs = array(), $size = 'large' ) {
		$this->id    = $id;
		$this->attrs = wp_parse_args( $attrs, Helpers::get_default_image_attrs() );
		$this->set_size( $size );
		$this->init();
	}

	/**
	 * Init the image
	 *
	 * @return void
	 */
	private function init() : void {

		// Try and init this from cache.
		$cached = $this->init_from_cache();
		if ( $cached ) {
			return;
		}

		// Get the normalized size string.
		$size = Helpers::normalize_size_attr( $this->get_size() );

		// Get the edge image sizes array.
		$sizes = apply_filters( 'Edge_Images\sizes', Helpers::get_wp_image_sizes() );

		// Grab the attrs for the image size, or continue with defaults.
		if ( array_key_exists( $size, $sizes ) ) {
			$this->attrs = wp_parse_args( $sizes[ $size ], $this->attrs );
		}

		// Sort the params.
		ksort( $this->attrs );

		// Init all of the attributes.
		$this->init_dimensions();
		$this->init_src();
		$this->init_ratio();
		$this->init_layout();
		$this->init_srcset();
		$this->init_sizes();
		$this->init_classes();

		// Cache the result.
		$this->cache();
	}

	/**
	 * Init the image from cache
	 *
	 * @return void
	 */
	private function init_from_cache() : void {

		// Get the cache key. Bail if we couldn't.
		$key = $this->get_cache_key();
		if ( ! $key ) {
			return;
		}

		// Get the cached image, bail if it wasn't found.
		$cache = wp_cache_get( $key, Helpers::CACHE_GROUP );
		if ( ! $cache ) {
			return;
		}

		// Set the properties.
		foreach ( $cache as $key => $val ) {
			$this->$key = $val;
		}
	}

	/**
	 * Cache the image
	 *
	 * @return void
	 */
	private function cache() : void {

		// Get the cache key. Bail if we couldn't.
		$key = $this->get_cache_key();
		if ( ! $key ) {
			return;
		}

		// Cache the image.
		wp_cache_set( $key, $this->attrs, Helpers::CACHE_GROUP, 30 );
	}

	/**
	 * Construct and return a cache key
	 *
	 * @return false|string The cache key, or FALSE
	 */
	private function get_cache_key() {

		// Bail if we don't have an ID.
		if ( ! $this->id ) {
			return false;
		}

		// Bail if we don't have a size.
		if ( ! $this->has_size() ) {
			return false;
		}

		// Construct the key string.
		$key = 'image_' . $this->id . '_' . Helpers::normalize_size_attr( $this->get_size() );

		return $key;

	}

	/**
	 * Init the layout
	 * Default to 'responsive'
	 *
	 * @return void
	 */
	private function init_layout() : void {

		// Bail if a layout is already set.
		if ( $this->has_attr( 'layout' ) ) {
			return;
		}

		$this->attrs['layout'] = 'responsive';
	}

	/**
	 * Get the ID
	 *
	 * @return int
	 */
	public function get_id() : int {
		return $this->id;
	}

	/**
	 * Init the dimensions
	 *
	 * @return void
	 */
	private function init_dimensions() : void {

		// Bail if w/h values are already set.
		if ( $this->has_dimensions() ) {
			return;
		}

		$size                  = $this->get_size();
		$sizes                 = Helpers::get_sizes_from_size( $size );
		$this->attrs['width']  = $sizes['width'];
		$this->attrs['height'] = $sizes['height'];
	}

	/**
	 * Checks if the height and width attrs are set
	 *
	 * @return bool bool
	 */
	private function has_dimensions() : bool {
		if ( ! isset( $this->attrs['width'] ) || ! isset( $this->attrs['height'] ) ) {
			return false;
		}
		if ( ! $this->attrs['width'] || ! $this->attrs['height'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Init the width
	 *
	 * @return void
	 */
	private function init_width() : void {
		if ( isset( $this->attrs['width'] ) && $this->attrs['width'] ) {
			return; // Bail if already set.
		}

		// Bail if width isn't available.
		$width = $this->get_attr( 'width' );
		if ( ! $width ) {
			return;
		}

		// Set the width.
		$this->attrs['width'] = $width;
	}

	/**
	 * Init the height
	 *
	 * @return void
	 */
	private function init_height() : void {
		if ( isset( $this->attrs['height'] ) && $this->attrs['height'] ) {
			return; // Bail if already set.
		}

		// Get the height.
		$height = $this->get_attr( 'height' );

		// Set the height, or calculate it if we know the width/ratio.
		if ( $height ) {
			// Just set the height.
			$this->attrs['height'] = $height;
		} else {
			// Or calculate it by using the width and the ratio.
			$width = $this->get_attr( 'width' );
			if ( ! $width ) {
				return; // Bail if there's no width.
			}
			$height                = $this->calculate_height_from_ratio( $width );
			$this->attrs['height'] = ( $height ) ? $height : null;
		}
	}

	/**
	 * Calculatge the height from the ratio
	 *
	 * @param int $width    The width in pixels.
	 *
	 * @return false|int    The height
	 */
	private function calculate_height_from_ratio( int $width ) {

		// We need the width and the ratio.
		if ( ! isset( $this->attrs['ratio'] ) ) {
			return false;
		}

		// Get the ratio components.
		$ratio = preg_split( '#/#', $this->attrs['ratio'] );
		if ( ! isset( $ratio[0] ) || ! isset( $ratio[1] ) ) {
			return false;
		}
		if ( ! $ratio[0] || ! $ratio[1] ) {
			return false;
		}

		// Divide the width by the ratio to get the height.
		return ceil( $width / ( $ratio[0] / $ratio[1] ) );
	}

	/**
	 * Init the ratio
	 *
	 * @return void
	 */
	private function init_ratio() : void {
		if ( isset( $this->attrs['ratio'] ) && $this->attrs['ratio'] ) {
			return; // Bail if already set.
		}

		$ratio = $this->get_attr( 'ratio' );
		if ( ! $ratio ) {
			if ( isset( $this->attrs['height'] ) && isset( $this->attrs['width'] ) ) {
				$ratio = $this->attrs['width'] . '/' . $this->attrs['height'];
			} else {
				return;
			}
		}
		$this->attrs['ratio'] = $ratio;
	}

	/**
	 * Replace the SRC attr with an edge version
	 *
	 * @return void
	 */
	private function init_src() : void {

		// Get the full-sized image.
		$full_image = wp_get_attachment_image_src( $this->id, 'full' );
		if ( ! $full_image || ! isset( $full_image[0] ) || ! $full_image[0] ) {
			return;
		}

		$this->attrs['src']      = $full_image[0];
		$this->attrs['full-src'] = $full_image[0];

		// Bail if we shouldn't transform the src.
		if ( ! Helpers::should_transform_image_src() ) {
			return;
		}

		// Bail if this is an SVG.
		if ( Helpers::is_svg( $this->attrs['src'] ) ) {
			return;
		}

		$this->convert_src_to_edge();
	}

	/**
	 * Convert the src to a CF string.
	 *
	 * @return void
	 */
	private function convert_src_to_edge() : void {
		$edge_src = Helpers::edge_src( $this->attrs['full-src'], $this->get_attrs() );

		if ( ! $edge_src ) {
			return; // Bail if the edge src generation fails.
		}

		$this->attrs['src'] = $edge_src;
	}

	/**
	 * Init the SRCSET attr
	 *
	 * @return void
	 */
	private function init_srcset() : void {

		// Bail if we're missing an SRC.
		if ( ! isset( $this->attrs['src'] ) || ! $this->attrs['src'] ) {
			return;
		}

		// Bail if this is an SVG.
		if ( Helpers::is_svg( $this->attrs['src'] ) ) {
			unset( $this->attrs['srcset'] ); // SVGs don't need/support this.
			return;
		}

		if ( ! isset( $this->attrs['layout'] ) || ! $this->attrs['layout'] ) {
			$this->attrs['layout'] = 'responsive';
		}

		switch ( $this->attrs['layout'] ) {
			case 'responsive':
				$srcset = array_merge(
					$this->get_dpx_srcset_sizes(),
					$this->get_generic_srcset_sizes(),
					$this->get_srcset_sizes_from_context( $this->attrs['full-src'] )
				);
				break;
			case 'fixed':
				$srcset = array_merge(
					$this->get_dpx_srcset_sizes(),
					$this->get_srcset_sizes_from_context( $this->attrs['full-src'] )
				);
				break;
		}

		$this->attrs['srcset'] = array_unique( $srcset );
	}

	/**
	 * Adds generic srcset values
	 *
	 * @return array The srcset values
	 */
	private function get_generic_srcset_sizes() : array {
		$srcset     = array();
		$args       = $this->get_attrs();
		$max_width  = min( 2 * $args['width'], Helpers::get_image_max_width() );
		$width_step = Helpers::get_width_step();

		for ( $w = Helpers::get_image_min_width(); $w <= $max_width; $w += $width_step ) {
			$args['width']  = $w;
			$args['height'] = $this->calculate_height_from_ratio( $w );
			$srcset[]       = Helpers::create_srcset_val( $this->attrs['full-src'], $args );

			// For larger images.
			if ( $w >= 1000 ) {
				$w += $width_step; // Increase the step increments.
			}
		}

		return $srcset;
	}

	/**
	 * Adds DPX srcset values
	 *
	 * @return array The srcset values
	 */
	private function get_dpx_srcset_sizes() : array {
		$attrs = $this->get_attrs();
		$args  = $attrs;

		// 2x.
		$args['width']  = $attrs['width'] * 2;
		$args['height'] = $this->calculate_height_from_ratio( $args['width'] );
		$srcset[]       = Helpers::create_srcset_val( $this->attrs['full-src'], $args );

		return $srcset;
	}

	/**
	 * Init the sizes attr
	 *
	 * @return void
	 */
	private function init_sizes() : void {
		$sizes = $this->get_attr( 'sizes' );
		if ( ! $sizes ) {
			$width = $this->attrs['width'];
			$sizes = '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px';
		}
		$this->attrs['sizes'] = $sizes;
	}

	/**
	 * Set a size attribute
	 *
	 * @param string|array $size The size value.
	 *
	 * @return self
	 */
	private function set_size( $size ) : self {
		$this->size = $size;
		return $this;
	}

	/**
	 * Get the size
	 *
	 * @return false|string The size
	 */
	public function get_size() {
		if ( ! $this->has_size() ) {
			return false;
		}
		return $this->size;
	}

	/**
	 * Checks if a size is set
	 *
	 * @return bool
	 */
	protected function has_size() : bool {
		if ( ! isset( $this->size ) || $this->size === '' ) {
			return false;
		}
		return true;
	}

	/**
	 * Parse the attr properties to construct an <img>
	 *
	 * @param bool $decorate If the el should be decorated.
	 *
	 * @return string The <img> el
	 */
	public function construct_img_el( $decorate = false ) : string {

		// srcset attributes need special treatment to comma-separate values.
		if ( isset( $this->attrs['srcset'] ) && ! empty( $this->attrs['srcset'] ) ) {
			$this->attrs['srcset'] = Helpers::srcset_array_to_string( $this->attrs['srcset'] );
		}

		// Build our HTML tag by running through all of our attrs.
		$html = sprintf(
			'<img %s>',
			implode(
				' ',
				array_map(
					function ( $v, $k ) {
						if ( is_array( $v ) ) {
							$v = implode( ' ', $v );
						}
						return sprintf( "%s='%s'", $k, $v );
					},
					$this->attrs,
					array_keys( $this->attrs )
				)
			)
		);

		$html = wp_kses(
			$html,
			array(
				'img' => Helpers::allowed_img_attrs(),
			)
		);

		if ( $decorate ) {
			$html = Handler::decorate_edge_image( $html, $this->id, $this->size, false, $this->attrs );
		}

		return $html;
	}



	/**
	 * Init the class attr.
	 *
	 * @return void
	 */
	private function init_classes() : void {

		// Get the size class(es).
		$size_class = Helpers::normalize_size_attr( $this->get_size() );

		// Get (and normalize) the class(es).
		$this->attrs['class'] = array_merge(
			Helpers::normalize_attr_array( $this->get_attr( 'class' ) ),
			array(
				'attachment-' . $size_class,
				'size-' . $size_class,
				'edge-images-img',
				'edge-images-img--' . $this->attrs['loading'],
			)
		);
		$this->attrs['class'] = array_unique( $this->attrs['class'] );

		// Get (and normalize) the container class(es).
		$this->attrs['container-class'] = array_merge(
			Helpers::normalize_attr_array( $this->get_attr( 'container-class' ) ),
			array(
				'edge-images-container',
				'picture-' . $size_class,
				isset( $this->attrs['layout'] ) ? $this->attrs['layout'] : null,
			)
		);
		$this->attrs['container-class'] = array_unique( $this->attrs['container-class'] );
	}

	/**
	 * Get context values for the image
	 *
	 * @param  string $val  The value to retrieve.
	 *
	 * @return false|string The value
	 */
	public function get_attr( string $val ) {
		if ( ! $this->has_attr( $val ) ) {
			return false;
		}
		return $this->attrs[ $val ];
	}

	/**
	 * Get the attributes array
	 *
	 * @return array The attributes
	 */
	private function get_attrs() : array {
		return $this->attrs;
	}

	/**
	 * Checks if an attr is set
	 *
	 * @param  string $val The attr key to check.
	 *
	 * @return bool
	 */
	public function has_attr( string $val ) : bool {
		if ( ! isset( $this->attrs[ $val ] ) || ! $this->attrs[ $val ] ) {
			return false;
		}
		if ( ( is_array( $this->attrs[ $val ] ) ) && empty( $this->attrs[ $val ] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Adds key srcset sizes from the image's size
	 *
	 * @param string $src The image src.
	 *
	 * @return array The srcset attr
	 */
	public function get_srcset_sizes_from_context( string $src ) : array {

		$sizes = array();

		// Start with any custom srcset values.
		if ( $this->has_attr( 'srcset' ) ) {
			$srcset = $this->get_attr( 'srcset' );
			if ( is_array( $srcset ) ) {
				$sizes = array_merge( $sizes, $srcset );
			}
		}

		// Create the srcset strings.
		$srcset = array();
		foreach ( $sizes as $v ) {
			$h              = ( isset( $v['width'] ) ) ? $v['height'] : null;
			$args           = $this->get_attrs();
			$args['width']  = $v['width'];
			$args['height'] = $h;
			$srcset[]       = Helpers::create_srcset_val( $src, $args );

			// Generate a 2x size if it's smaller than our max.
			if ( ( $v['width'] * 2 ) <= Helpers::get_image_max_width() ) {
				$args['width']  = $v['width'] * 2;
				$args['height'] = $h * 2;
				$srcset[]       = Helpers::create_srcset_val( $src, $args );
			}

			// Generate a smaller size if it's larger than our min.
			if ( ceil( $v['width'] / 2 ) > Helpers::get_image_min_width() ) {
				$args['width']  = ceil( $v['width'] / 2 );
				$args['height'] = ceil( $h / 2 );
				$srcset[]       = Helpers::create_srcset_val( $src, $args );
			}
		}

		$srcset = array_unique( $srcset );

		return $srcset;
	}

	/**
	 * Converts array properties like class and srcset into strings
	 *
	 * @return void
	 */
	public function flatten_array_properties() : void {

		// Convert the class to a string.
		if ( $this->has_attr( 'class' ) ) {
			$this->attrs['class'] = Helpers::classes_array_to_string( $this->attrs['class'] );
		}

		// Convert the container class(es) to a string.
		if ( $this->has_attr( 'container-class' ) ) {
			$this->attrs['container-class'] = Helpers::classes_array_to_string( $this->attrs['container-class'] );
		}

		// Convert the srcset to a (comma-separated) string.
		if ( $this->has_attr( 'srcset' ) ) {
			$this->attrs['srcset'] = Helpers::srcset_array_to_string( $this->attrs['srcset'] );
		}
	}

}
