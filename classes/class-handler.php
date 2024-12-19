<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

use Edge_Images\Features\Picture;

/**
 * Filters image attributes to use the edge provider.
 */
class Handler {
	
	/**
	 * Whether the handler has been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * The cached edge provider instance.
	 *
	 * @var Edge_Provider|null
	 */
	private static ?Edge_Provider $provider_instance = null;

	/**
	 * Register the integration
	 *
	 * @return void
	 */
	public static function register(): void {

		// Prevent multiple registrations
		if ( self::$registered ) {
			return;
		}

		// Bail if we shouldn't be transforming images
		if ( ! Helpers::should_transform_images() ) {
			return;
		}

		$instance = new self();
		$instance->add_filters();

		self::$registered = true;
	}

	/**
	 * Add filters to the handler.
	 *
	 * @return void
	 */
	private function add_filters() : void {

		// Hook into the earliest possible filter for image dimensions
		add_filter('wp_get_attachment_metadata', [$this, 'filter_attachment_metadata'], 1, 2);

		// First transform the attributes
		add_filter('wp_get_attachment_image_attributes', [$this, 'transform_attachment_image'], 10, 3);
		
		// Then wrap in picture element
		add_filter('wp_get_attachment_image', [$this, 'wrap_attachment_image'], 11, 5);
		
		// Finally enforce dimensions and cleanup
		add_filter('wp_get_attachment_image_attributes', [$this, 'enforce_dimensions'], 12, 3);
		add_filter('wp_get_attachment_image', [$this, 'cleanup_image_html'], 13, 5);

		// Transform images in content
		add_filter('wp_img_tag_add_width_and_height_attr', [$this, 'transform_image'], 5, 4);
		add_filter('render_block', [$this, 'transform_block_images'], 5, 2);

		// Ensure WordPress's default dimension handling still runs
		add_filter('wp_img_tag_add_width_and_height_attr', '__return_true', 999);

		// Enqueue styles
		add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);

		// Prevent WordPress from scaling images
		add_filter('big_image_size_threshold', [$this, 'adjust_image_size_threshold'], 10, 4);

		// Clean transformation attributes from attachment images
		add_filter('wp_get_attachment_image_attributes', [$this, 'clean_attachment_image_attributes'], 99);
	}

	/**
	 * Adjusts the threshold for big image scaling.
	 * Makes sure that we don't scale images that are already big enough.
	 *
	 * @param int|bool   $threshold     The threshold value in pixels.
	 * @param array|null $imagesize     Indexed array of width and height values in pixels.
	 * @param string     $file          Full path to the uploaded image file.
	 * @param int        $attachment_id Attachment post ID.
	 * 
	 * @return int|bool The adjusted threshold
	 */
	public function adjust_image_size_threshold( $threshold, $imagesize, string $file, int $attachment_id ) {
		if ( isset( $imagesize[0] ) && isset( $imagesize[1] ) ) {
			return max( $imagesize[0], $imagesize[1] );
		}
		return $threshold;
	}

	/**
	 * Filter attachment metadata to ensure correct dimensions
	 *
	 * @param array|string $data    Array of metadata or empty string.
	 * @param int         $post_id Attachment ID.
	 * 
	 * @return array Modified metadata
	 */
	public function filter_attachment_metadata($data, $post_id): array {
		// If $data is not an array, initialize it as one
		if (!is_array($data)) {
			$data = [];
		}

		// Check if we're processing a size that matches our pattern
		$size = get_post_meta($post_id, '_wp_attachment_requested_size', true);
		if ($size && preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
			$width = (int)$matches[1];
			$height = (int)$matches[2];
			
			// Store the dimensions in the metadata
			$data['sizes'][$size] = [
				'width' => $width,
				'height' => $height,
				'file' => basename($data['file']),
				'mime-type' => get_post_mime_type($post_id)
			];
		}
		
		return $data;
	}

	/**
	 * Final pass to enforce our dimensions
	 *
	 * @param array   $attr       Array of attribute values for the image markup.
	 * @param WP_Post $attachment Image attachment post.
	 * @param string  $size       Requested size.
	 * 
	 * @return array Modified attributes
	 */
	public function enforce_dimensions(array $attr, $attachment, $size): array {
		// Always use data-original-width/height if available
		if (isset($attr['data-original-width'], $attr['data-original-height'])) {
			// Force override the width and height
			$attr['width'] = $attr['data-original-width'];
			$attr['height'] = $attr['data-original-height'];
			
			// Return immediately to prevent any further modification
			return $attr;
		}
		
		// Fallback to size dimensions if needed
		$dimensions = $this->get_size_dimensions($size, $attachment->ID);
		if ($dimensions) {
			$attr['width'] = (string) $dimensions['width'];
			$attr['height'] = (string) $dimensions['height'];
		}
		
		return $attr;
	}

	/**
	 * Transform attachment image
	 *
	 * @param array        $attr        Array of attribute values.
	 * @param \WP_Post    $attachment  Attachment post object.
	 * @param string|array $size        Requested size.
	 * @return array Modified attributes
	 */
	public function transform_attachment_image(array $attr, \WP_Post $attachment, $size): array {
		// Skip if already processed
		if (isset($attr['class']) && strpos($attr['class'], 'edge-images-processed') !== false) {
			return $attr;
		}

		// Get dimensions and transform args
		$dimensions = $this->get_size_dimensions($size, $attachment->ID);
		if (!$dimensions) {
			return $attr;
		}

		// Check for special formats (SVG/AVIF)
		$is_special_format = isset($attr['src']) && 
			(Helpers::is_svg($attr['src']) || strpos($attr['src'], '.avif') !== false);

		// Get transform args based on size
		$transform_args = $this->get_registered_size_args($dimensions);

		// Transform src (skip for SVG/AVIF)
		if (isset($attr['src']) && !$is_special_format) {
			$provider = $this->get_provider_instance();
			$edge_args = array_merge(
				$provider->get_default_args(),
				$transform_args,
				array_filter([
					'w' => $dimensions['width'] ?? null,
					'h' => $dimensions['height'] ?? null,
					'fit' => $transform_args['fit'] ?? 'cover',
				])
			);
			$full_src = $this->get_full_size_url($attr['src'], $attachment->ID);
			$attr['src'] = Helpers::edge_src($full_src, $edge_args);
		}

		// Generate srcset with the same transformation args (skip for SVG/AVIF)
		if (isset($attr['src']) && $dimensions && !$is_special_format) {
			$full_src = $this->get_full_size_url($attr['src'], $attachment->ID);
			$srcset = Srcset_Transformer::transform(
				$full_src,
				$dimensions,
				$attr['sizes'] ?? "(max-width: {$dimensions['width']}px) 100vw, {$dimensions['width']}px",
				$transform_args
			);
			if ($srcset) {
				$attr['srcset'] = $srcset;
			}
		}

		// Add our classes
		$attr['class'] = isset($attr['class']) 
			? $attr['class'] . ' edge-images-img edge-images-processed' 
			: 'edge-images-img edge-images-processed';

		// Add picture wrap flag if picture wrapping is enabled
		if (Feature_Manager::is_feature_enabled('picture_wrap')) {
			$attr['data-wrap-in-picture'] = 'true';
		}

		return $attr;
	}

	/**
	 * Extract transformation arguments from attributes.
	 *
	 * @param array $attr The attributes array.
	 * @return array The transformation arguments.
	 */
	private function extract_transform_args(array $attr): array {
		$valid_args = Edge_Provider::get_valid_args();
		$all_valid_args = array_merge(
			array_keys($valid_args),
			array_merge(...array_filter(array_values($valid_args)))
		);
		
		return array_intersect_key($attr, array_flip($all_valid_args));
	}

	/**
	 * Get dimensions for a given size
	 */
	private function get_size_dimensions($size, int $attachment_id): ?array {
		// Check if this is an SVG
		$is_svg = get_post_mime_type($attachment_id) === 'image/svg+xml';

		// If size is an array, use those dimensions directly
		if (is_array($size) && isset($size[0], $size[1])) {
			return [
				'width' => $size[0],
				'height' => $size[1]
			];
		}

		// If it's a string size
		if (is_string($size)) {
			// Get registered image sizes
			$registered_sizes = wp_get_registered_image_subsizes();
			
			// Check if this is a registered size
			if (isset($registered_sizes[$size])) {
				$dimensions = [
					'width' => (int) $registered_sizes[$size]['width'],
					'height' => (int) $registered_sizes[$size]['height'],
					'crop' => (bool) $registered_sizes[$size]['crop']
				];

				// Constrain to max content width if necessary
				global $content_width;
				if ($content_width && $dimensions['width'] > $content_width) {
					$ratio = $dimensions['height'] / $dimensions['width'];
					$dimensions['width'] = $content_width;
					$dimensions['height'] = (int) round($content_width * $ratio);
				}

				return $dimensions;
			}

			// Try to get from attachment metadata
			$metadata = wp_get_attachment_metadata($attachment_id);
			if ($metadata && isset($metadata['sizes'])) {
				if ($size === 'full') {
					if ($is_svg) {
						// For SVGs, use the requested width or a default
						$width = $this->get_requested_svg_width($attachment_id);
						$height = $width; // Default to square if no height info available
						return [
							'width' => $width,
							'height' => $height
						];
					}
					return [
						'width' => $metadata['width'],
						'height' => $metadata['height']
					];
				} elseif (isset($metadata['sizes'][$size])) {
					return [
						'width' => $metadata['sizes'][$size]['width'],
						'height' => $metadata['sizes'][$size]['height']
					];
				}
			}
		}

		return null;
	}

	/**
	 * Wrap attachment image in picture element
	 *
	 * @param string       $html          The HTML img element markup.
	 * @param int         $attachment_id Image attachment ID.
	 * @param string|array $size          Size of image.
	 * @param bool|array   $attr_or_icon  Either the attributes array or icon boolean.
	 * @param bool|null    $icon          Whether the image should be treated as an icon.
	 * @return string Modified HTML
	 */
	public function wrap_attachment_image(string $html, int $attachment_id, $size, $attr_or_icon = [], $icon = null): string {
		// Handle flexible parameter order
		$attr = is_array($attr_or_icon) ? $attr_or_icon : [];
		if (is_bool($attr_or_icon)) {
			$icon = $attr_or_icon;
			$attr = [];
		}

		// Skip if already wrapped or if picture wrapping is disabled.
		if (strpos($html, '<picture') !== false || !Feature_Manager::is_feature_enabled('picture_wrap')) {
			return $html;
		}

		// Extract any wrapping anchor tag
		$has_link = false;
		$link_open = '';
		$link_close = '';
		$working_html = $html;
		
		if (preg_match('/<a[^>]*>(.*?)<\/a>/s', $html, $matches)) {
			$has_link = true;
			$working_html = $matches[1]; // Get just the img tag
			preg_match('/<a[^>]*>/', $matches[0], $link_matches);
			$link_open = $link_matches[0];
			$link_close = '</a>';
		}

		// Extract dimensions directly from the img tag
		$processor = new \WP_HTML_Tag_Processor($working_html);
		if ($processor->next_tag('img')) {
			$width = $processor->get_attribute('width');
			$height = $processor->get_attribute('height');
			if ($width && $height) {
				$dimensions = [
					'width' => (int) $width,
					'height' => (int) $height
				];
				
				// Create picture element with the dimensions from the img tag
				$picture_html = $this->create_picture_element($working_html, $dimensions, '');

				// If we have a link, wrap the picture element in it
				if ($has_link) {
					$picture_html = $link_open . $picture_html . $link_close;
				}

				// If the original HTML had a figure, replace just the img/anchor tag within it
				if (strpos($html, '<figure') !== false) {
					$replace = $has_link ? '/<a[^>]*>.*?<\/a>/' : '/<img[^>]*>/';
					return preg_replace($replace, $picture_html, $html);
				}

				return $picture_html;
			}
		}

		// If we couldn't get dimensions from the img tag, try getting them from the size
		$dimensions = $this->get_size_dimensions($size, $attachment_id);
		if ($dimensions) {
			// Create picture element with the dimensions from size
			$picture_html = $this->create_picture_element($working_html, $dimensions, '');

			// If we have a link, wrap the picture element in it
			if ($has_link) {
				$picture_html = $link_open . $picture_html . $link_close;
			}

			// If the original HTML had a figure, replace just the img/anchor tag within it
			if (strpos($html, '<figure') !== false) {
				$replace = $has_link ? '/<a[^>]*>.*?<\/a>/' : '/<img[^>]*>/';
				return preg_replace($replace, $picture_html, $html);
			}

			return $picture_html;
		}

		// If we still can't get dimensions, return the original HTML
		return $html;
	}

	/**
	 * Transform an image tag
	 *
	 * @param string|bool $value         The filtered value.
	 * @param string     $image_html    The HTML image tag.
	 * @param string     $context       The context (header, content, etc).
	 * @param int        $attachment_id The attachment ID.
	 * 
	 * @return string The transformed image HTML
	 */
	public function transform_image($value, string $image_html, string $context, $attachment_id): string {
		// Skip if already processed
		if (strpos($image_html, 'edge-images-processed') !== false) {
			return $image_html;
		}

		$processor = new \WP_HTML_Tag_Processor($image_html);
		if (!$processor->next_tag('img')) {
			return $image_html;
		}

		// Transform the image with context
		$processor = $this->transform_image_tag($processor, $attachment_id, $image_html, $context);
		$transformed = $processor->get_updated_html();

		// Check if picture wrapping is disabled.
		if (Feature_Manager::is_disabled('picture_wrap')) {
			return $transformed;
		}

		// Get dimensions for the picture element
		$dimensions = $this->get_dimensions_from_html(new \WP_HTML_Tag_Processor($transformed));
		if (!$dimensions) {
			return $transformed;
		}

		// If we have a figure, replace it with picture
		if (strpos($image_html, '<figure') !== false) {
			$figure_classes = $this->extract_figure_classes($image_html, []);
			$img_html = $this->extract_img_tag($transformed);
			
			return $this->create_picture_element($img_html, $dimensions, $figure_classes);
		}

		// Otherwise create picture element with just the image
		return $this->create_picture_element($transformed, $dimensions);
	}

	/**
	 * Clean transformation attributes from an image tag.
	 *
	 * @since 4.5.0
	 * 
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * @return \WP_HTML_Tag_Processor The cleaned processor.
	 */
	private function clean_transform_attributes(\WP_HTML_Tag_Processor $processor): \WP_HTML_Tag_Processor {
		// Get all valid transformation parameters
		$valid_args = Edge_Provider::get_valid_args();
		$all_params = [];

		// Include both short forms and aliases
		foreach ($valid_args as $short => $aliases) {
			// Skip width and height as they're valid HTML attributes
			if ($short === 'w' || $short === 'h') {
				continue;
			}
			
			$all_params[] = $short;
			if (is_array($aliases)) {
				$all_params = array_merge($all_params, $aliases);
			}
		}

		// Remove transformation attributes
		foreach ($all_params as $param) {
			$processor->remove_attribute($param);
		}

		return $processor;
	}

	/**
	 * Transform an image tag using the HTML Tag Processor.
	 *
	 * @since 4.0.0
	 * 
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * @param int|null               $image_id  Optional. The image attachment ID.
	 * @param string                 $html      Optional. The original HTML.
	 * @param string                 $context   Optional. The transformation context.
	 * @param array                  $args      Optional. Additional transformation arguments.
	 * @return \WP_HTML_Tag_Processor The transformed processor.
	 */
	public function transform_image_tag(
		\WP_HTML_Tag_Processor $processor,
		?int $image_id = null,
		string $html = '',
		string $context = '',
		array $args = []
	): \WP_HTML_Tag_Processor {
		// Check cache first
		$cache_key = 'img_' . md5($html . serialize($args));
		$cached_html = Cache::get_image_html($image_id ?: 0, $cache_key, []);
		
		if ($cached_html !== false) {
			return new \WP_HTML_Tag_Processor($cached_html);
		}

		// Get src
		$src = $processor->get_attribute('src');
		if (!$src) {
			return $processor;
		}

		// Check if we should transform this URL
		if (!Helpers::should_transform_url($src)) {
			return $processor;
		}

		// Get dimensions - first try width/height attributes
		$width = $processor->get_attribute('width');
		$height = $processor->get_attribute('height');
		
		$dimensions = null;
		if ($width && $height) {
			$dimensions = [
				'width' => (string) $width,
				'height' => (string) $height
			];
		}

		// If no dimensions from attributes, try Image_Dimensions::get
		if (!$dimensions) {
			$dimensions = Image_Dimensions::get($processor, $image_id);
		}

		// Transform the URL if we have dimensions
		if ($dimensions) {
			// Use transform_image_urls for consistent behavior
			$this->transform_image_urls($processor, $dimensions, $html, $context, $args);
		}

		// Always add the processed class
		$processor->set_attribute('class', trim($processor->get_attribute('class') . ' edge-images-processed'));

		// Cache the result
		Cache::set_image_html($image_id ?: 0, $cache_key, [], $processor->get_updated_html());

		// Clean transformation attributes before returning
		return $this->clean_transform_attributes($processor);
	}

	/**
	 * Add required classes to image
	 *
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * 
	 * @return void
	 */
	private function add_image_classes( \WP_HTML_Tag_Processor $processor ): void {
		$classes = $processor->get_attribute( 'class' );
		$classes = $classes ? $classes . ' edge-images-img edge-images-processed' : 'edge-images-img edge-images-processed';
		$processor->set_attribute( 'class', $classes );
	}

	/**
	 * Transform image URLs
	 *
	 * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
	 * @param array|null             $dimensions    Optional dimensions.
	 * @param string                 $original_html The original HTML.
	 * @param string                 $context       The context (content, header, etc).
	 * 
	 * @return void
	 */
	private function transform_image_urls( 
		\WP_HTML_Tag_Processor $processor, 
		?array $dimensions, 
		string $original_html = '',
		string $context = '',
		array $transform_args = []
	): void {
		// Get src
		$src = $processor->get_attribute('src');
		if (!$src || !$dimensions) {
			return;
		}

		// Get attachment ID
		$attachment_id = $this->get_attachment_id_from_classes($processor);
		if (!$attachment_id) {
			$attachment_id = attachment_url_to_postid($src);
		}

		// Get full size URL
		$full_src = $this->get_full_size_url($src, $attachment_id);

		// Initialize original dimensions with current dimensions
		$original_dimensions = $dimensions;

		// Determine if we should constrain the image
		$should_constrain = $this->should_constrain_image($processor, $original_html, $context);

		// Get content width if we're constraining
		if ($should_constrain) {
			$max_width = min($dimensions['width'], Settings::get_max_width());
			$dimensions = $this->constrain_dimensions($dimensions, [
				'width' => $max_width,
				'height' => null
			]);
		}

		// Get a provider instance to access default args
		$provider = Helpers::get_edge_provider();
		
		// Transform src with constrained dimensions when appropriate
		$edge_args = array_merge(
			$provider->get_default_args(),
			$transform_args,
			array_filter([
				'width' => $dimensions['width'],
				'height' => $dimensions['height'],
				'fit' => $fit ?? 'cover',
			])
		);
		
		$transformed_src = Helpers::edge_src($full_src, $edge_args);
		$processor->set_attribute('src', $transformed_src);
		
		// Update width and height attributes to match the dimensions we're using
		$processor->set_attribute('width', (string)$dimensions['width']);
		$processor->set_attribute('height', (string)$dimensions['height']);
		
		// Store original dimensions for picture element
		$processor->set_attribute('data-original-width', (string)$original_dimensions['width']);
		$processor->set_attribute('data-original-height', (string)$original_dimensions['height']);
		
		// Get sizes attribute (or set default)
		$sizes = "(max-width: {$dimensions['width']}px) 100vw, {$dimensions['width']}px";
		$processor->set_attribute('sizes', $sizes);
		
		// Generate srcset using the Srcset_Transformer with original dimensions
		$srcset = Srcset_Transformer::transform(
			$full_src, 
			$dimensions,
			$sizes
		);
		if ($srcset) {
			$processor->set_attribute('srcset', $srcset);
		}

		// Clean transformation attributes before adding classes
		$this->clean_transform_attributes($processor);

		// Add our classes
		$this->add_image_classes($processor);
	}

	/**
	 * Transform images in block content
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * 
	 * @return string The transformed block content
	 */
	public function transform_block_images( string $block_content, array $block ): string {
		// Bail if we don't have any images
		if ( ! str_contains( $block_content, '<img' ) ) {
			return $block_content;
		}

		// Transform figures with images first
		$block_content = $this->transform_figures_in_block( $block_content );

		// Then transform standalone images
		$block_content = $this->transform_standalone_images_in_block( $block_content );

		return $block_content;
	}

	/**
	 * Transform figures containing images in block content
	 *
	 * @param string $block_content The block content.
	 * 
	 * @return string Modified block content
	 */
	private function transform_figures_in_block( string $block_content ): string {
		if ( ! preg_match_all( '/<figure[^>]*>.*?<\/figure>/s', $block_content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $block_content;
		}

		$disable_picture_wrap = Feature_Manager::is_disabled('picture_wrap');
		$offset_adjustment = 0;

		foreach ( $matches[0] as $match ) {
			$figure_html = $match[0];

			if ( ! $this->should_transform_figure( $figure_html ) ) {
				continue;
			}

			$transformed_html = $this->transform_figure_content( 
				$figure_html, 
				$disable_picture_wrap 
			);

			$block_content = $this->replace_content_at_offset(
				$block_content,
				$transformed_html,
				$match[1] + $offset_adjustment,
				strlen( $figure_html )
			);
			
			$offset_adjustment += strlen( $transformed_html ) - strlen( $figure_html );
		}

		return $block_content;
	}

	/**
	 * Transform standalone images in block content
	 *
	 * @param string $block_content The block content.
	 * 
	 * @return string Modified block content
	 */
	private function transform_standalone_images_in_block( string $block_content ): string {
		if ( ! preg_match_all( '/<img[^>]*>/', $block_content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $block_content;
		}

		$offset_adjustment = 0;

		foreach ( $matches[0] as $match ) {
			$img_html = $match[0];
			
			if ( ! $this->should_transform_standalone_image( $img_html, $block_content, $match[1] ) ) {
				continue;
			}

			$transformed_html = $this->transform_image( true, $img_html, 'block', 0 );
			
			$block_content = $this->replace_content_at_offset(
				$block_content,
				$transformed_html,
				$match[1] + $offset_adjustment,
				strlen( $img_html )
			);
			
			$offset_adjustment += strlen( $transformed_html ) - strlen( $img_html );
		}

		return $block_content;
	}

	/**
	 * Check if a figure should be transformed
	 *
	 * @param string $figure_html The figure HTML.
	 * 
	 * @return bool Whether the figure should be transformed
	 */
	private function should_transform_figure( string $figure_html ): bool {
		return str_contains( $figure_html, '<img' ) && 
			   ! str_contains( $figure_html, '<picture' );
	}

	/**
	 * Check if a standalone image should be transformed
	 *
	 * @param string $img_html      The image HTML.
	 * @param string $block_content The full block content.
	 * @param int    $position      The position of the image in the content.
	 * 
	 * @return bool Whether the image should be transformed
	 */
	private function should_transform_standalone_image( string $img_html, string $block_content, int $position ): bool {
		return ! str_contains( $img_html, 'edge-images-processed' ) && 
			   ! str_contains( substr( $block_content, 0, $position ), '<picture' ) &&
			   ! str_contains( substr( $block_content, 0, $position ), '<figure' );
	}

	/**
	 * Transform figure content
	 *
	 * @since 4.0.0
	 * 
	 * @param string $figure_html         The figure HTML.
	 * @param bool   $disable_picture_wrap Whether picture wrapping is disabled.
	 * @return string Transformed HTML.
	 */
	private function transform_figure_content( string $figure_html, bool $disable_picture_wrap ): string {
		// Extract figure classes, passing empty array as second parameter
		$figure_classes = $this->extract_figure_classes( $figure_html );

		if ( $disable_picture_wrap ) {
			$img_html = $this->extract_img_tag( $figure_html );
			if ( ! $img_html ) {
				return $figure_html;
			}

			$transformed_img = $this->transform_image( true, $img_html, 'block', 0 );
			return str_replace( $img_html, $transformed_img, $figure_html );
		}

		return $this->transform_figure_block( $figure_html, $figure_classes );
	}

	/**
	 * Replace content at a specific offset
	 *
	 * @param string $content     The original content.
	 * @param string $new_content The new content.
	 * @param int    $offset      The offset position.
	 * @param int    $length      The length of content to replace.
	 * 
	 * @return string Modified content
	 */
	private function replace_content_at_offset( string $content, string $new_content, int $offset, int $length ): string {
		return substr_replace(
			$content,
			$new_content,
			$offset,
			$length
		);
	}

	/**
	 * Transform a figure block containing an image
	 *
	 * @param string $block_content The block content.
	 * @param array  $figure_classes The figure classes.
	 * 
	 * @return string The transformed block content
	 */
	private function transform_figure_block( string $block_content, array $figure_classes = [] ): string {
		// Extract the img tag
		$img_html = $this->extract_img_tag( $block_content );
		
		if ( ! $img_html ) {
			return $block_content;
		}

		// Transform the image
		$transformed_img = $this->transform_image( true, $img_html, 'block', 0 );
		
		// Get dimensions from the transformed image
		$processor = new \WP_HTML_Tag_Processor( $transformed_img );
		if ( ! $processor->next_tag( 'img' ) ) {
			return $block_content;
		}

		$width = $processor->get_attribute( 'width' );
		$height = $processor->get_attribute( 'height' );

		if ( ! $width || ! $height ) {
			return $block_content;
		}

		$dimensions = [
			'width' => $width,
			'height' => $height
		];

		// Create picture element with the transformed image
		return $this->create_picture_element( 
			$transformed_img, 
			$dimensions, 
			implode( ' ', array_filter( $figure_classes ) ) 
		);
	}

	/**
	 * Extract figure classes from block content and attributes.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $block_content The block content.
	 * @param array  $block         Optional block data.
	 * @return array The combined classes.
	 */
	private function extract_figure_classes( string $block_content, array $block = [] ): array {
		// Extract classes from figure element
		preg_match('/<figure[^>]*class=["\']([^"\']*)["\']/', $block_content, $matches);
		$figure_classes = $matches[1] ?? '';

		// Add alignment if present in block attributes
		if ( isset( $block['attrs']['align'] ) ) {
			$figure_classes .= " align{$block['attrs']['align']}";
		}

		// Convert to array and clean up
		$classes = array_filter( explode( ' ', $figure_classes ) );

		return array_unique( $classes );
	}

	/**
	 * Extract img tag from HTML
	 *
	 * @param string $html The HTML containing the img tag.
	 * 
	 * @return string|null The img tag HTML or null if not found
	 */
	private function extract_img_tag( string $html ): ?string {
		if ( preg_match( '/<img[^>]*>/', $html, $matches ) ) {
			return $matches[0];
		}
		return null;
	}

	/**
	 * Transform image and get its dimensions
	 *
	 * @param string $img_html The image HTML.
	 * 
	 * @return array|null Array with transformed HTML and dimensions, or null if failed
	 */
	private function transform_and_get_dimensions( string $img_html ): ?array {
		// First get dimensions from the original image
		$processor = new \WP_HTML_Tag_Processor( $img_html );
		if ( ! $processor->next_tag( 'img' ) ) {
			return null;
		}

		// If image is already processed, get dimensions directly
		if ( str_contains( $processor->get_attribute( 'class' ) ?? '', 'edge-images-processed' ) ) {
			$width = $processor->get_attribute( 'width' );
			$height = $processor->get_attribute( 'height' );
			
			if ( $width && $height ) {
				return [
					'html' => $img_html,
					'dimensions' => [
						'width' => $width,
						'height' => $height
					]
				];
			}
		}

		// Otherwise transform the image
		$transformed_img = $this->transform_image( true, $img_html, 'block', 0 );
		
		$processor = new \WP_HTML_Tag_Processor( $transformed_img );
		if ( ! $processor->next_tag( 'img' ) ) {
			return null;
		}

		$width = $processor->get_attribute( 'width' );
		$height = $processor->get_attribute( 'height' );

		return [
			'html' => $transformed_img,
			'dimensions' => [
				'width' => $width,
				'height' => $height
			]
		];
	}

	/**
	 * Enqueue required styles
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style(
			'edge-images',
			plugins_url( 'assets/css/images.min.css', dirname( __FILE__ ) ),
			[],
			filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/css/images.min.css')
		);

	}

	/**
	 * Get dimensions from HTML attributes
	 *
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * 
	 * @return array|null Array with width and height, or null if not found
	 */
	private function get_dimensions_from_html(\WP_HTML_Tag_Processor $processor): ?array {
		$width = $processor->get_attribute('width');
		$height = $processor->get_attribute('height');

		if (!$width || !$height) {
			return null;
		}

		return [
			'width' => (string)$width,
			'height' => (string)$height,
		];
	}

	/**
	 * Get dimensions from attachment metadata
	 *
	 * @param int $attachment_id The attachment ID.
	 * 
	 * @return array|null Array with width and height, or null if not found
	 */
	private function get_dimensions_from_attachment(int $attachment_id): ?array {
		$metadata = wp_get_attachment_metadata($attachment_id);
		
		if (!$metadata || !isset($metadata['width'], $metadata['height'])) {
			return null;
		}

		return [
			'width' => (string) $metadata['width'],
			'height' => (string) $metadata['height']
		];
	}

	/**
	 * Get the requested width for an SVG
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return int The requested width.
	 */
	private function get_requested_svg_width(int $attachment_id): int {
		// First check for explicitly requested size
		$size = get_post_meta($attachment_id, '_wp_attachment_requested_size', true);
		if ($size && preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
			return (int) $matches[1];
		}
		
		// Then check metadata
		$metadata = wp_get_attachment_metadata($attachment_id);
		if (isset($metadata['width']) && $metadata['width'] > 0) {
			return (int) $metadata['width'];
		}
		
		// Finally check the actual SVG file
		$file_path = get_attached_file($attachment_id);
		if ($file_path && file_exists($file_path)) {
			$svg_data = file_get_contents($file_path);
			if ($svg_data && preg_match('/width=["\'](\d+)["\']/i', $svg_data, $width_match)) {
				return (int) $width_match[1];
			}
		}
		
		// Default fallback
		return 1; // Default to 1px if we can't determine width
	}

	/**
	 * Get image dimensions, trying multiple sources
	 *
	 * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
	 * @param int|null              $attachment_id The attachment ID.
	 * 
	 * @return array|null Array with width and height, or null if not found
	 */
	private function get_image_dimensions(\WP_HTML_Tag_Processor $processor, ?int $attachment_id = null ): ?array {

		// Try HTML first
		$dimensions = $this->get_dimensions_from_html( $processor );
		if ( $dimensions ) {
			return $dimensions;
		}

		// Try attachment ID from parameter
		if ( $attachment_id ) {
			$dimensions = $this->get_dimensions_from_attachment( $attachment_id );
			if ( $dimensions ) {
				return $dimensions;
			}
		}

		// Try getting attachment ID from classes
		$found_id = $this->get_attachment_id_from_classes( $processor );
		if ( $found_id ) {
			$dimensions = $this->get_dimensions_from_attachment( $found_id );
			if ( $dimensions ) {
				return $dimensions;
			}
		}

		// Try getting the ID from the URL.
		$src = $processor->get_attribute( 'src' );
		if ( $src ) {
			$attachment_id = attachment_url_to_postid( $src );
			if ( $attachment_id ) {
				$dimensions = $this->get_dimensions_from_attachment( $attachment_id );
			}
		}

		// Try getting the dimensions from the image file.
		if ($src) {
			$dimensions = $this->get_dimensions_from_image_file( $src );
			if ( $dimensions ) {
				return $dimensions;
			}
		}
		
		return null;
	}

	/**
	 * Get dimensions from image file
	 *
	 * @param string $src The image URL.
	 * 
	 * @return array|null Array with width and height, or null if failed
	 */
	private function get_dimensions_from_image_file( string $src ): ?array {
		
		// Get upload directory info
		$upload_dir = wp_get_upload_dir();
		
		// First try to handle upload directory URLs
		if ( strpos( $src, $upload_dir['baseurl'] ) !== false ) {
			$file_path = str_replace( 
				$upload_dir['baseurl'], 
				$upload_dir['basedir'], 
				$src 
			);
		} 
		// Then try theme/plugin URLs
		else {
			$relative_path = str_replace( 
				site_url('/'), 
				'', 
				$src 
			);
			$file_path = ABSPATH . wp_normalize_path( $relative_path );
		}

		// Check if file exists and is readable
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return null;
		}

		// Get image dimensions
		$dimensions = @getimagesize( $file_path );
		if ( ! $dimensions ) {
			return null;
		}

		return [
			'width' => (string) $dimensions[0],
			'height' => (string) $dimensions[1]
		];
	}

	/**
	 * Fill gaps in srcset widths array
	 *
	 * @param array $widths Array of widths.
	 * @param int   $max_gap Maximum allowed gap between widths.
	 * 
	 * @return array Modified array of widths
	 */
	private function fill_srcset_gaps(array $widths, int $max_gap = 200): array {
		$filled = [];
		$count = count($widths);
		
		for ($i = 0; $i < $count - 1; $i++) {
			$filled[] = $widths[$i];
			$gap = $widths[$i + 1] - $widths[$i];
			
			// If gap is larger than max_gap, add intermediate values
			if ($gap > $max_gap) {
				$steps = ceil($gap / $max_gap);
				$step_size = $gap / $steps;
				
				for ($j = 1; $j < $steps; $j++) {
					$intermediate = round($widths[$i] + ($j * $step_size));
					$filled[] = $intermediate;
				}
			}
		}
		
		// Add the last width
		$filled[] = end($widths);
		
		return $filled;
	}

	/**
	 * Get srcset widths and DPR variants based on sizes attribute
	 *
	 * @param string $sizes    The sizes attribute value.
	 * @param int    $max_width The maximum width of the image.
	 * 
	 * @return array Array of widths for srcset
	 */
	private function get_srcset_widths_from_sizes( string $sizes, int $max_width ): array {
		// Get DPR multipliers from Srcset_Transformer
		$dprs = Srcset_Transformer::$width_multipliers;
		
		// Generate variants based on the original width
		$variants = [];

		// Always include minimum width if the image is large enough
		if ($max_width >= Srcset_Transformer::$min_srcset_width * 2) {
			$variants[] = Srcset_Transformer::$min_srcset_width;
		}
		
		foreach ($dprs as $dpr) {
			$scaled_width = round($max_width * $dpr);
			
			// If scaled width would exceed max_srcset_width
			if ($scaled_width > Srcset_Transformer::$max_srcset_width) {
				// Add max_srcset_width if we don't already have it
				if (!in_array(Srcset_Transformer::$max_srcset_width, $variants)) {
					$variants[] = Srcset_Transformer::$max_srcset_width;
				}
			} 
			// Otherwise add the scaled width if it meets our min/max criteria
			elseif ($scaled_width >= Srcset_Transformer::$min_srcset_width) {
				$variants[] = $scaled_width;
			}
		}

		// Sort and remove duplicates
		$variants = array_unique($variants);
		sort($variants);

		// Fill in any large gaps
		$variants = $this->fill_srcset_gaps($variants);

		return $variants;
	}

	/**
	 * Generate srcset string based on image dimensions and sizes
	 *
	 * @param string $src     Original image URL.
	 * @param array  $dimensions Image dimensions.
	 * @param string $sizes    The sizes attribute value.
	 * 
	 * @return string Generated srcset
	 */
	private function generate_srcset( string $src, array $dimensions, string $sizes ): string {
		$max_width = (int) $dimensions['width'];
		$ratio = $dimensions['height'] / $dimensions['width'];
		
		$widths = $this->get_srcset_widths_from_sizes($sizes, $max_width);
		
		$srcset_parts = [];
		foreach ($widths as $width) {
			$height = round($width * $ratio);
			$edge_args = array_merge(
				$this->default_edge_args,
				[
					'width' => $width,
					'height' => $height,
				]
			);
			$edge_url = Helpers::edge_src($src, $edge_args);
			$srcset_parts[] = "$edge_url {$width}w";
		}
		
		return implode(', ', $srcset_parts);
	}

	/**
	 * Get full size image dimensions
	 *
	 * @param int $attachment_id The attachment ID.
	 * 
	 * @return array|null Array with width and height, or null if not found
	 */
	private function get_full_size_dimensions(int $attachment_id): ?array {
		$metadata = wp_get_attachment_metadata($attachment_id);
		
		if (!$metadata || !isset($metadata['width'], $metadata['height'])) {
			return null;
		}

		return [
			'width' => (string) $metadata['width'],
			'height' => (string) $metadata['height']
		];
	}

	/**
	 * Get full size image URL
	 *
	 * @param string   $src          The current image URL.
	 * @param int|null $attachment_id Optional attachment ID.
	 * 
	 * @return string The full size image URL
	 */
	private function get_full_size_url(string $src, ?int $attachment_id = null): string {
		
		// Try getting the full URL from attachment ID first
		if ($attachment_id) {
			$full_url = wp_get_attachment_image_url($attachment_id, 'full');
			if ($full_url) {
				return $full_url;
			}
		}
		
		// Get the provider instance
		$provider = $this->get_provider_instance();
		
		// Remove any existing transformations
		$src = $provider::clean_transformed_url($src);
		
		// Try to convert the current URL to a full size URL
		$path_parts = pathinfo($src);
		
		// Ensure we have all required parts
		$dirname = $path_parts['dirname'] ?? '';
		$filename = $path_parts['filename'] ?? '';
		$extension = $path_parts['extension'] ?? '';
		
		if (!$filename || !$extension) {
			return $src; // Return original if we can't parse it
		}
		
		// Remove any size suffix from filename
		$clean_filename = preg_replace('/-\d+x\d+$/', '', $filename);
		
		// Reconstruct the URL
		return $dirname . '/' . $clean_filename . '.' . $extension;
	}

	/**
	 * Debug final attributes to catch any changes after our filter
	 *
	 * @param array   $attr       Array of attribute values for the image markup.
	 * @param WP_Post $attachment Image attachment post.
	 * @param string  $size       Requested size.
	 * 
	 * @return array Modified attributes
	 */
	public function debug_final_attributes( array $attr, $attachment, $size ): array {
		return $attr;
	}

	/**
	 * Final cleanup of the image HTML
	 *
	 * @since 4.0.0
	 * 
	 * @param string       $html          The HTML img element markup.
	 * @param int         $attachment_id Image attachment ID.
	 * @param string|array $size          Size of image.
	 * @param bool|array   $attr_or_icon  Either the attributes array or icon boolean.
	 * @param bool|null    $icon          Whether the image should be treated as an icon.
	 * @return string Modified HTML
	 */
	public function cleanup_image_html(string $html, int $attachment_id, $size, $attr_or_icon = [], $icon = null): string {
		// Handle flexible parameter order
		$attr = is_array($attr_or_icon) ? $attr_or_icon : [];
		if (is_bool($attr_or_icon)) {
			$icon = $attr_or_icon;
			$attr = [];
		}

		// Check if this is our processed image
		if (strpos($html, 'edge-images-processed') === false) {
			return $html;
		}

		// Get the correct dimensions from data attributes and update both width and height
		if (preg_match('/data-original-width="(\d+)"/', $html, $width_matches) && 
			preg_match('/data-original-height="(\d+)"/', $html, $height_matches)) {
			$correct_width = $width_matches[1];
			$correct_height = $height_matches[1];
			
			// Update both width and height attributes
			$html = preg_replace(
				['/width="\d+"/', '/height="\d+"/'], 
				['width="' . $correct_width . '"', 'height="' . $correct_height . '"'],
				$html
			);

			// Also fix any incorrect sizes attributes
			if (preg_match('/sizes="[^"]*"/', $html, $sizes_match)) {
				$current_sizes = $sizes_match[0];
				if (strpos($current_sizes, 'auto') !== false || strpos($current_sizes, '238px') !== false) {
					$new_sizes = 'sizes="(max-width: ' . $correct_width . 'px) 100vw, ' . $correct_width . 'px"';
					$html = str_replace($current_sizes, $new_sizes, $html);
				}
			}
		}

		// Remove the data attributes we don't want in the final output
		$html = preg_replace([
			'/\s*data-wrap-in-picture="[^"]*"/',
			'/\s*data-original-width="[^"]*"/',
			'/\s*data-original-height="[^"]*"/',
			'/\s*data-edge-images-locked-dimensions="[^"]*"/'
		], '', $html);

		return $html;
	}

	/**
	 * Create a picture element wrapper.
	 *
	 * @param string $img_html   The image HTML to wrap.
	 * @param array  $dimensions The image dimensions.
	 * @param string $class      Optional additional class.
	 * @return string The wrapped HTML.
	 */
	private function create_picture_element(string $img_html, array $dimensions, string $class = ''): string {
		return Picture::create($img_html, $dimensions, $class);
	}

	/**
	 * Check if image should be constrained by content width
	 *
	 * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
	 * @param string                $original_html  Original HTML string.
	 * @param string                $context       The context (content, header, etc).
	 * 
	 * @return bool Whether the image should be constrained
	 */
	private function should_constrain_image(\WP_HTML_Tag_Processor $processor, string $original_html, string $context = ''): bool {
		// Only constrain images in the main content area
		if (!in_array($context, ['content', 'block', 'post', 'page'], true)) {
			return false;
		}

		// Check for alignment classes that indicate full-width
		$classes = $processor->get_attribute('class') ?? '';
		if (preg_match('/(alignfull|alignwide|full-width|width-full)/i', $classes)) {
			return false;
		}

		// Check parent figure for alignment classes
		if (strpos($original_html, '<figure') !== false) {
			if (preg_match('/<figure[^>]*class=["\']([^"\']*)["\']/', $original_html, $matches)) {
				if (preg_match('/(alignfull|alignwide|full-width|width-full)/i', $matches[1])) {
					return false;
				}
			}
		}

		// Check for explicit width attribute or style
		$width_attr = $processor->get_attribute('width');
		$style_attr = $processor->get_attribute('style');
		
		// Check for vw units in width
		if ($width_attr && strpos($width_attr, 'vw') !== false) {
			return false;
		}

		// Check for percentage or vw units in style
		if ($style_attr && preg_match('/width\s*:\s*(100%|[0-9]+vw)/', $style_attr)) {
			return false;
		}

		// Check for size-full class on image or parent figure
		if (strpos($classes, 'size-full') !== false || 
				strpos($original_html, 'size-full') !== false) {
			return false;
		}

		return true;
	}

	/**
	 * Get transform args for a registered size
	 *
	 * @param array $dimensions The image dimensions.
	 * @param bool  $crop      Whether the size is cropped.
	 * @return array The transform arguments
	 */
	private function get_registered_size_args(array $dimensions, bool $crop = true): array {
		
		// Get provider instance to access default args
		$provider = $this->get_provider_instance();
		
		return array_merge(
			$provider->get_default_args(),
			[
				'w' => $dimensions['width'],
				'h' => $dimensions['height'],
				'fit' => $crop ? 'cover' : 'contain',
			]
		);
	}

	/**
	 * Get the edge provider instance.
	 * 
	 * @return Edge_Provider The provider instance.
	 */
	private function get_provider_instance(): Edge_Provider {
		if (self::$provider_instance === null) {
			self::$provider_instance = Helpers::get_edge_provider();
		}
		return self::$provider_instance;
	}

	/**
	 * Get attachment ID from image classes
	 *
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * 
	 * @return int|null Attachment ID or null if not found
	 */
	private function get_attachment_id_from_classes(\WP_HTML_Tag_Processor $processor): ?int {
		$classes = $processor->get_attribute('class');
		if (!$classes) {
			return null;
		}

		// Look for wp-image-{ID} class
		if (preg_match('/wp-image-(\d+)/', $classes, $matches)) {
			return (int) $matches[1];
		}

		// Look for attachment-{ID} class
		if (preg_match('/attachment-(\d+)/', $classes, $matches)) {
			return (int) $matches[1];
		}

		return null;
	}

	/**
	 * Constrain dimensions while maintaining aspect ratio.
	 *
	 * @param array $dimensions Original dimensions ['width' => int, 'height' => int].
	 * @param array $max_dimensions Maximum dimensions ['width' => int|null, 'height' => int|null].
	 * @return array Constrained dimensions.
	 */
	private function constrain_dimensions(array $dimensions, array $max_dimensions): array {
		$width = (int) $dimensions['width'];
		$height = (int) $dimensions['height'];
		$max_width = $max_dimensions['width'];
		$max_height = $max_dimensions['height'];

		// If no constraints or image is smaller than constraints, return original
		if ((!$max_width || $width <= $max_width) && (!$max_height || $height <= $max_height)) {
			return $dimensions;
		}

		$ratio = $width / $height;

		if ($max_width && (!$max_height || ($max_width / $max_height <= $ratio))) {
			$new_width = $max_width;
			$new_height = round($max_width / $ratio);
		} else {
			$new_height = $max_height;
			$new_width = round($max_height * $ratio);
		}

		return [
			'width' => (string) $new_width,
			'height' => (string) $new_height
		];
	}

	/**
	 * Clean transformation attributes from attachment image attributes.
	 *
	 * @since 4.5.0
	 * 
	 * @param array $attr Image attributes.
	 * @return array Cleaned attributes.
	 */
	public function clean_attachment_image_attributes(array $attr): array {
		// Get all valid transformation parameters
		$valid_args = Edge_Provider::get_valid_args();
		$all_params = [];

		// Include both short forms and aliases
		foreach ($valid_args as $short => $aliases) {
			// Skip width and height as they're valid HTML attributes
			if ($short === 'w' || $short === 'h') {
				continue;
			}
			
			$all_params[] = $short;
			if (is_array($aliases)) {
				$all_params = array_merge($all_params, $aliases);
			}
		}

		// Remove transformation attributes
		foreach ($all_params as $param) {
			unset($attr[$param]);
		}

		return $attr;
	}

}




