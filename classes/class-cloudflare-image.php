<?php
namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;
use Yoast_CF_Images\Cloudflare_Image_Handler as Handler;
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
	public $atts = array();

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
	 * @param array  $atts  The attachment attributes.
	 * @param string $size The size.
	 */
	public function __construct( int $id, array $atts = array(), $size ) {
		$this->id   = $id;
		$this->atts = $atts;
		$this->set_size( $size );
		$this->init();
	}

	/**
	 * Init the image
	 *
	 * @return void
	 */
	private function init() : void {
		$this->init_dimensions();
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
		$layout = $this->get_attr( 'layout' );
		if ( ! $layout ) {
			$layout = 'responsive';
		}
		$this->atts['data-layout'] = $layout;
	}

	/**
	 * Init the dimensions
	 *
	 * @return void
	 */
	private function init_dimensions() : void {
		$this->init_width();
		$this->init_height();
	}

	/**
	 * Init the width
	 *
	 * @return void
	 */
	private function init_width() : void {
		if ( isset( $this->atts['width'] ) && $this->atts['width'] ) {
			return; // Bail if already set.
		}
		// Bail if dimensions aren't available.
		$dimensions = $this->get_attr( 'dimensions' );
		if ( ! $dimensions ) {
			return;
		}
		// Set the width.
		$this->atts['width'] = $dimensions['w'];
	}

	/**
	 * Init the height
	 *
	 * @return void
	 */
	private function init_height() : void {
		if ( isset( $this->atts['height'] ) && $this->atts['height'] ) {
			return; // Bail if already set.
		}

		// Bail if dimensions aren't available.
		$dimensions = $this->get_attr( 'dimensions' );
		if ( ! $dimensions ) {
			return;
		}
		// Set the height, or calculate it if we know the ratio.
		if ( isset( $dimensions['h'] ) ) {
			$this->atts['height'] = $dimensions['h'];
		} else {
			$height               = $this->calculate_height_from_ratio( $dimensions['w'] );
			$this->atts['height'] = ( $height ) ? $height : null;
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
		if ( ! isset( $this->atts['data-ratio'] ) ) {
			return false;
		}

		// Get the ratio components.
		$ratio = preg_split( '#/#', $this->atts['data-ratio'] );
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
		$ratio = $this->get_attr( 'ratio' );
		if ( ! $ratio ) {
			if ( isset( $this->atts['width'] ) && isset( $this->atts['height'] ) ) {
				$ratio = $this->atts['width'] . '/' . $this->atts['height'];
			} else {
				return;
			}
		}
		$this->atts['data-ratio'] = $ratio;
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
		$height = ( isset( $this->atts['height'] ) ) ? $this->atts['height'] : null;
		$cf_src = Helpers::cf_src( $full_image[0], $this->atts['width'], $height );
		if ( ! $cf_src ) {
			return;
		}

		$this->atts['src']           = $cf_src;
		$this->atts['data-full-src'] = $full_image[0];
	}

	/**
	 * Init the SRCSET attr
	 *
	 * @return void
	 */
	private function init_srcset() : void {
		switch ( $this->atts['data-layout'] ) {
			case 'responsive':
				$srcset = array_merge(
					$this->add_generic_srcset_sizes(),
					$this->get_srcset_sizes_from_context( $this->atts['data-full-src'] )
				);
				break;
			case 'fixed':
				$srcset = array_merge(
					$this->add_x2_srcset_size(),
					$this->get_srcset_sizes_from_context( $this->atts['data-full-src'] )
				);
				break;
			default:
				return;
		}
		if ( empty( $srcset ) ) {
			return;
		}

		$srcset = array_unique( $srcset );
		$srcset = implode( ',', $srcset );
		if ( ! $srcset ) {
			return;
		}
		$this->atts['srcset'] = $srcset;
	}

	/**
	 * Adds generic srcset values
	 *
	 * @return array The srcset values
	 */
	private function add_generic_srcset_sizes() : array {
		$srcset = array();
		for ( $w = 300; $w <= ( 2 * $this->atts['width'] ); $w += 100 ) {
			$h        = $this->calculate_height_from_ratio( $w );
			$srcset[] = Helpers::create_srcset_val( $this->atts['data-full-src'], $w, $h );
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
	private function add_x2_srcset_size() : array {
		$w        = $this->atts['width'];
		$h        = $this->calculate_height_from_ratio( $w );
		$srcset[] = Helpers::create_srcset_val( $this->atts['data-full-src'], $w, $h );
		$srcset[] = Helpers::create_srcset_val( $this->atts['data-full-src'], $w * 2, $h * 2 );
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
			$width = $this->atts['width'];
			$sizes = '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px';
		}
		$this->atts['sizes'] = $sizes;
	}

	/**
	 * Set a size attribute
	 *
	 * @param string|array $size The size value.
	 *
	 * @return self
	 */
	private function set_size( $size ) : self {
		$size       = $this->sanitize_size( $size );
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
	 * Sanitize a size attribute
	 *
	 * @param string|array $size The size value.
	 *
	 * @return string            The sanitized value.
	 */
	private function sanitize_size( $size ) : string {
		if ( is_array( $size ) ) {
			// Convert [640, 480] into '640x480'.
			$size = implode( 'x', $size );
		}
		return $size;
	}

	/**
	 * Parse the attr properties to construct an <img>
	 *
	 * @param bool $wrap_in_picture If the el should be wrapped in a <picture>.
	 *
	 * @return string The <img> el
	 */
	public function construct_img_el( $wrap_in_picture = false ) : string {
		$html = sprintf(
			'<img %s>',
			implode(
				' ',
				array_map(
					function ( $v, $k ) {
						return sprintf( "%s='%s'", $k, $v ); },
					$this->atts,
					array_keys( $this->atts )
				)
			)
		);
		if ( ! $wrap_in_picture ) {
			return $html;
		}
		// Wrap the <img> in a <picture>.
		$html = Handler::wrap_in_picture( $html, $this->id, $this->size, false, $this->atts );
		return $html;
	}

	/**
	 * Init the class attr.
	 *
	 * @return void
	 */
	private function init_classes() : void {
		$this->atts['class'] = implode(
			' ',
			array(
				'attachment-' . $this->size,
				'size-' . $this->size,
				'cloudflared',
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

		// Bail if there's no size set.
		if ( ! $this->has_size() ) {
			return false;
		}

		// Get the h/w attrs based on the image size.
		$vals = Helpers::get_wp_size_vals( $this->get_size() );
		if ( ! $vals ) {
			// Fall back to the 'large' attrs.
			$vals = Helpers::get_wp_size_vals( 'large' );
		}

		$vals = wp_parse_args(
			$vals,
			array(
				'dimensions' => null,
				'srcset'     => null,
				'sizes'      => null,
				'ratio'      => null,
				'layout'     => null,
			)
		);

		if ( ! isset( $vals[ $val ] ) ) {
			return false;
		}

		return $vals[ $val ];
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
				'w' => $this->atts['width'],
				'h' => $this->atts['height'],
			),
		);

		// Start with any custom srcset values.
		$custom_srcset_vals = $this->get_attr( 'srcset' );
		if ( $custom_srcset_vals ) {
			$sizes = array_merge( $sizes, $custom_srcset_vals );
		}

		// Add with values based on the normalized w/h of the image.

		$srcset = array();

		// Create the srcset strings and x2 strings.
		foreach ( $sizes as $v ) {
			$h        = ( isset( $v['h'] ) ) ? $v['h'] : null;
			$srcset[] = Helpers::create_srcset_val( $src, $v['w'], $h );
			$srcset[] = Helpers::create_srcset_val( $src, $v['w'] * 2, $h * 2 );
		}

		$srcset = array_unique( $srcset );

		return $srcset;
	}
}
