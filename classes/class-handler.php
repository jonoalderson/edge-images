<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

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

		// Create an instance of the class to work with
		$instance = new self();

		// Hook into the earliest possible filter for image dimensions
		add_filter('wp_get_attachment_metadata', array($instance, 'filter_attachment_metadata'), 1, 2);

		// First transform the attributes
		add_filter('wp_get_attachment_image_attributes', array($instance, 'transform_attachment_image'), 10, 3);
		
		// Then wrap in picture element
		add_filter('wp_get_attachment_image', array($instance, 'wrap_attachment_image'), 11, 5);
		
		// Finally enforce dimensions and cleanup
		add_filter('wp_get_attachment_image_attributes', array($instance, 'enforce_dimensions'), 12, 3);
		add_filter('wp_get_attachment_image', array($instance, 'cleanup_image_html'), 13, 5);

		// Transform images in content
		add_filter('wp_img_tag_add_width_and_height_attr', array($instance, 'transform_image'), 5, 4);
		add_filter('render_block', array($instance, 'transform_block_images'), 5, 2);

		// Ensure WordPress's default dimension handling still runs
		add_filter('wp_img_tag_add_width_and_height_attr', '__return_true', 999);

		// Enqueue styles
		add_action('wp_enqueue_scripts', array($instance, 'enqueue_styles'));

		// Prevent WordPress from scaling images
		add_filter('big_image_size_threshold', array($instance, 'adjust_image_size_threshold'), 10, 4);

		self::$registered = true;
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
	 * @param array $data    Array of metadata.
	 * @param int   $post_id Attachment ID.
	 * 
	 * @return array Modified metadata
	 */
	public function filter_attachment_metadata($data, $post_id): array {
		// Check if we're processing a size that matches our pattern
		$size = get_post_meta($post_id, '_wp_attachment_requested_size', true);
		if ($size && preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
			$width = (int)$matches[1];
			$height = (int)$matches[2];
			
			// Store the dimensions in the metadata
			$data['_edge_images_dimensions'] = [
				'width' => $width,
				'height' => $height
			];
			
			// Also store in post meta for redundancy
			update_post_meta($post_id, '_edge_images_dimensions', [
				'width' => $width,
				'height' => $height
			]);
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
	 * Transform attachment image attributes
	 *
	 * @param array   $attr       Array of attribute values for the image markup.
	 * @param WP_Post $attachment Image attachment post.
	 * @param string  $size       Requested size.
	 * 
	 * @return array Modified attributes
	 */
	public function transform_attachment_image(array $attr, $attachment, $size): array {
		// Get dimensions and crop setting from the registered size if applicable
		$registered_size = null;
		$dimensions = null;
		$transform_args = [];

		// First try to get dimensions from registered size
		if (is_string($size)) {
			$registered_sizes = wp_get_registered_image_subsizes();
			if (isset($registered_sizes[$size])) {
				$registered_size = $registered_sizes[$size];
				$dimensions = [
					'width' => (int) $registered_size['width'],
					'height' => (int) $registered_size['height']
				];
				
				// Set transform args for registered size
				$transform_args = [
					'w' => $dimensions['width'],
					'h' => $dimensions['height'],
					'fit' => isset($attr['fit']) ? $attr['fit'] : ($registered_size['crop'] ? 'cover' : 'contain')
				];

				// Force these dimensions throughout the process
				$attr['width'] = (string) $dimensions['width'];
				$attr['height'] = (string) $dimensions['height'];
				$attr['data-original-width'] = (string) $dimensions['width'];
				$attr['data-original-height'] = (string) $dimensions['height'];
			}
		}

		// Only fall back to metadata if we don't have registered size dimensions
		if (!$dimensions) {
			$metadata = wp_get_attachment_metadata($attachment->ID);
			$dimensions = $metadata['_edge_images_dimensions'] ?? null;

			if (!$dimensions) {
				$dimensions = $this->get_size_dimensions($size, $attachment->ID);
			}

			if ($dimensions) {
				$attr['width'] = (string) $dimensions['width'];
				$attr['height'] = (string) $dimensions['height'];
				$attr['data-original-width'] = (string) $dimensions['width'];
				$attr['data-original-height'] = (string) $dimensions['height'];
			}
		}

		// List of valid HTML image attributes
		$valid_html_attrs = Helpers::$valid_html_attrs;
		
		// Move all non-HTML attributes to transform args
		$filtered_attr = [];
		foreach ($attr as $key => $value) {
			if (in_array($key, $valid_html_attrs, true)) {
				$filtered_attr[$key] = $value;
			} else {
				$transform_args[$key] = $value;
			}
		}
		$attr = $filtered_attr;

		// Transform src
		if (isset($attr['src'])) {
			$provider = Helpers::get_edge_provider();
			$edge_args = array_merge(
				$provider->get_default_args(),
				$transform_args,
				array_filter([
					'w' => $dimensions['width'] ?? null,
					'h' => $dimensions['height'] ?? null,
					'dpr' => 1,
				])
			);
			$full_src = $this->get_full_size_url($attr['src'], $attachment->ID);
			$attr['src'] = Helpers::edge_src($full_src, $edge_args);
		}

		// Generate srcset based on the correct dimensions
		if (isset($attr['src']) && $dimensions) {
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

		// Add attachment ID as data attribute
		$attr['data-attachment-id'] = $attachment->ID;

		// Add picture wrap flag if needed
		if (!get_option('edge_images_disable_picture_wrap', false)) {
			$attr['data-wrap-in-picture'] = 'true';
		}

		// Handle sizes attribute
		if (!isset($attr['sizes']) && isset($dimensions['width'])) {
			$attr['sizes'] = "(max-width: {$dimensions['width']}px) 100vw, {$dimensions['width']}px";
		}

		return $attr;
	}

	/**
	 * Extract transformation arguments from attributes
	 *
	 * @param array $attr The attributes array.
	 * 
	 * @return array The transformation arguments
	 */
	private function extract_transform_args(array $attr): array {
		// Get valid args from Edge_Provider
		$valid_args = Edge_Provider::get_valid_args();
		
		// Get all valid argument names (canonical forms and aliases)
		$all_valid_args = array_keys($valid_args);
		foreach ($valid_args as $aliases) {
			if (is_array($aliases)) {
				$all_valid_args = array_merge($all_valid_args, $aliases);
			}
		}
		
		// Return only the valid arguments from attributes
		return array_intersect_key($attr, array_flip($all_valid_args));
	}

	/**
	 * Normalize transformation arguments
	 *
	 * @param array $args The transformation arguments.
	 * 
	 * @return array Normalized arguments
	 */
	private function normalize_transform_args(array $args): array {
		if (empty($args)) {
			return [];
		}

		// Convert long-form parameters to short-form
		$conversions = [
			'gravity' => 'g',
			'quality' => 'q',
			'format' => 'f',
		];

		$normalized = [];
		foreach ($args as $key => $value) {
			// Convert long form to short form if applicable
			$normalized_key = $conversions[$key] ?? $key;
			$normalized[$normalized_key] = $value;
		}

		// Handle special values
		if (isset($normalized['g'])) {
			// Convert common gravity values
			$gravity_map = [
				'top' => 'north',
				'bottom' => 'south',
				'left' => 'west',
				'right' => 'east',
				'center' => 'center',
			];
			$normalized['g'] = $gravity_map[$normalized['g']] ?? $normalized['g'];
		}

		// Remove any null or empty values
		return array_filter($normalized, function($value) {
			return !is_null($value) && $value !== '';
		});
	}

	/**
	 * Get dimensions for a given size
	 *
	 * @param string|array $size          The size name or dimensions array.
	 * @param int         $attachment_id The attachment ID.
	 * 
	 * @return array|null The dimensions or null if not found
	 */
	private function get_size_dimensions($size, int $attachment_id): ?array {
		// If size is an array, use those dimensions
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
				return [
					'width' => (int) $registered_sizes[$size]['width'],
					'height' => (int) $registered_sizes[$size]['height'],
					'crop' => (bool) $registered_sizes[$size]['crop']
				];
			}

			// Try to get from attachment metadata
			$metadata = wp_get_attachment_metadata($attachment_id);
			if ($metadata) {
				if ($size === 'full') {
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
	 * @since 4.0.0
	 * 
	 * @param string       $html          The HTML img element markup.
	 * @param int         $attachment_id Image attachment ID.
	 * @param string|array $size          Size of image. Array can be [width, height] or string size name.
	 * @param bool|array   $attr_or_icon  Either the attributes array or icon boolean.
	 * @param bool|null    $icon          Whether the image should be treated as an icon.
	 * @return string Modified HTML
	 */
	public function wrap_attachment_image( string $html, int $attachment_id, $size, $attr_or_icon = [], $icon = null ): string {
		// Handle flexible parameter order
		$attr = is_array($attr_or_icon) ? $attr_or_icon : [];
		if (is_bool($attr_or_icon)) {
			$icon = $attr_or_icon;
			$attr = [];
		}

		// Skip if already wrapped or if we shouldn't wrap
		if ( strpos( $html, '<picture' ) !== false || get_option( 'edge_images_disable_picture_wrap', false ) ) {
			return $html;
		}

		// Get dimensions from registered size first if it exists
		$dimensions = null;
		if (is_string($size)) {
			$registered_sizes = wp_get_registered_image_subsizes();
			if (isset($registered_sizes[$size])) {
				$dimensions = [
					'width' => (int) $registered_sizes[$size]['width'],
					'height' => (int) $registered_sizes[$size]['height']
				];
			}
		}

		// If no registered size dimensions, try to get from metadata
		if (!$dimensions) {
			$metadata = wp_get_attachment_metadata($attachment_id);
			if (isset($metadata['_edge_images_dimensions'])) {
				$dimensions = $metadata['_edge_images_dimensions'];
			}
		}

		// If still no dimensions, try to get from the image tag
		if (!$dimensions) {
			$processor = new \WP_HTML_Tag_Processor($html);
			if ($processor->next_tag('img')) {
				$width = $processor->get_attribute('width');
				$height = $processor->get_attribute('height');
				if ($width && $height) {
					$dimensions = [
						'width' => (int) $width,
						'height' => (int) $height
					];
				}
			}
		}

		// Return original HTML if we can't get dimensions
		if (!$dimensions) {
			return $html;
		}

		// Get any existing figure classes
		$figure_classes = '';
		if (strpos($html, '<figure') !== false) {
			preg_match('/<figure[^>]*class=["\']([^"\']*)["\']/', $html, $matches);
			$figure_classes = $matches[1] ?? '';
		}

		// Create picture element
		$picture_html = $this->create_picture_element($html, $dimensions, $figure_classes);

		// If the original HTML had a figure, replace just the img tag within it
		if (strpos($html, '<figure') !== false) {
			return preg_replace('/<img[^>]*>/', $picture_html, $html);
		}

		return $picture_html;
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
	public function transform_image($value, string $image_html, string $context = '', $attachment_id = null): string {
		// Skip if already processed
		if (strpos($image_html, 'edge-images-processed') !== false) {
			return $image_html;
		}

		$processor = new \WP_HTML_Tag_Processor($image_html);
		if (!$processor->next_tag('img')) {
			return $image_html;
		}

		// Get dimensions and transform args
		$dimensions = $this->get_image_dimensions($processor, $attachment_id);
		if (!$dimensions) {
			return $image_html;
		}

		// Get attachment ID if not provided
		if (!$attachment_id) {
			$attachment_id = $this->get_attachment_id_from_classes($processor);
			if (!$attachment_id) {
				$src = $processor->get_attribute('src');
				if ($src) {
					$attachment_id = attachment_url_to_postid($src);
				}
			}
		}

		// Transform the image
		$processor = $this->transform_image_markup($processor, $dimensions, $attachment_id, $context);
		$transformed = $processor->get_updated_html();

		// Check if picture wrapping is disabled
		if (get_option('edge_images_disable_picture_wrap', false)) {
			return $transformed;
		}

		// Create picture element if needed
		return $this->maybe_wrap_in_picture($transformed, $dimensions);
	}

	/**
	 * Core image transformation logic, used by both content and template images
	 */
	private function transform_image_markup(
		\WP_HTML_Tag_Processor $processor, 
		array $dimensions,
		?int $attachment_id = null,
		string $context = ''
	): \WP_HTML_Tag_Processor {
		
		// Add our classes
		$this->add_image_classes($processor);

		// Get the src
		$src = $processor->get_attribute('src');
		if (!$src) {
			return $processor;
		}

		// Get full size URL
		$full_src = $this->get_full_size_url($src, $attachment_id);

		// Determine if we should constrain the image
		$should_constrain = $this->should_constrain_image($processor, $context);
		$working_dimensions = $should_constrain ? 
			$this->constrain_dimensions($dimensions, $this->get_content_width()) : 
			$dimensions;

		// Get provider instance
		$provider = Helpers::get_edge_provider();
		
		// Transform src
		$edge_args = array_merge(
			$provider->get_default_args(),
			[
				'width' => $working_dimensions['width'],
				'height' => $working_dimensions['height'],
				'dpr' => 1,
			]
		);
		
		$transformed_src = Helpers::edge_src($full_src, $edge_args);
		$processor->set_attribute('src', $transformed_src);

		// Set dimensions
		$processor->set_attribute('width', (string)$working_dimensions['width']);
		$processor->set_attribute('height', (string)$working_dimensions['height']);
		
		// Store original dimensions for picture element
		$processor->set_attribute('data-original-width', (string)$dimensions['width']);
		$processor->set_attribute('data-original-height', (string)$dimensions['height']);

		// Handle sizes attribute
		$sizes = $processor->get_attribute('sizes');
		if (!$sizes || strpos($sizes, 'auto') !== false) {
			$sizes = $this->generate_sizes_attribute($working_dimensions, $should_constrain);
			$processor->set_attribute('sizes', $sizes);
		}

		// Generate srcset
		$srcset = Srcset_Transformer::transform(
			$full_src,
			$should_constrain ? $working_dimensions : $dimensions,
			$sizes
		);
		if ($srcset) {
			$processor->set_attribute('srcset', $srcset);
		}

		return $processor;
	}

	/**
	 * Generate appropriate sizes attribute
	 */
	private function generate_sizes_attribute(array $dimensions, bool $constrained): string {
		if ($constrained) {
			return "(max-width: {$dimensions['width']}px) 100vw, {$dimensions['width']}px";
		}
		return "100vw";
	}

	/**
	 * Wrap image in picture element if needed
	 */
	private function maybe_wrap_in_picture(string $image_html, array $dimensions): string {
		// Skip if already wrapped
		if (strpos($image_html, '<picture') !== false) {
			return $image_html;
		}

		// Get any existing figure classes
		$figure_classes = '';
		if (strpos($image_html, '<figure') !== false) {
			preg_match('/<figure[^>]*class=["\']([^"\']*)["\']/', $image_html, $matches);
			$figure_classes = $matches[1] ?? '';
		}

		// Create picture element
		$picture_html = $this->create_picture_element($image_html, $dimensions, $figure_classes);

		// If the original HTML had a figure, replace just the img tag within it
		if (strpos($image_html, '<figure') !== false) {
			return preg_replace('/<img[^>]*>/', $picture_html, $image_html);
		}

		return $picture_html;
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

		$disable_picture_wrap = get_option( 'edge_images_disable_picture_wrap', false );
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
	private function get_dimensions_from_html( \WP_HTML_Tag_Processor $processor ): ?array {
		$width = $processor->get_attribute( 'width' );
		$height = $processor->get_attribute( 'height' );

		if ( ! $width || ! $height ) {
			return null;
		}

		return [
			'width' => (string) $width,
			'height' => (string) $height,
		];
	}

	/**
	 * Get attachment ID from image classes
	 *
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * 
	 * @return int|null Attachment ID or null if not found
	 */
	private function get_attachment_id_from_classes( \WP_HTML_Tag_Processor $processor ): ?int {
		$classes = $processor->get_attribute( 'class' );
		if ( ! $classes || ! preg_match( '/wp-image-(\d+)/', $classes, $matches ) ) {
			return null;
		}

		$attachment_id = (int) $matches[1];
		return $attachment_id;
	}

	/**
	 * Get dimensions from attachment metadata
	 *
	 * @param int $attachment_id The attachment ID.
	 * 
	 * @return array|null Array with width and height, or null if not found
	 */
	private function get_dimensions_from_attachment( int $attachment_id ): ?array {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! $metadata || ! isset( $metadata['width'], $metadata['height'] ) ) {
			return null;
		}

		return [
			'width' => (string) $metadata['width'],
			'height' => (string) $metadata['height'],
		];
	}

	/**
	 * Get image dimensions, trying multiple sources
	 *
	 * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
	 * @param int|null              $attachment_id The attachment ID.
	 * 
	 * @return array|null Array with width and height, or null if not found
	 */
	private function get_image_dimensions( \WP_HTML_Tag_Processor $processor, ?int $attachment_id = null ): ?array {

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
		$dimensions = $this->get_dimensions_from_image_file( $src );
		if ( $dimensions ) {
			return $dimensions;
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
					'dpr' => 1 // Always set dpr to 1
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
		
		// Remove any existing CDN transformations
		$src = preg_replace('#/cdn-cgi/image/[^/]+/#', '/', $src);
		
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

		// Get the correct width from data attribute and update the width attribute
		if (preg_match('/data-original-width="(\d+)"/', $html, $matches)) {
			$correct_width = $matches[1];
			$html = preg_replace(
				'/width="\d+"/', 
				'width="' . $correct_width . '"',
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
	 * Create picture element with given components
	 *
	 * @param string $img_html      The image HTML.
	 * @param array  $dimensions    The image dimensions.
	 * @param string $extra_classes Additional classes to add.
	 * 
	 * @return string The picture element HTML
	 */
	private function create_picture_element(string $img_html, array $dimensions, string $extra_classes = ''): string {
		// Start with base classes
		$classes = ['edge-images-container'];
		
		// Add any extra classes
		if ($extra_classes) {
			$classes = array_merge($classes, explode(' ', $extra_classes));
		}
		
		// Check if the image has explicit fit value
		if (strpos($img_html, 'fit=contain') !== false) {
			$classes[] = 'contain';
		} elseif (strpos($img_html, 'fit=cover') !== false || !strpos($img_html, 'fit=')) {
			$classes[] = 'cover';
		}

		// Get dimensions from the img tag itself
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if ($processor->next_tag('img')) {
			// Check for size class first
			$img_classes = $processor->get_attribute('class') ?? '';
			if (preg_match('/size-(\d+)x(\d+)/', $img_classes, $matches)) {
				$dimensions = [
					'width' => (int)$matches[1],
					'height' => (int)$matches[2]
				];
			} else {
				// Fallback to width/height attributes
				$width = $processor->get_attribute('width');
				$height = $processor->get_attribute('height');
				
				if ($width && $height) {
					$dimensions = [
						'width' => (int)$width,
						'height' => (int)$height
					];
				}
			}
		}
		
		// Validate dimensions
		if (!isset($dimensions['width']) || !isset($dimensions['height']) || 
				empty($dimensions['width']) || empty($dimensions['height']) ||
				$dimensions['width'] <= 0 || $dimensions['height'] <= 0) {
			return $img_html;
		}

		// Get reduced aspect ratio from actual dimensions
		$ratio = Image_Dimensions::reduce_ratio($dimensions['width'], $dimensions['height']);

		// Create the picture element with the actual dimensions
		return sprintf(
			'<picture class="%s" style="aspect-ratio: %d/%d; --max-width: %dpx;">%s</picture>',
			esc_attr(implode(' ', array_unique($classes))),
			$ratio['width'],
			$ratio['height'],
			$dimensions['width'],
			$img_html
		);
	}

	/**
	 * Check if image should be constrained by content width
	 *
	 * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
	 * @param string                $context       The context (content, header, etc).
	 * 
	 * @return bool Whether the image should be constrained
	 */
	private function should_constrain_image(\WP_HTML_Tag_Processor $processor, string $context = ''): bool {
		// Only constrain images in the main content area
		if (!in_array($context, ['content', 'block', 'post', 'page'], true)) {
			return false;
		}

		// Check for alignment classes that indicate full-width
		$classes = $processor->get_attribute('class') ?? '';
		if (preg_match('/(alignfull|alignwide|full-width|width-full)/i', $classes)) {
			return false;
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

		// Check for size-full class
		if (strpos($classes, 'size-full') !== false) {
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
		return [
			'w' => $dimensions['width'],
			'h' => $dimensions['height'],
			'fit' => $crop ? 'cover' : 'contain',
			'q' => 85,
			'f' => 'auto',
			'g' => 'auto'
		];
	}

	/**
	 * Add required classes to image
	 *
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * 
	 * @return void
	 */
	private function add_image_classes(\WP_HTML_Tag_Processor $processor): void {
		$classes = $processor->get_attribute('class');
		$classes = $classes ? $classes . ' edge-images-img edge-images-processed' : 'edge-images-img edge-images-processed';
		$processor->set_attribute('class', $classes);
	}

	/**
	 * Constrain dimensions to content width while maintaining aspect ratio
	 *
	 * @param array $dimensions Original dimensions.
	 * @param int   $max_width  Maximum allowed width.
	 * 
	 * @return array Constrained dimensions
	 */
	private function constrain_dimensions(array $dimensions, int $max_width): array {
		$width = (int)$dimensions['width'];
		$height = (int)$dimensions['height'];
		
		// If width is already smaller than max_width, return original dimensions
		if ($width <= $max_width) {
			return $dimensions;
		}
		
		// Calculate new height maintaining aspect ratio
		$ratio = $height / $width;
		$new_width = $max_width;
		$new_height = round($max_width * $ratio);
		
		return [
			'width' => $new_width,
			'height' => $new_height
		];
	}

	/**
	 * Get the content width to use for constraints
	 * 
	 * @return int The content width
	 */
	private function get_content_width(): int {
		global $content_width;
		return $content_width ?? 1200; // Fallback to 1200 if not set
	}

}




