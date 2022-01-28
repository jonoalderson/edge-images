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

		// Bail if these filters shouldn't run.
		if ( ! $instance->should_filter() ) {
			return;
		}

		add_filter( 'wpseo_xml_sitemap_img_src', array( $instance, 'use_edge_src' ), 100 );
	}

	/**
	 * Checks if these filters should run.
	 *
	 * @return bool
	 */
	private function should_filter() : bool {

		// Bail if the Yoast SEO integration is disabled.
		$disable_integration = apply_filters( 'Edge_Images\Yoast\disable', false );
		if ( $disable_integration ) {
			return false;
		}

		// Bail if schema image filtering is disabled.
		$disable_feature = apply_filters( 'Edge_Images\Yoast\disable_xml_sitemap_images', false );
		if ( $disable_feature ) {
			return false;
		}

		return true;
	}

	/**
	 * Transform the URI to an edge version
	 *
	 * @param  string $uri The URI.
	 *
	 * @return string      The modified URI
	 */
	public function use_edge_src( $uri ) : string {

		// Bail if $uri isn't a string.
		if ( ! is_string( $uri ) ) {
			return $uri;
		}

		$args = array(
			'width'  => self::IMAGE_WIDTH,
			'height' => self::IMAGE_HEIGHT,
			'fit'    => 'pad',
		);
		return Helpers::edge_src( $uri, $args );
	}


}
