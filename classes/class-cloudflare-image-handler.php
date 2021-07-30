<?php

namespace Yoast_CF_Images;

/**
 * Filters wp_get_attachment_image and related functions to use Cloudflare.
 */
class Cloudflare_Image_Handler {

	/**
	 * Register the integration
	 *
	 * @TODO: Add a wp_calculate_image_sizes filter.
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
		add_filter( 'wp_get_attachment_image_attributes', array( $instance, 'route_images_through_cloudflare' ), 10, 3 );
		add_filter( 'wp_calculate_image_srcset', array( $instance, 'alter_srcset_generation' ), 10, 5 );
	}

	/**
	 * Check whether an image should use Cloudflare
	 *
	 * @param  array $atts The attachment attributes.
	 *
	 * @return bool
	 */
	public function image_should_use_cloudflare( array $atts ) : bool {
		if ( strpos( $atts['src'], '.svg' ) !== false ) {
			return false;
		}
		return true;
	}

	/**
	 * Alter an image to use Cloudflare
	 *
	 * @param array  $atts          The attachment attributes.
	 * @param object $attachment    The attachment.
	 * @param string $size          The attachment size.
	 *
	 * @return array                The modified image attributes
	 */
	public function route_images_through_cloudflare( array $atts, object $attachment, string $size ) : array {
		if ( ! $this->image_should_use_cloudflare( $atts ) ) {
			return $atts;
		}
		$image = new Cloudflare_Image( $attachment->ID, $atts, $size );
		return $image->atts;
	}

	/**
	 * Alter the SRCSET attribut generation
	 *
	 * @param array  $sources        The image sources.
	 * @param array  $size_array     The sizes array.
	 * @param string $image_src      The image src attr.
	 * @param array  $image_meta     The image metadata.
	 * @param int    $attachment_id  The attachment ID.
	 *
	 * @return array                 The modified sources array
	 */
	public function alter_srcset_generation( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ) : array {
		print_r( $sources );
		print_r( $size_array );
		print_r( $image_src );
		print_r( $image_meta );
		print_r( $attachment_id );
		die;
		if ( ! $this->image_should_use_cloudflare( array( 'src' => $image_src ) ) ) {
			return $sources;
		}
		$sources = $this->replace_sources( $sources, $attachment_id, $size_array );
		return $sources;
	}

	/**
	 * Generate srcset sources from the original source and width
	 *
	 * @param array $sources        The image sources.
	 * @param int   $attachment_id  The attachment ID.
	 * @param array $size_array     The sizes array.
	 *
	 * @return array                The modified sources array
	 */
	private function replace_sources( $sources, $attachment_id, $size_array ) : array {

		$full_image = wp_get_attachment_image_src( $attachment_id, 'full' );

		$this->add_key_sizes( $sources );

		foreach ( $sources as &$source ) {

			// Alter the SRC to use Cloudflare.
			$source['url'] = self::alter_src( $full_image[0], $source['value'] );

			// Add x2 sizes for each registered variant (when it makes sense).
			if ( $this->should_add_x2_size( $source['value'], $size_array[0] ) ) {
				$x2             = $source['value'] * 2;
				$sources[ $x2 ] = array(
					'url'        => self::alter_src( $full_image[0], $x2 ),
					'descriptor' => 'w',
					'value'      => $x2,
				);
			}
		}

		ksort( $sources );
		return $sources;

	}

	/**
	 * Add key sources
	 *
	 * @param array $sources        The image sources.
	 *
	 * @return array                The modified sources
	 */
	private function add_key_sizes( $sources ) : array {
		$sources[ $size_array[0] ] = array(
			'url'        => $full_image[0],
			'descriptor' => 'w',
			'value'      => $size_array[0],
		);
		return $sources;
	}

	/**
	 * Determines if a x2 size should be added
	 *
	 * @param  int $source_width The SRC width.
	 * @param  int $max_width    The full size image SRC width.
	 *
	 * @return bool
	 */
	private function should_add_x2_size( int $source_width, int $max_width ) : bool {
		$x2 = $source_width * 2;
		if ( $x2 <= ( $max_width * 2 ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Replace a SRC string with a Cloudflared version
	 *
	 * @param  string $src               The SRC attr.
	 * @param  int    $w                 The width in pixels.
	 *
	 * @return string      The modified SRC attr.
	 */
	public static function alter_src( string $src, int $w ) : string {
		$cf_properties = array(
			'width'   => $w,
			'fit'     => 'crop',
			'f'       => 'auto',
			'gravity' => 'auto',
			'onerror' => 'redirect',
		);

		$cf_prefix = get_site_url() . '/cdn-cgi/image/';
		$cf_string = $cf_prefix . http_build_query(
			$cf_properties,
			'',
			'%2C'
		);
		return str_replace( get_site_url(), $cf_string, $src );
	}


}
