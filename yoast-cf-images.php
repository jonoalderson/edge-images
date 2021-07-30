<?php
/**
 * Plugin Name: Yoast Cloudflare images integration
 * Version: 1.0
 * Description: Provides support for Cloudflared images
 * Author: Jono Alderson
 * Text Domain: yoast-cf-image
 */

namespace Yoast_CF_Images;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set our constants.
if ( ! defined( 'YOAST_CF_IMAGES_VERSION' ) ) {
	define( 'YOAST_CF_IMAGES_VERSION', '1.0.0' );
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

	Cloudflare_Image_Handler::register();

} )();
