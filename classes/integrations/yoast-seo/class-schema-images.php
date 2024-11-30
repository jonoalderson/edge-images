<?php
/**
 * Yoast SEO schema integration.
 *
 * Handles the transformation of images in Yoast SEO's schema output.
 * Ensures that schema images are optimized and properly sized.
 *
 * @package    Edge_Images\Integrations
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.0.0
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\{Helpers, Image_Dimensions};

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
	 * The thumbnail width value.
	 *
	 * @since 4.1.0
	 * @var int
	 */
	public const THUMBNAIL_SIZE = 500;

	/**
	 * The cache group to use.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public const CACHE_GROUP = 'edge_images';

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

		add_filter( 'wpseo_schema_imageobject', [ $instance, 'edge_primary_image' ] );
		add_filter( 'wpseo_schema_organization', [ $instance, 'edge_organization_logo' ] );
		add_filter('wpseo_schema_webpage', [ $instance, 'edge_webpage_thumbnail' ]);
	}

	/**
	 * Edit the thumbnailUrl property of the WebPage to use the edge.
	 *
	 * @since 4.1.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_webpage_thumbnail( $data ): array {
		if ( ! isset( $data['thumbnailUrl'] ) ) {
			return $data;
		}

		// Get the image ID from the URL
		$image_id = attachment_url_to_postid( $data['thumbnailUrl'] );
		if ( ! $image_id ) {
			return $data;
		}

		// Get dimensions from the image
		$dimensions = Image_Dimensions::from_attachment( $image_id );
		if ( ! $dimensions ) {
			return $data;
		}

		// Define some args
		$args = [
			'width'  => self::SCHEMA_WIDTH,
			'height' => self::SCHEMA_HEIGHT,
			'fit'    => 'cover',
			'sharpen' => (int) $dimensions['width'] < self::THUMBNAIL_SIZE ? 3 : 2,
		];

		// Transform the thumbnail URL
		$data['thumbnailUrl'] = Helpers::edge_src( $data['thumbnailUrl'], $args );

		return $data;
	}

	/**
	 * Alter the primaryImageOfPage to use the edge.
	 *
	 * @since 4.0.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_primary_image( $data ): array {
		// Bail if this isn't a singular post.
		if ( ! is_singular() ) {
			return $data;
		}

		// Bail if $data isn't an array.
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( ! isset( $data['url'] ) || ! isset( $data['contentUrl'] ) ) {
			return $data;
		}

		// Get the original image URL (not the resized version)
		$original_url = preg_replace('/-\d+x\d+\./', '.', $data['url']);

		// Get the image ID from the original URL
		$image_id = attachment_url_to_postid( $original_url );
		if ( ! $image_id ) {
			return $data;
		}

		// Get dimensions from the image.
		$dimensions = Image_Dimensions::from_attachment( $image_id );
		if ( ! $dimensions ) {
			return $data;
		}

		// Set the default args for main image.
		$args = [
			'width'  => self::SCHEMA_WIDTH,
			'height' => self::SCHEMA_HEIGHT,
			'fit'    => 'cover',
			'sharpen' => (int) $dimensions['width'] < self::THUMBNAIL_SIZE ? 1 : 0,
		];

		// Tweak the behaviour for small images.
		if ( (int) $dimensions['width'] < self::SCHEMA_WIDTH || (int) $dimensions['height'] < self::SCHEMA_HEIGHT ) {
			$args['fit']     = 'pad';
		}

		$edge_url = Helpers::edge_src( $original_url, $args );

		// Update the main image values.
		$data['url']        = $edge_url;
		$data['contentUrl'] = $edge_url;
		$data['width']      = self::SCHEMA_WIDTH;
		$data['height']     = self::SCHEMA_HEIGHT;

		// Always add a thumbnail version
		// Set thumbnail args
		$thumb_args = [
			'width'  => self::THUMBNAIL_SIZE,
			'height' => self::THUMBNAIL_SIZE,
			'fit'    => 'cover',
			'sharpen' => (int) $dimensions['width'] < self::THUMBNAIL_SIZE ? 3 : 2,
		];

		$data['thumbnailUrl'] = Helpers::edge_src( $original_url, $thumb_args );

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
		$dimensions = Image_Dimensions::from_attachment( $image_id );
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
		$disable_feature = apply_filters( 'edge_images_yoast_disable_schema_images', false );
		if ( $disable_feature ) {
			return false;
		}

		// Check if the provider is properly configured
		if ( ! Helpers::is_provider_configured() ) {
			return false;
		}

		return true;
	}
}
