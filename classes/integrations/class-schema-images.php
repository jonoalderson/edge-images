<?php

namespace Yoast_CF_Images\Integrations;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;

/**
 * Configures our schema output to use the CF rewriter.
 */
class Schema_Images {

	/**
	 * The image width value
	 *
	 * @var integer
	 */
	const SCHEMA_WIDTH = 1200;

	/**
	 * The image height value
	 *
	 * @var integer
	 */
	const SCHEMA_HEIGHT = 675;

	/**
	 * Register the Integration
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
		add_filter( 'wpseo_schema_imageobject', array( $instance, 'cloudflare_primary_image' ) );
	}

	/**
	 * Alter the primaryImageOfPage to use CF.
	 *
	 * @param  array $data The image schema properties.
	 *
	 * @return array       The modified properties
	 */
	public function cloudflare_primary_image( array $data ) : array {
		if ( ! \strpos( $data['@id'], '#primaryimage' ) ) {
			return $data; // Bail if this isn't the primary image.
		}

		$cf_url = Helpers::cf_src( $data['url'], self::SCHEMA_WIDTH, self::SCHEMA_HEIGHT );

		$data['url']        = $cf_url;
		$data['contentUrl'] = $cf_url;
		$data['width']      = self::SCHEMA_WIDTH;
		$data['height']     = self::SCHEMA_HEIGHT;

		return $data;

	}

}
