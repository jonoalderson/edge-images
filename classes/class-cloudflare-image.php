<?php

namespace Yoast_CF_Images;

/**
 * Generates and managers a Cloudflared image.
 */
class Cloudflare_Image {

	/**
	 * Construct the image object
	 *
	 * @param int    $id    The attachment ID.
	 * @param array  $atts  The attachment attributes.
	 * @param string $size  The attachment size.
	 */
	public function __construct( int $id, array $atts, string $size ) {
		$this->id   = $id;
		$this->atts = $atts;
		$this->size = $size;
		$this->init();
	}

	/**
	 * Init the image
	 *
	 * @return void
	 */
	private function init() : void {
		$this->set_dimensions();
		$this->use_full_size();
		$this->add_srcset();
		$this->add_sizes();
		$this->add_class();
		$this->replace_src();
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
	 * Set the dimensions
	 *
	 * @return void
	 */
	private function set_dimensions() : void {
		$image                = wp_get_attachment_image_src( $this->id, $this->size );
		$this->atts['width']  = $image[1];
		$this->atts['height'] = $image[2];
	}

	/**
	 * Replace the SRC attr with a Cloudflared version
	 *
	 * @return void
	 */
	private function replace_src() : void {
		$this->atts['src'] = Cloudflare_Image_Handler::alter_src( $this->atts['src'], $this->atts['width'] );
	}

	/**
	 * Adds the SRCSET attr
	 *
	 * @return void
	 */
	private function add_srcset() : void {
		$srcset = wp_get_attachment_image_srcset( $this->id, $this->size );
		if ( ! $srcset ) {
			return;
		}
		$this->atts['srcset'] = $srcset;
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
	private function add_class() : void {
		$this->atts['class'] .= ' cloudflared';
	}



}
