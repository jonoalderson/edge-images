<?php
/**
 * Shared CLI test bootstrap: WordPress stubs + HTML Tag Processor.
 *
 * @package Edge_Images
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
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
if ( ! defined( 'EDGE_IMAGES_PLUGIN_FILE' ) ) {
	define( 'EDGE_IMAGES_PLUGIN_FILE', dirname( __DIR__ ) . '/edge-images.php' );
}
if ( ! defined( 'EDGE_IMAGES_ADMIN_PAGE_SLUG' ) ) {
	define( 'EDGE_IMAGES_ADMIN_PAGE_SLUG', 'edge-images' );
}

$GLOBALS['edge_images_test_options'] = array_merge(
	[
		'edge_images_provider'         => 'cloudflare',
		'edge_images_domain'           => 'https://cdn.example.com',
		'edge_images_localize'         => false,
		'edge_images_picture'          => false,
		'edge_images_bunny_subdomain'  => 'myzone',
		'edge_images_imgix_subdomain'  => 'mysite',
		'edge_images_imgproxy_url'     => 'https://imgproxy.example.com',
	],
	$GLOBALS['edge_images_test_options'] ?? []
);

$GLOBALS['edge_images_test_attachments'] = $GLOBALS['edge_images_test_attachments'] ?? [];
$GLOBALS['edge_images_test_filters']     = $GLOBALS['edge_images_test_filters'] ?? [];
$GLOBALS['edge_images_test_meta']        = $GLOBALS['edge_images_test_meta'] ?? [];
$GLOBALS['edge_images_test_queue']       = $GLOBALS['edge_images_test_queue'] ?? [];
$GLOBALS['edge_images_test_cron']        = $GLOBALS['edge_images_test_cron'] ?? [];

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return str_replace( [ ' ', '"' ], [ '%20', '%22' ], (string) $url );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'wp_kses_uri_attributes' ) ) {
	/**
	 * @return array<int, string>
	 */
	function wp_kses_uri_attributes() {
		return [ 'action', 'cite', 'background', 'longdesc', 'poster', 'src', 'href', 'xlink:href', 'usemap' ];
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string   $url       URL.
	 * @param int|null $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * @param string $value Value.
	 * @return string
	 */
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * @param string $value Value.
	 * @return string
	 */
	function trailingslashit( $value ) {
		return untrailingslashit( $value ) . '/';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return $GLOBALS['edge_images_test_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key        Key.
	 * @param mixed  $value      Value.
	 * @param mixed  $autoload   Autoload.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$GLOBALS['edge_images_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_option( $key ) {
		unset( $GLOBALS['edge_images_test_options'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_site_url' ) ) {
	/**
	 * @return string
	 */
	function get_site_url() {
		return 'https://example.com';
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function home_url( $path = '' ) {
		return 'https://example.com' . $path;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * @return bool
	 */
	function wp_doing_ajax() {
		return false;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		$args = func_get_args();
		array_shift( $args );
		if ( empty( $GLOBALS['edge_images_test_filters'][ $hook ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['edge_images_test_filters'][ $hook ] as $callback ) {
			$value = call_user_func_array( $callback, $args );
			$args[0] = $value;
		}
		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$GLOBALS['edge_images_test_filters'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted );
	}
}

if ( ! function_exists( 'get_post_mime_type' ) ) {
	/**
	 * @param int $attachment_id ID.
	 * @return string|false
	 */
	function get_post_mime_type( $attachment_id ) {
		return $GLOBALS['edge_images_test_attachments'][ (int) $attachment_id ]['mime'] ?? false;
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	/**
	 * @param int          $attachment_id ID.
	 * @param string|array $size          Size.
	 * @return string|false
	 */
	function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $GLOBALS['edge_images_test_attachments'][ (int) $attachment_id ]['url'] ?? false;
	}
}

if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
	/**
	 * @param int $attachment_id ID.
	 * @return array|false
	 */
	function wp_get_attachment_metadata( $attachment_id ) {
		return $GLOBALS['edge_images_test_attachments'][ (int) $attachment_id ]['meta'] ?? false;
	}
}

if ( ! function_exists( 'attachment_url_to_postid' ) ) {
	/**
	 * @param string $url URL.
	 * @return int
	 */
	function attachment_url_to_postid( $url ) {
		foreach ( $GLOBALS['edge_images_test_attachments'] as $id => $data ) {
			if ( ( $data['url'] ?? '' ) === $url ) {
				return (int) $id;
			}
			if ( ! empty( $data['aliases'] ) && in_array( $url, $data['aliases'], true ) ) {
				return (int) $id;
			}
		}
		return 0;
	}
}

if ( ! function_exists( 'get_attached_file' ) ) {
	/**
	 * @param int $attachment_id ID.
	 * @return string|false
	 */
	function get_attached_file( $attachment_id ) {
		return $GLOBALS['edge_images_test_attachments'][ (int) $attachment_id ]['file'] ?? false;
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	/**
	 * @return array<string, string>
	 */
	function wp_upload_dir() {
		$base = sys_get_temp_dir() . '/edge-images-uploads';
		if ( ! is_dir( $base ) ) {
			mkdir( $base, 0777, true );
		}
		return [
			'path'    => $base,
			'url'     => 'https://example.com/wp-content/uploads',
			'subdir'  => '',
			'basedir' => $base,
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => false,
		];
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Key.
	 * @param bool   $single  Single.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$value = $GLOBALS['edge_images_test_meta'][ (int) $post_id ][ $key ] ?? '';
		return $single ? $value : [ $value ];
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['edge_images_test_meta'][ (int) $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Key.
	 * @return bool
	 */
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['edge_images_test_meta'][ (int) $post_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * @param string $url URL.
	 * @param array  $args Args.
	 * @return array
	 */
	function wp_remote_get( $url, $args = [] ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return [
			'response' => [ 'code' => 200 ],
			'body'     => 'FAKEIMAGE:' . $url,
		];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param array $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return (int) ( $response['response']['code'] ?? 0 );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param array $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return (string) ( $response['body'] ?? '' );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Thing.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook ) {
		return $GLOBALS['edge_images_test_cron'][ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * @param int    $timestamp Timestamp.
	 * @param string $recurrence Recurrence.
	 * @param string $hook Hook.
	 * @return bool
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$GLOBALS['edge_images_test_cron'][ $hook ] = $timestamp;
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return void
	 */
	function wp_clear_scheduled_hook( $hook ) {
		unset( $GLOBALS['edge_images_test_cron'][ $hook ] );
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * @return void
	 */
	function wp_enqueue_style() {}
}

if ( ! function_exists( 'wp_get_registered_image_subsizes' ) ) {
	/**
	 * @return array<string, array{width:int,height:int}>
	 */
	function wp_get_registered_image_subsizes() {
		return [
			'medium' => [ 'width' => 300, 'height' => 300 ],
			'large'  => [ 'width' => 1024, 'height' => 1024 ],
		];
	}
}

// WordPress UTF-8 + HTML Tag Processor (vendored subset).
require_once __DIR__ . '/lib/wp/utf8.php';
require_once __DIR__ . '/lib/wp/html-api/class-wp-html-attribute-token.php';
require_once __DIR__ . '/lib/wp/html-api/class-wp-html-span.php';
require_once __DIR__ . '/lib/wp/html-api/class-wp-html-text-replacement.php';
require_once __DIR__ . '/lib/wp/html-api/class-wp-html-decoder.php';
require_once __DIR__ . '/lib/wp/html-api/class-wp-html-tag-processor.php';

require_once dirname( __DIR__ ) . '/autoload.php';

/**
 * Reset Settings option cache between cases.
 *
 * @return void
 */
function edge_images_test_reset_settings_cache(): void {
	\Edge_Images\Settings::reset_cache();
}

/**
 * Register a fixture attachment.
 *
 * @param int                  $id   ID.
 * @param array<string, mixed> $data Data.
 * @return void
 */
function edge_images_test_set_attachment( int $id, array $data ): void {
	$GLOBALS['edge_images_test_attachments'][ $id ] = $data;
}
