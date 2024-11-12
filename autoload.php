<?php
/**
 * Edge Images plugin autoloader file.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @copyright  2024 Jono Alderson
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 *
 * This file handles the autoloading of all plugin classes.
 * It follows PSR-4 autoloading standards and converts namespace paths
 * to file paths according to WordPress coding standards.
 */

namespace Edge_Images;

// Avoid direct calls to this file.
if ( ! defined( 'EDGE_IMAGES_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * An autoloader for the plugin's classes
 *
 * @since 1.0.0
 * 
 * @param string $class_name The name of the requested class.
 * @return bool Whether or not the requested class was found.
 */
function autoloader( string $class_name ): bool {
	// Bail if the class isn't in our namespace.
	if ( strpos( $class_name, __NAMESPACE__ ) !== 0 ) {
		return false;
	}

	// Tidy up the class name.
	$class_name = str_replace( array( __NAMESPACE__ . '\\' ), '', $class_name );
	$class_name = str_replace( '_', '-', $class_name );
	$class_name = explode( '\\', $class_name );

	// Align to our syntax and directory structure.
	$class_name[] = 'class-' . array_pop( $class_name );
	$class_name   = implode( '/', $class_name );
	$class_name   = strtolower( $class_name );

	// Construct the path.
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

// Register the autoloader.
spl_autoload_register( __NAMESPACE__ . '\autoloader' );
