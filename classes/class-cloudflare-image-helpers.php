<?php

namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Handler as Handler;

/**
 * Provides helper methods.
 */
class Cloudflare_Image_Helpers {

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

		$cf_prefix = '/cdn-cgi/image/';
		$cf_string = $cf_prefix . http_build_query(
			$cf_properties,
			'',
			'%2C'
		);

		$url = parse_url($src);
		$path = $url['path'];
		$src = '/https://yoast.com' . $path;

		return 'https://yoast.com' . $cf_string . $src;
	}

	/**
	 * Adds key srcset sizes from the image's size
	 *
	 * @param string $src The image src.
	 * @param string $size The image's size.
	 *
	 * @return array The srcset attr
	 */
	public static function get_srcset_sizes_from_context( string $src, string $size ) : array {
		$sizes  = Handler::get_context_vals( $size, 'srcset' );
		$srcset = array();
		if ( ! $sizes ) {
			return $srcset; // Bail if there are no srcset options.
		}

		// Create the srcset strings and x2 strings.
		foreach ( $sizes as $v ) {
			$h        = ( isset( $v['h'] ) ) ? $v['h'] : null;
			$srcset[] = self::create_srcset_val( $src, $v['w'], $h );
			$srcset[] = self::create_srcset_val( $src, $v['w'] * 2, $h * 2 );
		}

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
