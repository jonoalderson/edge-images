<?php
/**
 * End-to-end smoke checklist (HTML view-source equivalents).
 *
 * Mirrors the manual provider smoke path without needing a live CDN.
 *
 * Run: php tests/test-smoke.php
 *
 * @package Edge_Images
 */

declare(strict_types=1);

namespace Edge_Images\Tests {

	require_once __DIR__ . '/bootstrap.php';
	require_once __DIR__ . '/assertions.php';

	use Edge_Images\{Transformer, Settings, Localizer, Helpers};

	fwrite( STDOUT, "Smoke checklist (provider=cloudflare, localize=off → on)\n" );

	edge_images_test_set_attachment(
		21,
		[
			'url'  => 'https://example.com/wp-content/uploads/2024/hero.jpg',
			'mime' => 'image/jpeg',
			'meta' => [ 'width' => 2400, 'height' => 1600 ],
			'file' => wp_upload_dir()['basedir'] . '/2024/hero.jpg',
		]
	);
	@mkdir( wp_upload_dir()['basedir'] . '/2024', 0777, true );
	file_put_contents( wp_upload_dir()['basedir'] . '/2024/hero.jpg', 'ORIGINAL' );

	$GLOBALS['edge_images_test_options']['edge_images_localize'] = false;
	edge_images_test_reset_settings_cache();

	assert_true( Helpers::should_transform_images(), '1. provider configured → transforms enabled' );
	assert_same( 'cloudflare', Settings::get_provider(), '1b. provider is cloudflare' );
	assert_same( false, Settings::is_localization_enabled(), '1c. localize off by default in this run' );

	$t = new Transformer();

	// 2. Resized image block in content.
	$block = '<figure class="wp-block-image size-large is-resized"><img loading="lazy" decoding="async" width="1024" height="683" src="https://example.com/wp-content/uploads/2024/hero-1024x683.jpg" alt="" class="wp-image-21" style="width:480px;height:auto"/></figure>';
	$out   = $t->filter_content_images( $block );
	assert_contains( 'cdn-cgi/image/', $out, '2. resized block uses edge URL' );
	assert_contains( 'w=480', $out, '2b. edge width matches editor 480px' );
	assert_contains( 'width="480"', $out, '2c. width attr updated to layout' );
	assert_contains( '<picture class="edge-images-container"', $out, '2d. picture wrap present' );
	assert_contains( 'sizes="auto, (max-width: 480px) 100vw, 480px"', $out, '2e. sizes from layout width' );

	// 3. Gallery block.
	$gallery = <<<'HTML'
<figure class="wp-block-gallery has-nested-images columns-3 is-cropped">
<figure class="wp-block-image size-large"><a href="https://example.com/wp-content/uploads/2024/hero.jpg"><img width="1024" height="683" src="https://example.com/wp-content/uploads/2024/hero-1024x683.jpg" alt="" class="wp-image-21"/></a></figure>
<figure class="wp-block-image size-large"><img width="1024" height="683" src="https://example.com/wp-content/uploads/2024/hero-1024x683.jpg" alt="" class="wp-image-21"/></figure>
</figure>
HTML;
	$out = $t->filter_content_images( $gallery );
	assert_contains( 'w=341', $out, '3. gallery column layout width (~1024/3)' );
	assert_contains( '(max-width: 599px) 50vw, 33vw', $out, '3b. gallery sizes' );
	assert_not_contains( '<picture', $out, '3c. gallery not picture-wrapped (core flex intact)' );

	// 4. Featured / attachment API HTML.
	$attachment = '<img width="1200" height="800" src="https://example.com/wp-content/uploads/2024/hero-1200x800.jpg" class="attachment-post-thumbnail size-post-thumbnail wp-image-21" alt="" decoding="async" />';
	$out        = $t->filter_attachment_image( $attachment, 21, [ 1200, 800 ], false, [] );
	assert_contains( '<picture', $out, '4. featured image picture wrap' );
	assert_contains( 'w=1200', $out, '4b. featured edge width' );
	assert_contains( '/2024/hero.jpg', $out, '4c. full file in edge path' );
	assert_contains( 'srcset=', $out, '4d. DPR srcset present' );
	assert_contains( 'dpr=2', $out, '4e. 2x candidate in srcset' );

	// 5. SEO social/schema size.
	$seo = Transformer::url( 'https://example.com/wp-content/uploads/2024/hero.jpg', 1200, 675, [ 'fit' => 'cover', 'sharpen' => 1 ] );
	assert_contains( 'w=1200', $seo, '5. SEO helper width' );
	assert_contains( 'h=675', $seo, '5b. SEO helper height' );
	assert_contains( 'cdn-cgi/image/', $seo, '5c. SEO stays on edge URL' );

	// 6. Localize on: first view edge + queue; after worker local.
	$GLOBALS['edge_images_test_options']['edge_images_localize'] = true;
	edge_images_test_reset_settings_cache();
	Localizer::register();

	$out = $t->filter_content_images( '<img class="wp-image-21" src="https://example.com/wp-content/uploads/2024/hero.jpg" width="480" height="320" />' );
	assert_contains( 'cdn-cgi/image/', $out, '6. first view still edge while localizing' );
	Localizer::flush_queue();
	( new Localizer() )->process_queue();

	$out = $t->filter_content_images( '<img class="wp-image-21" src="https://example.com/wp-content/uploads/2024/hero.jpg" width="480" height="320" />' );
	assert_not_contains( 'cdn-cgi/image/', $out, '6b. second view uses local files' );
	assert_contains( '/wp-content/uploads/', $out, '6c. local uploads URL' );
	assert_contains( '-edge.', $out, '6d. localized filename marker' );

	finish( 'smoke checklist' );
}
