<?php
/**
 * Imgproxy provider functionality.
 *
 * Handles image transformation through a self-hosted imgproxy setup.
 * This provider:
 * - Integrates with imgproxy's image processing service
 * - Transforms image URLs to use imgproxy's endpoint
 * - Supports dynamic image resizing and optimization
 * - Maps standard parameters to imgproxy's format
 * - Provides extensive image manipulation options
 * - Ensures secure and efficient image delivery
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      5.4.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Settings};

class Imgproxy extends Edge_Provider {

    /**
     * The option name for the imgproxy URL.
     *
     * WordPress option key for storing the imgproxy URL.
     * Used for configuration and settings management.
     *
     * @since      5.4.0
     * @var        string
     */
    public const URL_OPTION = 'edge_images_imgproxy_url';

    /**
     * Get the imgproxy URL.
     *
     * Retrieves the configured imgproxy URL from settings.
     * This method:
     * - Fetches the URL setting
     * - Returns empty string if not configured
     * - Supports URL generation
     * - Enables dynamic endpoint configuration
     *
     * @since      5.4.0
     * 
     * @return string The configured imgproxy URL or empty string.
     */
    public static function get_url(): string {
        return (string) Settings::get_option(self::URL_OPTION, '');
    }

    /**
     * Register provider settings.
     *
     * Registers the imgproxy-specific settings in WordPress.
     * This method:
     * - Registers the URL setting
     * - Sets up sanitization
     * - Provides default values
     * - Adds setting description
     *
     * @since      5.4.0
     * 
     * @return void
     */
    public static function register_settings(): void {
        register_setting(
            Settings::OPTION_GROUP,
            self::URL_OPTION,
            [
                'type' => 'string',
                'description' => __('Your imgproxy URL (e.g., https://imgproxy.example.com)', 'edge-images'),
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            ]
        );
    }

    /**
     * Get the edge URL for an image.
     *
     * Transforms the image URL into an imgproxy-compatible format with
     * transformation parameters. This method:
     * - Validates URL configuration
     * - Maps standard parameters to imgproxy format
     * - Constructs the CDN URL
     * - Handles parameter formatting
     * - Ensures proper URL structure
     *
     * Format: https://imgproxy.example.com/insecure/fit/200/200/no/1/plain/path-to-image.jpg
     *
     * @since      5.4.0
     * 
     * @return string The transformed edge URL with imgproxy parameters.
     */
    public function get_edge_url(): string {
        // Get the imgproxy URL from settings
        $imgproxy_url = self::get_url();
        if (empty($imgproxy_url)) {
            // Return original URL if no URL is configured
            return Helpers::get_rewrite_domain() . $this->path;
        }
        
        // Map our standard args to imgproxy's parameters
        $transform_args = $this->get_imgproxy_transform_args();

        // Build the URL with path and parameters
        return sprintf(
            '%s/insecure/%s/plain%s',
            rtrim($imgproxy_url, '/'),
            implode('/', $transform_args),
            $this->path
        );
    }

    /**
     * Get the URL pattern used to identify transformed images.
     *
     * Used to detect if an image has already been transformed by imgproxy.
     * This method:
     * - Returns the imgproxy-specific URL pattern
     * - Enables detection of transformed images
     * - Prevents duplicate transformations
     * - Supports URL validation
     *
     * @since      5.4.0
     * 
     * @return string The imgproxy URL pattern for transformed images.
     */
    public static function get_url_pattern(): string {
        return '/insecure/';
    }

    /**
     * Get the pattern to identify transformed URLs.
     * 
     * Returns a regex pattern that matches imgproxy's URL structure.
     * This method:
     * - Provides regex for URL matching
     * - Captures transformation parameters
     * - Supports URL validation
     * - Ensures proper pattern detection
     * 
     * @since      5.4.0
     * 
     * @return string The regex pattern to match imgproxy-transformed URLs.
     */
    public static function get_transform_pattern(): string {
        return '/insecure/[^/]+/';
    }

    /**
     * Convert standard transform args to imgproxy format.
     *
     * Maps our standardized parameters to imgproxy's specific format.
     * This method:
     * - Converts width and height parameters
     * - Maps resize modes and fit options
     * - Handles quality settings
     * - Manages format conversion
     * - Processes gravity/focus points
     * Reference: https://docs.imgproxy.net/#/url?id=generating-the-url
     *
     * @since 5.4.0
     * 
     * @return array<string> Array of imgproxy parameters.
     */
    private function get_imgproxy_transform_args(): array {
        $args = $this->get_transform_args();
        $imgproxy_args = [];

        // Map width and height
        if (isset($args['w'])) {
            $imgproxy_args[] = "width:{$args['w']}";
        }
        if (isset($args['h'])) {
            $imgproxy_args[] = "height:{$args['h']}";
        }

        // Map fit/resize mode
        if (isset($args['fit'])) {
            $imgproxy_args[] = 'fit:' . $this->map_fit_mode($args['fit']);
        }

        // Map quality
        if (isset($args['q'])) {
            $imgproxy_args[] = "quality:{$args['q']}";
        }

        // Map format
        if (isset($args['f']) && $args['f'] !== 'auto') {
            $imgproxy_args[] = "format:{$args['f']}";
        }

        // Map gravity/focus point
        if (isset($args['g']) && $args['g'] !== 'auto') {
            $imgproxy_args[] = 'gravity:' . $this->map_gravity($args['g']);
        }

        // Map blur
        if (isset($args['blur'])) {
            $imgproxy_args[] = "blur:{$args['blur']}";
        }

        // Map sharpen
        if (isset($args['sharpen'])) {
            $imgproxy_args[] = "sharpen:{$args['sharpen']}";
        }

        return $imgproxy_args;
    }

    /**
     * Map standard fit modes to imgproxy modes.
     *
     * Converts our standardized fit modes to imgproxy's specific options.
     * Reference: https://docs.imgproxy.net/#/url?id=fit
     *
     * @since 5.4.0
     * 
     * @param string $fit The standard fit mode.
     * @return string The imgproxy fit mode.
     */
    private function map_fit_mode(string $fit): string {
        $mode_map = [
            'cover'      => 'cover',
            'contain'    => 'contain',
            'scale-down' => 'scale-down',
            'crop'       => 'crop',
            'pad'        => 'pad',
        ];

        return $mode_map[$fit] ?? 'cover';
    }

    /**
     * Map standard gravity values to imgproxy gravity options.
     *
     * Converts our standardized gravity values to imgproxy's gravity options.
     * Reference: https://docs.imgproxy.net/#/url?id=gravity
     *
     * @since 5.4.0
     * 
     * @param string $gravity The standard gravity value.
     * @return string The imgproxy gravity option.
     */
    private function map_gravity(string $gravity): string {
        $gravity_map = [
            'north'  => 'north',
            'south'  => 'south',
            'east'   => 'east',
            'west'   => 'west',
            'center' => 'center',
        ];

        return $gravity_map[$gravity] ?? 'center';
    }

    /**
     * Check if this provider is properly configured.
     *
     * @since 5.4.0
     * 
     * @return bool Whether the provider is properly configured.
     */
    public static function is_configured(): bool {
        $url = self::get_url();
        return !empty($url);
    }
}
