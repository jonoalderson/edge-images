<?php
/**
 * Edge provider base functionality.
 *
 * Provides the foundation for all edge provider implementations.
 * This abstract class defines:
 * - Core transformation parameters and their aliases
 * - URL generation and manipulation
 * - Argument validation and normalization
 * - Provider configuration management
 * - Common utility methods for all providers
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.0.0
 */

namespace Edge_Images;

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
		'g' => ['gravity'],            // Gravity/crop position: auto, north, south, east, west, center, left, right
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
		'sharpen' => null,           // Sharpen amount (1-10)
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
		'q' => 85,
	];

	/**
	 * Get the default quality value.
	 *
	 * @since 5.2.14
	 * 
	 * @return int The default quality value.
	 */
	protected function get_default_quality(): int {
		/**
		 * Filters the default image quality value.
		 *
		 * @since 5.2.14
		 * 
		 * @param int $quality The default quality value (1-100). Default 85.
		 */
		$quality = apply_filters('edge_images_default_quality', 85);

		// Ensure the quality is within valid bounds
		return min(100, max(1, (int) $quality));
	}

	/**
	 * The image path
	 *
	 * @since 4.0.0
	 * @var string
	 */
	protected string $path = '';

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
	 */
	public function __construct() {
		$this->init();
	}

	private function init(): void {
		// Set the default quality value
		$this->default_edge_args['q'] = $this->get_default_quality();
	}

	/**
	 * Set the image path for transformation.
	 *
	 * @since 4.5.0
	 * 
	 * @param string $path The path to the image.
	 * @return self
	 */
	final public function set_path(string $path): self {
		$this->path = Helpers::clean_url($path);
		return $this;
	}

	/**
	 * Set the transformation arguments.
	 *
	 * @since 4.5.0
	 * 
	 * @param array $args The transformation arguments.
	 * @return self
	 */
	final public function set_args(array $args): self {
		$this->args = $this->validate_args($args);
		$this->normalize_args();
		return $this;
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
	 * Get all valid transformation arguments.
	 *
	 * @since 4.5.0
	 * 
	 * @return array<string,array|null> Array of valid arguments and their aliases.
	 */
	public static function get_valid_args(): array {
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
			$key = strtolower($key);
			
			// Direct match with canonical form
			if (array_key_exists($key, self::$valid_args)) {
				if ($this->is_valid_value($key, $value)) {
					$validated[$key] = $value;
				}
				continue;
			}
			
			// Check if it's an alias
			foreach (self::$valid_args as $canonical => $aliases) {
				if ($aliases && in_array($key, array_map('strtolower', $aliases), true)) {
					if ($this->is_valid_value($canonical, $value)) {
						$validated[$canonical] = $value;
					}
					break;
				}
			}
		}
		
		return $validated;
	}

	/**
	 * Check if a value is valid for a given argument.
	 *
	 * Validates transformation argument values based on their type
	 * and expected format. This ensures that only safe and valid
	 * values are used in image transformations.
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
				// Width and height must be positive integers between 1 and 5000
				return is_numeric($value) && $value > 0 && $value <= 5000;
			
			case 'dpr':
				// Device pixel ratio must be between 1 and 3
				return is_numeric($value) && $value >= 1 && $value <= 3;
			
			case 'q':
				// Quality must be between 1 and 100
				return is_numeric($value) && $value >= 1 && $value <= 100;
			
			case 'fit':
				// Fit must be one of the predefined values
				return in_array($value, ['scale-down', 'contain', 'cover', 'crop', 'pad'], true);
			
			case 'g':
				// Gravity must be one of the predefined positions
				return in_array($value, ['auto', 'north', 'south', 'east', 'west', 'center', 'left', 'right', 'top', 'bottom'], true);
			
			case 'bg':
				// Background must be a valid hex color, rgb(a) value, or named color
				return is_string($value) && (
					preg_match('/^#[0-9a-f]{3,8}$/i', $value) ||                     // Hex color
					preg_match('/^rgb\(\d{1,3},\s*\d{1,3},\s*\d{1,3}\)$/', $value) || // RGB
					preg_match('/^rgba\(\d{1,3},\s*\d{1,3},\s*\d{1,3},\s*[0-1](\.\d+)?\)$/', $value) || // RGBA
					in_array($value, ['transparent', 'black', 'white'], true)         // Named colors
				);
			
			case 'blur':
				// Blur must be between 1 and 250
				return is_numeric($value) && $value >= 1 && $value <= 10;
			
			case 'brightness':
			case 'contrast':
				// Brightness and contrast must be between -100 and 100
				return is_numeric($value) && $value >= -100 && $value <= 100;
			
			case 'gamma':
				// Gamma must be between 1 and 100
				return is_numeric($value) && $value >= 1 && $value <= 100;
			
			case 'sharpen':
				// Sharpen must be between 1 and 10
				return is_numeric($value) && $value >= 1 && $value <= 10;
			
			case 'trim':
				// Trim must be between 1 and 100
				return is_numeric($value) && $value >= 1 && $value <= 100;
			
			case 'rot':
				// Rotation must be a multiple of 90
				return is_numeric($value) && $value % 90 === 0 && abs($value) <= 360;
			
			case 'flip':
				// Flip must be h, v, or hv
				return in_array($value, ['h', 'v', 'hv'], true);
			
			case 'f':
				// Format must be one of the supported types
				return in_array($value, ['auto', 'webp', 'json', 'jpeg', 'png', 'gif', 'avif'], true);
			
			case 'metadata':
				// Metadata must be one of the predefined options
				return in_array($value, ['keep', 'copyright', 'none'], true);
			
			case 'onerror':
				// Error handling must be redirect or 404
				return in_array($value, ['redirect', '404'], true);
			
			default:
				return false;
		}
	}

	/**
	 * Normalize transformation arguments.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	private function normalize_args(): void {
		// Merge provided args with defaults, ensuring provided args take precedence
		$this->args = array_replace($this->default_edge_args, $this->args);

		// Convert numeric values
		foreach ($this->args as $key => $value) {
			if (is_numeric($value)) {
				// Only convert to int for width and height
				if (in_array($key, ['w', 'h', 'width', 'height'], true)) {
					$this->args[$key] = (int) $value;
				}
			}
		}
	}

	/**
	 * Get the transformation arguments.
	 *
	 * @since 4.0.0
	 * 
	 * @return array The transformation arguments.
	 */
	protected function get_transform_args(): array {
		// Sort args alphabetically by key
		$args = $this->args;
		ksort($args);
		return $args;
	}

	/**
	 * Get the canonical form of an argument.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $arg The argument to get the canonical form for.
	 * @return string|null The canonical form, or null if not found.
	 */
	public static function get_canonical_arg(string $arg): ?string {
		$arg = strtolower($arg);

		// If it's already a canonical arg, return it
		if (isset(self::$valid_args[$arg])) {
			return $arg;
		}

		// Search through aliases
		foreach (self::$valid_args as $canonical => $aliases) {
			if ($aliases && in_array($arg, array_map('strtolower', $aliases), true)) {
				return $canonical;
			}
		}

		return null;
	}

	/**
	 * Map a value to its provider-specific form.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $param The parameter name.
	 * @param string $value The value to map.
	 * @return string The mapped value.
	 */
	public static function get_mapped_value(string $param, string $value): string {
		// By default, return the value unchanged
		return $value;
	}

	/**
	 * Get the URL pattern used to transform images.
	 *
	 * @since 4.0.0
	 * 
	 * @return string The transformation pattern.
	 */
	abstract public static function get_transform_pattern(): string;

	/**
	 * Clean a transformed URL back to its original form.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $url The URL to clean.
	 * @return string The cleaned URL.
	 */
	public static function clean_transformed_url(string $url): string {
		$pattern = static::get_transform_pattern();
		// Escape forward slashes and ensure proper delimiters
		$pattern = '#' . str_replace('#', '\\#', $pattern) . '#';
		$cleaned_url = preg_replace($pattern, '', $url);
		return $cleaned_url === null ? $url : $cleaned_url;
	}

	/**
	 * Check if the provider is properly configured.
	 *
	 * @since 4.0.0
	 * 
	 * @return bool Whether the provider is configured.
	 */
	public static function is_configured(): bool {
		return true;
	}

	/**
	 * Check if this provider uses a hosted subdomain.
	 *
	 * Determines if this provider requires a subdomain for image transformation.
	 * By default, providers do not use hosted subdomains. Child classes that do
	 * (like Imgix and Bunny) should override this method.
	 *
	 * @since 5.4.0
	 * 
	 * @return bool Whether this provider uses a hosted subdomain.
	 */
	public static function uses_hosted_subdomain(): bool {
		return false;
	}
}
