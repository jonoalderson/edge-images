<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Describes an edge provider.
 */
class Edge_Provider {

	/**
	 * List of all valid edge transformation arguments and their aliases.
	 * Key is the canonical (short) form, value is array of aliases or null if no aliases.
	 *
	 * @var array
	 */
	protected static array $valid_args = [
		// Core parameters
		'w' => ['width'],              // Width of the image, in pixels
		'h' => ['height'],             // Height of the image, in pixels
		'dpr' => null,                 // Device Pixel Ratio (1-3)
		'fit' => null,                 // Resizing behavior: scale-down, contain, cover, crop, pad
		'g' => ['gravity'],            // Gravity/crop position: auto, north, south, east, west, center
		'q' => ['quality'],            // Quality (1-100)
		'f' => ['format'],             // Output format: auto, webp, json, jpeg, png, gif, avif
		
		// Advanced parameters
		'metadata' => null,            // Keep or strip metadata: keep, copyright, none
		'onerror' => null,            // Error handling: redirect, 404
		'anim' => null,               // Whether to preserve animation frames
		'blur' => null,               // Blur radius (1-250)
		'brightness' => null,         // Adjust brightness (-100 to 100)
		'contrast' => null,          // Adjust contrast (-100 to 100)
		'gamma' => null,             // Adjust gamma (1-100)
		'sharpen' => null,          // Sharpen radius (1-100)
		'trim' => null,             // Trim edges by color (1-100)
		
		// Background and border
		'bg' => ['background'],     // Background color for 'pad' fit
		'border' => null,          // Border width and color
		'pad' => null,            // Padding when using 'pad' fit
		
		// Rotation and flipping
		'rot' => ['rotate'],      // Rotation angle (multiple of 90)
		'flip' => null,          // Flip image: h, v, hv
		
		// Text and watermarks
		'txt' => null,          // Text to render
		'txt-color' => null,    // Text color
		'txt-align' => null,    // Text alignment
		'txt-font' => null,     // Text font family
		'txt-size' => null,     // Text size
		'txt-pad' => null,      // Text padding
		'txt-line' => null,     // Text line height
		'txt-width' => null,    // Text box width
		
		// Optimization
		'strip' => null,        // Strip metadata: all, color, none
	];

	/**
	 * Default edge transformation arguments.
	 *
	 * @var array
	 */
	protected array $default_edge_args = [
		'fit' => 'cover',
		'dpr' => 1, 
		'f' => 'auto',
		'g' => 'auto',
		'q' => 85,
		'w' => null,
		'h' => null,
	];

	/**
	 * Value mappings for specific parameters
	 *
	 * @var array
	 */
	protected static array $value_mappings = [
		'g' => [
			'top' => 'north',
			'bottom' => 'south',
			'left' => 'west',
			'right' => 'east',
			'center' => 'center',
	 ],
	];

	/**
	 * The args to set for images.
	 *
	 * @var array
	 */
	public array $args = [];

	/**
	 * The image path
	 *
	 * @var string
	 */
	public string $path;

	/**
	 * Create the provider
	 *
	 * @param string $path The path to the image.
	 * @param array  $args The arguments.
	 */
	public function __construct( string $path, array $args = [] ) {
		$this->path = $path;
		$this->args = $args;

		global $content_width;
		if ( ! $content_width ) {
			$content_width = 600;
		}

		$this->normalize_args();
	}

	/**
	 * Get the args
	 *
	 * @return array The args.
	 */
	protected function get_transform_args(): array {
		$args = array_merge(
			$this->default_edge_args,
			array_filter([
				'w' => $this->args['w'] ?? null,
				'h' => $this->args['h'] ?? null,
				'fit' => $this->args['fit'] ?? 'cover',
				'f' => $this->args['f'] ?? 'auto',
				'q' => $this->args['q'] ?? 85,
				'dpr' => $this->args['dpr'] ?? 1,
				'g' => $this->args['g'] ?? 'auto',
				'sharpen' => $this->args['sharpen'] ?? null,
				'blur' => $this->args['blur'] ?? null,
				// Add other parameters as needed
			])
		);

		// Remove empty/null properties
		$args = array_filter($args, function($value) {
			return !is_null($value) && $value !== '';
		});

		// Sort our array
		ksort($args);

		return $args;
	}

	/**
	 * Normalize our argument values.
	 *
	 * @return void
	 */
	private function normalize_args(): void {
		$normalized = [];
		
		foreach ($this->args as $key => $value) {
			$canonical = self::get_canonical_arg($key);
			if ($canonical) {
				// Map the value if needed, but only if it's not null
				$normalized[$canonical] = $value !== null ? self::get_mapped_value($canonical, (string)$value) : null;
			}
		}

		$this->args = array_filter($normalized, function($value) {
			return $value !== null && $value !== '';
		});
	}

	/**
	 * If loading is set to eager, set fetchpriority to high
	 *
	 * @return void
	 */
	private function align_loading_and_fetchpriority(): void {
		if ( isset( $this->args['loading'] ) && ( $this->args['loading'] === 'eager' ) ) {
			$this->args['fetchpriority'] = 'high';
		}
	}

	/**
	 * Get the URL pattern used to identify transformed images
	 *
	 * @return string The URL pattern
	 */
	public static function get_url_pattern(): string {
		return '/';
	}

	/**
	 * Get default edge transformation arguments
	 *
	 * @return array The default arguments
	 */
	public function get_default_args(): array {
		return $this->default_edge_args;
	}

	/**
	 * Get all valid edge arguments
	 *
	 * @return array Array of all valid arguments and their aliases
	 */
	public static function get_valid_args(): array {
		return self::$valid_args;
	}

	/**
	 * Get canonical form of an argument
	 *
	 * @param string $arg The argument name to check.
	 * 
	 * @return string|null The canonical form or null if not valid
	 */
	public static function get_canonical_arg(string $arg): ?string {
		// If it's already a canonical form
		if (isset(self::$valid_args[$arg])) {
			return $arg;
		}

		// Search through aliases
		foreach (self::$valid_args as $canonical => $aliases) {
			if (is_array($aliases) && in_array($arg, $aliases)) {
				return $canonical;
			}
		}

		return null;
	}

	/**
	 * Get mapped value for a parameter
	 *
	 * @param string $param The parameter name.
	 * @param string $value The value to map.
	 * 
	 * @return string The mapped value or original if no mapping exists
	 */
	public static function get_mapped_value(string $param, string $value): string {
		if (isset(self::$value_mappings[$param][$value])) {
			return self::$value_mappings[$param][$value];
		}
		return $value;
	}
}
