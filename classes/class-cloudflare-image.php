<?php

namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Helper as Helper;
use Yoast_CF_Images\Cloudflare_Image_Handler as Handler;

/**
 * Generates and managers a Cloudflared image.
 */
class Cloudflare_Image {

	/**
	 * Construct the image object
	 *
	 * @param int   $id    The attachment ID.
	 * @param array $atts  The attachment attributes.
	 */
	public function __construct( int $id, array $atts = array() ) {
		$this->id   = $id;
		$this->atts = $atts;
		$this->init();
	}

	/**
	 * Init the image
	 *
	 * @return void
	 */
	private function init() : void {
		$this->init_properties();
		$this->init_dimensions();
		$this->init_ratio();
		$this->init_classes();
		$this->init_layout();
		$this->init_src();
		$this->init_srcset();
		$this->init_sizes();
	}

	/**
	 * Signpost that the image has been Cloudflared
	 *
	 * @return void
	 */
	private function init_properties() : void {
		$this->atts['data-cloudflared'] = 'true';
	}

	/**
	 * Init the layout
	 * Default to 'responsive'
	 *
	 * @return void
	 */
	private function init_layout() : void {
		$layout = Handler::get_context_vals( $this->atts['data-context'], 'layout' );
		if ( ! $dimensions ) {
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
		$dimensions = Handler::get_context_vals( $this->atts['data-context'], 'dimensions' );
		if ( ! $dimensions ) {
			return;
		}
		$this->atts['width']  = $dimensions['w'];
		$this->atts['height'] = $dimensions['h'];
	}

	/**
	 * Init the ratio
	 *
	 * @return void
	 */
	private function init_ratio() : void {
		$ratio = Handler::get_context_vals( $this->atts['data-context'], 'ratio' );
		if ( ! $ratio ) {
			return;
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
		$src = Helper::cf_src( $full_image[0], $this->atts['width'], $this->atts['height'] );
		if ( ! $src ) {
			return;
		}

		$this->atts['src'] = $src;
	}

	/**
	 * Init the SRCSET attr
	 *
	 * @return void
	 */
	private function init_srcset() : void {
		$srcset = array_merge(
			$this->add_generic_srcset_sizes(),
			Helper::get_srcset_sizes_from_context( $this->atts['src'], $this->atts['data-context'] )
		);
		if ( empty( $srcset ) ) {
			return;
		}
		$srcset = implode( ',', $srcset );
		if ( ! $srcset ) {
			return;
		}
		$this->atts['srcset'] = $srcset;
	}

	/**
	 * Adds generic srcset values
	 *
	 * TODO: Get ratio, calculate height, pass to creation method.
	 *
	 * @return array The srcset values
	 */
	private function add_generic_srcset_sizes() : array {
		$srcset = array();
		for ( $w = 100; $w <= 2400; $w += 100 ) {
			$srcset[] = Helper::create_srcset_val( $this->atts['src'], $w );
		}
		return $srcset;
	}

	/**
	 * Init the sizes attr
	 *
	 * @return void
	 */
	private function init_sizes() : void {
		$sizes = Handler::get_context_vals( $this->atts['data-context'], 'sizes' );
		if ( ! $sizes ) {
			return;
		}
		$this->atts['sizes'] = $sizes;
	}

	/**
	 * Inits any custom classes
	 *
	 * @return void
	 */
	private function init_classes() : void {
		$classes = array(
			'cloudflared',
			'size-' . sanitize_title( $this->atts['data-context'] ), // Replaces native.
			'attachment-' . sanitize_title( $this->atts['data-context'] ), // Replaces native.
		);

		$this->atts['class'] .= implode( ' ', $classes );
	}



}
