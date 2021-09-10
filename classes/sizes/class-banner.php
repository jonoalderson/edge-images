<?php
namespace Yoast_CF_Images\Sizes;

use Yoast_CF_Images\Cloudflare_Image;

/**
 * Manages a Banner image.
 */
class Banner extends Cloudflare_Image {

	/**
	 * Init the attributes
	 *
	 * @return void
	 */
	protected function init_attrs() : void {
		$this->attrs = array(
			'width'         => 123,
			'height'        => 456,
			'srcset'        => array(
				array(
					'w' => 456,
					'h' => 123,
				),
				array(
					'w' => 567,
					'h' => 234,
				),
			),
			'sizes'         => '(max-width: 1234px) calc(100vw - 20px), calc(100vw - 20px)',
			'ratio'         => '4/3',
			'class'         => array( 'test123', 'test456' ),
			'picture_class' => array( 'banner_test_class' ),
		);
	}

}
