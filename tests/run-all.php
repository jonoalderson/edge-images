<?php
/**
 * Run the full Edge Images CLI test suite.
 *
 * Usage: php tests/run-all.php
 *
 * @package Edge_Images
 */

declare(strict_types=1);

$root  = __DIR__;
$suites = [
	'test-layout.php',
	'test-provider-urls.php',
	'test-providers-all.php',
	'test-transformer.php',
	'test-localizer.php',
	'test-smoke.php',
];

$failed = 0;

foreach ( $suites as $suite ) {
	$path = $root . '/' . $suite;
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "MISSING: {$suite}\n" );
		++$failed;
		continue;
	}

	fwrite( STDOUT, "\n======== {$suite} ========\n" );
	passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $path ), $code );
	if ( 0 !== (int) $code ) {
		++$failed;
	}
}

fwrite( STDOUT, "\n======== SUMMARY ========\n" );
if ( $failed > 0 ) {
	fwrite( STDERR, "FAILED suites: {$failed}\n" );
	exit( 1 );
}

fwrite( STDOUT, "All suites passed.\n" );
exit( 0 );
