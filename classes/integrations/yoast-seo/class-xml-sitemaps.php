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

use Edge_Images\{Helpers, Image_Dimensions, Integration, Settings, Integration_Manager};

/**
 * Configures XML sitemaps to use the image rewriter.
 *
 * @since 4.0.0
 */
class XML_Sitemaps extends Integration {

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
	 * Add integration-specific filters.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		add_filter( 'wpseo_sitemap_url_images', [ $this, 'transform_sitemap_images' ], 10, 2 );
		add_filter( 'wpseo_sitemap_entry', [ $this, 'transform_sitemap_entry' ], 10, 3 );
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

	/**
	 * Get default settings for this integration.
	 *
	 * @since 4.5.0
	 * 
	 * @return array<string,mixed> Default settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_yoast_xml_sitemap_images' => true,
		];
	}

	/**
	 * Check if this integration should filter.
	 *
	 * @since 4.5.0
	 * 
	 * @return bool Whether the integration should filter.
	 */
	protected function should_filter(): bool {

		// Bail if the Yoast SEO integration is disabled
		if ( ! Integration_Manager::is_enabled('yoast-seo') ) {
			return false;
		}

		return Settings::get_option('edge_images_yoast_xml_sitemap_images');
	}

}
