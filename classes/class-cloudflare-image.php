<?php
namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;
use Yoast_CF_Images\Integrations\Cloudflare_Image_Handler as Handler;

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
	public function __construct( int $id, array $attrs = array(), $size ) {
		$this->id    = $id;
		$this->attrs = $attrs;
		$this->set_size( $size );
		$this->init();
	}

	/**
	 * Init the image
	 *
	 * @return void
	 */
	private function init() : void {

		// Init attributes from a child class if one exists.
		if ( method_exists( get_called_class(), 'init_attrs' ) ) {
			$this->init_attrs();
		}

		$this->init_dimensions();
		$this->init_fit();
		$this->init_src();
		$this->init_ratio();
		$this->init_layout();
		$this->init_srcset();
		$this->init_sizes();
		$this->init_classes();
	}

	/**
	 * Init the layout
	 * Default to 'responsive'
	 *
	 * @return void
	 */
	private function init_layout() : void {

		// Bail if a layout is already set.
		if ( $this->has_layout() ) {
			return;
		}

		$layout                     = $this->get_attr( 'layout' );
		$this->attrs['data-layout'] = ( $layout ) ? $layout : 'responsive';
	}

	/**
	 * Init the fit
	 * Default to 'contain'
	 *
	 * @return void
	 */
	private function init_fit() : void {

		// Bail if a fit is already set.
		if ( $this->has_fit() ) {
			return;
		}

		$fit                     = $this->get_attr( 'data-fit' );
		$this->attrs['data-fit'] = ( $fit ) ? $fit : 'contain';
	}

	/**
	 * Checks if a layout is set
	 *
	 * @return bool
	 */
	private function has_layout() : bool {
		if ( ! isset( $this->attrs['data-layout'] ) || ! $this->attrs['data-layout'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if a fit is set
	 *
	 * @return bool
	 */
	private function has_fit() : bool {
		if ( ! isset( $this->attrs['data-fit'] ) || ! $this->attrs['data-fit'] ) {
			return false;
		}
		return true;
	}

	public function get_id() {
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
		if ( ! $this->has_size() ) {
			$this->init_width();
			$this->init_height();
			return; // Early exit.
		}

		$size = $this->get_size();

		if ( is_string( $size ) ) {
			$vals = Helpers::get_wp_size_vals( $size );
			if ( $vals && ! empty( $vals ) ) {
				$image                 = wp_get_attachment_image_src( $this->get_id(), $size );
				$this->attrs['width']  = $image[2];
				$this->attrs['height'] = $image[1];
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
		if ( ! isset( $this->attrs['data-ratio'] ) ) {
			return false;
		}

		// Get the ratio components.
		$ratio = preg_split( '#/#', $this->attrs['data-ratio'] );
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
		if ( isset( $this->attrs['data-ratio'] ) && $this->attrs['data-ratio'] ) {
			return; // Bail if already set.
		}

		$ratio = $this->get_attr( 'ratio' );
		if ( ! $ratio ) {
			if ( isset( $this->attrs['height'] ) && isset( $this->attrs['width'] ) ) {
				$ratio = $this->attrs['height'] . '/' . $this->attrs['width'];
			} else {
				return;
			}
		}
		$this->attrs['data-ratio'] = $ratio;
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

		// Convert the SRC to a CF string.
		$height = ( isset( $this->attrs['height'] ) ) ? $this->attrs['height'] : null;
		$cf_src = Helpers::cf_src( $full_image[0], $this->attrs['width'], $height, $this->attrs['data-fit'] );

		if ( ! $cf_src ) {
			return;
		}

		$this->attrs['src']           = $cf_src;
		$this->attrs['data-full-src'] = $full_image[0];
	}

	/**
	 * Init the SRCSET attr
	 *
	 * @return void
	 */
	private function init_srcset() : void {

		if ( ! isset( $this->attrs['data-layout'] ) || ! $this->attrs['data-layout'] ) {
			$this->attrs['data-layout'] = 'responsive';
		}

		switch ( $this->attrs['data-layout'] ) {
			case 'responsive':
				$srcset = array_merge(
					$this->get_generic_srcset_sizes(),
					$this->get_srcset_sizes_from_context( $this->attrs['data-full-src'] )
				);
				break;
			case 'fixed':
				$srcset = array_merge(
					$this->get_x2_srcset_size(),
					$this->get_srcset_sizes_from_context( $this->attrs['data-full-src'] )
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
		$srcset = array();
		$max    = min( 2 * $this->attrs['width'], Helpers::WIDTH_MAX );
		for ( $w = Helpers::WIDTH_MIN; $w <= $max; $w += Helpers::WIDTH_STEP ) {
			$h        = $this->calculate_height_from_ratio( $w );
			$srcset[] = Helpers::create_srcset_val( $this->attrs['data-full-src'], $w, $h );
			if ( $w >= 1000 ) {
				$w += 100; // Increase the increments on larger sizes.
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
		$w        = $this->attrs['width'];
		$h        = $this->calculate_height_from_ratio( $w );
		$srcset[] = Helpers::create_srcset_val( $this->attrs['data-full-src'], $w, $h );
		$srcset[] = Helpers::create_srcset_val( $this->attrs['data-full-src'], $w * 2, $h * 2 );
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
		$this->attrs['srcset'] = implode( ', ', $this->attrs['srcset'] );

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

		// Get (and normalize) the class(es).
		$classes = Helpers::normalize_attr_array( $this->get_attr( 'class' ) );

		// Get (and normalize) the picture class(es).
		$picture_classes = Helpers::normalize_attr_array( $this->get_attr( 'data-picture-class' ) );

		// Get the size class(es).
		$size_class = ( is_array( $this->size ) ) ? $this->size[0] . 'x' . $this->size[1] : $this->size;

		$this->attrs['class'] = array_merge(
			$classes,
			array(
				'attachment-' . $size_class,
				'size-' . $size_class,
				'cloudflared',
			)
		);
		$this->attrs['class'] = array_unique( $this->attrs['class'] );

		$this->attrs['data-picture-class'] = array_merge(
			$picture_classes,
			array(
				'cloudflared', // Placeholde for default classes.
			)
		);
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
	 * Checks if an attr is set
	 *
	 * @param  string $val The attr key to check.
	 *
	 * @return bool
	 */
	private function has_attr( string $val ) : bool {
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
			$h        = ( isset( $v['width'] ) ) ? $v['height'] : null;
			$srcset[] = Helpers::create_srcset_val( $src, $v['width'], $h );

			// Generate a 2x size if it's smaller than our max.
			if ( ( $v['width'] * 2 ) <= Helpers::WIDTH_MAX ) {
				$srcset[] = Helpers::create_srcset_val( $src, $v['width'] * 2, $h * 2 );
			}

			// Generate a smaller size if it's larger than our min.
			if ( ceil( $v['width'] / 2 ) > Helpers::WIDTH_MIN ) {
				$srcset[] = Helpers::create_srcset_val( $src, ceil( $v['width'] / 2 ), ceil( $h / 2 ) );
			}
		}

		$srcset = array_unique( $srcset );

		return $srcset;
	}
}
