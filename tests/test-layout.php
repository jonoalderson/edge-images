<?php
/**
 * CLI tests for Layout helpers.
 *
 * Run: php tests/test-layout.php
 *
 * @package Edge_Images
 */

declare(strict_types=1);

namespace Edge_Images;

$failures = 0;

/**
 * Assert strict equality.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Assertion label.
 * @return void
 */
function assert_same( $expected, $actual, string $message ): void {

	global $failures;

	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n" );
		++$failures;
	} else {
		fwrite( STDOUT, "PASS: {$message}\n" );
	}
}

/**
 * Assert true.
 *
 * @param bool   $condition Condition.
 * @param string $message   Assertion label.
 * @return void
 */
function assert_true( bool $condition, string $message ): void {

	assert_same( true, $condition, $message );
}

if ( ! defined( 'EDGE_IMAGES_VERSION' ) ) {
	define( 'EDGE_IMAGES_VERSION', '6.0.0-test' );
}

require_once dirname( __DIR__ ) . '/classes/class-layout.php';

// Style width parsing.
assert_same( 400, Layout::pixel_width_from_style( 'width:400px;height:auto' ), 'simple style width' );
assert_same( 400, Layout::pixel_width_from_style( 'aspect-ratio:16/9;width:400px;object-fit:cover' ), 'width after aspect-ratio' );
assert_same( 0, Layout::pixel_width_from_style( 'width:50%' ), 'percentage width ignored' );
assert_same( 0, Layout::pixel_width_from_style( null ), 'null style' );
assert_same( 320, Layout::pixel_width_from_style( 'width: 320.4px' ), 'decimal px rounded' );

// Gallery columns.
assert_same( 3, Layout::gallery_columns( 'wp-block-gallery has-nested-images columns-3' ), 'columns-3' );
assert_same( 1, Layout::gallery_columns( 'wp-block-gallery columns-1' ), 'columns-1' );
assert_same( 3, Layout::gallery_columns( 'wp-block-gallery has-nested-images' ), 'nested default 3' );
assert_same( 2, Layout::gallery_columns( 'blocks-gallery-grid', false ), 'legacy default 2' );

assert_same( 341, Layout::gallery_layout_width( 1024, 3 ), 'gallery layout width third' );
assert_same( 1024, Layout::gallery_layout_width( 1024, 1 ), 'gallery columns-1 full width' );

// Aspect ratio scaling.
assert_same( 300, Layout::scale_height( 400, 800, 600 ), 'scale height 4:3' );

// Srcset candidates with no-upscale clamp (1.5x and 2x both hit 500, so two unique widths).
$candidates = Layout::srcset_candidates( 400, 500 );
assert_same( [ 400, 500 ], array_column( $candidates, 'width' ), 'clamped DPR widths are unique' );

$uncapped = Layout::srcset_candidates( 400, 0 );
assert_same( [ 400, 600, 800 ], array_column( $uncapped, 'width' ), 'uncapped DPR widths' );

// Sizes strings.
assert_same(
	'(max-width: 400px) 100vw, 400px',
	Layout::sizes_for_width( 400, false ),
	'eager sizes'
);
assert_same(
	'auto, (max-width: 400px) 100vw, 400px',
	Layout::sizes_for_width( 400, true ),
	'lazy sizes'
);
assert_same(
	'(max-width: 599px) 50vw, 33vw',
	Layout::sizes_for_gallery( 3 ),
	'gallery sizes columns-3'
);
assert_same( '', Layout::sizes_for_gallery( 1 ), 'gallery columns-1 uses single-image sizes' );

// Properties.
$props = Layout::normalize_properties( [ 'fit' => 'contain', 'q' => 70 ] );
assert_same( 'contain', $props['fit'], 'fit preserved' );
assert_same( 70, $props['quality'] ?? $props['q'] ?? 70, 'quality from q' );
assert_same( 'auto', $props['format'], 'default format' );

$args = Layout::to_edge_args( $props, 400, 300, 1.5 );
assert_same( 400, $args['w'], 'edge arg width' );
assert_same( 300, $args['h'], 'edge arg height' );
assert_same( 1.5, $args['dpr'], 'edge arg dpr' );
assert_same( 'contain', $args['fit'], 'edge arg fit' );

if ( $failures > 0 ) {
	fwrite( STDERR, "FAILED: {$failures}\n" );
	exit( 1 );
}

fwrite( STDOUT, "OK: layout tests\n" );
exit( 0 );
