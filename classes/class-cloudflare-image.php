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
		$this->use_full_size();
		$this->init_dimensions();
		$this->init_srcset();
		$this->add_classes();
		$this->init_src();
	}

	/**
	 * Alter the SRC attr to use the full size image
	 *
	 * @return void
	 */
	private function use_full_size() : void {
		$full_image        = wp_get_attachment_image_src( $this->id, 'full' );
		$this->atts['src'] = $full_image[0];
	}

	/**
	 * Init the dimensions
	 *
	 * @return void
	 */
	private function init_dimensions() : void {
		$dimensions           = Handler::get_context_vals( $this->atts['data-context'], 'dimensions' );
		$this->atts['width']  = $dimensions['w'];
		$this->atts['height'] = $dimensions['h'];
	}

	/**
	 * Init the ratio
	 *
	 * @return void
	 */
	private function init_ratio() : void {
		$ratio               = Handler::get_context_vals( $this->atts['data-context'], 'ratio' );
		$this->atts['ratio'] = $ratio;
	}

	/**
	 * Replace the SRC attr with a Cloudflared version
	 *
	 * @return void
	 */
	private function init_src() : void {
		$this->atts['src'] = Helper::alter_src( $this->atts['src'], $this->atts['width'], $this->atts['height'] );
	}

	/**
	 * Init the SRCSET attr
	 *
	 * @return void
	 */
	private function init_srcset() : void {
		$srcset               = array_merge(
			$this->add_generic_srcset_sizes(),
			Helper::get_key_srcset_sizes_from_context( $this->atts['src'], $this->atts['data-context'] )
		);
		$this->atts['srcset'] = implode( ',', $srcset );
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
	 * Add the SIZES attr
	 *
	 * @return void
	 */
	private function add_sizes() : void {
		$sizes = wp_get_attachment_image_sizes( $this->id, $this->size );
		if ( ! $sizes ) {
			return;
		}
		$this->atts['sizes'] = $sizes;
	}

	/**
	 * Add a cloudflare CLASS
	 *
	 * @return void
	 */
	private function add_classes() : void {
		$this->atts['class'] .= ' cloudflared';
	}



}
