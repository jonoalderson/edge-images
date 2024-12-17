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

		// Create an instance and store it statically
		static $instance = null;
		if ($instance === null) {
			$instance = new self();
		}

		// Hook into the earliest possible filter for image dimensions
		add_filter('wp_get_attachment_metadata', [$instance, 'filter_attachment_metadata'], 1, 2);

		// First transform the attributes
		add_filter('wp_get_attachment_image_attributes', [$instance, 'transform_attachment_image'], 10, 3);
		
		// Then wrap in picture element
		add_filter('wp_get_attachment_image', [$instance, 'wrap_attachment_image'], 11, 5);
		
		// Finally enforce dimensions and cleanup
		add_filter('wp_get_attachment_image_attributes', [$instance, 'enforce_dimensions'], 12, 3);
		add_filter('wp_get_attachment_image', [$instance, 'cleanup_image_html'], 13, 5);

		// Transform images in content
		add_filter('wp_img_tag_add_width_and_height_attr', [$instance, 'transform_image'], 5, 4);
		add_filter('render_block', [$instance, 'transform_block_images'], 5, 2);

		// Ensure WordPress's default dimension handling still runs
		add_filter('wp_img_tag_add_width_and_height_attr', '__return_true', 999);

		// Enqueue styles
		add_action('wp_enqueue_scripts', [$instance, 'enqueue_styles']);

		// Prevent WordPress from scaling images
		add_filter('big_image_size_threshold', [$instance, 'adjust_image_size_threshold'], 10, 4);

		// Avatar transformations
		add_filter( 'get_avatar_url', [ $instance, 'transform_avatar_url' ], 10, 3 );
		add_filter( 'get_avatar', [ $instance, 'transform_avatar_html' ], 10, 6 );

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
	 * Transform attachment image attributes
	 *
	 * @param array   $attr       Array of attribute values for the image markup.
	 * @param WP_Post $attachment Image attachment post.
	 * @param string  $size       Requested size.
	 * 
	 * @return array Modified attributes
	 */
	public function transform_attachment_image(array $attr, $attachment, $size): array {
		// Bail early if no attachment
		if (!$attachment) {
			return $attr;
		}

		// Get the metadata first and validate it
		$metadata = wp_get_attachment_metadata($attachment->ID);
		if (!$metadata || !isset($metadata['width'], $metadata['height'])) {
			// Return original attributes if metadata is invalid
			return $attr;
		}

		// Check if this is an SVG
		$is_svg = get_post_mime_type($attachment->ID) === 'image/svg+xml';

		// Extract any transformation arguments from the attributes first
		$transform_args = [];
		foreach ($attr as $key => $value) {
			if (Edge_Provider::get_canonical_arg($key)) {
				$transform_args[$key] = $value;
				unset($attr[$key]); // Remove from HTML attributes
			}
		}

		// First priority: Check if size is an array with explicit dimensions
		if (is_array($size) && isset($size[0], $size[1])) {
			$dimensions = [
				'width' => (int) $size[0],
				'height' => (int) $size[1]
			];
			
			// Set transform args for explicit dimensions, preserving user-specified fit
			$transform_args = array_merge(
				$transform_args,
				[
					'w' => $dimensions['width'],
					'h' => $dimensions['height'],
					'fit' => $transform_args['fit'] ?? 'cover' // Only use cover as fallback
				]
			);

			// Force these dimensions throughout the process
			$attr['width'] = (string) $dimensions['width'];
			$attr['height'] = (string) $dimensions['height'];
			$attr['data-original-width'] = (string) $dimensions['width'];
			$attr['data-original-height'] = (string) $dimensions['height'];
			
			// Store the fit mode for the picture element
			$attr['data-fit'] = $transform_args['fit'];

			// For SVG, return early with dimensions preserved
			if ($is_svg) {
				$attr['class'] = isset($attr['class']) 
						? $attr['class'] . ' edge-images-img edge-images-processed' 
						: 'edge-images-img edge-images-processed';
				$attr['data-attachment-id'] = $attachment->ID;
				return $attr;
			}
			
			return $this->apply_transform($attr, $dimensions, $transform_args, $attachment);
		}

		// Second priority: Check registered sizes
		if (is_string($size)) {
			$registered_sizes = wp_get_registered_image_subsizes();
			if (isset($registered_sizes[$size])) {
				$dimensions = [
					'width' => (int) $registered_sizes[$size]['width'],
					'height' => (int) $registered_sizes[$size]['height']
				];
				
				// Set transform args for registered size
				$transform_args = array_merge(
					$this->get_registered_size_args(
						$dimensions,
						$registered_sizes[$size]['crop']
					),
					$transform_args // Put user args last so they override defaults
				);

				// Force these dimensions throughout the process
				$attr['width'] = (string) $dimensions['width'];
				$attr['height'] = (string) $dimensions['height'];
				$attr['data-original-width'] = (string) $dimensions['width'];
				$attr['data-original-height'] = (string) $dimensions['height'];
				
				// For SVG, return early with dimensions preserved
				if ($is_svg) {
					$attr['class'] = isset($attr['class']) 
						? $attr['class'] . ' edge-images-img edge-images-processed' 
						: 'edge-images-img edge-images-processed';
					$attr['data-attachment-id'] = $attachment->ID;
					return $attr;
				}
				
				return $this->apply_transform($attr, $dimensions, $transform_args, $attachment);
			}
		}

		// Third priority: For SVGs, use width/height from attributes
		if ($is_svg && isset($attr['width'], $attr['height'])) {
			$dimensions = [
				'width' => (int) $attr['width'],
				'height' => (int) $attr['height']
			];
		} else {
			// Fourth priority: Check metadata
			$metadata = wp_get_attachment_metadata($attachment->ID);
			$dimensions = $metadata['_edge_images_dimensions'] ?? null;
		}

		// Fifth priority: Try size dimensions
		if (!$dimensions) {
			$dimensions = $this->get_size_dimensions($size, $attachment->ID);
		}

		// If we still don't have dimensions, return unmodified attributes
		if (!$dimensions) {
			return $attr;
		}

		// Set the dimensions in attributes
		$attr['width'] = (string) $dimensions['width'];
		$attr['height'] = (string) $dimensions['height'];
		$attr['data-original-width'] = (string) $dimensions['width'];
		$attr['data-original-height'] = (string) $dimensions['height'];

		return $this->apply_transform($attr, $dimensions, $transform_args, $attachment);
	}

	/**
	 * Apply transformation to attributes
	 *
	 * @param array   $attr          The attributes array.
	 * @param array   $dimensions    The image dimensions.
	 * @param array   $transform_args The transformation arguments.
	 * @param WP_Post $attachment    The attachment post.
	 * @return array Modified attributes
	 */
	private function apply_transform(array $attr, array $dimensions, array $transform_args, $attachment): array {
		// List of valid HTML image attributes
		$valid_html_attrs = Helpers::$valid_html_attrs;
		
		// Move all non-HTML attributes to transform args
		$filtered_attr = [];
		foreach ($attr as $key => $value) {
			if (in_array($key, $valid_html_attrs, true)) {
				$filtered_attr[$key] = $value;
			} else if (Edge_Provider::get_canonical_arg($key)) {
				// Move transform args to transform_args array
				$transform_args[$key] = $value;
			} else {
				// Keep other custom attributes
				$filtered_attr[$key] = $value;
			}
		}
		$attr = $filtered_attr;

		// Get file type
		$file = get_attached_file($attachment->ID);
		$is_special_format = preg_match('/\.(svg|avif)$/i', $file);

		// If no alt text is set, try to get it from the attachment
		if (!isset($attr['alt']) && $attachment) {
			$alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
			if ($alt_text) {
				$attr['alt'] = $alt_text;
			} else {
				// Fallback to attachment title if no alt text
				$attr['alt'] = $attachment->post_title;
			}
		}

		// Transform src (skip for SVG/AVIF)
		if (isset($attr['src']) && !$is_special_format) {
			$provider = $this->get_provider_instance();
			$edge_args = array_merge(
				$provider->get_default_args(),
				$transform_args, // Put transform_args before dimensions so they don't get overwritten
				array_filter([
					'w' => $dimensions['width'] ?? null,
					'h' => $dimensions['height'] ?? null,
					'fit' => $transform_args['fit'] ?? 'cover', // Use the fit mode from transform_args
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

		// Add attachment ID as data attribute
		$attr['data-attachment-id'] = $attachment->ID;

		// Add picture wrap flag if needed
		if (!get_option('edge_images_disable_picture_wrap', false)) {
			$attr['data-wrap-in-picture'] = 'true';
		}

		// Store the fit mode for the picture element
		$attr['data-fit'] = $transform_args['fit'] ?? 'cover';

		// Handle sizes attribute
		if (!isset($attr['sizes']) && isset($dimensions['width'])) {
			$attr['sizes'] = "(max-width: {$dimensions['width']}px) 100vw, {$dimensions['width']}px";
		}

		return $attr;
	}

	/**
	 * Get dimensions for a given size
	 */
	private function get_size_dimensions($size, $attachment_id) {
		// If size is array with explicit dimensions
		if (is_array($size)) {
			// Ensure we have both dimensions
			if (!isset($size[0]) || !isset($size[1])) {
				// Get original dimensions to calculate missing dimension
				$metadata = wp_get_attachment_metadata($attachment_id);
				if ($metadata && isset($metadata['width'], $metadata['height'])) {
					$ratio = $metadata['height'] / $metadata['width'];
					if (!isset($size[0])) {
						$size[0] = round($size[1] / $ratio);
					}
					if (!isset($size[1])) {
						$size[1] = round($size[0] * $ratio);
					}
				} else {
					// If we can't calculate, use defaults
					$size[0] = $size[0] ?? 150;
					$size[1] = $size[1] ?? 150;
				}
			}

			return [
				'width' => (int) $size[0],
				'height' => (int) $size[1]
			];
		}

		// Get attachment metadata
		$metadata = wp_get_attachment_metadata($attachment_id);
		
		// Validate metadata
		if (!$metadata || !isset($metadata['width'], $metadata['height'])) {
			// Return null if metadata is invalid
			return null;
		}

		// If size is a string (e.g., 'thumbnail', 'medium', etc.)
		if (is_string($size) && isset($metadata['sizes'][$size])) {
			return [
				'width' => (int) $metadata['sizes'][$size]['width'],
				'height' => (int) $metadata['sizes'][$size]['height']
			];
		}

		// Fall back to original dimensions
		return [
			'width' => (int) $metadata['width'],
			'height' => (int) $metadata['height']
		];
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

		// Skip if already wrapped or if we shouldn't wrap
		if (strpos($html, '<picture') !== false || get_option('edge_images_disable_picture_wrap', false)) {
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
	public function transform_image( $value, string $image_html, string $context, $attachment_id ): string {

		// Skip if already processed
		if ( strpos( $image_html, 'edge-images-processed' ) !== false ) {
			return $image_html;
		}

		$processor = new \WP_HTML_Tag_Processor( $image_html );
		if ( ! $processor->next_tag( 'img' ) ) {
			return $image_html;
		}

		// Transform the image with context
		$processor = $this->transform_image_tag( $processor, $attachment_id, $image_html, $context );
		$transformed = $processor->get_updated_html();

		// Check if picture wrapping is disabled
		if ( get_option( 'edge_images_disable_picture_wrap', false ) ) {
			return $transformed;
		}

		// Get dimensions for the picture element
		$dimensions = $this->get_dimensions_from_html( new \WP_HTML_Tag_Processor( $transformed ) );
		if ( ! $dimensions ) {
			return $transformed;
		}

		// If we have a figure, replace it with picture
		if ( strpos( $image_html, '<figure' ) !== false ) {
			$figure_classes = $this->extract_figure_classes( $image_html, [] );
			$img_html = $this->extract_img_tag( $transformed );
			
			return $this->create_picture_element( $img_html, $dimensions, $figure_classes );
		}

		// Otherwise create picture element with just the image
		return $this->create_picture_element( $transformed, $dimensions );
	}

	/**
	 * Transform an image tag with processor
	 *
	 * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
	 * @param int|null              $attachment_id The attachment ID.
	 * @param string               $original_html  The original HTML.
	 * @param string               $context       The context (content, header, etc).
	 * 
	 * @return \WP_HTML_Tag_Processor The modified processor
	 */
	public function transform_image_tag( 
		\WP_HTML_Tag_Processor $processor, 
		?int $attachment_id, 
		string $original_html = '',
		string $context = '',
		array $transform_args = []
	): \WP_HTML_Tag_Processor {
		// Get dimensions
		$dimensions = $this->get_image_dimensions($processor, $attachment_id);
		if (!$dimensions) {
			return $processor;
		}

		// Add our classes
		$this->add_image_classes($processor);

		// Get attachment ID if not provided
		if (!$attachment_id) {
			$attachment_id = $this->get_attachment_id_from_classes($processor);
		}

		// Transform URLs with context
		$this->transform_image_urls($processor, $dimensions, $original_html, $context, $transform_args);

		return $processor;
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
	 * @param array|null            $dimensions    Optional dimensions.
	 * @param string               $original_html  The original HTML.
	 * @param string               $context       The context (content, header, etc).
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
			global $content_width;
			$max_width = $content_width ?? (int) get_option('edge_images_max_width', 650);
			$dimensions = $this->constrain_dimensions($dimensions, $max_width);
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
				'fit' => $fit ?? 'cover', // Use the fit mode we determined from registered size
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
		$sizes = $processor->get_attribute('sizes');
		if (!$sizes || strpos($sizes, 'auto') !== false) {
			if ($should_constrain) {
				$sizes = "(max-width: {$dimensions['width']}px) 100vw, {$dimensions['width']}px";
			} else {
				$sizes = "100vw"; // Full width
			}
			$processor->set_attribute('sizes', $sizes);
		}
		
		// Generate srcset using the Srcset_Transformer with appropriate dimensions
		$srcset = Srcset_Transformer::transform(
			$full_src, 
			$should_constrain ? $dimensions : $original_dimensions,
			$sizes,
			$transform_args
		);
		if ($srcset) {
			$processor->set_attribute('srcset', $srcset);
		}
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
	 * Create picture element with given components
	 *
	 * @param string $img_html      The image HTML.
	 * @param array  $dimensions    The image dimensions.
	 * @param string $extra_classes Additional classes to add.
	 * 
	 * @return string The picture element HTML
	 */
	public function create_picture_element(string $img_html, array $dimensions, string $extra_classes = ''): string {
		// Start with base classes
		$classes = ['edge-images-container'];
		
		// Add any extra classes
		if ($extra_classes) {
			$classes = array_merge($classes, explode(' ', $extra_classes));
		}
		
		// Get dimensions from data-original attributes first
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if ($processor->next_tag('img')) {
			// Check for container-class attribute
			$container_class = $processor->get_attribute('container-class');
			if ($container_class) {
				$classes = array_merge($classes, explode(' ', $container_class));
				// Remove the container-class attribute from the img tag
				$processor->remove_attribute('container-class');
				$img_html = $processor->get_updated_html();
			}

			// Get the fit mode from data-fit attribute
			$fit = $processor->get_attribute('data-fit');
			if ($fit) {
				$classes[] = $fit;
				// Remove the data-fit attribute as we don't need it anymore
				$processor->remove_attribute('data-fit');
				$img_html = $processor->get_updated_html();
			}

			// Use data-original dimensions if available
			$original_width = $processor->get_attribute('data-original-width');
			$original_height = $processor->get_attribute('data-original-height');
			if ($original_width && $original_height) {
				$dimensions = [
					'width' => (int) $original_width,
					'height' => (int) $original_height
				];
			}
		}

		// Get reduced aspect ratio from actual dimensions
		$ratio = Image_Dimensions::reduce_ratio($dimensions['width'], $dimensions['height']);

		// Create the picture element with the actual dimensions
		return sprintf(
			'<picture class="%s" style="--aspect-ratio: %d/%d; --max-width: %dpx;">%s</picture>',
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
	 * Transform avatar URLs.
	 *
	 * @since 4.2.0
	 * 
	 * @param string $url         The URL of the avatar.
	 * @param mixed  $id_or_email The Gravatar to retrieve.
	 * @param array  $args        Arguments passed to get_avatar_data().
	 * @return string The transformed URL.
	 */
	public function transform_avatar_url( string $url, $id_or_email, array $args ): string {
		// Skip if URL is empty or remote.
		if ( empty( $url ) || ! Helpers::is_local_url( $url ) ) {
			return $url;
		}

		// Get size from args.
		$size = $args['size'] ?? 96;

		// Transform URL using edge provider.
		return Helpers::edge_src( $url, [
			'width'  => $size,
			'height' => $size,
			'sharpen' => 1,
		]);
	}

	/**
	 * Transform avatar HTML.
	 *
	 * @since 4.2.0
	 * 
	 * @param string $avatar      HTML for the user's avatar.
	 * @param mixed  $id_or_email The Gravatar to retrieve.
	 * @param int    $size        Square avatar width and height in pixels.
	 * @param string $default     URL for the default image.
	 * @param string $alt         Alternative text.
	 * @param array  $args        Arguments passed to get_avatar_data().
	 * @return string The transformed avatar HTML.
	 */
	public function transform_avatar_html( string $avatar, $id_or_email, int $size, string $default, string $alt, array $args ): string {
		// Skip if avatar is empty.
		if ( empty( $avatar ) ) {
			return $avatar;
		}

		// Create HTML processor.
		$processor = new \WP_HTML_Tag_Processor( $avatar );
		if ( ! $processor->next_tag( 'img' ) ) {
			return $avatar;
		}

		// Skip if remote URL.
		$src = $processor->get_attribute( 'src' );
		if ( ! $src || ! Helpers::is_local_url( $src ) ) {
			return $avatar;
		}

		// Get container class if it exists
		$container_class = $processor->get_attribute( 'container-class' );

		// Transform the image.
		$transform_args = [
			'width'  => $size,
			'height' => $size,
			'sharpen' => 1,
		];

		// Create processor with transform args
		$processor = new \WP_HTML_Tag_Processor( $avatar );
		$processor->next_tag( 'img' );
		$processor = $this->transform_image_tag( $processor, null, $avatar, 'avatar', $transform_args );
		$transformed = $processor->get_updated_html();

		// Check if picture wrapping is disabled
		if ( get_option( 'edge_images_disable_picture_wrap', false ) ) {
			return $transformed;
		}

		// Create dimensions array for picture element
		$dimensions = [
			'width'  => $size,
			'height' => $size,
		];

		// Create picture element with container class if it exists
		$classes = ['avatar-picture'];
		if ( $container_class ) {
			$classes[] = $container_class;
		}

		// Create picture element
		return $this->create_picture_element( 
			$transformed, 
			$dimensions, 
			implode( ' ', $classes )
		);
	}

}




