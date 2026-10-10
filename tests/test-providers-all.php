<?php
/**
 * URL shape tests for all five edge providers.
 *
 * Run: php tests/test-providers-all.php
 *
 * @package Edge_Images
 */

declare(strict_types=1);

namespace Edge_Images\Tests {

	require_once __DIR__ . '/bootstrap.php';
	require_once __DIR__ . '/assertions.php';

	use Edge_Images\Edge_Providers;

	$args = [
		'w'   => 400,
		'h'   => 300,
		'dpr' => 1.5,
		'fit' => 'cover',
		'f'   => 'auto',
		'q'   => 85,
	];
	$path = '/wp-content/uploads/photo.jpg';

	$cf = new Edge_Providers\Cloudflare();
	$cf->set_path( $path );
	$cf->set_args( $args );
	$cf_url = $cf->get_edge_url();
	assert_contains( 'https://cdn.example.com/cdn-cgi/image/', $cf_url, 'cloudflare prefix' );
	assert_contains( 'w=400', $cf_url, 'cloudflare w' );
	assert_contains( 'dpr=1.5', $cf_url, 'cloudflare dpr' );
	assert_contains( $path, $cf_url, 'cloudflare path' );

	$acd = new Edge_Providers\Accelerated_Domains();
	$acd->set_path( $path );
	$acd->set_args( $args );
	$acd_url = $acd->get_edge_url();
	assert_contains( 'https://cdn.example.com/acd-cgi/img/v1', $acd_url, 'acd prefix' );
	assert_contains( 'width=400', $acd_url, 'acd width' );
	assert_contains( 'height=300', $acd_url, 'acd height' );
	assert_contains( 'dpr=1.5', $acd_url, 'acd dpr' );

	$bunny = new Edge_Providers\Bunny();
	$bunny->set_path( $path );
	$bunny->set_args( $args );
	$bunny_url = $bunny->get_edge_url();
	assert_contains( 'https://myzone.b-cdn.net/', $bunny_url, 'bunny host' );
	assert_contains( 'width=400', $bunny_url, 'bunny width' );
	assert_contains( $path, $bunny_url, 'bunny path' );
	assert_true( Edge_Providers\Bunny::is_configured(), 'bunny configured with subdomain' );

	$imgix = new Edge_Providers\Imgix();
	$imgix->set_path( $path );
	$imgix->set_args( $args );
	$imgix_url = $imgix->get_edge_url();
	assert_contains( 'mysite.imgix.net', $imgix_url, 'imgix host' );
	assert_contains( 'w=400', $imgix_url, 'imgix w' );
	assert_true( Edge_Providers\Imgix::is_configured(), 'imgix configured' );

	$imgproxy = new Edge_Providers\Imgproxy();
	$imgproxy->set_path( $path );
	$imgproxy->set_args( $args );
	$imgproxy_url = $imgproxy->get_edge_url();
	assert_contains( 'https://imgproxy.example.com', $imgproxy_url, 'imgproxy host' );
	assert_true( Edge_Providers\Imgproxy::is_configured(), 'imgproxy configured' );

	finish( 'all provider URLs' );
}
