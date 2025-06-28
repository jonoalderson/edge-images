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
 * @license    GPL-2.0-or-later
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
		if (!Helpers::should_transform_images()) {
			return;
		}

		// Bail if no valid provider is configured
		$provider = Helpers::get_edge_provider();
		if (!$provider || !$provider::is_configured()) {
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
		
		// Transform theme images
		add_filter('wp_get_attachment_image', [$this, 'wrap_attachment_image'], 11, 5);

		// Transform images in content - move this after the_content filter
		add_filter('wp_img_tag_add_width_and_height_attr', [$this, 'transform_image'], 5, 4);
		
		// Enforce dimensions and cleanup
		add_filter('wp_get_attachment_image_attributes', [$this, 'enforce_dimensions'], 12, 3);
		add_filter('wp_get_attachment_image', [$this, 'cleanup_image_html'], 999, 5);

		// Hook into image_downsize to handle dimensions before srcset calculation
		add_filter('image_downsize', [$this, 'handle_image_downsize'], 10, 3);

		// Hook into srcset calculation
		add_filter('wp_calculate_image_srcset', [$this, 'calculate_image_srcset'], 10, 5);

		// Ensure WordPress's default dimension handling still runs
		add_filter('wp_img_tag_add_width_and_height_attr', '__return_true', 999);

		// Transform content images last
		add_filter('the_content', [$this, 'transform_content_images'], 20);
		
		// Enqueue styles
		add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);

		// Prevent WordPress from scaling images
		add_filter('big_image_size_threshold', [$this, 'adjust_image_size_threshold'], 10, 4);

		// Clean transformation attributes from attachment images
		add_filter('wp_get_attachment_image_attributes', [Images::class, 'clean_attachment_image_attributes'], 99);
	}

	/**
	 * Adjust the threshold for WordPress's big image scaling.
	 *
	 * Controls when WordPress should scale down large images during upload.
	 * This ensures that we maintain high-quality source images for edge
	 * transformations, while still preventing excessively large uploads.
	 * Images larger than 4800px on either dimension will be scaled down.
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
			return min(4800, max($imagesize[0], $imagesize[1]));
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
	 * @since 4.0.0
	 * @param array        $attr       Array of attribute values for the image markup.
	 * @param \WP_Post     $attachment Image attachment post.
	 * @param string|array $size       Requested image size.
	 * @return array Modified attributes with enforced dimensions.
	 */
	public function enforce_dimensions(array $attr, $attachment, $size): array {

		// Create temporary HTML to check if transformation should be disabled
		$temp_html = '<img ' . Helpers::attributes_to_string($attr) . ' />';
		if (Helpers::should_disable_transform($temp_html)) {
			return $attr;
		}

		// Skip dimension enforcement for SVGs
		if (isset($attr['src']) && Helpers::is_non_transformable_format($attr['src'])) {
			return $attr;
		}

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
		$provider = Helpers::get_edge_provider();
		
		// If no provider, return just the dimensions
		if (!$provider) {
			return [
				'w' => $dimensions['width'] ?? '',
				'h' => $dimensions['height'] ?? '',
			];
		}

		return array_merge(
			$provider->get_default_args(),
			[
				'w' => $dimensions['width'] ?? '',
				'h' => $dimensions['height'] ?? '',
			]
		);
	}

	/**
	 * Transform attachment image attributes.
	 *
	 * @param array    $attr       The image attributes.
	 * @param \WP_Post $attachment The attachment post object.
	 * @param mixed    $size       The requested image size.
	 * @return array The transformed attributes.
	 */
	public function transform_attachment_image(array $attr, \WP_Post $attachment, $size): array {

		// Create temporary HTML to check if transformation should be disabled
		$temp_html = '<img ' . Helpers::attributes_to_string($attr) . ' />';
		if (Helpers::should_disable_transform($temp_html)) {
			return $attr;
		}

		// Skip if already processed
		if (!empty($attr['class']) && Helpers::is_image_processed($attr['class'])) {
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
			// Get dimensions from registered size
			$dimensions = Image_Dimensions::from_size($size, $attachment->ID);
		}

		// Create a processor from the attributes
		$processor = new \WP_HTML_Tag_Processor('<img ' . Helpers::attributes_to_string($attr) . ' />');
		$processor->next_tag();

		// Add wp-image-{id} class if not present
		$classes = $processor->get_attribute('class') ?? '';
		if (!str_contains($classes, 'wp-image-' . $attachment->ID)) {
			$processor->set_attribute('class', trim($classes . ' wp-image-' . $attachment->ID));
		}

		// Get transform args based on size and attributes
		$transform_args = array_merge(
			$this->get_registered_size_args($dimensions ?: []),
			$this->extract_transform_args($attr)
		);

		// Transform the image
		$processor = Images::transform_image_tag($processor, $attachment->ID, '', 'attachment', $transform_args);

		// Convert processor back to attributes
		return Helpers::processor_to_attributes($processor);
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

		// First, normalize any long-form args to their short form
		$normalized = [];
		foreach ($valid_args as $short => $aliases) {
			// If we have the short form, use it directly
			if (isset($attr[$short])) {
				$normalized[$short] = $attr[$short];
				continue;
			}
			
			// Check aliases and use the first one found
			if (is_array($aliases)) {
				foreach ($aliases as $alias) {
					if (isset($attr[$alias])) {
						$normalized[$short] = $attr[$alias];
						break;
					}
				}
			}
		}
		
		return $normalized;
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
	 * @since 4.0.0
	 * 
	 * @param string       $html         The HTML img element markup.
	 * @param mixed        $attachment_id The attachment ID.
	 * @param string|array $size         The registered image size or array of width and height values.
	 * @param array|string $attr_or_icon Array of attributes or 'icon' if it's the fourth argument.
	 * @param bool|null    $icon         Whether the image should be treated as an icon.
	 * @return string The wrapped HTML.
	 */
	public function wrap_attachment_image(string $html, $attachment_id = null, $size = 'full', $attr_or_icon = [], $icon = null): string {

		// Check if transformation should be disabled
		if (Helpers::should_disable_transform($html)) {
			return $html;
		}

		// Check if we should wrap this image
		if (!Picture::should_wrap($html, 'attachment')) {
			return $html;
		}

		// Extract dimensions from the image
		$processor = new \WP_HTML_Tag_Processor($html);
		if (!$processor->next_tag('img')) {
			return $html;
		}

		// Get dimensions from size first if it's an array
		$dimensions = null;
		if (is_array($size) && isset($size[0], $size[1])) {
			$dimensions = [
				'width' => (string) $size[0],
				'height' => (string) $size[1]
			];
		}

		// If no dimensions from size, try getting from image attributes
		if (!$dimensions) {
			$width = $processor->get_attribute('width');
			$height = $processor->get_attribute('height');

			if (!$width || !$height) {
				return $html;
			}

			$dimensions = [
				'width' => $width,
				'height' => $height
			];
		}

		// If we have a figure, try to transform it
		$img_html = Helpers::extract_img_tag($html);
		if ($img_html && strpos($html, '<figure') !== false) {
			$picture = Picture::transform_figure($html, $img_html, $dimensions);
			if ($picture) {
				return $picture;
			}
		}

		// Otherwise create picture element with just the image
		return Picture::create($img_html ?: $html, $dimensions);
	}

	/**
	 * Transform image
	 *
	 * @since 4.5.0
	 * 
	 * @param string|bool $value         The filtered value.
	 * @param string     $image_html    The HTML image tag.
	 * @param string     $context       The context (header, content, etc).
	 * @param int        $attachment_id The attachment ID.
	 * @return string The transformed image HTML
	 */
	public function transform_image($value, $image_html, $context = '', $attachment_id = null): string {

		// Skip if transformation should be disabled
		if (Helpers::should_disable_transform($image_html)) {
			return $image_html;
		}

		// Skip if already processed
		if (Helpers::is_image_processed($image_html)) {
			return $image_html;
		}

		// Create a processor for the image HTML.
		$processor = new \WP_HTML_Tag_Processor($image_html);
		if (!$processor->next_tag('img')) {
			return $image_html;
		}

		// Get the src
		$src = $processor->get_attribute('src');
		if (!$src) {
			return $image_html;
		}

		// Skip if it's a non-transformable format
		if (Helpers::is_non_transformable_format($src)) {
			return $image_html;
		}

		// Transform the image tag.
		$processor = Images::transform_image_tag($processor, $attachment_id, $image_html, $context);
		$transformed = $processor->get_updated_html();

		// If we shouldn't wrap in picture, return the transformed image.
		if (!Picture::should_wrap($transformed, $context)) {
			// If we had a figure tag originally, we should preserve it
			if (strpos($image_html, '<figure') !== false) {
				$img_html = Helpers::extract_img_tag($transformed);
				if ($img_html) {
					return str_replace(Helpers::extract_img_tag($image_html), $img_html, $image_html);
				}
			}
			return $transformed;
		}

		// Get dimensions for the picture element
		$processor->next_tag('img');  // Reset to the img tag
		$dimensions = Image_Dimensions::from_html($processor);
		
		if (!$dimensions) {
			// Try getting dimensions from attachment as fallback
			$dimensions = Image_Dimensions::from_attachment($attachment_id);
		}

		if (!$dimensions) {
			return $transformed;
		}

		// Create picture element
		return Picture::create($transformed, $dimensions);
	}

	/**
	 * Transform content images
	 *
	 * @since 4.5.0
	 * 
	 * @param string $content The post content.
	 * @return string Modified content.
	 */
	public function transform_content_images(string $content): string {
		$transformer = new Content_Transformer();
		return $transformer->transform($content);
	}

	/**
	 * Output required styles inline.
	 *
	 * @since 4.5.0
	 * @return void
	 */
	public function enqueue_styles(): void {
		
		$transient_key = 'edge_images_css_' . EDGE_IMAGES_VERSION;
		$css = get_transient($transient_key);

		if ($css === false) {
			$css_file = plugin_dir_path(dirname(__FILE__)) . 'assets/css/images.min.css';
			$css = file_exists($css_file) ? file_get_contents($css_file) : '';
			
			// Cache for a week, or until plugin update
			set_transient($transient_key, $css, WEEK_IN_SECONDS);
		}

		wp_register_style('edge-images', false);
		wp_enqueue_style('edge-images');
		wp_add_inline_style('edge-images', $css);
	}

	/**
	 * Clean up image HTML.
	 *
	 * Removes unnecessary attributes and normalizes HTML structure.
	 *
	 * @since 4.5.0
	 * 
	 * @param string       $html          The HTML img element markup.
	 * @param mixed        $attachment_id Image attachment post ID.
	 * @param string|array $size          Requested image size name or dimensions array.
	 * @param bool|array   $attr_or_icon  Array of attributes or boolean for icon status.
	 * @param bool|null    $icon          Whether the image should be treated as an icon.
	 * @return string Modified HTML with cleaned up attributes.
	 */
	public function cleanup_image_html(string $html, $attachment_id = null, $size = 'full', $attr_or_icon = [], $icon = null): string {
	
		// Skip if no HTML
		if (empty($html)) {
			return $html;
		}

		// Check if transformation should be disabled
		if (Helpers::should_disable_transform($html)) {
			return $html;
		}

		// Create a processor from the HTML
		$processor = new \WP_HTML_Tag_Processor($html);

		// Process the img tag
		if (!$processor->next_tag('img')) {
			return $html;
		}

		// Remove any transformation attributes
		$processor = Images::clean_transform_attributes($processor);

		return $processor->get_updated_html();
	}

	/**
	 * Handle image downsizing before WordPress processes srcset.
	 *
	 * Intercepts the image downsizing process to ensure we maintain the original
	 * requested dimensions throughout the srcset generation process.
	 *
	 * @since 4.5.0
	 * 
	 * @param array|false  $downsize      Whether to short-circuit the image downsize.
	 * @param mixed        $attachment_id The attachment ID or null.
	 * @param string|array $size         Requested size. Can be an array of width and height or a registered size.
	 * @return array|false Array containing the image URL, width, height, and whether it's an intermediate size, or false.
	 */
	public function handle_image_downsize($downsize, $attachment_id, $size) {

		// Skip if transformation should be disabled
		if (Helpers::should_disable_transform('')) {
			return $downsize;
		}

		// Skip if already downsized
		if ($downsize !== false) {
			return $downsize;
		}

		// Skip if no attachment ID or invalid type
		if ($attachment_id === null || !is_numeric($attachment_id)) {
			return false;
		}

		// Convert to integer
		$attachment_id = (int) $attachment_id;

		// Get the full size image URL
		$img_url = wp_get_attachment_url($attachment_id);
		if (!$img_url) {
			return false;
		}

		// If size is an array, use those dimensions directly
		if (is_array($size) && isset($size[0]) && isset($size[1])) {
			return [
				$img_url,
				(int) $size[0],
				(int) $size[1],
				true // Changed to true to indicate this is a valid intermediate size
			];
		}

		// For named sizes, let WordPress handle it
		return false;
	}

	/**
	 * Calculate image srcset values.
	 *
	 * Generates srcset values based on the original image dimensions and
	 * the requested size.
	 *
	 * @since 4.5.0
	 * 
	 * @param array  $sources       {
	 *     One or more arrays of source data to include in the 'srcset'.
	 *
	 *     @type array $width {
	 *         @type string $url        The URL of an image source.
	 *         @type string $descriptor The descriptor type used in the image candidate string,
	 *                                  either 'w' or 'x'.
	 *         @type int    $value      The source width if paired with a 'w' descriptor, or a
	 *                                  pixel density value if paired with an 'x' descriptor.
	 *     }
	 * }
	 * @param array  $size_array     Array of width and height values in pixels.
	 * @param string $image_src      The 'src' of the image.
	 * @param array  $image_meta     The image meta data as returned by wp_get_attachment_metadata().
	 * @param mixed  $attachment_id  Image attachment ID or 0.
	 * @return array|false A list of image sources and descriptors, or false.
	 */
	public function calculate_image_srcset($sources, array $size_array, string $image_src, array $image_meta, $attachment_id) {
		
		// Skip if transformation should be disabled
		if (Helpers::should_disable_transform('')) {
			return $sources;
		}

		// Skip if no attachment ID or invalid type
		if (!is_numeric($attachment_id)) {
			return $sources;
		}

		// Convert to integer
		$attachment_id = (int) $attachment_id;

		// Get the original image URL
		$img_url = wp_get_attachment_url($attachment_id);
		if (!$img_url) {
			return $sources;
		}

		// Get srcset string from transformer
		$srcset = Srcset_Transformer::transform(
			$img_url,
			[
				'width' => $size_array[0],
				'height' => $size_array[1],
			]
		);

		// If no srcset generated, return original sources
		if (!$srcset) {
			return $sources;
		}

		// Convert srcset string to sources array
		$sources = [];
		$srcset_pairs = explode(',', $srcset);
		foreach ($srcset_pairs as $pair) {
			if (preg_match('/(.+)\s+(\d+)w$/', trim($pair), $matches)) {
				$url = trim($matches[1]);
				$width = (int) $matches[2];
				$sources[$width] = [
					'url' => $url,
					'descriptor' => 'w',
					'value' => $width,
				];
			}
		}

		return $sources;
	}

}







