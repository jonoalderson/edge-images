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
	 * Register the integration
	 *
	 * @return void
	 */
	public static function register() : void {

		$instance = new self();

		// Bail if these filters shouldn't run.
		if ( ! $instance->should_filter() ) {
			return;
		}

		add_filter( 'wpseo_schema_imageobject', array( $instance, 'edge_primary_image' ) );
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
		$disable_feature = apply_filters( 'Edge_Images\Yoast\disable_schema_images', false );
		if ( $disable_feature ) {
			return false;
		}

		return true;
	}

	/**
	 * Alter the primaryImageOfPage to use the edge.
	 *
	 * @param  array $data The image schema properties.
	 *
	 * @return array       The modified properties
	 */
	public function edge_primary_image( $data ) : array {

		// Bail if $data isn't an array.
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( ! \strpos( $data['@id'], '#primaryimage' ) ) {
			return $data; // Bail if this isn't the primary image.
		}

		// Get the image.
		global $post;
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' );
		if ( ! $image || ! isset( $image ) || ! isset( $image[0] ) ) {
			return $data; // Bail if there's no image.
		}

		$width  = $image[1];
		$height = $image[2];

		// Set the default args.
		$args = array(
			'width'  => $width,
			'height' => $height,
			'fit'    => 'cover',
		);

		// Tweak the behaviour for small images.
		if ( ( $width < self::SCHEMA_WIDTH ) || ( $height < self::SCHEMA_HEIGHT ) ) {
			$args['fit']     = 'pad';
			$args['sharpen'] = 2;
		}

		$edge_url = Helpers::edge_src( $data['url'], $args );

		// Update the schema values.
		$data['url']        = $edge_url;
		$data['contentUrl'] = $edge_url;
		$data['width']      = self::SCHEMA_WIDTH;
		$data['height']     = self::SCHEMA_HEIGHT;

		return $data;

	}

}
