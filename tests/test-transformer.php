<?php
/**
 * Integration-style tests for Transformer HTML rewriting.
 *
 * Run: php tests/test-transformer.php
 *
 * @package Edge_Images
 */

declare(strict_types=1);

namespace Edge_Images\Tests {

	require_once __DIR__ . '/bootstrap.php';
	require_once __DIR__ . '/assertions.php';

	use Edge_Images\{Transformer, Settings, Localizer, Integration};

	edge_images_test_reset_settings_cache();

	edge_images_test_set_attachment(
		12,
		[
			'url'  => 'https://example.com/wp-content/uploads/photo.jpg',
			'mime' => 'image/jpeg',
			'meta' => [
				'width'  => 2000,
				'height' => 1500,
			],
			'file' => sys_get_temp_dir() . '/edge-images-uploads/photo.jpg',
			'aliases' => [
				'https://example.com/wp-content/uploads/photo-1024x768.jpg',
			],
		]
	);

	edge_images_test_set_attachment(
		99,
		[
			'url'  => 'https://example.com/wp-content/uploads/icon.svg',
			'mime' => 'image/svg+xml',
			'meta' => [
				'width'  => 100,
				'height' => 100,
			],
		]
	);

	$uploads = wp_upload_dir();
	if ( ! is_dir( $uploads['basedir'] ) ) {
		mkdir( $uploads['basedir'], 0777, true );
	}
	file_put_contents( $uploads['basedir'] . '/photo.jpg', 'ORIGINAL' );

	$transformer = new Transformer();

	// --- Attachment array size ---
	$html = '<img width="640" height="400" src="https://example.com/wp-content/uploads/photo-640x400.jpg" class="wp-image-12" />';
	$out  = $transformer->filter_attachment_image(
		$html,
		12,
		[ 640, 400 ],
		false,
		[ 'fit' => 'contain', 'q' => 70 ]
	);
	assert_contains( '<picture', $out, 'attachment wrapped in picture' );
	assert_contains( 'edge-images-container', $out, 'picture has container class' );
	assert_contains( '/cdn-cgi/image/', $out, 'attachment uses cloudflare edge URL' );
	assert_contains( 'w=640', $out, 'attachment edge width 640' );
	assert_contains( 'fit=contain', $out, 'attachment fit from attr' );
	assert_contains( 'q=70', $out, 'attachment quality from attr' );
	assert_contains( 'srcset=', $out, 'attachment has srcset' );
	assert_not_contains( ' fit=', $out, 'fit attribute stripped from img attrs listing' ); // loose
	assert_contains( '--max-width: 640px', $out, 'picture CSS var max-width' );
	assert_contains( '/wp-content/uploads/photo.jpg', $out, 'edge URL targets full file not intermediate' );

	// --- Named size from tag dimensions ---
	$html = '<img width="300" height="200" src="https://example.com/wp-content/uploads/photo-300x200.jpg" class="attachment-medium size-medium wp-image-12" />';
	$out  = $transformer->filter_attachment_image( $html, 12, 'medium', false, [] );
	assert_contains( 'w=300', $out, 'named size uses tag width' );
	assert_contains( '<picture', $out, 'named size picture wrap' );

	// --- SVG skip transform but keep dimensions + picture ---
	$html = '<img width="80" height="80" src="https://example.com/wp-content/uploads/icon.svg" class="wp-image-99" />';
	$out  = $transformer->filter_attachment_image( $html, 99, [ 80, 80 ], false, [] );
	assert_not_contains( '/cdn-cgi/image/', $out, 'SVG not edge-transformed' );
	assert_contains( 'width="80"', $out, 'SVG width kept' );
	assert_contains( '<picture', $out, 'SVG still picture-wrapped' );

	// --- Block editor style width ---
	$content = '<figure class="wp-block-image"><img class="wp-image-12" src="https://example.com/wp-content/uploads/photo-1024x768.jpg" width="1024" height="768" style="width:400px;height:auto" loading="lazy" /></figure>';
	$out     = $transformer->filter_content_images( $content );
	assert_contains( 'width="400"', $out, 'block style sets layout width attr' );
	assert_contains( 'height="300"', $out, 'block style scales height from aspect ratio' );
	assert_contains( 'w=400', $out, 'block style edge request width 400' );
	assert_contains( 'sizes="auto, (max-width: 400px) 100vw, 400px"', $out, 'block style lazy sizes from layout width' );
	assert_contains( '<picture', $out, 'content image picture-wrapped' );
	assert_contains( '/wp-content/uploads/photo.jpg', $out, 'content uses full attachment URL' );

	// --- Figure style width (older markup) ---
	$content = '<figure class="wp-block-image" style="width:320px"><img class="wp-image-12" src="https://example.com/wp-content/uploads/photo.jpg" width="1024" height="768" /></figure>';
	$out     = $transformer->filter_content_images( $content );
	assert_contains( 'width="320"', $out, 'figure style width used' );
	assert_contains( 'height="240"', $out, 'figure style height scaled' );
	assert_contains( 'w=320', $out, 'figure style edge width' );

	// --- Percentage width ignored ---
	$content = '<figure class="wp-block-image"><img class="wp-image-12" src="https://example.com/wp-content/uploads/photo.jpg" width="800" height="600" style="width:50%" sizes="(max-width: 800px) 100vw, 800px" /></figure>';
	$out     = $transformer->filter_content_images( $content );
	assert_contains( 'width="800"', $out, 'percentage style falls back to attr width' );
	assert_contains( 'sizes="(max-width: 800px) 100vw, 800px"', $out, 'existing sizes left when percentage width' );

	// --- Gallery columns-3: layout third, no picture ---
	$content = <<<'HTML'
<figure class="wp-block-gallery has-nested-images columns-3 is-cropped">
<figure class="wp-block-image size-large"><img class="wp-image-12" src="https://example.com/wp-content/uploads/photo.jpg" width="1024" height="768" alt="" /></figure>
</figure>
HTML;
	$out = $transformer->filter_content_images( $content );
	assert_contains( 'width="341"', $out, 'gallery columns-3 layout width' );
	assert_contains( 'height="256"', $out, 'gallery columns-3 scaled height' );
	assert_contains( 'w=341', $out, 'gallery edge width third' );
	assert_contains( 'sizes="(max-width: 599px) 50vw, 33vw"', $out, 'gallery sizes columns-3' );
	assert_not_contains( '<picture', $out, 'gallery images not picture-wrapped' );

	// --- Gallery columns-1 uses normal sizes path ---
	$content = <<<'HTML'
<figure class="wp-block-gallery has-nested-images columns-1">
<figure class="wp-block-image"><img class="wp-image-12" src="https://example.com/wp-content/uploads/photo.jpg" width="800" height="600" /></figure>
</figure>
HTML;
	$out = $transformer->filter_content_images( $content );
	assert_contains( 'width="800"', $out, 'gallery columns-1 keeps full width' );
	assert_not_contains( '<picture', $out, 'gallery columns-1 still no picture' );
	assert_contains( 'sizes="(max-width: 800px) 100vw, 800px"', $out, 'gallery columns-1 single-image sizes' );

	// --- Transform attrs stripped from content img ---
	$content = '<img class="wp-image-12" src="https://example.com/wp-content/uploads/photo.jpg" width="400" height="300" fit="cover" q="90" />';
	$out     = $transformer->filter_content_images( $content );
	assert_not_contains( ' fit="', $out, 'content fit attr removed' );
	assert_not_contains( ' q="', $out, 'content q attr removed' );
	assert_contains( 'q=90', $out, 'quality applied to edge URL' );

	// --- Avatar local URL ---
	$avatar = '<img alt="A" src="https://example.com/wp-content/uploads/avatar.jpg" class="avatar avatar-96" height="96" width="96" />';
	$out    = $transformer->filter_avatar( $avatar, 1, 96, '', 'A', [ 'size' => 96 ] );
	assert_contains( '/cdn-cgi/image/', $out, 'local avatar edged' );
	assert_contains( 'w=96', $out, 'avatar size 96' );

	// --- Avatar remote skipped ---
	$avatar = '<img alt="A" src="https://secure.gravatar.com/avatar/abc" class="avatar" height="96" width="96" />';
	$out    = $transformer->filter_avatar( $avatar, 1, 96, '', 'A', [ 'size' => 96 ] );
	assert_not_contains( '/cdn-cgi/image/', $out, 'remote gravatar not edged' );

	// --- SEO helper ---
	$seo = new class() extends Integration {
		protected function add_filters(): void {}
		protected function should_filter(): bool { return true; }
		public function run( string $url ): string {
			return $this->transform_seo_image( $url, [ 'sharpen' => 1 ] );
		}
	};
	$seo_url = $seo->run( 'https://example.com/wp-content/uploads/photo.jpg' );
	assert_contains( '/cdn-cgi/image/', $seo_url, 'seo helper edges local URL' );
	assert_contains( 'w=1200', $seo_url, 'seo width 1200' );
	assert_contains( 'h=675', $seo_url, 'seo height 675' );

	// --- Localization off keeps edge URLs ---
	$GLOBALS['edge_images_test_options']['edge_images_localize'] = false;
	edge_images_test_reset_settings_cache();
	$content = '<img class="wp-image-12" src="https://example.com/wp-content/uploads/photo.jpg" width="400" height="300" />';
	$out     = $transformer->filter_content_images( $content );
	assert_contains( '/cdn-cgi/image/', $out, 'localize off uses edge URLs' );

	// --- Localization on queues when missing ---
	$GLOBALS['edge_images_test_options']['edge_images_localize'] = true;
	edge_images_test_reset_settings_cache();
	Localizer::register();
	$content = '<img class="wp-image-12" src="https://example.com/wp-content/uploads/photo.jpg" width="400" height="300" />';
	$out     = $transformer->filter_content_images( $content );
	assert_contains( '/cdn-cgi/image/', $out, 'localize on still edge until ready' );
	Localizer::flush_queue();
	$queue = get_option( Localizer::QUEUE_OPTION, [] );
	assert_true( is_array( $queue ) && [] !== $queue, 'localize queues a job' );

	finish( 'transformer integration' );
}
