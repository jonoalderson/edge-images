<?php

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\Helpers;

/**
 * Configures XML sitemaps to use the image rewriter.
 */
class XML_Sitemaps {

	/**
	 * The width value to use
	 *
	 * @var integer
	 */
	const IMAGE_WIDTH = 1200;

	/**
	 * The height value to use
	 *
	 * @var integer
	 */
	const IMAGE_HEIGHT = 675;

	/**
	 * Register the Integration
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
		add_filter( 'wpseo_xml_sitemap_img_src', array( $instance, 'use_edge_src' ), 100 );
	}

	/**
	 * Transform the URI to an edge version
	 *
	 * @param  string $uri The URI.
	 *
	 * @return string      The modified URI
	 */
	public function use_edge_src( string $uri ) : string {
		$args = array(
			'width'  => self::IMAGE_WIDTH,
			'height' => self::IMAGE_HEIGHT,
		);

		echo wp_get_environment_type();
		die;
		return Helpers::cf_src( $uri, $args );

	}


}
