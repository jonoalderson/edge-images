<?php

/**
 * Replace a SRC string with an edge version
 *
 * @param  string       $src  The src.
 * @param  string|array $size The image size.
 * @param  array        $args The args.
 *
 * @return string       The modified SRC attr.
 */
function get_edge_image_from_src( string $src, $size = 'large', array $args = array() ) : string {

	// Get the attachment ID from the string.
	$attachment_id = attachment_url_to_postid( $src );
	if ( ! $attachment_id ) {
		// Do a direct replacement if we couldn't find one.
		return Helpers::edge_src( $src, $args, $size );
	}

	$image = get_edge_image( $attachment_id, $args, $size, false );
	return $image;
}

/**
 * Returns a Cloudflared image
 *
 * @param  int          $id    The attachment ID.
 * @param  array        $atts  The atts to pass (see wp_get_attachment_image).
 * @param  string|array $size  The image size.
 * @param  bool         $echo  If the image should be echo'd.
 *
 * @return false|string  The image HTML
 */
function get_edge_image( int $id, array $atts = array(), $size = 'large', bool $echo = true ) {

	// Bail if this isn't a valid image ID.
	if ( get_post_type( $id ) !== 'attachment' ) {
		return;
	}

	// Get the image object.
	$image = get_edge_image_object( $id, $atts, $size );

	// Try to fall back to a normal WP image if we didn't get an image object.
	if ( ! $image ) {

		$image = wp_get_attachment_image( $id, $size, false, $atts );
		if ( $echo ) {
			echo wp_kses( $image, array( 'img' ) );
			return;
		}
		return $image;
	}

	// Construct the <img>, and wrap it in a <picture>.
	$html = $image->construct_img_el( true );

	if ( $echo ) {
		// Echo the image.
		echo wp_kses( $html, array( 'picture', 'figure', 'img', 'a' ) );
		return;
	}

	// Or just return the HTML.
	return $html;
}
