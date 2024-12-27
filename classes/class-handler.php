<?php
/**
 * Image transformation handler.
 *
 * Manages the plugin's image transformation functionality, including:
 * - Image attribute transformation
 * - Picture element wrapping
 * - Image dimension enforcement
 * - Image HTML cleanup
 * - Content and block image transformation
 * - Image caching and optimization
 * - Integration with WordPress settings API
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

use Edge_Images\Features\Picture;

class Handler {
	
	/**
	 * Whether the handler has been registered.
	 *
	 * Used to prevent multiple registrations of hooks and filters.
	 *
	 * @since 4.0.0
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * The cached edge provider instance.
	 *
	 * Stores the current provider to avoid multiple instantiations.
	 * This is set when get_provider() is first called and reused
	 * for subsequent transformations.
	 *
	 * @since 4.0.0
	 * @var Edge_Provider|null
	 */
	private static ?Edge_Provider $provider_instance = null;

	/**
	 * Register the handler.
	 *
	 * Sets up all hooks and filters needed for image transformation.
	 * This includes content filters, attachment filters, and block
	 * rendering filters.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public static function register(): void {
		// Prevent multiple registrations
		if (self::$registered) {
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
	 * Sets up all WordPress filters needed for image transformation.
	 * The filters are added in a specific order to ensure proper
	 * transformation and optimization:
	 * 1. Transform attachment image attributes
	 * 2. Wrap images in picture elements
	 * 3. Enforce image dimensions
	 * 4. Clean up image HTML
	 * 5. Transform content and block images
	 *
	 * @since 4.0.0
	 * @return void
	 */
	private function add_filters(): void {
		// First transform the attributes
		add_filter('wp_get_attachment_image_attributes', [$this, 'transform_attachment_image'], 10, 3);
		
		// Then wrap in picture element
		add_filter('wp_get_attachment_image', [$this, 'wrap_attachment_image'], 11, 5);
		
		// Finally enforce dimensions and cleanup
		add_filter('wp_get_attachment_image_attributes', [$this, 'enforce_dimensions'], 12, 3);
		add_filter('wp_get_attachment_image', [$this, 'cleanup_image_html'], 13, 5);

		// Transform images in content
		add_filter('wp_img_tag_add_width_and_height_attr', [$this, 'transform_image'], 5, 4);
		add_filter('the_content', [$this, 'transform_content_images'], 5);
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
	 * Adjust the threshold for WordPress's big image scaling.
	 *
	 * Controls when WordPress should scale down large images during upload.
	 * This ensures that we maintain high-quality source images for edge
	 * transformations, while still preventing excessively large uploads.
	 *
	 * @since 4.0.0
	 * @param int|bool   $threshold     The threshold value in pixels, or false to disable scaling.
	 * @param array|null $imagesize     Indexed array of width and height values in pixels.
	 * @param string     $file          Full path to the uploaded image file.
	 * @param int        $attachment_id Attachment post ID.
	 * @return int|bool The adjusted threshold or false to disable scaling.
	 */
	public function adjust_image_size_threshold($threshold, $imagesize, string $file, int $attachment_id) {
		if (isset($imagesize[0]) && isset($imagesize[1])) {
			return max($imagesize[0], $imagesize[1]);
		}
		return $threshold;
	}

	/**
	 * Validate and normalize image dimensions.
	 *
	 * Ensures that image dimensions are within acceptable bounds and
	 * properly formatted. This method:
	 * - Enforces maximum size limits
	 * - Ensures positive values
	 * - Maintains aspect ratios
	 * - Converts values to integers
	 * - Handles missing dimensions
	 *
	 * @since      4.5.4
	 * 
	 * @param array $dimensions The dimensions to validate.
	 * @return array Validated and normalized dimensions.
	 */
	private function validate_dimensions(array $dimensions): array {

		// Define limits
		$min_size = 12;       // Minimum reasonable image dimension
		$max_size = 5000;     // Maximum reasonable image dimension
		$max_ratio = 5;       // Maximum aspect ratio (width:height or height:width)

		// Get current values or defaults
		$width = isset($dimensions['width']) ? absint($dimensions['width']) : 0;
		$height = isset($dimensions['height']) ? absint($dimensions['height']) : 0;

		// Enforce minimum size
		$width = max($min_size, $width);
		$height = max($min_size, $height);

		// Enforce maximum size
		$width = min($max_size, $width);
		$height = min($max_size, $height);

		// Check and adjust aspect ratio if needed
		if ($width > 0 && $height > 0) {
			$ratio = $width / $height;
			if ($ratio > $max_ratio) {
				// Too wide, adjust width
				$width = (int)($height * $max_ratio);
			} elseif (1 / $ratio > $max_ratio) {
				// Too tall, adjust height
				$height = (int)($width * $max_ratio);
			}
		}

		return [
			'width' => (string)$width,
			'height' => (string)$height,
		];
	}

	/**
	 * Enforce image dimensions in attachment attributes.
	 *
	 * Ensures that image markup has the correct width and height attributes,
	 * which is crucial for preventing layout shift and maintaining proper
	 * aspect ratios during image loading.
	 *
	 * @since 4.0.0
	 * @param array        $attr       Array of attribute values for the image markup.
	 * @param \WP_Post     $attachment Image attachment post.
	 * @param string|array $size       Requested image size. Can be any registered image size name or an array of width and height values.
	 * @return array Modified attributes with enforced dimensions.
	 */
	public function enforce_dimensions(array $attr, $attachment, $size): array {
		// Always use data-original-width/height if available
		if (isset($attr['data-original-width'], $attr['data-original-height'])) {
			// Validate the original dimensions
			$validated = $this->validate_dimensions([
				'width' => $attr['data-original-width'],
				'height' => $attr['data-original-height']
			]);
			
			// Force override the width and height with validated values
			$attr['width'] = $validated['width'];
			$attr['height'] = $validated['height'];
			$attr['data-original-width'] = $validated['width'];
			$attr['data-original-height'] = $validated['height'];
			
			return $attr;
		}
		
		// Fallback to size dimensions if needed
		$dimensions = $this->get_size_dimensions($size, $attachment->ID);
		if ($dimensions) {
			$validated = $this->validate_dimensions($dimensions);
			$attr['width'] = $validated['width'];
			$attr['height'] = $validated['height'];
		}
		
		return $attr;
	}

	/**
	 * Get transform arguments for a registered image size.
	 *
	 * Generates the appropriate transformation arguments based on
	 * a registered WordPress image size's dimensions and crop settings.
	 * If no provider is available, returns only basic dimension arguments.
	 *
	 * @since 4.0.0
	 * @param array $dimensions The image dimensions with 'width' and 'height' keys.
	 * @param bool  $crop      Whether the size should be cropped to exact dimensions.
	 * @return array The transformation arguments including provider-specific settings.
	 */
	private function get_registered_size_args(array $dimensions, bool $crop = true): array {
		// Get provider instance to access default args
		$provider = $this->get_provider_instance();
		
		// If no provider, return just the dimensions
		if (!$provider) {
			return [
				'w' => $dimensions['width'],
				'h' => $dimensions['height'],
			];
		}

		return array_merge(
			$provider->get_default_args(),
			[
				'w' => $dimensions['width'],
				'h' => $dimensions['height'],
			]
		);
	}

	/**
	 * Transform attachment image attributes.
	 *
	 * Processes and modifies image attributes for attachment images:
	 * - Adds edge provider transformation parameters
	 * - Handles special formats (SVG, AVIF)
	 * - Manages image dimensions
	 * - Adds necessary classes and data attributes
	 * - Generates responsive image attributes
	 *
	 * @since 4.0.0
	 * @param array        $attr       Array of attribute values for the image markup.
	 * @param \WP_Post     $attachment Attachment post object.
	 * @param string|array $size       Requested image size. Can be any registered size name or array of dimensions.
	 * @return array Modified attributes with transformation parameters.
	 */
	public function transform_attachment_image(array $attr, \WP_Post $attachment, $size): array {
		// Skip if already processed
		if (isset($attr['class']) && Helpers::is_image_processed($attr['class'])) {
			return $attr;
		}

		// Get dimensions from size
		$dimensions = null;
		if (is_array($size) && isset($size[0], $size[1])) {
			$dimensions = [
				'width' => (string) $size[0],
				'height' => (string) $size[1]
			];
		} else {
			// Get dimensions from attachment metadata for named size
			$dimensions = Image_Dimensions::from_attachment($attachment->ID, $size);
		}

		// Fall back to original dimensions if no size-specific dimensions found
		if (!$dimensions) {
			$dimensions = Image_Dimensions::from_attachment($attachment->ID);
		}

		if (!$dimensions) {
			return $attr;
		}

		// Check for special formats (SVG/AVIF)
		$is_special_format = isset($attr['src']) && 
			(Helpers::is_svg($attr['src']) || strpos($attr['src'], '.avif') !== false);

		// Get transform args based on size and attributes
		$transform_args = array_merge(
			$this->get_registered_size_args($dimensions),
			$this->extract_transform_args($attr)
		);

		// Transform src (skip for SVG/AVIF)
		if (isset($attr['src']) && !$is_special_format) {
			$provider = $this->get_provider_instance();
			if ($provider) {
				$full_src = $this->get_full_size_url($attr['src'], $attachment->ID);
				$attr['src'] = Helpers::edge_src($full_src, $transform_args);
			}
		}

		// Generate srcset with the same transformation args
		if (isset($attr['src']) && $dimensions && !$is_special_format) {
			$provider = $this->get_provider_instance();
			if ($provider) {
				$full_src = $this->get_full_size_url($attr['src'], $attachment->ID);
				$srcset = Srcset_Transformer::transform(
					$full_src,
					$dimensions,
					"(max-width: {$dimensions['width']}px) 100vw, {$dimensions['width']}px",
					$transform_args
				);
				if ($srcset) {
					$attr['srcset'] = $srcset;
				}
			}
		}

		// Store original dimensions
		$attr['data-original-width'] = $dimensions['width'];
		$attr['data-original-height'] = $dimensions['height'];

		// Set dimensions
		$attr['width'] = $dimensions['width'];
		$attr['height'] = $dimensions['height'];

		// Add our classes
		$attr['class'] = isset($attr['class']) 
			? $attr['class'] . ' edge-images-img edge-images-processed' 
			: 'edge-images-img edge-images-processed';

		// Add picture wrap flag if enabled
		if (Feature_Manager::is_feature_enabled('picture_wrap')) {
			$attr['data-wrap-in-picture'] = 'true';
		}

		return $attr;
	}

	/**
	 * Extract transformation arguments from image attributes.
	 *
	 * Filters the image attributes to extract only valid transformation
	 * parameters that are supported by the edge provider. This includes
	 * both canonical argument names and their aliases.
	 *
	 * @since 4.0.0
	 * @param array $attr The image attributes array.
	 * @return array The filtered transformation arguments.
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
	 * Get dimensions for a specific image size.
	 *
	 * Retrieves the width and height dimensions for a given image size,
	 * whether it's a registered size name or custom dimensions array.
	 * Falls back to attachment metadata if needed.
	 *
	 * @since 4.0.0
	 * @param string|array $size          The size name or dimensions array [width, height].
	 * @param int         $attachment_id The attachment post ID.
	 * @return array<string,string>|null Array with 'width' and 'height' keys, or null if dimensions cannot be determined.
	 */
	private function get_size_dimensions($size, int $attachment_id): ?array {
		return Image_Dimensions::from_size($size, $attachment_id);
	}

	/**
	 * Wrap an attachment image in a picture element.
	 *
	 * Creates a responsive picture element wrapper around an image,
	 * which provides better control over responsive images and art
	 * direction. Handles various edge cases including:
	 * - Existing anchor tags
	 * - Icon images
	 * - Custom attributes
	 * - Dimension preservation
	 *
	 * @since 4.0.0
	 * @param string       $html          The HTML img element markup.
	 * @param int         $attachment_id Image attachment post ID.
	 * @param string|array $size          Requested image size name or dimensions array.
	 * @param bool|array   $attr_or_icon  Array of attributes or boolean for icon status.
	 * @param bool|null    $icon          Whether the image should be treated as an icon.
	 * @return string Modified HTML with picture element wrapper if appropriate.
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
	 * Transform image
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
		if (Helpers::is_image_processed($image_html)) {
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
	 * @param string               $html      Optional. The original HTML.
	 * @param string               $context   Optional. The transformation context.
	 * @param array                $args      Optional. Additional transformation arguments.
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
		$attachment_id = Helpers::get_attachment_id_from_classes($processor);
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
			$max_width = min((int)$dimensions['width'], Settings::get_max_width());
			
			$dimensions = $this->constrain_dimensions($dimensions, [
				'width' => $max_width,
				'height' => null
			]);

			// Update original dimensions to match constrained dimensions
			$original_dimensions = $dimensions;
		}

		// Get a provider instance to access default args
		$provider = $this->get_provider_instance();

		// Bail if we don't have a provider
		if (!$provider) {
			return;
		}
		
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
		
		// Update width and height attributes
		$processor->set_attribute('width', (string)$dimensions['width']);
		$processor->set_attribute('height', (string)$dimensions['height']);
		
		// Store original dimensions for picture element
		$processor->set_attribute('data-original-width', (string)$original_dimensions['width']);
		$processor->set_attribute('data-original-height', (string)$original_dimensions['height']);
		
		// Get sizes attribute
		$sizes = "(max-width: {$dimensions['width']}px) 100vw, {$dimensions['width']}px";
		
		// Generate srcset
		$srcset = Srcset_Transformer::transform(
			$full_src, 
			$dimensions,
			$sizes
		);
		if ($srcset) {
			$processor->set_attribute('srcset', $srcset);
		}
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
	private function transform_figures_in_block(string $block_content): string {

		// Bail if no figures found
		if (!preg_match_all('/<figure[^>]*>.*?<\/figure>/s', $block_content, $matches, PREG_OFFSET_CAPTURE)) {
			return $block_content;
		}

		$offset_adjustment = 0;

		foreach ($matches[0] as $match) {
			$figure_html = $match[0];
			$position = $match[1];

			// If the figure already contains a picture element, extract and use it
			if (preg_match('/<picture[^>]*>.*?<\/picture>/s', $figure_html, $picture_matches)) {
				$picture_html = $picture_matches[0];
				
				// Extract figure classes
				$figure_classes = $this->extract_figure_classes($figure_html);
				
				// Extract picture classes
				if (preg_match('/class=["\']([^"\']*)["\']/', $picture_html, $picture_class_matches)) {
					$picture_classes = array_filter(explode(' ', $picture_class_matches[1]));
					
					// Merge classes
					$all_classes = array_unique(array_merge($figure_classes, $picture_classes));
					
					// Replace picture classes with merged classes
					$picture_html = preg_replace(
						'/class=["\'][^"\']*["\']/',
						'class="' . implode(' ', $all_classes) . '"',
						$picture_html
					);
				} else {
					// If picture has no class, add figure classes
					$picture_html = str_replace('<picture', '<picture class="' . implode(' ', $figure_classes) . '"', $picture_html);
				}
				
				// Extract any link wrapping and move it inside the picture element
				if (preg_match('/<a[^>]*>.*?<\/a>/s', $figure_html, $link_matches)) {
					$link_open = substr($link_matches[0], 0, strpos($link_matches[0], '>') + 1);
					
					// Insert the link opening tag after the picture opening tag
					$picture_html = preg_replace(
						'/(<picture[^>]*>)/',
						'$1' . $link_open,
						$picture_html
					);
					
					// Insert the link closing tag before the picture closing tag
					$picture_html = str_replace('</picture>', '</a></picture>', $picture_html);
				}
				
				// Extract any caption
				if (preg_match('/<figcaption.*?>(.*?)<\/figcaption>/s', $figure_html, $caption_matches)) {
					$picture_html .= $caption_matches[0];
				}

				// Replace the entire figure with our picture element
				$block_content = substr_replace(
					$block_content,
					$picture_html,
					$position + $offset_adjustment,
					strlen($figure_html)
				);
				
				$offset_adjustment += strlen($picture_html) - strlen($figure_html);
				continue;
			}

			// Extract figure classes
			$figure_classes = $this->extract_figure_classes($figure_html);

			// Extract the image and any wrapping link
			$img_html = $this->extract_img_tag($figure_html);
			if (!$img_html) {
				continue;
			}

			// Get dimensions from the image
			$processor = new \WP_HTML_Tag_Processor($img_html);
			if (!$processor->next_tag('img')) {
				continue;
			}

			// Get width and height
			$width = $processor->get_attribute('width');
			$height = $processor->get_attribute('height');

			// Bail if no width or height
			if (!$width || !$height) {
				continue;
			}

			// Set the dimensions
			$dimensions = [
				'width' => $width,
				'height' => $height
			];

			// Transform the image first
			$transformed_img = $this->transform_image(true, $img_html, 'block', 0);

			// Create picture element with figure classes
			$picture_html = Picture::create(
				$transformed_img,
				$dimensions,
				implode(' ', array_merge($figure_classes, ['edge-images-container']))
			);

			// If there's a link wrapping the image, move it inside the picture element
			if (preg_match('/<a[^>]*>.*?<\/a>/s', $figure_html, $link_matches)) {
				$link_open = substr($link_matches[0], 0, strpos($link_matches[0], '>') + 1);
				
				// Insert the link opening tag after the picture opening tag
				$picture_html = preg_replace(
					'/(<picture[^>]*>)/',
					'$1' . $link_open,
					$picture_html
				);
				
				// Insert the link closing tag before the picture closing tag
				$picture_html = str_replace('</picture>', '</a></picture>', $picture_html);
			}

			// Extract any caption from the figure
			if (preg_match('/<figcaption.*?>(.*?)<\/figcaption>/s', $figure_html, $caption_matches)) {
				$picture_html .= $caption_matches[0];
			}

			// Replace the entire figure with our new markup
			$block_content = substr_replace(
				$block_content,
				$picture_html,
				$position + $offset_adjustment,
				strlen($figure_html)
			);
			
			// Adjust the offset adjustment
			$offset_adjustment += strlen($picture_html) - strlen($figure_html);
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
	private function transform_standalone_images_in_block(string $block_content): string {

		// Bail if no images found
		if (!preg_match_all('/<img[^>]*>/', $block_content, $matches, PREG_OFFSET_CAPTURE)) {
			return $block_content;
		}

		// Initialize offset adjustment
		$offset_adjustment = 0;

		// Loop through each image
		foreach ($matches[0] as $match) {

			// Get the image HTML and its position
			$img_html = $match[0];
			$position = $match[1];

			// Bail if image should not be transformed
			if (!$this->should_transform_standalone_image($img_html, $block_content, $position)) {
				continue;
			}

			// Transform the image
			$transformed_html = $this->transform_image(true, $img_html, 'block', 0);
			
			// Replace the image with the transformed image
			$block_content = substr_replace(
				$block_content,
				$transformed_html,
				$position + $offset_adjustment,
				strlen($img_html)
			);
			
			// Adjust the offset adjustment
			$offset_adjustment += strlen($transformed_html) - strlen($img_html);
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
	 * Extract figure classes from block content and attributes.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $block_content The block content.
	 * @param array  $block         Optional block data.
	 * @return array The combined classes.
	 */
	private function extract_figure_classes(string $block_content, array $block = []): array {
		$classes = [];

		// Create a processor for the figure
		$processor = new \WP_HTML_Tag_Processor($block_content);
		if ($processor->next_tag('figure')) {
			$figure_classes = $processor->get_attribute('class');
			if ($figure_classes) {
				$classes = array_filter(explode(' ', $figure_classes));
			}
		}

		// Add alignment if present in block attributes
		if (isset($block['attrs']['align'])) {
			$classes[] = "align{$block['attrs']['align']}";
		}

		// Return unique classes
		return array_unique($classes);
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
	 * Get dimensions from HTML
	 *
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * @return array<string,string>|null The dimensions or null if not found.
	 */
	private function get_dimensions_from_html(\WP_HTML_Tag_Processor $processor): ?array {
		return Image_Dimensions::from_html($processor);
	}

	/**
	 * Get dimensions from attachment
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return array<string,string>|null The dimensions or null if not found.
	 */
	private function get_dimensions_from_attachment(int $attachment_id): ?array {
		return Image_Dimensions::from_attachment($attachment_id);
	}

	/**
	 * Get full size dimensions
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return array<string,string>|null The dimensions or null if not found.
	 */
	private function get_full_size_dimensions(int $attachment_id): ?array {
		return Image_Dimensions::get_full_size($attachment_id);
	}

	/**
	 * Get dimensions from processor and attachment
	 *
	 * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
	 * @param int|null              $attachment_id The attachment ID.
	 * @param string               $size          The size name.
	 * @return array<string,string>|null The dimensions or null if not found.
	 */
	private function get_dimensions(\WP_HTML_Tag_Processor $processor, ?int $attachment_id = null, string $size = 'full'): ?array {
		return Image_Dimensions::get($processor, $attachment_id, $size);
	}

	/**
	 * Constrain dimensions to maximum values
	 *
	 * @param array<string,string> $dimensions     The dimensions to constrain.
	 * @param array<string,string> $max_dimensions The maximum dimensions.
	 * @return array<string,string> The constrained dimensions.
	 */
	private function constrain_dimensions(array $dimensions, array $max_dimensions): array {
		return Image_Dimensions::constrain($dimensions, $max_dimensions);
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
		
		// If no provider, return original URL
		if (!$provider) {
			return $src;
		}

		// Remove any existing transformations
		$src = $provider::clean_transformed_url($src);
		
		// Try to convert the current URL to a full size URL
		$path_parts = pathinfo($src);
		
		// Ensure we have all required parts
		$dirname = $path_parts['dirname'] ?? '';
		
		return $src;
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
		if (!Helpers::is_image_processed($html)) {
			return $html;
		}

		// Create a new tag processor
		$processor = new \WP_HTML_Tag_Processor($html);
		if (!$processor->next_tag('img')) {
			return $html;
		}

		// Get the original dimensions
		$original_width = $processor->get_attribute('data-original-width');
		$original_height = $processor->get_attribute('data-original-height');

		if ($original_width && $original_height) {
			// Update width and height attributes
			$processor->set_attribute('width', $original_width);
			$processor->set_attribute('height', $original_height);

			// Update sizes attribute if needed
			$sizes = $processor->get_attribute('sizes');
			if ($sizes && (strpos($sizes, 'auto') !== false || strpos($sizes, '238px') !== false)) {
				$processor->set_attribute(
					'sizes',
					"(max-width: {$original_width}px) 100vw, {$original_width}px"
				);
			}
		}

		// Remove data attributes we don't want in the final output
		$processor->remove_attribute('data-wrap-in-picture');
		$processor->remove_attribute('data-original-width');
		$processor->remove_attribute('data-original-height');

		return $processor->get_updated_html();
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

		return true;
	}

	/**
	 * Get the edge provider instance.
	 * 
	 * @return Edge_Provider|null The provider instance, or null if none configured.
	 */
	private function get_provider_instance(): ?Edge_Provider {
		if (self::$provider_instance === null) {
			self::$provider_instance = Helpers::get_edge_provider();
		}
		return self::$provider_instance;
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

	/**
	 * Transform images in post content.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $content The post content.
	 * @return string Modified content.
	 */
	public function transform_content_images(string $content): string {
		// Bail if no images found
		if (!str_contains($content, '<img')) {
			return $content;
		}

		// Find all img tags
		if (!preg_match_all('/<img[^>]+>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
			return $content;
		}

		// Track our offset adjustment as we make replacements
		$offset_adjustment = 0;

		// Process each image
		foreach ($matches[0] as $match) {
			$img_html = $match[0];
			$position = $match[1];

			// Skip if already processed
			if (Helpers::is_image_processed($img_html)) {
				continue;
			}

			// Create a processor for this image
			$processor = new \WP_HTML_Tag_Processor($img_html);
			if (!$processor->next_tag('img')) {
				continue;
			}

			// Get attachment ID from wp-image-X class
			$attachment_id = null;
			$class = $processor->get_attribute('class') ?? '';
			if (preg_match('/wp-image-(\d+)/', $class, $id_matches)) {
				$attachment_id = (int) $id_matches[1];
			}

			// Transform the image
			$processor = $this->transform_image_tag(
				$processor,
				$attachment_id,
				$img_html,
				'content'
			);

			// Get dimensions for picture wrapping
			$dimensions = $this->get_dimensions($processor, $attachment_id);
			if ($dimensions && Feature_Manager::is_feature_enabled('picture_wrap')) {
				$transformed_html = Picture::create(
					$processor->get_updated_html(),
					$dimensions,
					''
				);
			} else {
				$transformed_html = $processor->get_updated_html();
			}

			// Replace in content
			$content = substr_replace(
				$content,
				$transformed_html,
				$position + $offset_adjustment,
				strlen($img_html)
			);

			// Update offset
			$offset_adjustment += strlen($transformed_html) - strlen($img_html);
		}

		return $content;
	}

}





