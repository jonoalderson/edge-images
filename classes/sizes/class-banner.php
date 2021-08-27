<?php
namespace Yoast_CF_Images\Sizes;

use Yoast_CF_Images\Cloudflare_Image;

/**
 * Generates and managers a Cloudflared image.
 */
class Banner extends Cloudflare_Image {

	/**
	 * Get an attribute for the image
	 *
	 * @param  string $attr The attribute to get.
	 *
	 * @return mixed        The requested values.
	 */
	public function get_attr( string $attr ) {

		$dimensions = array(
			'w' => 123,
			'h' => 456,
		);

		$srcset = array(
			array(
				'w' => 456,
				'h' => 123,
			),
			array(
				'w' => 567,
				'h' => 234,
			),
		);

		$class = ['test', 'test123'];

		$picture_class = ['banner_test_class'];

		$sizes = '(max-width: 1234px) calc(100vw - 20px), calc(100vw - 20px)';

		$ratio = '4/3';

		if ( ! isset( $$attr ) ) {
			return;
		}

		return $$attr;

	}

}
