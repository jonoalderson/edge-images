<?php
/**
 * CLI tests for edge provider URL shapes.
 *
 * Run: php tests/test-provider-urls.php
 *
 * @package Edge_Images
 */

declare(strict_types=1);

namespace {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}
	if ( ! defined( 'EDGE_IMAGES_VERSION' ) ) {
		define( 'EDGE_IMAGES_VERSION', '6.0.0-test' );
	}
	if ( ! defined( 'EDGE_IMAGES_PLUGIN_DIR' ) ) {
		define( 'EDGE_IMAGES_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
	}
	if ( ! defined( 'EDGE_IMAGES_PLUGIN_URL' ) ) {
		define( 'EDGE_IMAGES_PLUGIN_URL', 'https://example.com/wp-content/plugins/edge-images/' );
	}

	$GLOBALS['edge_images_test_options'] = [
		'edge_images_provider' => 'cloudflare',
		'edge_images_domain'   => 'https://cdn.example.com',
	];

	if ( ! function_exists( 'apply_filters' ) ) {
		/**
		 * Stub apply_filters.
		 *
		 * @param string $hook  Hook.
		 * @param mixed  $value Value.
		 * @return mixed
		 */
		function apply_filters( $hook, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return $value;
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		/**
		 * Stub get_option.
		 *
		 * @param string $key     Key.
		 * @param mixed  $default Default.
		 * @return mixed
		 */
		function get_option( $key, $default = false ) {
			return $GLOBALS['edge_images_test_options'][ $key ] ?? $default;
		}
	}

	if ( ! function_exists( 'get_site_url' ) ) {
		/**
		 * Stub get_site_url.
		 *
		 * @return string
		 */
		function get_site_url() {
			return 'https://example.com';
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		/**
		 * Stub esc_attr.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		function esc_attr( $text ) {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_url_raw' ) ) {
		/**
		 * Stub esc_url_raw.
		 *
		 * @param string $url URL.
		 * @return string
		 */
		function esc_url_raw( $url ) {
			return $url;
		}
	}

	if ( ! function_exists( 'untrailingslashit' ) ) {
		/**
		 * Stub untrailingslashit.
		 *
		 * @param string $value Value.
		 * @return string
		 */
		function untrailingslashit( $value ) {
			return rtrim( $value, '/\\' );
		}
	}

	if ( ! function_exists( '__' ) ) {
		/**
		 * Stub i18n.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		function __( $text ) {
			return $text;
		}
	}

	if ( ! function_exists( 'wp_parse_url' ) ) {
		/**
		 * Stub wp_parse_url.
		 *
		 * @param string $url       URL.
		 * @param int    $component Component.
		 * @return mixed
		 */
		function wp_parse_url( $url, $component = -1 ) {
			return parse_url( $url, $component );
		}
	}
}

namespace Edge_Images {

	$failures = 0;

	/**
	 * Assert equality.
	 *
	 * @param mixed  $expected Expected.
	 * @param mixed  $actual   Actual.
	 * @param string $message  Message.
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
	 * Assert string contains.
	 *
	 * @param string $needle   Needle.
	 * @param string $haystack Haystack.
	 * @param string $message  Message.
	 * @return void
	 */
	function assert_contains( string $needle, string $haystack, string $message ): void {

		global $failures;

		if ( ! str_contains( $haystack, $needle ) ) {
			fwrite( STDERR, "FAIL: {$message}\n  missing: {$needle}\n  in: {$haystack}\n" );
			++$failures;
		} else {
			fwrite( STDOUT, "PASS: {$message}\n" );
		}
	}

	require_once dirname( __DIR__ ) . '/autoload.php';

	$cf = new Edge_Providers\Cloudflare();
	$cf->set_path( '/wp-content/uploads/photo.jpg' );
	$cf->set_args(
		[
			'w'   => 400,
			'h'   => 300,
			'dpr' => 1,
			'fit' => 'cover',
			'f'   => 'auto',
			'q'   => 85,
		]
	);
	$cf_url = $cf->get_edge_url();
	assert_contains( 'https://cdn.example.com/cdn-cgi/image/', $cf_url, 'cloudflare prefix' );
	assert_contains( '/wp-content/uploads/photo.jpg', $cf_url, 'cloudflare path' );
	assert_contains( 'w=400', $cf_url, 'cloudflare width' );
	assert_contains( 'h=300', $cf_url, 'cloudflare height' );

	$acd = new Edge_Providers\Accelerated_Domains();
	$acd->set_path( '/wp-content/uploads/photo.jpg' );
	$acd->set_args(
		[
			'w'   => 400,
			'h'   => 300,
			'dpr' => 1.5,
			'fit' => 'cover',
			'f'   => 'auto',
			'q'   => 85,
		]
	);
	$acd_url = $acd->get_edge_url();
	assert_contains( 'https://cdn.example.com/acd-cgi/img/v1/wp-content/uploads/photo.jpg?', $acd_url, 'acd base' );
	assert_contains( 'width=400', $acd_url, 'acd width' );
	assert_contains( 'height=300', $acd_url, 'acd height' );
	assert_contains( 'dpr=1.5', $acd_url, 'acd dpr' );

	if ( $failures > 0 ) {
		fwrite( STDERR, "FAILED: {$failures}\n" );
		exit( 1 );
	}

	fwrite( STDOUT, "OK: provider URL tests\n" );
	exit( 0 );
}
