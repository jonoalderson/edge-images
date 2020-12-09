<?php
/**
 * Plugin Name: Yoast Cloudflare images integration
 * Version: 1.0
 * Description: Provides support for get_cf_image()
 * Author: Jono Alderson
 * Text Domain: yoast-cf-image
 *
 * @package doty-email
 */

namespace Yoast\Plugins\CF_Images;

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

// Load our autoloader.
require_once 'autoloader.php'; // Include our autoloader.
spl_autoload_register( __NAMESPACE__ . '\autoloader' );


/**
 * Load functions
 */
( function() {
	include_once 'functions.php';
	Enqueues::register();
} )();
