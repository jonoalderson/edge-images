<?php
namespace Yoast_CF_Images;

use Yoast_CF_Images\{Helpers, Handler};

/**
 * Generates and managers a Cloudflared image.
 */
class Cloudflare_Image {

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
	public function __construct( int $id, array $attrs = array(), $size = 'full' ) {
		$this->id    = $id;
		$this->attrs = wp_parse_args( $attrs, $this->get_default_attrs() );
		$this->set_size( $size );
		$this->init();
	}

	/**
	 * Init the image
	 *
	 * @return void
	 */
	private function init() : void {

		// Get the normalized size string.
		$size = Helpers::normalize_size_attr( $this->get_size() );

		// Get the cf image sizes array.
		$cf_image_sizes = apply_filters( 'cf_image_sizes', array() );

		// Grab the attrs for the image size, or continue with defaults.
		if ( array_key_exists( $size, $cf_image_sizes ) ) {
			$this->attrs = wp_parse_args( $cf_image_sizes[ $size ], $this->attrs );
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
	}

	/**
	 * Returns an array with default properties.
	 *
	 * @return array Array with default properties.
	 */
	public function get_default_attrs() : array {
		$width  = Helpers::get_content_width();
		$height = $width * 0.75;
		$attrs  = array(
			'width'         => $width,
			'height'        => $height,
			'sizes'         => "(max-width: {$width}px) 100vw, {$width}px",
			'class'         => array(),
			'picture-class' => array(),
			'fit'           => 'cover',
			'loading'       => 'lazy',
			'decoding'      => 'async',
		);

		return $attrs;
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

		// If we don't have a size, try and work out some values.
		// If it's an SVG, we know the sizes.
		if ( ! $this->has_size() || $this->is_svg() ) {
			$this->init_width();
			$this->init_height();
			return; // Early exit.
		}

		$size = $this->get_size();

		if ( is_string( $size ) ) {
			$vals = Helpers::get_wp_size_vals( $size );
			if ( $vals && ! empty( $vals ) ) {
				$image                 = wp_get_attachment_image_src( $this->get_id(), $size );
				$this->attrs['width']  = $image[1];
				$this->attrs['height'] = $image[2];
			}
			return; // Early exit.
		}

		if ( is_array( $size ) ) {
			$this->attrs['width']  = $size[0];
			$this->attrs['height'] = $size[1];
			return;
		}

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
	 * Replace the SRC attr with a Cloudflared version
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

		// Bail if this is an SVG.
		if ( $this->is_svg() ) {
			return;
		}

		// Convert the SRC to a CF string.
		$args   = array(
			'width'   => ( $this->has_attr( 'width' ) ) ? $this->get_attr( 'width' ) : null,
			'height'  => ( $this->has_attr( 'height' ) ) ? $this->get_attr( 'height' ) : null,
			'fit'     => ( $this->has_attr( 'fit' ) ) ? $this->get_attr( 'fit' ) : null,
			'blur'    => ( $this->has_attr( 'blur' ) ) ? $this->get_attr( 'blur' ) : null,
			'format'  => ( $this->has_attr( 'format' ) ) ? $this->get_attr( 'format' ) : null,
			'quality' => ( $this->has_attr( 'quality' ) ) ? $this->get_attr( 'quality' ) : null,
		);
		$cf_src = Helpers::cf_src( $full_image[0], $args );

		if ( ! $cf_src ) {
			return; // Bail if the CF src generation fails.
		}

		$this->attrs['src'] = $cf_src;
	}

	/**
	 * Determines if an image is an SVG.
	 *
	 * @return bool
	 */
	public function is_svg() : bool {
		if ( ! isset( $this->attrs['src'] ) ) {
			// Presume it's not, if we don't know.
			return false;
		}
		if ( strpos( $this->attrs['src'], '.svg' ) !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * Init the SRCSET attr
	 *
	 * @return void
	 */
	private function init_srcset() : void {

		if ( $this->is_svg() ) {
			return;
		}

		if ( ! isset( $this->attrs['layout'] ) || ! $this->attrs['layout'] ) {
			$this->attrs['layout'] = 'responsive';
		}

		switch ( $this->attrs['layout'] ) {
			case 'responsive':
				$srcset = array_merge(
					$this->get_generic_srcset_sizes(),
					$this->get_srcset_sizes_from_context( $this->attrs['full-src'] )
				);
				break;
			case 'fixed':
				$srcset = array_merge(
					$this->get_x2_srcset_size(),
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
		$srcset          = array();
		$args            = $this->get_attrs();
		$max             = min( 2 * $args['width'], Helpers::WIDTH_MAX );
		$args['quality'] = Helpers::get_image_quality_high();
		for ( $w = Helpers::WIDTH_MIN; $w <= $max; $w += Helpers::WIDTH_STEP ) {
			$args['width']  = $w;
			$args['height'] = $this->calculate_height_from_ratio( $w );
			$srcset[]       = Helpers::create_srcset_val( $this->attrs['full-src'], $args );
			if ( $w >= 1000 ) {
				$w += Helpers::WIDTH_STEP; // Increase the increments on larger sizes.
				if ( $args['quality'] >= Helpers::get_image_quality_low() ) {
					$args['quality'] = $args['quality'] - 5; // Decrement the quality as we increse size.
				}
			}
		}
		return $srcset;
	}

	/**
	 * Adds x2 srcset values
	 *
	 * @return array The srcset values
	 */
	private function get_x2_srcset_size() : array {
		$args            = $this->get_attrs();
		$args['width']   = $attrs['width'] * 2;
		$args['height']  = $this->calculate_height_from_ratio( $attrs['width'] );
		$args['quality'] = Helpers::get_image_quality_low();
		$srcset[]        = Helpers::create_srcset_val( $this->attrs['full-src'], $args );
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
	 * @param bool $wrap_in_picture If the el should be wrapped in a <picture>.
	 *
	 * @return string The <img> el
	 */
	public function construct_img_el( $wrap_in_picture = false ) : string {

		// srcset attributes need special treatment to comma-separate values.
		$this->attrs['srcset'] = Helpers::srcset_array_to_string( $this->attrs['srcset'] );

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

		if ( ! $wrap_in_picture ) {
			return $html;
		}

		// Wrap the <img> in a <picture>.
		return Handler::wrap_in_picture( $html, $this->id, $this->size, false, $this->attrs );
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
				'img-cloudflared',
			)
		);
		$this->attrs['class'] = array_unique( $this->attrs['class'] );

		// Get (and normalize) the picture class(es).
		$this->attrs['picture-class'] = array_merge(
			Helpers::normalize_attr_array( $this->get_attr( 'picture-class' ) ),
			array(
				'picture-' . $size_class,
			)
		);
		$this->attrs['picture-class'] = array_unique( $this->attrs['picture-class'] );
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

		$sizes = array(
			array(
				'width'  => $this->attrs['width'],
				'height' => $this->attrs['height'],
			),
		);

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
			if ( ( $v['width'] * 2 ) <= Helpers::WIDTH_MAX ) {
				$args['width']   = $v['width'] * 2;
				$args['height']  = $h * 2;
				$args['quality'] = Helpers::get_image_quality_low();
				$srcset[]        = Helpers::create_srcset_val( $src, $args );
			}

			// Generate a smaller size if it's larger than our min.
			if ( ceil( $v['width'] / 2 ) > Helpers::WIDTH_MIN ) {
				$args['width']   = ceil( $v['width'] / 2 );
				$args['height']  = ceil( $h / 2 );
				$args['quality'] = Helpers::get_image_quality_high();
				$srcset[]        = Helpers::create_srcset_val( $src, $args );
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

		// Convert the picture class(es) to a string.
		if ( $this->has_attr( 'picture-class' ) ) {
			$this->attrs['picture-class'] = Helpers::classes_array_to_string( $this->attrs['picture-class'] );
		}

		// Convert the srcset to a (comma-separated) string.
		if ( $this->has_attr( 'srcset' ) ) {
			$this->attrs['srcset'] = Helpers::srcset_array_to_string( $this->attrs['srcset'] );
		}
	}

}
