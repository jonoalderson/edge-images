<?php
/**
 * Yoast SEO schema integration.
 *
 * Handles the transformation of images in Yoast SEO's schema output.
 * Ensures that schema images are optimized and properly sized.
 *
 * @package    Edge_Images\Integrations
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\Helpers;

/**
 * Configures Yoast SEO schema output to use the image rewriter.
 *
 * @since 4.0.0
 */
class Schema_Images {

	/**
	 * The image width value for schema images.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const SCHEMA_WIDTH = 1200;

	/**
	 * The image height value for schema images.
	 *
	 * @since 4.0.0
	 * @var int
	 */
	public const SCHEMA_HEIGHT = 675;

	/**
	 * Register the integration.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	public static function register(): void {
		$instance = new self();

		// Bail if these filters shouldn't run.
		if ( ! $instance->should_filter() ) {
			return;
		}

		add_filter( 'wpseo_schema_imageobject', [ $instance, 'edge_image' ] );
		add_filter( 'wpseo_schema_organization', [ $instance, 'edge_organization_logo' ] );
		add_filter('wpseo_schema_webpage', [ $instance, 'edge_thumbnail' ]);
		add_filter('wpseo_schema_article', [ $instance, 'edge_thumbnail' ]);
	}

	/**
	 * Edit the thumbnailUrl property of the WebPage to use the edge.
	 *
	 * @since 4.1.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_thumbnail( $data ): array {

		if ( ! isset( $data['thumbnailUrl'] ) ) {
			return $data;
		}

		// Get the image ID from the URL
		$image_id = Helpers::get_attachment_id( $data['thumbnailUrl'] );
		if ( ! $image_id ) {
			return $data;
		}

		// Get dimensions from the image
		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			return $data;
		}

		// Set our default args
		$args = [
			'width'  => self::SCHEMA_WIDTH,
			'height' => self::SCHEMA_HEIGHT,
			'fit'    => 'cover',
			'sharpen' => (int) $dimensions['width'] < self::SCHEMA_WIDTH ? 3 : 2,
		];

		// Tweak the behaviour for small images
		if ( (int) $dimensions['width'] < self::SCHEMA_WIDTH || (int) $dimensions['height'] < self::SCHEMA_HEIGHT ) {
			$args['fit']     = 'pad';
			$args['sharpen'] = 2;
		}

		// Convert the image src to an edge SRC
		$edge_url = Helpers::edge_src( $data['thumbnailUrl'], $args );

		// Update the schema values
		$data['thumbnailUrl'] = $edge_url;

		return $data;
	}

	/**
	 * Alter the Organization's logo property to use the edge.
	 *
	 * @since 4.0.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_organization_logo( $data ): array {
		// Get the image ID from Yoast SEO
		$image_id = YoastSEO()->meta->for_current_page()->company_logo_id;

		// Bail if we didn't get an image.
		if ( ! $image_id ) {
			return $data;
		}

		// Get dimensions from the image
		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			return $data;
		}

		// Set our default args
		$args = [
			'width'  => min( (int) $dimensions['width'], self::SCHEMA_WIDTH ),
			'height' => min( (int) $dimensions['height'], self::SCHEMA_HEIGHT ),
			'fit'    => 'contain',
		];

		// Tweak the behaviour for small images
		if ( (int) $dimensions['width'] < self::SCHEMA_WIDTH || (int) $dimensions['height'] < self::SCHEMA_HEIGHT ) {
			$args['fit']     = 'pad';
			$args['sharpen'] = 2;
		}

		// Get the original image URL
		$image = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! $image ) {
			return $data;
		}

		// Convert the image src to an edge SRC
		$edge_url = Helpers::edge_src( $image[0], $args );

		// Update the schema values
		$data['logo'] = [
			'url'         => $edge_url,
			'contentUrl'  => $edge_url,
			'width'       => $args['width'],
			'height'      => $args['height'],
			'@type'       => 'ImageObject',
		];

		return $data;
	}

	/**
	 * Checks if these filters should run.
	 *
	 * @since 4.0.0
	 * 
	 * @return bool Whether the filters should run.
	 */
	private function should_filter(): bool {
		// Bail if the Yoast SEO integration is disabled.
		$disable_integration = apply_filters( 'edge_images_yoast_disable', false );
		if ( $disable_integration ) {
			return false;
		}

		// Bail if schema image filtering is disabled.
		$enabled = get_option( 'edge_images_yoast_schema_images', true );
		if ( ! $enabled ) {
			return false;
		}

		// Check if the provider is properly configured
		if ( ! Helpers::is_provider_configured() ) {
			return false;
		}

		return true;
	}

	/**
	 * Transform the primary image to use the edge.
	 *
	 * @since 4.1.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_image( $data ): array {

		// Bail if the URL is not set.
		if ( ! isset( $data['url'] ) ) {
			return $data;
		}

		// Get the image ID from the URL
		$image_id = Helpers::get_attachment_id( $data['url'] );
		if ( ! $image_id ) {
			return $data;
		}

		// Get dimensions from the image
		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			return $data;
		}

		// Set transformation arguments
		$args = [
			'width'  => self::SCHEMA_WIDTH,
			'height' => self::SCHEMA_HEIGHT,
			'fit'    => 'contain',
			'sharpen' => (int) $dimensions['width'] < self::SCHEMA_WIDTH ? 3 : 2,
		];

		// Convert the image src to an edge SRC
		$edge_url = Helpers::edge_src( $data['url'], $args );

		// Update the schema values
		$data['url'] = $edge_url;
		$data['contentUrl'] = $edge_url;
		$data['width'] = $args['width'];
		$data['height'] = $args['height'];

		return $data;
	}
}

