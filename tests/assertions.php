<?php
/**
 * Minimal assertion helpers for CLI tests.
 *
 * @package Edge_Images
 */

declare(strict_types=1);

namespace Edge_Images\Tests;

/**
 * Shared failure counter.
 *
 * @var int
 */
$GLOBALS['edge_images_failures'] = $GLOBALS['edge_images_failures'] ?? 0;

/**
 * Assert strict equality.
 *
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 * @param string $message  Message.
 * @return void
 */
function assert_same( $expected, $actual, string $message ): void {

	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n" );
		++$GLOBALS['edge_images_failures'];
	} else {
		fwrite( STDOUT, "PASS: {$message}\n" );
	}
}

/**
 * Assert true.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 * @return void
 */
function assert_true( bool $condition, string $message ): void {

	assert_same( true, $condition, $message );
}

/**
 * Assert string contains.
 *
 * @param string $needle   Needle.
 * @param string $haystack Haystack.
 * @param string $message  Message.
 * @return void
 */
function assert_contains( string $needle, string $haystack, string $message ): void {

	if ( ! str_contains( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: {$message}\n  missing: {$needle}\n  in: {$haystack}\n" );
		++$GLOBALS['edge_images_failures'];
	} else {
		fwrite( STDOUT, "PASS: {$message}\n" );
	}
}

/**
 * Assert string does not contain.
 *
 * @param string $needle   Needle.
 * @param string $haystack Haystack.
 * @param string $message  Message.
 * @return void
 */
function assert_not_contains( string $needle, string $haystack, string $message ): void {

	if ( str_contains( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: {$message}\n  unexpectedly found: {$needle}\n  in: {$haystack}\n" );
		++$GLOBALS['edge_images_failures'];
	} else {
		fwrite( STDOUT, "PASS: {$message}\n" );
	}
}

/**
 * Exit with status from failure count.
 *
 * @param string $suite Suite name.
 * @return never
 */
function finish( string $suite ): void {

	$failures = (int) $GLOBALS['edge_images_failures'];
	if ( $failures > 0 ) {
		fwrite( STDERR, "FAILED: {$suite} ({$failures})\n" );
		exit( 1 );
	}

	fwrite( STDOUT, "OK: {$suite}\n" );
	exit( 0 );
}
