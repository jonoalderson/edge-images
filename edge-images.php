<?php

namespace Edge_Images;

/**
 * Plugin Name: Edge Images
 * Version: 1.5.2
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
	define( 'EDGE_IMAGES_VERSION', '1.5.2' );
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

	// Load our core functionality.
	Handler::register();

	// Features.
	Features\Preloads::register();

	// Integrations.
	Integrations\Yoast_SEO\Social_Images::register();
	Integrations\Yoast_SEO\Schema_Images::register();
	Integrations\Yoast_SEO\XML_Sitemaps::register();

} )();


if ( ! function_exists( 'get_edge_image' ) ) {
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
	function get_edge_image( int $id, array $atts = array(), $size, bool $echo = true ) {

		// Bail if this isn't a valid image ID.
		if ( ! is_attachment( $id ) ) {
			return;
		}

		// Get the image object.
		$image = get_edge_image_object( $id, $atts, $size, $echo );

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
			echo wp_kses( $html, array( 'picture', 'img' ) );
			return;
		}

		// Or just return the HTML.
		return $html;
	}
}

if ( ! function_exists( 'get_edge_image_object' ) ) {
	/**
	 * Get a an edge image as an object
	 *
	 * @param  int          $id    The attachment ID.
	 * @param  array        $atts  The atts to pass (see wp_get_attachment_image).
	 * @param  string|array $size  The image size.
	 *
	 * @return false|object        The image object
	 */
	function get_edge_image_object( int $id, array $atts = array(), $size ) {

		// Fall back to a normal image if we don't have everything we need.
		if (
			! is_attachment( $id ) || // If this isn't a valid image ID.
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
}
