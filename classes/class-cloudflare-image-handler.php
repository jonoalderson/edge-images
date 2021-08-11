<?php

namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;

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
		add_filter( 'wp_get_attachment_image_attributes', array( $instance, 'route_images_through_cloudflare' ), 100, 3 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'remove_dimension_attributes' ), 10 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'remove_style_attribute' ), 10 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'wrap_in_picture' ), 1000, 5 );
		add_action( 'wp_head', array( $instance, 'enqueue_css' ), 2 );
		add_filter( 'render_block', array( $instance, 'alter_image_block_rendering' ), 1000, 5 );
	}

	/**
	 * Enqueue our CSS
	 *
	 * @return void
	 */
	public function enqueue_css() : void {
		wp_enqueue_style( 'yoast-cf-images-image', Helpers::STYLES_URL . '/images.css', array(), YOAST_CF_IMAGES_VERSION );
	}

	/**
	 * Alter block editor image rendering
	 *
	 * TODO: Account for when images are linked (via $block['attrs']['linkDestination']).
	 * TODO: Account for gallery blocks.
	 * TODO: Account for figure/figcaption.
	 *
	 * @param  string $block_content  The block's HTML content.
	 * @param  array  $block           The block's properties.
	 *
	 * @return string                 The block's modified content
	 */
	public function alter_image_block_rendering( $block_content, $block ) : string {
		if ( 'core/image' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! isset( $block['attrs']['id'] ) ) {
			return $block_content;
		}

		$image = wp_get_attachment_image_src( $block['attrs']['id'], 'full' );
		if ( ! $image ) {
			return $block_content;
		}

		$atts  = $this->constrain_dimensions_to_content_width( $image[1], $image[2] );
		$image = get_cf_image( $block['attrs']['id'], $atts, 'content', false );

		return $image;
	}

	/**
	 * Constrain the width of the image to the max content width
	 *
	 * @param  int $w The width.
	 * @param  int $h The height.
	 *
	 * @return array The atts values
	 */
	private function constrain_dimensions_to_content_width( int $w, int $h ) : array {
		$w             = $sizes[0];
		$h             = $sizes[1];
		$content_width = Helpers::get_content_width();

		if ( $w > $content_width ) {
			$ratio          = $content_width / $h;
			$atts['width']  = $content_width;
			$atts['height'] = ceil( $w * $ratio );
		}

		return $atts;
	}

	/**
	 * Wrap our image tags in a <picture> to use the aspect ratio approach
	 *
	 * @param  string $html             The <img> HTML.
	 * @param  int    $attachment_id    The attachment ID.
	 * @param  mixed  $size             The image size.
	 * @param  bool   $icon             Whether to use an icon.
	 * @param  array  $attr             The image attributes.
	 *
	 * @return string                   The modified HTML.
	 */
	public static function wrap_in_picture( string $html, int $attachment_id = 0, $size = false, bool $icon = false, array $attr = array() ) : string {

		$html = sprintf(
			'<picture style="--aspect-ratio:%s" class="layout-%s %s">%s</picture>',
			$attr['data-ratio'],
			$attr['data-layout'],
			$size,
			$html
		);

		return $html;
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

		$image = new Cloudflare_Image( $attachment->ID, $atts, $size );

		return $image->atts;
	}

	/**
	 * Get values from the image context
	 *
	 * @param  string $size  The image's size.
	 * @param  string $return   The val(s) to return.
	 *
	 * @return mixed            The requested values.
	 */
	public static function get_context_vals( string $size, string $return ) {

		switch ( $size ) {

			case '4-columns': // An example layout.
				$dimensions = array(
					'w' => 800,
					'h' => 600,
				);
				$srcset     = array(
					array(
						'w' => 456,
						'h' => 123,
					),
					array(
						'w' => 567,
						'h' => 234,
					),
				);
				$sizes      = '(max-width: 1234px) calc(100vw - 20px), calc(100vw - 20px)';
				$ratio      = '4/3';
				break;

			case '3-columns': // An example layout.
				$dimensions = array(
					'w' => 600,
					'h' => 400,
				);
				$srcset     = array(
					array(
						'w' => 456,
						'h' => 123,
					),
				);
				$sizes      = '(max-width: 1500px) calc(90vw - 20px), calc(90vw - 20px)';
				$ratio      = '6/5';
				break;

			case 'avatar': // An example custom fixed layout.
				$dimensions = array(
					'w' => 128,
					'h' => 128,
				);
				$layout     = 'fixed';
				$sizes      = '(max-width: 128px) 100vw, 128px';
				break;

			default: // Set some sensible fallback behavior.
				$vals = self::get_wp_size_vals( $size );
				if ( ! $vals ) {
					$vals = self::get_wp_size_vals( 'large' );
				}

				foreach ( self::get_image_vals_keys() as $key ) {
					if ( ! isset( $vals[ $key ] ) ) {
						continue;
					}
					$$key = $vals[ $key ];
				}
				break;
		}

		// Thumbnails should always be 'fixed'.
		if ( $size === 'thumbnail' ) {
			$layout = 'fixed';
		}

		if ( isset( $$return ) && $return ) {
			return $$return;
		}

		return false;
	}

	/**
	 * Get the vals for a WP image size
	 *
	 * @param  string $size The size.
	 *
	 * @return false|array  The values
	 */
	private static function get_wp_size_vals( string $size ) {
		$image_sizes         = array();
		$default_image_sizes = get_intermediate_image_sizes();

		if ( ! in_array( $size, $default_image_sizes, true ) ) {
			return false;
		}

		$key = array_search( $size, $default_image_sizes, true );

		$vals = array(
			'dimensions' => array(
				'w' => intval( get_option( "{$default_image_sizes[$key]}_size_w" ) ),
				'h' => intval( get_option( "{$default_image_sizes[$key]}_size_h" ) ),
			),
		);

		return $vals;

	}

	/**
	 * Get all of the customizable vals for image sizes
	 *
	 * @return array The keys.
	 */
	private static function get_image_vals_keys() : array {
		return array(
			'dimensions',
			'srcset',
			'sizes',
			'ratio',
			'layout',
		);
	}


}
