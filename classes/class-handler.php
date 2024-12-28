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

		// Ensure WordPress's default dimension handling still runs
		add_filter('wp_img_tag_add_width_and_height_attr', '__return_true', 999);

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
			// Get dimensions from attachment metadata for named size
			$dimensions = Image_Dimensions::from_attachment($attachment->ID, $size);
		}

		// Create a processor from the attributes
		$processor = new \WP_HTML_Tag_Processor('<img ' . Helpers::attributes_to_string($attr) . ' />');
		$processor->next_tag();

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
		if (strpos($html, '<picture') !== false || !Features::is_feature_enabled('picture_wrap')) {
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
				$picture_html = Picture::create($working_html, $dimensions, '');

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
			$picture_html = Picture::create($working_html, $dimensions, '');

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
		$processor = Images::transform_image_tag($processor, $attachment_id, $image_html, $context);
		$transformed = $processor->get_updated_html();

		// Check if picture wrapping is disabled or if we're in a block context (where wrapping happens later)
		if (Features::is_disabled('picture_wrap') || $context === 'block') {
			return $transformed;
		}

		// Get dimensions for the picture element
		$dimensions = Image_Dimensions::from_html(new \WP_HTML_Tag_Processor($transformed));
		if (!$dimensions) {
			return $transformed;
		}

		// If we have a figure, replace it with picture
		if (strpos($image_html, '<figure') !== false) {
			$figure_classes = $this->extract_figure_classes($image_html, []);
			$img_html = $this->extract_img_tag($transformed);
			
			return Picture::create($img_html, $dimensions, $figure_classes);
		}

		// Otherwise create picture element with just the image
		return Picture::create($transformed, $dimensions);
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
	 * Extract figure classes from HTML
	 *
	 * @param string $html    The HTML containing the figure tag.
	 * @param array  $default Default classes to use if none found.
	 * 
	 * @return string Space-separated list of classes
	 */
	private function extract_figure_classes( string $html, array $default ): string {
		if ( preg_match( '/<figure[^>]*class=["\']([^"\']*)["\']/', $html, $matches ) ) {
			return $matches[1];
		}
		return implode( ' ', $default );
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

		// Bail if we don't have any images
		if (!str_contains($content, '<img')) {
			return $content;
		}

		// Extract all img tags first
		if (!preg_match_all('/<img[^>]+>/', $content, $matches)) {
			return $content;
		}

	// Process each image once
	foreach ($matches[0] as $img_html) {
		// Skip if already processed
		if (Helpers::is_image_processed($img_html)) {
			continue;
		}

		// Create a processor for this image
		$processor = new \WP_HTML_Tag_Processor($img_html);
		if (!$processor->next_tag('img')) {
			continue;
		}

		// Transform the image
		$processor = Images::transform_image_tag($processor, null, $img_html, 'content');
		$transformed = $processor->get_updated_html();

		// If picture wrapping is enabled and we have dimensions, wrap in picture element
		if (Features::is_feature_enabled('picture_wrap')) {
			$dimensions = Image_Dimensions::from_html(new \WP_HTML_Tag_Processor($transformed));
			if ($dimensions) {
				$transformed = Picture::create($transformed, $dimensions);
			}
		}

		// Replace the original image with the transformed one
		$content = str_replace($img_html, $transformed, $content);
	}

	return $content;
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
	 * Clean up image HTML.
	 *
	 * Removes unnecessary attributes and normalizes HTML structure.
	 *
	 * @since 4.5.0
	 * 
	 * @param string       $html          The HTML img element markup.
	 * @param int         $attachment_id Image attachment post ID.
	 * @param string|array $size          Requested image size name or dimensions array.
	 * @param bool|array   $attr_or_icon  Array of attributes or boolean for icon status.
	 * @param bool|null    $icon          Whether the image should be treated as an icon.
	 * @return string Modified HTML with cleaned up attributes.
	 */
	public function cleanup_image_html(string $html, int $attachment_id, $size, $attr_or_icon = [], $icon = null): string {
	
		// Skip if no HTML
		if (empty($html)) {
			return $html;
		}

		// Create a processor from the HTML
		$processor = new \WP_HTML_Tag_Processor($html);

		// Bail if no img tag
		if (!$processor->next_tag('img')) {
			return $html;
		}

		// Remove any transformation attributes
		$processor = Images::clean_transform_attributes($processor);

		return $processor->get_updated_html();
	}
}





