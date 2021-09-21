<?php

namespace Yoast_CF_Images;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An autoloader for the plugin's classes
 *
 * @param string $class_name The name of the requested class.
 *
 * @return bool Whether or not the requested class was found.
 */
function autoloader( $class_name ) {
	if ( strpos( $class_name, __NAMESPACE__ ) !== 0 ) {
		return false;
	}

	$class_name = str_replace( array( __NAMESPACE__ . '\\' ), '', $class_name );
	$class_name = str_replace( '_', '-', $class_name );
	$class_name = explode( '\\', $class_name );

	$class_name[] = 'class-' . array_pop( $class_name );
	$class_name   = implode( '/', $class_name );
	$class_name   = strtolower( $class_name );

	$path = sprintf(
		'%1$s%2$s%3$s.php',
		realpath( dirname( __FILE__ ) ),
		'/classes/',
		$class_name
	);

	if ( file_exists( $path ) ) {
		include $path;
		return true;
	}

	return false;
}

spl_autoload_register( __NAMESPACE__ . '\autoloader' );
