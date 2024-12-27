<?php
/**
 * Imgix provider functionality.
 *
 * Handles image transformation through Imgix.
 * This provider:
 * - Integrates with Imgix's image processing service
 * - Transforms image URLs to use Imgix's endpoint
 * - Supports dynamic image resizing and optimization
 * - Maps standard parameters to Imgix's format
 * - Provides extensive image manipulation options
 * - Ensures secure and efficient image delivery
 * - Handles subdomain configuration and validation
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.0.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Settings};

class Imgix extends Edge_Provider {

	/**
	 * The option name for the Imgix subdomain.
	 *
	 * WordPress option key for storing the Imgix subdomain.
	 * Used for configuration and settings management.
	 *
	 * @since      4.5.0
	 * @var        string
	 */
	public const SUBDOMAIN_OPTION = 'edge_images_imgix_subdomain';

	/**
	 * Get the Imgix subdomain.
	 *
	 * Retrieves the configured Imgix subdomain from settings.
	 * This method:
	 * - Fetches the subdomain setting
	 * - Returns empty string if not configured
	 * - Supports URL generation
	 * - Enables dynamic endpoint configuration
	 *
	 * @since      4.5.0
	 * 
	 * @return string The configured Imgix subdomain or empty string.
	 */
	public static function get_subdomain(): string {
		return (string) Settings::get_option(self::SUBDOMAIN_OPTION, '');
	}

	/**
	 * Register provider settings.
	 *
	 * Registers the Imgix-specific settings in WordPress.
	 * This method:
	 * - Registers the subdomain setting
	 * - Sets up sanitization
	 * - Provides default values
	 * - Adds setting description
	 *
	 * @since      4.5.0
	 * 
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			Settings::OPTION_GROUP,
			self::SUBDOMAIN_OPTION,
			[
				'type' => 'string',
				'description' => __('Your Imgix subdomain (e.g., your-site)', 'edge-images'),
				'sanitize_callback' => 'sanitize_key',
				'default' => '',
			]
		);
	}

	/**
	 * The Imgix domain pattern.
	 *
	 * This identifies Imgix's transformation endpoint.
	 * Used as a suffix for the configured subdomain.
	 * Format: your-subdomain.imgix.net
	 *
	 * @since      4.1.0
	 * @var        string
	 */
	public const EDGE_ROOT = '.imgix.net';

	/**
	 * Get the edge URL for an image.
	 *
	 * Transforms the image URL into an Imgix-compatible format with
	 * transformation parameters. This method:
	 * - Validates subdomain configuration
	 * - Maps standard parameters to Imgix format
	 * - Constructs the CDN URL
	 * - Handles parameter formatting
	 * - Ensures proper URL structure
	 *
	 * Format: https://your-subdomain.imgix.net/path-to-image.jpg?w=200&h=200&fit=crop
	 *
	 * @since      4.1.0
	 * 
	 * @return string The transformed edge URL with Imgix parameters.
	 */
	public function get_edge_url(): string {
		// Get the Imgix subdomain from settings
		$subdomain = self::get_subdomain();
		if (empty($subdomain)) {
			// Return original URL if no subdomain is configured
			return Helpers::get_rewrite_domain() . $this->path;
		}
		
		// Build the Imgix domain
		$imgix_domain = sprintf('https://%s%s', $subdomain, self::EDGE_ROOT);
		
		// Map our standard args to Imgix's parameters
		$transform_args = $this->get_imgix_transform_args();

		// Build the URL with query parameters
		return sprintf(
			'%s%s?%s',
			$imgix_domain,
			$this->path,
			http_build_query($transform_args)
		);
	}

	/**
	 * Get the URL pattern used to identify transformed images.
	 *
	 * Used to detect if an image has already been transformed by Imgix.
	 * This method:
	 * - Returns the Imgix-specific domain pattern
	 * - Enables detection of transformed images
	 * - Prevents duplicate transformations
	 * - Supports URL validation
	 *
	 * @since      4.1.0
	 * 
	 * @return string The Imgix URL pattern for transformed images.
	 */
	public static function get_url_pattern(): string {
		return self::EDGE_ROOT;
	}

	/**
	 * Convert standard transform args to Imgix format.
	 *
	 * Maps our standardized parameters to Imgix's specific format.
	 * This method:
	 * - Converts width and height parameters
	 * - Maps resize modes and fit options
	 * - Handles quality settings
	 * - Manages format conversion
	 * - Processes gravity/focus points
	 * Reference: https://docs.imgix.com/apis/rendering
	 *
	 * @since 4.1.0
	 * 
	 * @return array<string,mixed> Array of Imgix parameters.
	 */
	private function get_imgix_transform_args(): array {
		$args = $this->get_transform_args();
		$imgix_args = [];

		// Map width and height
		if (isset($args['w'])) {
			$imgix_args['w'] = $args['w'];
		}
		if (isset($args['h'])) {
			$imgix_args['h'] = $args['h'];
		}

		// Map fit/resize mode
		if (isset($args['fit'])) {
			$imgix_args['fit'] = $this->map_fit_mode($args['fit']);
		}

		// Map quality
		if (isset($args['q'])) {
			$imgix_args['q'] = $args['q'];
		}

		// Map format
		if (isset($args['f']) && $args['f'] !== 'auto') {
			$imgix_args['fm'] = $args['f'];
		} else {
			// Enable auto format and compression
			$imgix_args['auto'] = 'format,compress';
		}

		// Map gravity/focus point
		if (isset($args['g']) && $args['g'] !== 'auto') {
			$imgix_args['crop'] = $this->map_gravity($args['g']);
		}

		// Map blur
		if (isset($args['blur'])) {
			$imgix_args['blur'] = $args['blur'];
		}

		// Map sharpen
		if (isset($args['sharpen'])) {
			$imgix_args['sharp'] = $args['sharpen'];
		}

		// Add Imgix-specific optimizations
		$imgix_args['cs'] = 'srgb';        // Color space optimization
		$imgix_args['dpr'] = $args['dpr'] ?? 1;  // Device pixel ratio

		return $imgix_args;
	}

	/**
	 * Map standard fit modes to Imgix modes.
	 *
	 * Converts our standardized fit modes to Imgix's specific options.
	 * Reference: https://docs.imgix.com/apis/rendering/size/fit
	 *
	 * @since 4.1.0
	 * 
	 * @param string $fit The standard fit mode.
	 * @return string The Imgix fit mode.
	 */
	private function map_fit_mode(string $fit): string {
		$mode_map = [
			'cover'      => 'crop',
			'contain'    => 'fit',
			'scale-down' => 'max',
			'crop'       => 'crop',
			'pad'        => 'fill',
		];

		return $mode_map[$fit] ?? 'crop';
	}

	/**
	 * Map standard gravity values to Imgix crop modes.
	 *
	 * Converts our standardized gravity values to Imgix's crop focus points.
	 * Reference: https://docs.imgix.com/apis/rendering/size/crop
	 *
	 * @since 4.1.0
	 * 
	 * @param string $gravity The standard gravity value.
	 * @return string The Imgix crop focus point.
	 */
	private function map_gravity(string $gravity): string {
		$gravity_map = [
			'north'  => 'top',
			'south'  => 'bottom',
			'east'   => 'right',
			'west'   => 'left',
			'center' => 'center',
		];

		return $gravity_map[$gravity] ?? 'center';
	}

	/**
	 * Get the pattern to identify transformed URLs.
	 * 
	 * @since 4.5.0
	 * 
	 * @return string The pattern to match in transformed URLs.
	 */
	public static function get_transform_pattern(): string {
		// Get the configured subdomain
		$subdomain = self::get_subdomain();
		if (empty($subdomain)) {
			return '';
		}
		
		// Match the Imgix subdomain and any query parameters
		return $subdomain . '\.imgix\.net/[^?]+\?';
	}

	/**
	 * Check if this provider is properly configured.
	 *
	 * @since 4.1.0
	 * 
	 * @return bool Whether the provider is properly configured.
	 */
	public static function is_configured(): bool {
		$subdomain = self::get_subdomain();
		return !empty($subdomain);
	}
} 