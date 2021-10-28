<?php

namespace Yoast_CF_Images\Integrations;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;
use Yoast_CF_Images\Cloudflare_Image;

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
		add_filter( 'wp_get_attachment_image', array( $instance, 'remove_data_attributes' ), PHP_INT_MAX - 100 );
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

		$attrs = $this->constrain_dimensions_to_content_width( $image[1], $image[2] );

		$image = get_cf_image( $block['attrs']['id'], $attrs, 'content', false );
		$image = $this->remove_data_attributes( $image );

		return $image;
	}

	/**
	 * Constrain the width of the image to the max content width
	 *
	 * @param  int $w The width.
	 * @param  int $h The height.
	 *
	 * @return array The attrs values
	 */
	private function constrain_dimensions_to_content_width( int $w, int $h ) : array {
		$attrs['width']  = $w;
		$attrs['height'] = $h;
		$content_width   = Helpers::get_content_width();

		// Calculate the ratio and constrain the width.
		if ( $w > $content_width ) {
			$ratio           = $content_width / $w;
			$attrs['width']  = $content_width;
			$attrs['height'] = ceil( $h * $ratio );
		}

		return $attrs;
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
	public static function wrap_in_picture( string $html, int $attachment_id = 0, $size = false, bool $icon = false, $attr = array() ) : string {

		// Construct the HTML.
		$html = sprintf(
			'<picture style="--aspect-ratio:%s" class="layout-%s %s">%s</picture>',
			isset( $attr['data-ratio'] ) ? $attr['data-ratio'] : '1:1',
			isset( $attr['data-layout'] ) ? $attr['data-layout'] : 'unknown',
			isset( $attr['data-picture-class'] ) ? Helpers::classes_array_to_string( $attr['data-picture-class'] ) : null,
			$html
		);

		return $html;
	}

	/**
	 * Remove the (first two) height and width attrs from <img> markup
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
	 * Remove data- attributes from the <img> tag
	 *
	 * @param  string $html The HTML <img> tag.
	 *
	 * @return string       The modified tag
	 */
	public function remove_data_attributes( string $html ) : string {
		$html = preg_replace( '/data-([^"]+)="[^"]+"/i', '', $html );
		$html = preg_replace( '/data-([^\']+)=\'[^\']+\'/i', '', $html );
		$html = preg_replace( '/\s+/', ' ', $html );
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
	 * @param  array $attrs The attachment attributes.
	 *
	 * @return bool
	 */
	public function image_should_use_cloudflare( array $attrs ) : bool {
		if ( strpos( $attrs['src'], '.svg' ) !== false ) {
			return false;
		}
		return true;
	}

	/**
	 * Alter an image to use Cloudflare
	 *
	 * @param array        $attrs      The attachment attributes.
	 * @param object       $attachment The attachment.
	 * @param string|array $size The attachment size.
	 *
	 * @return array             The modified image attributes
	 */
	public function route_images_through_cloudflare( array $attrs, object $attachment, $size ) : array {
		if ( ! $this->image_should_use_cloudflare( $attrs ) ) {
			return $attrs;
		}

		// Get the image object.
		$image = new Cloudflare_Image( $attachment->ID, $attrs, $size );

		// Flatten the array properties.
		$image->flatten_array_properties();

		return $image->attrs;
	}

}
