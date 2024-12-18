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

use Edge_Images\Helpers;

/**
 * Configures Yoast SEO schema output to use the image rewriter.
 *
 * @since 4.0.0
 */
class Schema_Images {

	/**
	 * Cached result of should_filter check.
	 *
	 * @since 4.1.0
	 * @var bool|null
	 */
	private static $should_filter = null;

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
	 * Whether the integration has been registered.
	 *
	 * @since 4.5.0
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the integration.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	public static function register(): void {
		
		// Prevent double registration.
		if (self::$registered) {
			return;
		}

		$instance = new self();

		// Use cached result if available.
		if ( null === self::$should_filter ) {
			self::$should_filter = $instance->should_filter();
		}

		// Bail if these filters shouldn't run.
		if ( ! self::$should_filter ) {
			return;
		}

		add_filter( 'wpseo_schema_imageobject', [ $instance, 'edge_image' ] );
		add_filter( 'wpseo_schema_organization', [ $instance, 'edge_organization_logo' ] );
		add_filter( 'wpseo_schema_webpage', [ $instance, 'edge_thumbnail' ] );
		add_filter( 'wpseo_schema_article', [ $instance, 'edge_thumbnail' ] );

		self::$registered = true;
	}

	/**
	 * Process an image for schema output.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $image_url The original image URL.
	 * @param array  $custom_args Optional. Custom transformation arguments.
	 * @return array|false Array of edge URL and dimensions, or false on failure.
	 */
	private function process_schema_image( string $image_url, array $custom_args = [] ) {
		$image_id = Helpers::get_attachment_id( $image_url );
		if ( ! $image_id ) {
			return false;
		}

		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			return false;
		}

		// Set default args.
		$args = [
			'width'   => self::SCHEMA_WIDTH,
			'height'  => self::SCHEMA_HEIGHT,
			'fit'     => 'cover',
			'sharpen' => (int) $dimensions['width'] < self::SCHEMA_WIDTH ? 3 : 2,
		];

		// Merge with custom args.
		$args = array_merge( $args, $custom_args );

		// Tweak the behaviour for small images.
		if ( (int) $dimensions['width'] < self::SCHEMA_WIDTH || (int) $dimensions['height'] < self::SCHEMA_HEIGHT ) {
			$args['fit']     = 'pad';
			$args['sharpen'] = 2;
		}

		$edge_url = Helpers::edge_src( $image_url, $args );
		if ( ! $edge_url ) {
			return false;
		}

		return [
			'url'     => $edge_url,
			'width'   => $args['width'],
			'height'  => $args['height'],
		];
	}

	/**
	 * Edit the thumbnailUrl property of the WebPage to use the edge.
	 *
	 * @since 4.1.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_thumbnail( array $data ): array {
		if ( ! isset( $data['thumbnailUrl'] ) ) {
			return $data;
		}

		$processed = $this->process_schema_image( $data['thumbnailUrl'] );
		if ( $processed ) {
			$data['thumbnailUrl'] = $processed['url'];
		}

		return $data;
	}

	/**
	 * Transform the primary image to use the edge.
	 *
	 * @since 4.1.0
	 * 
	 * @param array $data The image schema properties.
	 * @return array The modified properties.
	 */
	public function edge_image( array $data ): array {
		if ( ! isset( $data['url'] ) ) {
			return $data;
		}

		$processed = $this->process_schema_image( 
			$data['url'],
			['fit' => 'contain']
		);

		if ( $processed ) {
			$data['url']         = $processed['url'];
			$data['contentUrl']  = $processed['url'];
			$data['width']       = $processed['width'];
			$data['height']      = $processed['height'];
		}

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
	public function edge_organization_logo( array $data ): array {
		// Get the image ID from Yoast SEO.
		$image_id = YoastSEO()->meta->for_current_page()->company_logo_id;
		if ( ! $image_id ) {
			return $data;
		}

		$image = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! $image ) {
			return $data;
		}

		// Get dimensions from the image.
		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			return $data;
		}

		$processed = $this->process_schema_image(
			$image[0],
			[
				'fit'    => 'contain',
				'width'  => min( (int) $dimensions['width'], self::SCHEMA_WIDTH ),
				'height' => min( (int) $dimensions['height'], self::SCHEMA_HEIGHT ),
			]
		);

		if ( $processed ) {
			$data['logo'] = [
				'url'         => $processed['url'],
				'contentUrl'  => $processed['url'],
				'width'       => $processed['width'],
				'height'      => $processed['height'],
				'@type'       => 'ImageObject',
			];
		}

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
}


