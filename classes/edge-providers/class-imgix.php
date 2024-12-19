<?php
/**
 * Imgix edge provider implementation.
 *
 * Handles image transformation through Imgix's image optimization service.
 * Documentation: https://docs.imgix.com/apis/rendering
 *
 * @package    Edge_Images\Edge_Providers
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.1.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * Imgix edge provider class.
 *
 * @since 4.1.0
 */
class Imgix extends Edge_Provider {

    /**
     * The Imgix domain pattern.
     *
     * This identifies Imgix's transformation endpoint.
     * Format: your-subdomain.imgix.net
     *
     * @since 4.1.0
     * @var string
     */
    public const EDGE_ROOT = '.imgix.net';

    /**
     * Get the edge URL for an image.
     *
     * Transforms the image URL into an Imgix-compatible format with
     * transformation parameters. Format:
     * https://your-subdomain.imgix.net/path-to-image.jpg?w=200&h=200&fit=crop
     *
     * @since 4.1.0
     * 
     * @return string The transformed edge URL.
     */
    public function get_edge_url(): string {
        // Get the Imgix subdomain from settings
        $subdomain = get_option('edge_images_imgix_subdomain', '');
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
     *
     * @since 4.1.0
     * 
     * @return string The URL pattern.
     */
    public static function get_url_pattern(): string {
        return self::EDGE_ROOT;
    }

    /**
     * Convert standard transform args to Imgix format.
     *
     * Maps our standardized parameters to Imgix's specific format.
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
        $subdomain = get_option('edge_images_imgix_subdomain', '');
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
        $subdomain = get_option('edge_images_imgix_subdomain', '');
        return !empty($subdomain);
    }
} 