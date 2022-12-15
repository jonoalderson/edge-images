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
		add_filter( 'wp_get_attachment_image', array( $instance, 'decorate_edge_image' ), 100, 5 );
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_css' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_js' ), 2 );
		// add_filter( 'pre_render_block', array( $instance, 'alter_image_block_rendering' ), 10, 3 );
		add_filter( 'safe_style_css', array( $instance, 'allow_container_ratio_style' ) );
	}

	/**
	 * Get the inline styles for the container tag
	 *
	 * @param  array $attr The image attributes.
	 * @return string      The style attribute values
	 */
	private static function get_container_styles( $attr ) : string {

		$styles = array();

		// Set the aspect ratio.
		$ratio    = isset( $attr['ratio'] ) ? $attr['ratio'] : self::get_default_ratio( $attr );
		$styles[] = '--aspect-ratio:' . $ratio;

		// Add max height and width inline styles if defined.
		if ( isset( $attr['max-width'] ) ) {
			$styles[] = sprintf( 'max-width:%dpx', $attr['max-width'] );
		}
		if ( isset( $attr['max-height'] ) ) {
			$styles[] = sprintf( 'max-height:%dpx', $attr['max-height'] );
		}

		// Add height and width inline styles if this is a fixed image.
		if ( isset( $attr['layout'] ) && $attr['layout'] === 'fixed' ) {
			if ( isset( $attr['width'] ) && $attr['width'] ) {
				$styles[] = sprintf( 'max-width:%dpx', $attr['width'] );
			}
			if ( isset( $attr['height'] ) && $attr['height'] ) {
				$styles[] = sprintf( 'max-height:%dpx', $attr['height'] );
			}
		}

		return implode( ';', $styles );
	}

	/**
	 * Build a default ratio based on attrs
	 *
	 * @param  array $attr The image attributes.
	 *
	 * @return string The ratio string
	 */
	private static function get_default_ratio( $attr ) : string {
		if ( ! isset( $attr['width'] ) || ! isset( $attr['height'] ) ) {
			return '1/1';
		}
		return $attr['width'] . '/' . $attr['height'];
	}

	/**
	 * Adds our aspect ratio variable as a safe style
	 *
	 * @param  array $styles The safe styles.
	 *
	 * @return array         The filtered styles
	 */
	public function allow_container_ratio_style( $styles ) : array {

		// Bail if $styles isn't an array.
		if ( ! is_array( $styles ) ) {
			return $styles;
		}

		$styles[] = '--aspect-ratio';
		return $styles;
	}

	/**
	 * Enqueue our CSS (and render it inline)
	 *
	 * @return void
	 */
	public function enqueue_css() : void {

		// Get our stylesheet.
		$stylesheet_path = Helpers::STYLES_PATH . '/images.css';
		if ( ! file_exists( $stylesheet_path ) ) {
			return; // Bail if we couldn't find it.
		}

		// Enqueue a dummy style to attach our inline styles to.
		wp_register_style( 'edge-images', false, array(), EDGE_IMAGES_VERSION );
		wp_enqueue_style( 'edge-images' );

		// Output the stylesheet inline.
		$stylesheet = file_get_contents( $stylesheet_path );
		wp_add_inline_style( 'edge-images', $stylesheet );
	}

	/**
	 * Enqueue our JS (and output it inline)
	 *
	 * @return void
	 */
	public function enqueue_js() : void {

		// Get our script.
		$script_path = Helpers::SCRIPTS_PATH . '/main.min.js';
		if ( ! file_exists( $script_path ) ) {
			return; // Bail if we couldn't find it.
		}

		// Enqueue a dummy style to attach our inline styles to.
		wp_register_script( 'edge-images', false, array(), EDGE_IMAGES_VERSION, true );
		wp_enqueue_script( 'edge-images' );

		// Output the stylesheet inline.
		$script = file_get_contents( $script_path );
		wp_add_inline_script( 'edge-images', $script );
	}


	/**
	 * Alter block editor image rendering, and return the modified image HTML
	 *
	 * @param string|null   $pre_render   The pre-rendered content.
	 * @param array         $parsed_block The parsed block's properties.
	 * @param WP_Block|null $parent_block The parent block.
	 *
	 * @return string|null                The modified HTML content
	 */
	public function alter_image_block_rendering( $pre_render, array $parsed_block, $parent_block ) {

		// Bail if we're in the admin, but not the post editor.
		if ( ! function_exists( 'get_current_screen' ) ) {
			require_once ABSPATH . '/wp-admin/includes/screen.php';
		}
		if ( is_admin() && get_current_screen() && get_current_screen()->parent_base !== 'edit' ) {
			return $pre_render;
		}

		// Bail if this isn't an image block.
		if ( $parsed_block['blockName'] !== 'core/image' ) {
			return $pre_render;
		}

		// Bail if there's no image ID set.
		if ( ! isset( $parsed_block['attrs']['id'] ) ) {
			return $pre_render;
		}

		// Build our image.
		$atts  = $this->get_image_atts( $parsed_block );
		$image = $this->get_content_image( $parsed_block['attrs']['id'], $atts );

		// Bail if we didn't get an image; fall back to the original block.
		if ( ! $image ) {
			return $pre_render;
		}

		return $image;
	}

	/**
	 * Gets atts from the <img> to pass to the edge <img>
	 *
	 * @param  array $parsed_block The parsed block's properties.
	 *
	 * @return array               The image atts array
	 */
	private function get_image_atts( array $parsed_block ) : array {
		$atts  = array();
		$attrs = $parsed_block['attrs'];

		// Get the alt attribute if it's set.
		$atts['alt'] = Helpers::get_alt_from_img_el( $parsed_block['innerHTML'] );

		// Get the link destination if it's set.
		if ( isset( $attrs['linkDestination'] ) ) {
			$atts['href'] = $this->get_image_link( $parsed_block );
		}

		// Get the caption if there's one present.
		$caption = Helpers::get_caption_from_img_el( $parsed_block['innerHTML'] );
		if ( $caption && $caption !== '' ) {
			$atts['caption'] = $caption;
		}

		// Get the width from the attrs.
		if ( isset( $attrs['width'] ) ) {
			$atts['width'] = $attrs['width'];
		}

		// Get the height from the attrs.
		if ( isset( $attrs['height'] ) ) {
			$atts['height'] = $attrs['height'];
		}

		// Get the size from the attrs.
		if ( isset( $attrs['sizeSlug'] ) ) {
			$atts['size'] = $attrs['sizeSlug'];
		}

		// Get the alignment from the attrs.
		if ( isset( $attrs['align'] ) ) {
			$atts['align'] = $attrs['align'];
		}

		return $atts;
	}

	/**
	 * Get the image link
	 *
	 * @param  array $parsed_block The parsed block's properties.
	 *
	 * @return string              The image link
	 */
	private function get_image_link( array $parsed_block ) : string {
		switch ( $parsed_block['attrs']['linkDestination'] ) {
			case 'custom':
				$href = Helpers::get_link_from_img_el( $parsed_block['innerHTML'] );
				break;
			case 'attachment':
				$href = get_attachment_link( $parsed_block['attrs']['id'] );
				break;
			case 'media':
				$image = get_edge_image_object( $parsed_block['attrs']['id'], array(), 'full' );
				if ( ! $image ) {
					break;
				}
				$href = ( isset( $image->attrs['src'] ) ) ? $image->attrs['src'] : '';
				break;
			default:
				$href = '';
		}
		return $href;
	}

	/**
	 * Gets an image sized for display in the_content.
	 *
	 * @param  int   $id The attachment ID.
	 * @param array $atts The image attributes.
	 *
	 * @return false|Image The Edge Image
	 */
	private function get_content_image( int $id, array $atts = array() ) {

		// Get the size, or fall back to 'full'.
		$size = ( isset( $atts['size'] ) ) ? $atts['size'] : 'full';

		// Get the full-sized image.
		$image = wp_get_attachment_image_src( $id, $size );

		// Bail if the image doesn't exist.
		if ( ! $image ) {
			return false;
		}

		// If there's no specific size, constrain our image to the maximum content width, based on the ratio.
		if ( ! ( isset( $atts['width'] ) && isset( $atts['height'] ) ) ) {
			$atts = array_merge( $atts, Helpers::constrain_image_to_content_width( $image[1], $image[2] ) );
		}

		// If there's a specific size set, use this for our max height/width.
		if ( isset( $atts['width'] ) ) {
			$atts['max-width'] = $atts['width'];
		}
		if ( isset( $atts['height'] ) ) {
			$atts['max-height'] = $atts['height'];
		}

		// Add WP's native block class(es).
		if ( ! isset( $atts['container-class'] ) ) {
			$atts['container-class'] = array();
		}
		$atts['container-class'][] = 'wp-block-image';

		// Add alignment.
		if ( isset( $atts['align'] ) ) {
			$atts['container-class'][] = 'align' . $atts['align'];
		}

		// Get our transformed image.
		$image = get_edge_image( $id, $atts, 'content', false );

		// Bail if we didn't get an image.
		if ( ! $image ) {
			return false;
		}

		return $image;
	}

	/**
	 * Decorate our edge image with appropriate atts and markup
	 *
	 * @param  string $html             The <img> HTML.
	 * @param  int    $attachment_id    The attachment ID.
	 * @param  mixed  $size             The image size.
	 * @param  bool   $icon             Whether to use an icon.
	 * @param  array  $attr             The image attributes.
	 *
	 * @return string                   The modified HTML.
	 */
	public static function decorate_edge_image( $html = '', $attachment_id = 0, $size = false, $icon = false, $attr = array() ) : string {

		// Bail if there's no HTML.
		if ( ! $html ) {
			return '';
		}

		// Bail if there's no attachment ID.
		if ( ! $attachment_id ) {
			return $html;
		}

		// Bail if this image has been excluded via a filter.
		if ( ! Helpers::should_transform_image( $attachment_id ) ) {
			return $html;
		}

		$attr = self::maybe_backfill_missing_dimensions( $html, $size, $attr );
		$html = self::maybe_wrap_image_in_link( $html, $attr );
		$html = self::maybe_add_caption( $html, $attr );
		$html = self::maybe_wrap_image_in_container( $attachment_id, $html, $attr );
		$html = Helpers::sanitize_image_html( $html );

		return $html;
	}

	/**
	 * Construct a viable <picture> even when the image is missing.
	 *
	 * @param  string $html             The <img> HTML.
	 * @param  mixed  $size             The image size.
	 * @param  array  $attr             The image attributes.
	 *
	 * @return array                    The modified $attr array
	 */
	public static function maybe_backfill_missing_dimensions( $html, $size, $attr ) : array {

		// Bail if the height and width are set (because then we know we have a valid image).
		if ( isset( $attr['height'] ) && isset( $attr['width'] ) ) {
			return $attr;
		}

		// Get the normalized size string.
		$normalized_size = Helpers::normalize_size_attr( $size );

		// Get the edge image sizes array.
		$sizes = apply_filters( 'Edge_Images\sizes', Helpers::get_wp_image_sizes() );

		// Grab the attrs for the image size, or continue with defaults.
		if ( array_key_exists( $normalized_size, $sizes ) ) {
			$attr = wp_parse_args( $sizes[ $normalized_size ], Helpers::get_default_image_attrs() );
		}

		return $attr;
	}


	/**
	 * Maybe wrap the image in a link.
	 *
	 * @param  string $html The image HTML.
	 * @param  array  $attr The image attributes.
	 *
	 * @return string       The modified HTML
	 */
	private static function maybe_wrap_image_in_link( string $html, array $attr ) : string {

		// Bail if there's no link.
		if ( ! isset( $attr['href'] ) || ! $attr['href'] ) {
			return $html;
		}

		$html = sprintf(
			'<a href="%s">%s</a>',
			$attr['href'],
			$html
		);

		return $html;
	}

	/**
	 * Maybe add a caption.
	 *
	 * @param  string $html The image HTML.
	 * @param  array  $attr The image attributes.
	 *
	 * @return string       The modified HTML
	 */
	private static function maybe_add_caption( string $html, array $attr ) : string {

		// Bail if there's no link.
		if ( ! isset( $attr['caption'] ) || ! $attr['caption'] ) {
			return $html;
		}

		$html = sprintf(
			'%s<figcaption>%s</figcaption>',
			$html,
			$attr['caption']
		);

		return $html;
	}

	/**
	 * Maybe wrap the image in a container.
	 *
	 * @param  int    $attachment_id The attachment ID.
	 * @param  string $html The image HTML.
	 * @param  array  $attr The image attributes.
	 *
	 * @return string       The modified HTML
	 */
	private static function maybe_wrap_image_in_container( int $attachment_id, string $html, array $attr ) : string {

		// Bail if image wrapping is disabled.
		if ( apply_filters( 'Edge_Images\disable_container_wrap', false ) === true ) {
			return $html;
		}

		$html = sprintf(
			'<picture style="%s" class="%s %s">%s</picture>',
			self::get_container_styles( $attr ),
			( isset( $attr['container-class'] ) ) ? Helpers::classes_array_to_string( $attr['container-class'] ) : null,
			'image-id-' . $attachment_id,
			$html,
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
	public function remove_dimension_attributes( $html = '', $attachment_id, $size = false, $icon = false, $attr = array() ) : string {

		// Bail if there's no HTML.
		if ( ! $html ) {
			return '';
		}

		// Bail if there's no attachment ID.
		if ( ! $attachment_id ) {
			return $html;
		}

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

		// Bail if we shouldn't be transforming any images.
		if ( ! Helpers::should_transform_images() ) {
			return false;
		}

		return true;
	}

	/**
	 * Alter an image to use the edge
	 *
	 * @param array        $attrs      The attachment attributes.
	 * @param \WP_Post     $attachment The attachment.
	 * @param string|array $size             The attachment size.
	 *
	 * @return array             The modified image attributes
	 */
	public function route_images_through_edge( $attrs, $attachment, $size ) : array {

		// Bail if $attrs isn't an array, or if it's empty.
		if ( ! is_array( $attrs ) || empty( $attrs ) ) {
			return $attrs;
		}

		// Bail if $attachment isn't a WP_POST.
		if ( ! is_a( $attachment, '\WP_POST' ) ) {
			return $attrs;
		}

		// Bail if $size isn't a string or an array.
		if ( ! ( is_string( $size ) || is_array( $size ) ) ) {
			return $attrs;
		}

		// Bail if this image shouldn't use the edge.
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
