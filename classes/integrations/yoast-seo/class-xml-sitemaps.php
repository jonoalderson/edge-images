<?php
/**
 * Yoast SEO XML sitemap integration.
 *
 * Handles the transformation of images in Yoast SEO's XML sitemaps.
 * Ensures that image URLs in sitemaps point to optimized edge versions.
 *
 * @package    Edge_Images\Integrations
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.0.0
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\{Helpers, Image_Dimensions};

/**
 * Configures XML sitemaps to use the image rewriter.
 *
 * @since 4.0.0
 */
class XML_Sitemaps {

	/**
	 * Cached result of should_filter check.
	 *
	 * @since 4.1.0
	 * @var bool|null
	 */
	private static $should_filter = null;

	/**
	 * The width value to use for sitemap images.
	 *
	 * @since 4.0.0
	 * @var int
	 */
	public const IMAGE_WIDTH = 1200;

	/**
	 * The height value to use for sitemap images.
	 *
	 * @since 4.0.0
	 * @var int
	 */
	public const IMAGE_HEIGHT = 675;

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
	 * Sets up filters to transform image URLs in XML sitemaps.
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


		add_filter( 'wpseo_sitemap_url_images', [ $instance, 'transform_sitemap_images' ], 10, 2 );
		add_filter( 'wpseo_sitemap_entry', [ $instance, 'transform_sitemap_entry' ], 10, 3 );
		add_action( 'wpseo_sitemap_entries', [ $instance, 'debug_sitemap_entries' ], 10, 2 );
		add_action( 'wpseo_sitemap_content', [ $instance, 'debug_sitemap_content' ], 10, 2 );

		self::$registered = true;
	}

	/**
	 * Transform images in a sitemap URL entry.
	 *
	 * @since 4.0.0
	 * 
	 * @param array $url  The URL entry.
	 * @param array $post The post data.
	 * @return array The modified URL entry.
	 */
	public function transform_post_url( $url, $post ): array {
		if ( ! isset( $url['images'] ) || empty( $url['images'] ) ) {
			return $url;
		}
		
		foreach ( $url['images'] as &$image ) {
			if ( isset( $image['src'] ) ) {
				$image['src'] = $this->transform_image_url($image['src'], $post->ID);
			}
			if ( isset( $image['image:loc'] ) ) {
				$image['image:loc'] = $this->transform_image_url($image['image:loc'], $post->ID);
			}
		}

		return $url;
	}

	/**
	 * Transform a single image URL.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $url     The image URL.
	 * @param int    $post_id The post ID.
	 * @return string The transformed URL.
	 */
	private function transform_image_url( string $url, int $post_id ): string {
		$image_id = attachment_url_to_postid( $url );
		if ( ! $image_id ) {
			return $url;
		}

		$dimensions = Image_Dimensions::from_attachment( $image_id );
		if ( ! $dimensions ) {
			return $url;
		}

		$args = [
			'width'  => self::IMAGE_WIDTH,
			'height' => self::IMAGE_HEIGHT,
			'fit'    => 'contain',
		];

		if ( (int) $dimensions['width'] < self::IMAGE_WIDTH || (int) $dimensions['height'] < self::IMAGE_HEIGHT ) {
			$args['fit']     = 'pad';
			$args['sharpen'] = 2;
		}

		$args = apply_filters( 'edge_images_yoast_sitemap_image_args', $args );

		return Helpers::edge_src( $url, $args );
	}

	/**
	 * Checks if these filters should run.
	 *
	 * @since 4.0.0
	 * 
	 * @return bool Whether the filters should run.
	 */
	private function should_filter(): bool {
		$disable_integration = apply_filters( 'edge_images_yoast_disable', false );
		if ( $disable_integration ) {
			return false;
		}

		$disable_feature = apply_filters( 'edge_images_yoast_disable_xml_sitemap_images', false );
		if ( $disable_feature ) {
			return false;
		}

		if ( ! Helpers::is_provider_configured() ) {
			return false;
		}

		return true;
	}

	/**
	 * Debugs the sitemap entries.
	 *
	 * @since 4.0.0
	 * 
	 * @param array $entries The sitemap entries.
	 * @param string $type The sitemap type.
	 * @return array The debugged entries.
	 */
	public function debug_sitemap_entries( $entries, $type ): array {
		return $entries;
	}

	/**
	 * Debugs the sitemap content.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $content The sitemap content.
	 * @param string $type The sitemap type.
	 * @return string The debugged content.
	 */
	public function debug_sitemap_content( $content, $type ): string {
		return $content;
	}

	/**
	 * Transforms a sitemap entry.
	 *
	 * @since 4.0.0
	 * 
	 * @param array  $url    The URL entry.
	 * @param string $type   The sitemap type.
	 * @param object $object The sitemap object.
	 * @return array The transformed URL entry.
	 */
	public function transform_sitemap_entry( $url, $type, $object ): array {
		if ( ! isset( $url['images'] ) ) {
			return $url;
		}

		foreach ( $url['images'] as &$image ) {
			$image_url = $image['image:loc'] ?? ($image['src'] ?? null);
			if ( ! $image_url ) {
				continue;
			}

			$image_id = attachment_url_to_postid( $image_url );
			if ( ! $image_id ) {
				continue;
			}

			$dimensions = Image_Dimensions::from_attachment( $image_id );
			if ( ! $dimensions ) {
				continue;
			}

			$args = [
				'width'  => self::IMAGE_WIDTH,
				'height' => self::IMAGE_HEIGHT,
				'fit'    => 'contain',
			];

			if ( (int) $dimensions['width'] < self::IMAGE_WIDTH || (int) $dimensions['height'] < self::IMAGE_HEIGHT ) {
				$args['fit']     = 'pad';
				$args['sharpen'] = 2;
			}

			$args = apply_filters( 'edge_images_yoast_sitemap_image_args', $args );

			$edge_url = Helpers::edge_src( $image_url, $args );

			if (isset($image['image:loc'])) {
				$image['image:loc'] = $edge_url;
			}
			if (isset($image['src'])) {
				$image['src'] = $edge_url;
			}
		}

		return $url;
	}

}
