<?php
/**
 * Yoast SEO Schema Images integration functionality.
 * 
 * This class handles the integration of Edge Images with Yoast SEO's schema image functionality.	
 * It provides methods to transform schema image URLs, manage image optimization, and handle schema markup.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\{Integration, Helpers, Integrations, Settings, Features\Cache};


class Schema_Images extends Integration {

	/**
	 * Cache group for schema image processing.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	private const SCHEMA_CACHE_GROUP = 'edge_images_schema';

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
	 * Add integration-specific filters.
	 *
	 * Sets up required filters for Yoast SEO schema integration.
	 * This method:
	 * - Hooks into schema filters
	 * - Manages image transformation
	 * - Handles schema types
	 * - Ensures proper integration
	 *
	 * @since      4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {

		// Bail if we shouldn't be filtering
		if (!$this->should_filter()) {
			return;
		}
		
		add_filter('wpseo_schema_imageobject', [$this, 'transform_schema_image'], 10, 3);
		add_filter('wpseo_schema_organization', [$this, 'edge_organization_logo']);
		add_filter('wpseo_schema_webpage', [$this, 'edge_thumbnail']);
		add_filter('wpseo_schema_article', [$this, 'edge_thumbnail']);
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

		// Bail if no thumbnail URL
		if ( ! isset( $data['thumbnailUrl'] ) ) {
			return $data;
		}

		// Process the image
		$processed = $this->process_schema_image( $data['thumbnailUrl'] );
		if ( $processed ) {
			$data['thumbnailUrl'] = $processed['url'];
		}

		return $data;
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

		// Check cache first
		$cache_key = 'schema_' . md5($image_url . serialize($custom_args));
		$cached_result = wp_cache_get($cache_key, Cache::CACHE_GROUP);
		if ($cached_result !== false) {
			return $cached_result;
		}

		// Get the image ID
		$image_id = Helpers::get_attachment_id_from_url( $image_url );
		if ( ! $image_id ) {
			wp_cache_set($cache_key, false, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return false;
		}

		// Get the image dimensions
		$dimensions = Helpers::get_image_dimensions( $image_id );
		if ( ! $dimensions ) {
			wp_cache_set($cache_key, false, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return false;
		}

		// Set default args
		$args = [
			'width'   => self::SCHEMA_WIDTH,
			'height'  => self::SCHEMA_HEIGHT,
			'fit'     => 'cover',
			'sharpen' => (int) $dimensions['width'] < self::SCHEMA_WIDTH ? 3 : 2,
		];

		// Merge with custom args
		$args = array_merge( $args, $custom_args );

		// Tweak the behaviour for small images
		if ( (int) $dimensions['width'] < self::SCHEMA_WIDTH || (int) $dimensions['height'] < self::SCHEMA_HEIGHT ) {
			$args['fit']     = 'pad';
			$args['sharpen'] = 2;
		}

		// Get the edge URL
		$edge_url = Helpers::edge_src( $image_url, $args );
		if ( ! $edge_url ) {
			wp_cache_set($cache_key, false, Cache::CACHE_GROUP, HOUR_IN_SECONDS);
			return false;
		}

		// Build the result
		$result = [
			'url'     => $edge_url,
			'width'   => $args['width'],
			'height'  => $args['height'],
		];

		// Cache the result
		wp_cache_set($cache_key, $result, Cache::CACHE_GROUP, HOUR_IN_SECONDS);

		return $result;
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
	 * Transform schema image.
	 *
	 * Processes and transforms schema image data.
	 * This method:
	 * - Transforms image URLs
	 * - Handles image data
	 * - Manages dimensions
	 * - Ensures optimization
	 * - Maintains schema format
	 * - Supports multiple sizes
	 *
	 * @since      4.5.0
	 * 
	 * @param  array  $image_data The schema image data.
	 * @param  object $context    The context object.
	 * @param  object $graph_piece The graph piece object.
	 * @return array             Modified schema image data.
	 */
	public function transform_schema_image(array $image_data, $context, $graph_piece): array {

		// Skip if no URL
		if (!isset($image_data['url'])) {
			return $image_data;
		}

		// Skip if not a local URL
		if (!Helpers::is_local_url($image_data['url'])) {
			return $image_data;
		}

		// Get dimensions
		$width = $image_data['width'] ?? null;
		$height = $image_data['height'] ?? null;

		// Skip if we don't have dimensions
		if (!$width || !$height) {
			return $image_data;
		}

		// Transform the URL
		$image_data['url'] = Helpers::edge_src($image_data['url'], [
			'width' => $width,
			'height' => $height,
			'fit' => 'cover',
			'quality' => 85,
		]);

		$image_data['contentUrl'] = $image_data['url'];

		return $image_data;
	}

	/**
	 * Get default settings for this integration.
	 *
	 * Provides default configuration settings for the schema integration.
	 * This method:
	 * - Sets feature defaults
	 * - Configures options
	 * - Ensures consistency
	 * - Supports customization
	 *
	 * @since      4.5.0
	 * 
	 * @return array<string,mixed> Array of default feature settings.
	 */
	public static function get_default_settings(): array {
		return [
			'edge_images_integration_yoast_schema' => true,
		];
	}

	/**
	 * Check if this integration should filter.
	 *
	 * Determines if schema integration should be active.
	 * This method:
	 * - Checks feature status
	 * - Validates settings
	 * - Ensures requirements
	 * - Controls processing
	 *
	 * @since      4.5.0
	 * 
	 * @return bool True if integration should be active, false otherwise.
	 */
	protected function should_filter(): bool {
		
		// Check if Yoast SEO is installed and active
		if (!Integrations::is_enabled('yoast-seo')) {
			return false;
		}

		// Check if image transformation is enabled
		if (!Helpers::should_transform_images()) {
			return false;
		}

		// Check if this specific integration is enabled in settings
		return Settings::get_option('edge_images_integration_yoast_schema', true);
	}

	/**
	 * Render integration settings.
	 *
	 * @since 5.3.0
	 * @return void
	 */
	public static function render_settings(): void {
		// Bail if user doesn't have sufficient permissions.
		if (!current_user_can('manage_options')) {
			return;
		}

		$schema_enabled = Settings::get_option('edge_images_integration_yoast_schema', true);
		$social_enabled = Settings::get_option('edge_images_integration_yoast_social', true);
		$sitemap_enabled = Settings::get_option('edge_images_integration_yoast_xml', true);
		?>
		<fieldset>
			<p>
				<label>
					<input type="checkbox" 
						name="edge_images_integration_yoast_schema" 
						value="1" 
						<?php checked($schema_enabled); ?>
					>
					<?php esc_html_e('Enable schema.org image optimization', 'edge-images'); ?>
				</label>
			</p>

			<p>
				<label>
					<input type="checkbox" 
						name="edge_images_integration_yoast_social" 
						value="1" 
						<?php checked($social_enabled); ?>
					>
					<?php esc_html_e('Enable social media image optimization', 'edge-images'); ?>
				</label>
			</p>

			<p>
				<label>
					<input type="checkbox" 
						name="edge_images_integration_yoast_xml" 
						value="1" 
						<?php checked($sitemap_enabled); ?>
					>
					<?php esc_html_e('Enable XML sitemap image optimization', 'edge-images'); ?>
				</label>
			</p>

			<p class="description">
				<?php esc_html_e('Edge Images can optimize images in Yoast SEO\'s schema.org output, social media tags, and XML sitemaps. Enable or disable these features as needed.', 'edge-images'); ?>
			</p>
		</fieldset>
		<?php
	}
}


