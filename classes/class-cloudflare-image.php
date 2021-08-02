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
		$this->init_src();
		$this->init_ratio();
		$this->add_classes();
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
		$this->atts['ratio'] = $ratio;
	}

	/**
	 * Replace the SRC attr with a Cloudflared version
	 *
	 * @return void
	 */
	private function init_src() : void {
		$src = Helper::alter_src( $this->atts['src'], $this->atts['width'], $this->atts['height'] );
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
			Helper::get_key_srcset_sizes_from_context( $this->atts['src'], $this->atts['data-context'] )
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
