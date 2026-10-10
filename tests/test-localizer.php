<?php
/**
 * Localizer queue and file-swap tests.
 *
 * Run: php tests/test-localizer.php
 *
 * @package Edge_Images
 */

declare(strict_types=1);

namespace Edge_Images\Tests {

	require_once __DIR__ . '/bootstrap.php';
	require_once __DIR__ . '/assertions.php';

	use Edge_Images\{Localizer, Settings, Layout};

	$uploads = wp_upload_dir();
	$file    = $uploads['basedir'] . '/hero.jpg';
	file_put_contents( $file, 'ORIGINAL' );

	edge_images_test_set_attachment(
		7,
		[
			'url'  => 'https://example.com/wp-content/uploads/hero.jpg',
			'mime' => 'image/jpeg',
			'meta' => [ 'width' => 1600, 'height' => 900 ],
			'file' => $file,
		]
	);

	$GLOBALS['edge_images_test_options']['edge_images_localize'] = true;
	edge_images_test_reset_settings_cache();
	assert_true( Settings::is_localization_enabled(), 'localize option on' );

	Localizer::register();

	$properties = Layout::normalize_properties( [] );
	$result     = Localizer::maybe_localize( 7, 400, 225, $properties, 'https://example.com/wp-content/uploads/hero.jpg', 1600 );
	assert_same( null, $result, 'missing variants return null (keep edge)' );

	Localizer::flush_queue();
	$queue = get_option( Localizer::QUEUE_OPTION, [] );
	assert_true( is_array( $queue ) && count( $queue ) >= 1, 'job flushed to durable queue' );

	$worker = new Localizer();
	$worker->process_queue();

	$sizes = get_post_meta( 7, Localizer::SIZES_META_KEY, true );
	assert_true( is_array( $sizes ) && [] !== $sizes, 'variants stored in meta after process' );

	$ready = Localizer::maybe_localize( 7, 400, 225, $properties, 'https://example.com/wp-content/uploads/hero.jpg', 1600 );
	assert_true( is_array( $ready ), 'ready bundle returned when files exist' );
	assert_contains( 'https://example.com/wp-content/uploads/', $ready['src'] ?? '', 'local src under uploads' );
	assert_contains( ' 400w', $ready['srcset'] ?? '', 'local srcset has 1x width' );
	assert_not_contains( '/cdn-cgi/image/', $ready['src'] ?? '', 'ready src is local not edge' );

	$worker->delete_variants( 7 );
	assert_same( '', get_post_meta( 7, Localizer::SIZES_META_KEY, true ), 'meta cleared on delete' );

	finish( 'localizer' );
}
