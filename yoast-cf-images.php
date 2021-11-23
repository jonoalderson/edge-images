<?php
/**
 * Plugin Name: Yoast Cloudflare images integration
 * Version: 1.3.7
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
	define( 'YOAST_CF_IMAGES_VERSION', '1.3.7' );
}

if ( ! defined( 'YOAST_CF_IMAGES_PLUGIN_DIR' ) ) {
	define( 'YOAST_CF_IMAGES_PLUGIN_DIR', __DIR__ );
}

if ( ! defined( 'YOAST_CF_IMAGES_PLUGIN_PLUGIN_URL' ) ) {
	define( 'YOAST_CF_IMAGES_PLUGIN_PLUGIN_URL', trailingslashit( plugins_url() ) . trailingslashit( plugin_basename( __DIR__ ) ) );
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

	// Load our core functionality.
	Yoast_CF_Images\Handler::register();

	// Additional bits.
	Yoast_CF_Images\Integrations\Social_Images::register();
	Yoast_CF_Images\Integrations\Schema_Images::register();
	Yoast_CF_Images\Integrations\Preloads::register();
	Yoast_CF_Images\Integrations\XML_Sitemaps::register();

} )();

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
function get_cf_image( int $id, array $atts = array(), $size, $echo = true ) {

	$image = get_cf_image_object( $id, $atts, $size, $echo );

	// Try to fall back to a normal WP image if we didn't get an image object.
	if ( ! $image ) {
		$image = wp_get_attachment_image( $id, $size, false, $atts );
		if ( $echo ) {
			echo wp_kses( $image, array( 'img' ) );
			return;
		}
		return $image;
	}

	$html = $image->construct_img_el( true );

	// Construct the <img>, wrap it in a <picture>, and echo it.
	if ( $echo ) {
		echo wp_kses( $html, array( 'picture', 'img' ) );
		return;
	}

	// Or just return the image object.
	return $html;
}

/**
 * Get a CF image as an object
 *
 * @param  int          $id    The attachment ID.
 * @param  array        $atts  The atts to pass (see wp_get_attachment_image).
 * @param  string|array $size  The image size.
 *
 * @return false|object        The image object
 */
function get_cf_image_object( int $id, array $atts = array(), $size ) {

	// Fall back to a normal image if we don't have everything we need.
	if (
		! class_exists( 'Yoast_CF_Images\Helpers' ) ||
		! method_exists( 'Yoast_CF_Images\Helpers', 'should_transform_image' ) ||
		! Yoast_CF_Images\Helpers::should_transform_image( $id ) ||
		! $id // Maintain native failure conditions for missing/invalid IDs.
	) {
		return false;
	}

	// Get the image.
	$image = new Yoast_CF_Images\Cloudflare_Image( $id, $atts, $size );

	if ( ! $image ) {
		return false;
	}

	return $image;
}
