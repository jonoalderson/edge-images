<?php

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\Helpers;

/**
 * Configures Yoast SEO schema output to use the image rewriter.
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
		add_filter( 'wpseo_schema_imageobject', array( $instance, 'edge_primary_image' ) );
	}

	/**
	 * Alter the primaryImageOfPage to use the edge.
	 *
	 * @param  array $data The image schema properties.
	 *
	 * @return array       The modified properties
	 */
	public function edge_primary_image( array $data ) : array {
		if ( ! \strpos( $data['@id'], '#primaryimage' ) ) {
			return $data; // Bail if this isn't the primary image.
		}

		$args = array(
			'width'  => self::SCHEMA_WIDTH,
			'height' => self::SCHEMA_HEIGHT,
		);

		$edge_url = Helpers::edge_src( $data['url'], $args );

		$data['url']        = $edge_url;
		$data['contentUrl'] = $edge_url;
		$data['width']      = self::SCHEMA_WIDTH;
		$data['height']     = self::SCHEMA_HEIGHT;

		return $data;

	}

}
