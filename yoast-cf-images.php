<?php
/**
 * Plugin Name: Yoast Cloudflare images integration
 * Version: 1.0.1
 * Description: Provides support for Cloudflared images
 * Author: Jono Alderson
 * Text Domain: yoast-cf-image
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set our constants.
if ( ! defined( 'YOAST_CF_IMAGES_VERSION' ) ) {
	define( 'YOAST_CF_IMAGES_VERSION', '1.0.1' );
}

if ( ! defined( 'YOAST_CF_IMAGES_PLUGIN_DIR' ) ) {
	define( 'YOAST_CF_IMAGES_PLUGIN_DIR', __DIR__ );
}

if ( ! defined( 'YOAST_CF_IMAGES_PLUGIN_FILE' ) ) {
	define( 'YOAST_CF_IMAGES_PLUGIN_FILE', __FILE__ );
}

/**
 * Init the plugin
 */
( function() {

	// Load our autoloaders.
	require_once 'autoload.php';

	Yoast_CF_Images\Cloudflare_Image_Handler::register();

} )();

/**
 * Returns a Cloudflared image
 *
 * @param  int    $id    The attachment ID.
 * @param  array  $atts  The atts to pass (see wp_get_attachment_image).
 * @param  string $size  The image size.
 *
 * @return void
 */
function get_cf_image( int $id, array $atts = array(), string $size ) : void {
	$image = new Yoast_CF_Images\Cloudflare_Image( $id, $atts, $size );
	if ( ! $image ) {
		return;
	}

	$html = sprintf(
		'<img %s>',
		implode( ' ', $image->atts )
	);

	$html = Yoast_CF_Images\Cloudflare_Image_Handler::wrap_in_picture( $html, $image->id, $size, false, $atts );
	echo $html;
	die;
}
