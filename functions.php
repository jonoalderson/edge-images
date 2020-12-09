<?php

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and renders a CF image
 *
 * @param int   $id   The attachment ID.
 * @param array $args Optional args.
 * @param array $sizes Optional sizes args.
 */
function get_cf_image( $id, $args = array(), $sizes = array() ) {
	$image = new \Yoast\Plugins\CF_Images\CF_Image( $id, $args, $sizes );
	$image->render();
}
