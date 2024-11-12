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

	// TODO:
	// 1. SRCSET values should be derived from the sizes attribute.
	// 2.Options pages
	
	/**
	 * Default edge transformation arguments.
	 *
	 * @var array
	 */
	private array $default_edge_args = [
		'fit' => 'cover',
		'dpr' => 1, 
		'f' => 'auto',
		'gravity' => 'auto',
		'q' => 85
	];

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

		// Transform images in templates - run at priority 1 to ensure we run before other plugins
		add_filter( 'wp_get_attachment_image_attributes', array( $instance, 'transform_attachment_image' ), 1, 3 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'wrap_attachment_image' ), 1, 5 );

		// Transform images in content
		add_filter( 'wp_img_tag_add_width_and_height_attr', array( $instance, 'transform_image' ), 5, 4 );
		add_filter( 'render_block', array( $instance, 'transform_block_images' ), 5, 2 );

		// Ensure WordPress's default dimension handling still runs
		add_filter( 'wp_img_tag_add_width_and_height_attr', '__return_true', 999 );

		// Enqueue styles
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_styles' ) );

		self::$registered = true;
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
	public function transform_attachment_image( array $attr, $attachment, $size ): array {
		// Skip if already processed
		if ( isset( $attr['class'] ) && strpos( $attr['class'], 'edge-images-processed' ) !== false ) {
			return $attr;
		}

		// Add our classes
		$attr['class'] = isset( $attr['class'] ) 
			? $attr['class'] . ' edge-images-img edge-images-processed' 
			: 'edge-images-img edge-images-processed';

		// Add attachment ID as data attribute
		$attr['data-attachment-id'] = $attachment->ID;

		// Get dimensions
		$width = $attr['width'] ?? null;
		$height = $attr['height'] ?? null;

		// If no dimensions in attributes, try to get from attachment
		if ( ! $width || ! $height ) {
			$metadata = wp_get_attachment_metadata( $attachment->ID );
			if ( $metadata ) {
				if ( $size === 'full' ) {
					$width = $metadata['width'];
					$height = $metadata['height'];
				} elseif ( isset( $metadata['sizes'][$size] ) ) {
					$width = $metadata['sizes'][$size]['width'];
					$height = $metadata['sizes'][$size]['height'];
				}
				$attr['width'] = (string) $width;
				$attr['height'] = (string) $height;
			}
		}

		if ( $width && $height ) {
			global $content_width;
			if ( $content_width && (int) $width > $content_width ) {
				$ratio = (int) $height / (int) $width;
				$width = (string) $content_width;
				$height = (string) round( $content_width * $ratio );
				$attr['width'] = $width;
				$attr['height'] = $height;
			}
		}

		// Transform src
		if ( isset( $attr['src'] ) ) {
			$edge_args = array_merge(
				$this->default_edge_args,
				array_filter([
					'width' => $width,
					'height' => $height,
				])
			);
			$attr['src'] = Helpers::edge_src( $attr['src'], $edge_args );
		}

		// Transform srcset
		if ( isset( $attr['srcset'] ) ) {
			$attr['srcset'] = $this->transform_srcset( $attr['srcset'], $edge_args );
		}

		// Add flag for wrapping
		$attr['data-wrap-in-picture'] = 'true';

		// Preserve the sizes attribute if it exists
		if ( isset( $attr['sizes'] ) ) {
			$attr['sizes'] = $attr['sizes'];
		} else {
			// Set a default sizes attribute if not present
			$attr['sizes'] = '(max-width: ' . $attr['width'] . 'px) 100vw, ' . $attr['width'] . 'px';
		}

		return $attr;
	}

	/**
	 * Wrap attachment image in picture element
	 *
	 * @param string  $html        The HTML img element markup.
	 * @param int     $attachment_id Image attachment ID.
	 * @param string  $size        Size of image.
	 * @param bool    $icon        Whether the image should be treated as an icon.
	 * @param array   $attr        Array of image attributes.
	 * 
	 * @return string Modified HTML
	 */
	public function wrap_attachment_image( string $html, int $attachment_id, string $size, bool $icon, array $attr ): string {
		// Skip if already wrapped or if we shouldn't wrap
		if ( 
			strpos( $html, '<picture' ) !== false || 
			! isset( $attr['data-wrap-in-picture'] ) ||
			! isset( $attr['width'], $attr['height'] )
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

			// Get full size dimensions and URL
			$full_dimensions = $attachment_id ? $this->get_full_size_dimensions($attachment_id) : null;
			$max_dimensions = $full_dimensions ?? $dimensions;
			$full_src = $this->get_full_size_url($src, $attachment_id);

			// Transform src with requested dimensions
			$edge_args = array_merge(
				$this->default_edge_args,
				array_filter([
					'width' => $dimensions['width'] ?? null,
					'height' => $dimensions['height'] ?? null,
				])
			);
			$transformed_src = Helpers::edge_src($src, $edge_args);
			$processor->set_attribute('src', $transformed_src);

			// Get sizes attribute
			$sizes = $processor->get_attribute('sizes');
			if (!$sizes) {
				// Set a default sizes attribute if not present
				$width = $dimensions['width'];
				$sizes = is_numeric($width) 
					? "(max-width: {$width}px) 100vw, {$width}px"
					: '100vw';
				$processor->set_attribute('sizes', $sizes);
			}

			// Always generate our own srcset based on the full-size image
			$srcset = $this->generate_srcset($full_src, $max_dimensions, $sizes);
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

		// First pass: transform figures with images
		if ( preg_match_all( '/<figure[^>]*>.*?<\/figure>/s', $block_content, $matches, PREG_OFFSET_CAPTURE ) ) {
			$offset_adjustment = 0;

			foreach ( $matches[0] as $match ) {
				$figure_html = $match[0];

				if ( str_contains( $figure_html, '<img' ) && ! str_contains( $figure_html, '<picture' ) ) {

					$transformed_html = $this->transform_figure_block( $figure_html, $block );

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
	 * Get the HTML for a tag from the processor
	 *
	 * @param string                $content   The full HTML content.
	 * @param \WP_HTML_Tag_Processor $processor The tag processor.
	 * 
	 * @return string The tag's HTML
	 */
	private function get_tag_html( string $content, \WP_HTML_Tag_Processor $processor ): string {
		$tag = $processor->get_tag();
		$attrs = '';
		
		foreach ( $processor->get_attribute_names() as $name ) {
			$value = $processor->get_attribute( $name );
			$attrs .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}

		return "<{$tag}{$attrs}>";
	}

	/**
	 * Check if an element has a picture element as a parent
	 *
	 * @param string $content The HTML content.
	 * @param int    $position The position of the element.
	 * 
	 * @return bool Whether the element has a picture parent
	 */
	private function has_picture_parent( string $content, int $position ): bool {
		$before_content = substr( $content, 0, $position );
		$picture_count = substr_count( $before_content, '<picture' );
		$picture_close_count = substr_count( $before_content, '</picture>' );
		
		return $picture_count > $picture_close_count;
	}

	/**
	 * Extract HTML for a specific tag
	 *
	 * @param string $content The full HTML content.
	 * @param int    $offset  The tag offset.
	 * @param int    $length  The tag length.
	 * 
	 * @return string The extracted HTML
	 */
	private function extract_tag_html( string $content, int $offset, int $length ): string {
		return substr( $content, $offset, $length );
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
	 * Transform a figure block containing an image
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * 
	 * @return string The transformed block content
	 */
	private function transform_figure_block( string $block_content, array $block ): string {
		// Extract necessary components
		$figure_classes = $this->extract_figure_classes( $block_content, $block );
		$img_html = $this->extract_img_tag( $block_content );
		
		if ( ! $img_html ) {
			return $block_content;
		}

		// Transform the image and get dimensions
		$transformed_data = $this->transform_and_get_dimensions( $img_html );
		if ( ! $transformed_data ) {
			return $block_content;
		}

		// Always create a picture element, even if the image is already processed
		$picture = sprintf(
			'<picture class="edge-images-container %s" style="aspect-ratio: %d/%d;">%s</picture>',
			esc_attr( $figure_classes ),
			$transformed_data['dimensions']['width'],
			$transformed_data['dimensions']['height'],
			$transformed_data['html']
		);

		return $picture;
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
		$classes = trim( 'edge-images-container ' . $extra_classes );
		
		return sprintf(
			'<picture class="%s" style="aspect-ratio: %d/%d;">%s</picture>',
			esc_attr( $classes ),
			$dimensions['width'],
			$dimensions['height'],
			$img_html
		);
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

		// Add inline script to wrap template images
		wp_add_inline_script( 'wp-embed', '
			document.addEventListener("DOMContentLoaded", function() {
				document.querySelectorAll("img[data-edge-wrap=\'true\']").forEach(function(img) {
					if (!img.parentElement.matches("picture")) {
						const picture = document.createElement("picture");
						picture.className = "edge-images-container";
						if (img.width && img.height) {
							picture.style.aspectRatio = img.width + "/" + img.height;
						}
						img.parentNode.insertBefore(picture, img);
						picture.appendChild(img);
					}
				});
			});
		');
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
		if ($attachment_id) {
			$full_url = wp_get_attachment_image_url($attachment_id, 'full');
			if ($full_url) {
				return $full_url;
			}
		}
		
		// Try to convert the current URL to a full size URL
		$url_parts = explode('-', pathinfo($src, PATHINFO_FILENAME));
		if (count($url_parts) > 1 && is_numeric(end($url_parts))) {
			array_pop($url_parts);
			$base_filename = implode('-', $url_parts);
			$extension = pathinfo($src, PATHINFO_EXTENSION);
			$dir = pathinfo($src, PATHINFO_DIRNAME);
			return $dir . '/' . $base_filename . '.' . $extension;
		}
		
		return $src;
	}

}



