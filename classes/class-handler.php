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

		// Add our transformations with high priority
		add_filter('wp_get_attachment_image_attributes', array($instance, 'transform_attachment_image'), 999900, 3);
		add_filter('wp_get_attachment_image_attributes', array($instance, 'enforce_dimensions'), PHP_INT_MAX, 3);

		// Transform images in templates
		add_filter('wp_get_attachment_image', array($instance, 'wrap_attachment_image'), PHP_INT_MAX - 100, 5);

		// Transform images in content
		add_filter('wp_img_tag_add_width_and_height_attr', array($instance, 'transform_image'), 5, 4);
		add_filter('render_block', array($instance, 'transform_block_images'), 5, 2);

		// Ensure WordPress's default dimension handling still runs
		add_filter('wp_img_tag_add_width_and_height_attr', '__return_true', 999);

		// Enqueue styles
		add_action('wp_enqueue_scripts', array($instance, 'enqueue_styles'));

		// Prevent WordPress from scaling images
		add_filter('big_image_size_threshold', array($instance, 'adjust_image_size_threshold'), 10, 4);

		// Add a final filter to fix the width attribute in the HTML
		add_filter('wp_get_attachment_image', array($instance, 'cleanup_image_html'), PHP_INT_MAX, 5);

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
		// Get dimensions from metadata first
		$metadata = wp_get_attachment_metadata($attachment->ID);
		$dimensions = $metadata['_edge_images_dimensions'] ?? null;

		// Fallback to getting dimensions from size
		if (!$dimensions) {
			$dimensions = $this->get_size_dimensions($size, $attachment->ID);
		}

		if ($dimensions) {
			// Force dimensions in attributes
			$attr['width'] = (string) $dimensions['width'];
			$attr['height'] = (string) $dimensions['height'];
			
			// Store original dimensions in data attributes
			$attr['data-original-width'] = (string) $dimensions['width'];
			$attr['data-original-height'] = (string) $dimensions['height'];
			
			// Store in post meta for redundancy
			update_post_meta($attachment->ID, '_edge_images_dimensions', $dimensions);
		}

		// Extract transformation arguments from attributes
		$transform_args = $this->extract_transform_args($attr);

		// Remove these from the final HTML attributes
		foreach ($transform_args as $key => $value) {
			unset($attr[$key]);
		}

		// Normalize the transformation arguments
		$transform_args = $this->normalize_transform_args($transform_args);

		// Generate srcset first (before transforming src)
		if ($dimensions) {
			$full_src = isset($attr['src']) ? $this->get_full_size_url($attr['src'], $attachment->ID) : '';
			if ($full_src) {
				$srcset = Srcset_Transformer::transform(
					$full_src,
					$dimensions,
					$attr['sizes'] ?? '',
					$transform_args
				);

				if ($srcset) {
					$attr['srcset'] = $srcset;
				}
			}
		}

		// Transform src
		if (isset($attr['src'])) {
			// Get a provider instance to access default args
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

		// Add our classes
		$attr['class'] = isset($attr['class']) 
			? $attr['class'] . ' edge-images-img edge-images-processed' 
			: 'edge-images-img edge-images-processed';

		// Add attachment ID as data attribute
		$attr['data-attachment-id'] = $attachment->ID;

		// Add flag for wrapping
		$attr['data-wrap-in-picture'] = 'true';

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
			// Check registered sizes first
			global $_wp_additional_image_sizes;
			if (isset($_wp_additional_image_sizes[$size])) {
				return [
					'width' => $_wp_additional_image_sizes[$size]['width'],
					'height' => $_wp_additional_image_sizes[$size]['height']
				];
			}

			// Check if size name contains dimensions
			if (preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
				return [
					'width' => (int)$matches[1],
					'height' => (int)$matches[2]
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
	 * @param string       $html          The HTML img element markup.
	 * @param int         $attachment_id Image attachment ID.
	 * @param string|array $size          Size of image. Array can be [width, height] or string size name.
	 * @param bool        $icon          Whether the image should be treated as an icon.
	 * @param array       $attr          Array of image attributes.
	 * 
	 * @return string Modified HTML
	 */
	public function wrap_attachment_image( string $html, int $attachment_id, $size, bool $icon, array $attr ): string {
		// Skip if already wrapped or if we shouldn't wrap
		if ( 
			strpos( $html, '<picture' ) !== false || 
			! isset( $attr['data-wrap-in-picture'] ) ||
			! isset( $attr['width'], $attr['height'] ) ||
			get_option( 'edge_images_disable_picture_wrap', false ) // Check the option
		) {
			return $html;
		}

		$dimensions = [
			'width' => $attr['width'],
			'height' => $attr['height']
		];

		return $this->create_picture_element( $html, $dimensions );
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

		// Transform the image
		$processor = $this->transform_image_tag( $processor, $attachment_id, $image_html );
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
	 * 
	 * @return \WP_HTML_Tag_Processor The modified processor
	 */
	private function transform_image_tag( \WP_HTML_Tag_Processor $processor, ?int $attachment_id, string $original_html = '' ): \WP_HTML_Tag_Processor {
		// Get dimensions
		$dimensions = $this->get_image_dimensions( $processor, $attachment_id );
		if ( $dimensions ) {
			$processor->set_attribute( 'width', $dimensions['width'] );
			$processor->set_attribute( 'height', $dimensions['height'] );
		}

		// Add our classes
		$this->add_image_classes( $processor );

		// Get attachment ID if not provided
		if ( ! $attachment_id ) {
			$attachment_id = $this->get_attachment_id_from_classes( $processor );
		}

		// Add srcset if we have an attachment ID
		if ( $attachment_id ) {
			$srcset = wp_get_attachment_image_srcset( $attachment_id );
			
			if ( $srcset ) {
				$processor->set_attribute( 'srcset', $srcset );
			}
		}

		// Transform URLs
		$this->transform_image_urls( $processor, $dimensions ?? null, $original_html );

		// Preserve the sizes attribute if it exists
		$sizes = $processor->get_attribute( 'sizes' );
		if ( $sizes ) {
			$processor->set_attribute( 'sizes', $sizes );
		} else {
			// Set a default sizes attribute if not present
			$width = $dimensions['width'] ?? '100%';
			$default_sizes = is_numeric($width) 
				? "(max-width: {$width}px) 100vw, {$width}px"
				: '100vw';
			$processor->set_attribute( 'sizes', $default_sizes );
		}

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
	 * Transform image URLs (src and srcset)
	 *
	 * @param \WP_HTML_Tag_Processor $processor  The HTML processor.
	 * @param array|null            $dimensions Optional dimensions.
	 * @param string               $original_html The original HTML.
	 * 
	 * @return void
	 */
	private function transform_image_urls( 
		\WP_HTML_Tag_Processor $processor, 
		?array $dimensions, 
		string $original_html = ''
	): void {
		// Transform src
		$src = $processor->get_attribute('src');
		
		if ($src && $dimensions) {
			// Get attachment ID for full size dimensions
			$attachment_id = $this->get_attachment_id_from_classes($processor);
			if (!$attachment_id) {
				$attachment_id = attachment_url_to_postid($src);
			}
			
			// Get full size URL
			$full_src = $this->get_full_size_url($src, $attachment_id);
			
			// Get a provider instance to access default args
			$provider = Helpers::get_edge_provider();
			
			// Transform src with requested dimensions
			$edge_args = array_merge(
				$provider->get_default_args(),
				array_filter([
					'width' => $dimensions['width'] ?? null,
					'height' => $dimensions['height'] ?? null,
					'dpr' => 1,
				])
			);
			
			$transformed_src = Helpers::edge_src($full_src, $edge_args);
			$processor->set_attribute('src', $transformed_src);
			
			// Get sizes attribute (or set default)
			$sizes = $processor->get_attribute('sizes');
			if (!$sizes) {
				$sizes = "(max-width: {$dimensions['width']}px) 100vw, {$dimensions['width']}px";
				$processor->set_attribute('sizes', $sizes);
			}
			
			// Generate srcset using the Srcset_Transformer
			$srcset = Srcset_Transformer::transform($full_src, $dimensions, $sizes);
			if ($srcset) {
				$processor->set_attribute('srcset', $srcset);
			}
		}
	}

	/**
	 * Transform an existing srcset string
	 *
	 * @param string $srcset     The original srcset string.
	 * @param array  $dimensions The image dimensions.
	 * @param string $full_src   The full size image URL.
	 * 
	 * @return string The transformed srcset
	 */
	private function transform_existing_srcset(string $srcset, array $dimensions, string $full_src): string {
		$srcset_parts = explode(',', $srcset);
		$transformed_parts = [];

		foreach ($srcset_parts as $part) {
			$part = trim($part);
			if (preg_match('/^(.+)\s+(\d+w)$/', $part, $matches)) {
				$width_descriptor = $matches[2];
				
				// Extract width from descriptor
				$width = (int)str_replace('w', '', $width_descriptor);
				$height = round($width * ($dimensions['height'] / $dimensions['width']));
				
				$edge_args = array_merge(
					$this->default_edge_args,
					[
						'width' => $width,
						'height' => $height,
						'dpr' => 1,
					]
				);
				
				// Use full size URL as base
				$transformed_url = Helpers::edge_src($full_src, $edge_args);
				$transformed_parts[] = "$transformed_url $width_descriptor";
			}
		}

		return implode(', ', $transformed_parts);
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

		// Check if picture wrapping is disabled
		$disable_picture_wrap = get_option( 'edge_images_disable_picture_wrap', false );

		// First pass: transform figures with images
		if ( preg_match_all( '/<figure[^>]*>.*?<\/figure>/s', $block_content, $matches, PREG_OFFSET_CAPTURE ) ) {
			$offset_adjustment = 0;

			foreach ( $matches[0] as $match ) {
				$figure_html = $match[0];

				if ( str_contains( $figure_html, '<img' ) && ! str_contains( $figure_html, '<picture' ) ) {
					// Extract figure classes before transformation
					$figure_classes = [];
					if ( preg_match( '/class=["\']([^"\']*)["\']/', $figure_html, $class_matches ) ) {
						$figure_classes = explode( ' ', $class_matches[1] );
					}

					// Transform the image
					if ($disable_picture_wrap) {
						// Just transform the image without wrapping
						$img_html = $this->extract_img_tag($figure_html);
						if ($img_html) {
							$transformed_html = $this->transform_image(true, $img_html, 'block', 0);
							// Replace only the img tag within the figure
							$transformed_html = str_replace($img_html, $transformed_html, $figure_html);
						} else {
							$transformed_html = $figure_html;
						}
					} else {
						// Transform with picture wrapping
						$transformed_html = $this->transform_figure_block($figure_html, $figure_classes);
					}

					$block_content = substr_replace(
						$block_content,
						$transformed_html,
						$match[1] + $offset_adjustment,
						strlen( $figure_html )
					);
					$offset_adjustment += strlen( $transformed_html ) - strlen( $figure_html );
				}
			}
		}

		// Second pass: transform standalone images
		if ( preg_match_all( '/<img[^>]*>/', $block_content, $matches, PREG_OFFSET_CAPTURE ) ) {
			$offset_adjustment = 0;
			foreach ( $matches[0] as $match ) {
				$img_html = $match[0];
				// Skip if already processed or if inside a picture/figure element
				if ( ! str_contains( $img_html, 'edge-images-processed' ) && 
					 ! str_contains( substr( $block_content, 0, $match[1] ), '<picture' ) &&
					 ! str_contains( substr( $block_content, 0, $match[1] ), '<figure' ) ) {
					
					// Transform the image with or without picture wrapping
					$transformed_html = $this->transform_image( true, $img_html, 'block', 0 );
					$block_content = substr_replace(
						$block_content,
						$transformed_html,
						$match[1] + $offset_adjustment,
						strlen( $img_html )
					);
					$offset_adjustment += strlen( $transformed_html ) - strlen( $img_html );
				}
			}
		}

		return $block_content;
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
	 * Extract figure classes from block content and attributes
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * 
	 * @return string The combined classes
	 */
	private function extract_figure_classes( string $block_content, array $block ): string {
		// Extract classes from figure element
		preg_match('/<figure[^>]*class=["\']([^"\']*)["\']/', $block_content, $matches);
		$figure_classes = $matches[1] ?? '';

		// Add alignment if present in block attributes
		$alignment = $block['attrs']['align'] ?? '';
		if ( $alignment ) {
			$figure_classes .= " align{$alignment}";
		}

		return trim( $figure_classes );
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
	 * @param string       $html          The HTML img element markup.
	 * @param int         $attachment_id Image attachment ID.
	 * @param string|array $size          Size of image.
	 * @param bool        $icon          Whether the image should be treated as an icon.
	 * @param array       $attr          Array of image attributes.
	 * 
	 * @return string Modified HTML
	 */
	public function cleanup_image_html(string $html, int $attachment_id, $size, bool $icon, array $attr): string {
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
	private function create_picture_element( string $img_html, array $dimensions, string $extra_classes = '' ): string {
		// Start with base classes
		$classes = ['edge-images-container'];
		
		// Add any extra classes
		if ($extra_classes) {
			$classes[] = $extra_classes;
		}
		
		// Check if the image has explicit fit value
		if (strpos($img_html, 'fit=contain') !== false) {
			$classes[] = 'contain';
		} elseif (strpos($img_html, 'fit=cover') !== false || !strpos($img_html, 'fit=')) {
			$classes[] = 'cover';
		}
		
		// Validate dimensions before using them
		if (!isset($dimensions['width']) || !isset($dimensions['height']) || 
				empty($dimensions['width']) || empty($dimensions['height'])) {
			// Return just the img tag if dimensions are invalid
			return $img_html;
		}
		
		// Ensure dimensions are numeric
		$width = (int)$dimensions['width'];
		$height = (int)$dimensions['height'];
		
		if ($width <= 0 || $height <= 0) {
			// Return just the img tag if dimensions are invalid
			return $img_html;
		}

		return sprintf(
			'<picture class="%s" style="aspect-ratio: %d/%d; --max-width: %dpx;">%s</picture>',
			esc_attr( implode(' ', $classes) ),
			$width,
			$height,
			$width,
			$img_html
		);
	}

}




