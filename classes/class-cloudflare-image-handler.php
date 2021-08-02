<?php

namespace Yoast_CF_Images;

/**
 * Filters wp_get_attachment_image and related functions to use Cloudflare.
 */
class Cloudflare_Image_Handler {

	/**
	 * Register the integration
	 *
	 * @TODO: Add a wp_calculate_image_sizes filter.
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
		add_filter( 'wp_get_attachment_image_attributes', array( $instance, 'route_images_through_cloudflare' ), 10, 3 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'remove_dimension_attributes' ), 10 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'remove_style_attribute' ), 10 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'replace_size_class' ), 10 );
	}

	/**
	 * Remove the (first two) height and width attrs from <img> markup.
	 *
	 * NOTE: Widthout this, we create duplicate properties
	 *       with wp_get_attachment_image_attributes!
	 *
	 * @param  string $html The HTML <img> tag.
	 *
	 * @return string       The modified tag
	 */
	public function remove_dimension_attributes( string $html ) : string {
		$html = preg_replace( '/(width|height)="\d*"\s/', '', $html, 2 );
		return $html;
	}

	/**
	 * Remove the 'size-x' class, as we chance that elsewhere
	 *
	 * @param  string $html The HTML <img> tag.
	 *
	 * @return string       The modified tag
	 */
	public function reset_size_class( string $html ) : string {
		$html = preg_replace( '/(size-\d*\s/', '', $html, 2 );
		return $html;
	}

	/**
	 * Remove any inline style attribute from <img> markup.
	 *
	 * @param  string $html The HTML <img> tag.
	 *
	 * @return string       The modified tag
	 */
	public function remove_style_attribute( string $html ) : string {
		$html = preg_replace( '/(<[^>]+) style=".*?"/i', '$1', $html );
		return $html;
	}

	/**
	 * Check whether an image should use Cloudflare
	 *
	 * @param  array $atts The attachment attributes.
	 *
	 * @return bool
	 */
	public function image_should_use_cloudflare( array $atts ) : bool {
		if ( strpos( $atts['src'], '.svg' ) !== false ) {
			return false;
		}
		return true;
	}

	/**
	 * Alter an image to use Cloudflare
	 *
	 * @param array  $atts          The attachment attributes.
	 * @param object $attachment    The attachment.
	 * @param string $size          The attachment size.
	 *
	 * @return array                The modified image attributes
	 */
	public function route_images_through_cloudflare( array $atts, object $attachment, string $size ) : array {
		if ( ! $this->image_should_use_cloudflare( $atts ) ) {
			return $atts;
		}

		$image = new Cloudflare_Image( $attachment->ID, $atts );

		return $image->atts;
	}

	/**
	 * Get values from the image context
	 *
	 * @param  string $context  The image's context.
	 * @param  string $return   The val(s) to return.
	 *
	 * @return mixed            The requested values.
	 */
	public static function get_context_vals( string $context, string $return ) {

		switch ( $context ) {

			case '4-columns':
				$dimensions = array(
					'w' => 800,
					'h' => 600,
				);
				$srcset     = array(
					array(
						'h' => 123,
						'w' => 456,
					),
					array(
						'h' => 234,
						'w' => 567,
					),
				);
				$sizes      = '(max-width: 1234px) calc(100vw - 20px), calc(100vw - 20px)';
				$ratio      = '4/3';
				break;

			case '3-columns':
				$dimensions = array(
					'w' => 600,
					'h' => 400,
				);
				$srcset     = array(
					array(
						'h' => 123,
						'w' => 456,
					),
				);
				$sizes      = '(max-width: 1500px) calc(90vw - 20px), calc(90vw - 20px)';
				$ratio      = '6/5';
				break;

		}

		if ( $$return ) {
			return $$return;
		}

		return false;
	}


}
