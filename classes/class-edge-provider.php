<?php
/**
 * Base edge provider class.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Abstract base class for edge providers.
 *
 * @since 4.0.0
 */
abstract class Edge_Provider {

	/**
	 * List of all valid edge transformation arguments and their aliases.
	 * Key is the canonical (short) form, value is array of aliases or null if no aliases.
	 *
	 * @since 4.0.0
	 * @var array<string,array|null>
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
	];

	/**
	 * Default edge transformation arguments.
	 *
	 * @since 4.0.0
	 * @var array<string,mixed>
	 */
	protected array $default_edge_args = [
		'fit' => 'cover',
		'dpr' => 1, 
		'f' => 'auto',
		'g' => 'auto',
		'q' => 85,
	];

	/**
	 * The image path
	 *
	 * @since 4.0.0
	 * @var string
	 */
	protected string $path;

	/**
	 * The args to set for images.
	 *
	 * @since 4.0.0
	 * @var array<string,mixed>
	 */
	protected array $args = [];

	/**
	 * Create a new edge provider instance.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $path The path to the image.
	 * @param array  $args The transformation arguments.
	 */
	final public function __construct(string $path, array $args = []) {
		$this->path = $path;
		$this->args = $this->validate_args($args);
		$this->normalize_args();
	}

	/**
	 * Get the URL pattern used to identify transformed images.
	 *
	 * @since 4.0.0
	 * 
	 * @return string The URL pattern.
	 */
	abstract public static function get_url_pattern(): string;

	/**
	 * Get the edge URL for the image.
	 *
	 * @since 4.0.0
	 * 
	 * @return string The transformed edge URL.
	 */
	abstract public function get_edge_url(): string;

	/**
	 * Get default edge transformation arguments.
	 *
	 * @since 4.0.0
	 * 
	 * @return array<string,mixed> The default arguments.
	 */
	final public function get_default_args(): array {
		return $this->default_edge_args;
	}

	/**
	 * Get all valid edge arguments.
	 *
	 * @since 4.0.0
	 * 
	 * @return array<string,array|null> Array of all valid arguments and their aliases.
	 */
	final public static function get_valid_args(): array {
		return self::$valid_args;
	}

	/**
	 * Validate transformation arguments.
	 *
	 * @since 4.0.0
	 * 
	 * @param array $args The arguments to validate.
	 * @return array Validated arguments.
	 */
	protected function validate_args(array $args): array {
		$validated = [];
		foreach ($args as $key => $value) {
			$canonical = self::get_canonical_arg($key);
			if ($canonical && $this->is_valid_value($canonical, $value)) {
				$validated[$canonical] = $value;
			}
		}
		return $validated;
	}

	/**
	 * Check if a value is valid for a given argument.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $arg   The argument name.
	 * @param mixed  $value The value to check.
	 * @return bool Whether the value is valid.
	 */
	protected function is_valid_value(string $arg, $value): bool {
		switch ($arg) {
			case 'w':
			case 'h':
				return is_numeric($value) && $value > 0;
			case 'dpr':
				return is_numeric($value) && $value >= 1 && $value <= 3;
			case 'q':
				return is_numeric($value) && $value >= 1 && $value <= 100;
			case 'fit':
				return in_array($value, ['scale-down', 'contain', 'cover', 'crop', 'pad'], true);
			case 'g':
				return in_array($value, ['auto', 'north', 'south', 'east', 'west', 'center'], true);
			default:
				return true;
		}
	}

	/**
	 * Normalize argument values.
	 *
	 * @since 4.0.0
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
	 * Get the transformation arguments.
	 *
	 * @since 4.0.0
	 * 
	 * @return array<string,mixed> The transformation arguments.
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
			])
		);

		// Remove empty/null properties
		$args = array_filter($args, function($value) {
			return $value !== null && $value !== '';
		});

		// Sort our array
		ksort($args);

		return $args;
	}

	/**
	 * Get canonical form of an argument.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $arg The argument name to check.
	 * @return string|null The canonical form or null if not valid.
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
	 * Get mapped value for a parameter.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $param The parameter name.
	 * @param string $value The value to map.
	 * @return string The mapped value or original if no mapping exists.
	 */
	public static function get_mapped_value(string $param, string $value): string {
		if (isset(self::$value_mappings[$param][$value])) {
			return self::$value_mappings[$param][$value];
		}
		return $value;
	}
}
