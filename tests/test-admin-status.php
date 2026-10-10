<?php
/**
 * Admin status helper coverage via reflection.
 *
 * Run: php tests/test-admin-status.php
 *
 * @package Edge_Images
 */

declare(strict_types=1);

namespace Edge_Images\Tests {

	require_once __DIR__ . '/bootstrap.php';
	require_once __DIR__ . '/assertions.php';

	use Edge_Images\{Admin_Page, Settings};

	$admin = new Admin_Page();
	$method = new \ReflectionMethod( Admin_Page::class, 'get_status' );
	$method->setAccessible( true );

	$GLOBALS['edge_images_test_options']['edge_images_provider'] = 'none';
	edge_images_test_reset_settings_cache();
	$status = $method->invoke( $admin );
	assert_same( 'warning', $status['state'], 'none provider is inactive/warning' );

	$GLOBALS['edge_images_test_options']['edge_images_provider'] = 'bunny';
	$GLOBALS['edge_images_test_options']['edge_images_bunny_subdomain'] = '';
	edge_images_test_reset_settings_cache();
	$status = $method->invoke( $admin );
	assert_same( 'error', $status['state'], 'bunny without subdomain is error' );

	$GLOBALS['edge_images_test_options']['edge_images_bunny_subdomain'] = 'myzone';
	edge_images_test_reset_settings_cache();
	$status = $method->invoke( $admin );
	assert_same( 'ready', $status['state'], 'bunny with subdomain is ready' );
	assert_contains( 'Bunny', $status['summary'], 'ready summary names provider' );

	$GLOBALS['edge_images_test_options']['edge_images_provider'] = 'cloudflare';
	edge_images_test_reset_settings_cache();
	$status = $method->invoke( $admin );
	assert_same( 'ready', $status['state'], 'cloudflare ready' );
	assert_true( Settings::get_provider() === 'cloudflare', 'provider option readable' );

	finish( 'admin status' );
}
