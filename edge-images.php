<?php

namespace Edge_Images;

/**
 * Plugin Name: Edge Images
 * Version: 2.03
 * Description: Provides support for Cloudflare's images transformation service.
 * Author: Jono Alderson
 * Text Domain: edge-images
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set our constants.
if ( ! defined( 'EDGE_IMAGES_VERSION' ) ) {
	define( 'EDGE_IMAGES_VERSION', '2.03' );
}

if ( ! defined( 'EDGE_IMAGES_PLUGIN_DIR' ) ) {
	define( 'EDGE_IMAGES_PLUGIN_DIR', __DIR__ );
}

if ( ! defined( 'EDGE_IMAGES_PLUGIN_URL' ) ) {
	define( 'EDGE_IMAGES_PLUGIN_URL', trailingslashit( plugins_url() ) . trailingslashit( plugin_basename( __DIR__ ) ) );
}

if ( ! defined( 'EDGE_IMAGES_PLUGIN_FILE' ) ) {
	define( 'EDGE_IMAGES_PLUGIN_FILE', __FILE__ );
}

/**
 * Init the plugin
 */
( function() {

	// Load our autoloaders.
	require_once 'autoload.php';
	spl_autoload_register( __NAMESPACE__ . '\autoloader' );

	// Register activation & deactivation functions
	register_activation_hook( __NAMESPACE__, 'activate_plugin' );
	register_deactivation_hook( __NAMESPACE__, 'deactivate_plugin' );

	// Load our core functionality
	Handler::register();

	// Load admin interface
	Admin::register();

	// Load features
	Features\Preloads::register();

	// Load integrations
	Integrations\Yoast_SEO\Social_Images::register();
	Integrations\Yoast_SEO\Schema_Images::register();
	Integrations\Yoast_SEO\XML_Sitemaps::register();

} )();

/**
 * Runs our plugin activation routine
 */
function activate_plugin() : void {

}

/**
 * Runs our plugin deactivation routine
 */
function deactivate_plugin() : void {

}

/**
 * Returns an edge image
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
		echo Helpers::sanitize_image_html( $html );
		return;
	}

	// Or just return the HTML.
	return Helpers::sanitize_image_html( $html );
}

/**
 * Get a an edge image as an object
 *
 * @param  int          $id    The attachment ID.
 * @param  array        $atts  The atts to pass (see wp_get_attachment_image).
 * @param  string|array $size  The image size.
 *
 * @return false|object        The image object
 */
function get_edge_image_object( int $id, array $atts = array(), $size = 'large' ) {

	// Fall back to a normal image if we don't have everything we need.
	if (
		! $id || // Maintain native failure conditions for missing/invalid IDs.
		! Helpers::should_transform_image( $id )
	) {
		return false;
	}

	// Get the image.
	$image = new Image( $id, $atts, $size );

	// Fail if we didn't get a valid image.
	if ( ! $image ) {
		return false;
	}

	return $image;
}

/**
 * Replace a SRC string with an edge version
 *
 * @param  string       $src  The src.
 * @param  string|array $size The image size.
 *
 * @return string       The modified SRC attr.
 */
function convert_src( string $src, $size = 'large' ) : string {

	if ( is_string( $size ) ) {
		$sizes          = Helpers::get_sizes_from_size( $size );
		$args['width']  = $sizes['width'];
		$args['height'] = $sizes['height'];
	}

	if ( is_array( $size ) ) {
		$args['width']  = $size[0];
		$args['height'] = $size[1];
	}

	return Helpers::edge_src( $src, $args );
}
