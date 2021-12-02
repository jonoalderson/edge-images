<?php

namespace Edge_Images;

use Edge_Images\Helpers;
use Edge_Images\Image;

/**
 * Filters wp_get_attachment_image and related functions to use the edge.
 */
class Handler {

	/**
	 * Register the integration
	 *
	 * @TODO: Add a wp_calculate_image_sizes filter.
	 *
	 * @return void
	 */
	public static function register() : void {

		// Bail if we shouldn't be transforming images.
		if ( ! Helpers::should_transform_images() ) {
			return;
		}

		$instance = new self();
		add_filter( 'wp_get_attachment_image_attributes', array( $instance, 'route_images_through_edge' ), 100, 3 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'remove_dimension_attributes' ), 10, 5 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'wrap_in_picture' ), 100, 5 );
		add_action( 'wp_head', array( $instance, 'enqueue_css' ), 2 );
		add_filter( 'render_block', array( $instance, 'alter_image_block_rendering' ), 100, 5 );
		add_filter( 'safe_style_css', array( $instance, 'allow_picture_ratio_style' ) );
	}

	/**
	 * Adds our aspect ratio variable as a safe style
	 *
	 * @param  array $styles The safe styles.
	 *
	 * @return array         The filtered styles
	 */
	public function allow_picture_ratio_style( array $styles ) : array {
		$styles[] = '--aspect-ratio';
		return $styles;
	}

	/**
	 * Enqueue our CSS
	 *
	 * @return void
	 */
	public function enqueue_css() : void {
		wp_enqueue_style( 'edge-images', Helpers::STYLES_URL . '/images.css', array(), EDGE_IMAGES_VERSION );
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
		// Bail if this isn't an image block.
		if ( 'core/image' !== $block['blockName'] ) {
			return $block_content;
		}

		// If there's no ID fall back.
		if ( ! isset( $block['attrs']['id'] ) ) {
			return $block_content;
		}

		// Get the height and width of our full-sized image.
		$image = wp_get_attachment_image_src( $block['attrs']['id'], 'full' );
		if ( ! $image ) {
			return $block_content;
		}

		// Constrain our image to the maximum content width.
		$attrs = $this->constrain_dimensions_to_content_width( $image[1], $image[2] );

		// Get our transformed image.
		$image = get_edge_image( $block['attrs']['id'], $attrs, 'content', false );

		// Bail if we didn't get an image; fall back to the original block.
		if ( ! $image ) {
			return $block_content;
		}

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

		// Bail if this image has been excluded via a filter.
		if ( ! Helpers::should_transform_image( $attachment_id ) ) {
			return $html;
		}

		// Bail if the HTML is missing or empty.
		if ( ! $html || $html === '' ) {
			return '';
		}

		// Construct the HTML.
		$html = sprintf(
			'<picture style="--aspect-ratio:%s" class="layout-%s %s">%s</picture>',
			isset( $attr['ratio'] ) ? $attr['ratio'] : '1/1',
			isset( $attr['layout'] ) ? $attr['layout'] : 'unknown',
			isset( $attr['picture-class'] ) ? Helpers::classes_array_to_string( $attr['picture-class'] ) : null,
			$html
		);

		$html = wp_kses(
			$html,
			array(
				'picture' => Helpers::allowed_picture_attrs(),
				'img'     => Helpers::allowed_img_attrs(),
			)
		);

		return $html;
	}

	/**
	 * Remove the (first two) height and width attrs from <img> markup.
	 *
	 * NOTE: Widthout this, we create duplicate properties
	 *       with wp_get_attachment_image_attributes!
	 *
	 * @param  string $html             The <img> HTML.
	 * @param  int    $attachment_id    The attachment ID.
	 * @param  mixed  $size             The image size.
	 * @param  bool   $icon             Whether to use an icon.
	 * @param  array  $attr             The image attributes.
	 *
	 * @return string       The modified tag
	 */
	public function remove_dimension_attributes( string $html, int $attachment_id = 0, $size = false, bool $icon = false, $attr = array() ) : string {

		// Bail if this image has been excluded via a filter.
		if ( ! Helpers::should_transform_image( $attachment_id ) ) {
			return $html;
		}

		$html = preg_replace( '/(width|height)="\d*"\s/', '', $html, 2 );
		return $html;
	}

	/**
	 * Check whether an image should use the edge
	 *
	 * @param int   $id The attachment ID.
	 *
	 * @param  array $attrs The attachment attributes.
	 *
	 * @return bool
	 */
	public function image_should_use_edge( int $id, array $attrs ) : bool {

		// Bail if we shouldn't be transforming this image.
		if ( ! Helpers::should_transform_image( $id ) ) {
			return false;
		}

		// Placeholder logic for broader exclusion.

		return true;
	}

	/**
	 * Alter an image to use the edge
	 *
	 * @param array        $attrs      The attachment attributes.
	 * @param object       $attachment The attachment.
	 * @param string|array $size The attachment size.
	 *
	 * @return array             The modified image attributes
	 */
	public function route_images_through_edge( array $attrs, object $attachment, $size ) : array {
		if ( ! $this->image_should_use_edge( $attachment->ID, $attrs ) ) {
			return $attrs;
		}

		// Get the image object.
		$image = new Image( $attachment->ID, $attrs, $size );

		// Flatten the array properties.
		$image->flatten_array_properties();

		return $image->attrs;
	}

}
