<?php

namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Handler as Handler;

/**
 * Provides helper methods.
 */
class Cloudflare_Image_Helper {

	const STYLES_URL = YOAST_CF_IMAGES_PLUGIN_PLUGIN_URL . 'assets/css';

	/**
	 * Replace a SRC string with a Cloudflared version
	 *
	 * @param  string $src               The SRC attr.
	 * @param  int    $w                 The width in pixels.
	 * @param  int    $h                 The height in pixels.
	 *
	 * @return string      The modified SRC attr.
	 */
	public static function cf_src( string $src, int $w, int $h = null ) : string {
		$cf_properties = array(
			'width'   => $w,
			'fit'     => 'crop',
			'f'       => 'auto',
			'gravity' => 'auto',
			'onerror' => 'redirect',
		);
		if ( $h ) {
			$cf_properties['height'] = $h;
		}

		$cf_prefix = get_site_url() . '/cdn-cgi/image/';
		$cf_string = $cf_prefix . http_build_query(
			$cf_properties,
			'',
			'%2C'
		);
		return str_replace( get_site_url(), $cf_string, $src );
	}

	/**
	 * Adds key srcset sizes from the image's context
	 *
	 * @param string $src The image src.
	 * @param string $context The image's context.
	 *
	 * @return array The srcset attr
	 */
	public static function get_srcset_sizes_from_context( string $src, string $context ) : array {
		$sizes  = Handler::get_context_vals( $context, 'srcset' );
		$srcset = array();
		foreach ( $sizes as $size ) {
			$srcset[] = self::create_srcset_val( $src, $size['w'], $size['h'] );
			$srcset[] = self::create_srcset_val( $src, $size['w'] * 2, $size['h'] * 2 );
		}
		$dimensions = Handler::get_context_vals( $context, 'dimensions' );
		return $srcset;
	}

	/**
	 * Creates an srcset val from a src and dimensions
	 *
	 * @param string $src  The image src attr.
	 * @param int    $w    The width in pixels.
	 * @param int    $h    The height in pixels.
	 *
	 * @return string   The srcset value
	 */
	public static function create_srcset_val( string $src, int $w, int $h = null ) : string {
		return sprintf(
			'%s %dw',
			self::cf_src( $src, $w, $h ),
			$w
		);
	}

}
