<?php
/**
 * Edge Images plugin autoloader file.
 *
 * This file handles the autoloading of all plugin classes. It follows PSR-4 autoloading 
 * standards and converts namespace paths to file paths according to WordPress coding standards.
 * The autoloader supports class names in the format Edge_Images\Class_Name and maps them to
 * files in the classes directory.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @copyright  2024 Jono Alderson
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

namespace Edge_Images;

// Avoid direct calls to this file.
if ( ! defined( 'EDGE_IMAGES_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * An autoloader for the plugin's classes.
 *
 * Converts class names in the Edge_Images namespace to file paths and includes them.
 * For example:
 * - Edge_Images\Handler becomes /classes/class-handler.php
 * - Edge_Images\Edge_Providers\Cloudflare becomes /classes/edge-providers/class-cloudflare.php
 *
 * @since 1.0.0
 * 
 * @param string $class_name The fully qualified class name to load.
 * @return bool Whether the class was successfully loaded.
 */
function autoloader( string $class_name ): bool {
	// Bail if the class isn't in our namespace.
	if ( strpos( $class_name, __NAMESPACE__ ) !== 0 ) {
		return false;
	}

	// Remove namespace prefix and clean up class name.
	$class_name = str_replace( array( __NAMESPACE__ . '\\' ), '', $class_name );
	$class_name = str_replace( '_', '-', $class_name );
	$class_name = explode( '\\', $class_name );

	// Add 'class-' prefix to the final component.
	$class_name[] = 'class-' . array_pop( $class_name );
	$class_name   = implode( '/', $class_name );
	$class_name   = strtolower( $class_name );

	// Build the full file path.
	$path = sprintf(
		'%1$s%2$s%3$s.php',
		realpath( dirname( __FILE__ ) ),
		'/classes/',
		$class_name
	);

	// Include the file if it exists.
	if ( file_exists( $path ) ) {
		include $path;
		return true;
	}

	return false;
}

// Register our autoloader with PHP.
spl_autoload_register( __NAMESPACE__ . '\autoloader' );