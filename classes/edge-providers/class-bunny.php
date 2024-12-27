<?php
/**
 * Bunny CDN edge provider implementation.
 *
 * Handles image transformation through Bunny CDN's image optimization service.
 * This provider:
 * - Integrates with Bunny CDN's image processing service
 * - Transforms image URLs to use Bunny CDN's endpoint
 * - Supports dynamic image resizing and optimization
 * - Maps standard parameters to Bunny CDN's format
 * - Provides extensive image manipulation options
 * - Ensures secure and efficient image delivery
 *
 * Documentation: https://docs.bunny.net/docs/stream-image-processing
 * 
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.1.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

class Bunny extends Edge_Provider {

    /**
     * The root of the Bunny CDN edge URL.
     *
     * This path identifies Bunny CDN's image transformation endpoint.
     * Used as a suffix for the configured subdomain.
     * Format: .b-cdn.net
     *
     * @since      4.1.0
     * @var        string
     */
    public const EDGE_ROOT = '.b-cdn.net';

    /**
     * Get the subdomain from settings.
     *
     * Retrieves the configured Bunny CDN subdomain from WordPress options.
     * This method:
     * - Fetches the subdomain setting
     * - Returns empty string if not configured
     * - Supports URL generation
     * - Enables dynamic endpoint configuration
     *
     * @since      4.5.4
     * 
     * @return string The configured subdomain or empty string.
     */
    private static function get_subdomain(): string {
        return get_option('edge_images_bunny_subdomain', '');
    }

    /**
     * Get the edge URL for an image.
     *
     * Transforms the image URL into a Bunny CDN-compatible format with
     * transformation parameters. This method:
     * - Validates subdomain configuration
     * - Maps standard parameters to Bunny format
     * - Constructs the CDN URL
     * - Handles parameter formatting
     * - Ensures proper URL structure
     *
     * Format: https://your-site.b-cdn.net/width=200,height=200/path-to-image.jpg
     *
     * @since      4.1.0
     * 
     * @return string The transformed edge URL with Bunny CDN parameters.
     */
    public function get_edge_url(): string {
        // Get the Bunny CDN subdomain from settings
        $subdomain = self::get_subdomain();
        if (empty($subdomain)) {
            // Return original URL if no subdomain is configured
            return Helpers::get_rewrite_domain() . $this->path;
        }
        
        // Map our standard args to Bunny CDN's parameters
        $transform_args = $this->get_bunny_transform_args();

        // Build the URL with comma-separated parameters
        $edge_url = sprintf('https://%s%s/%s%s', 
            $subdomain,
            self::EDGE_ROOT,
            implode(',', $transform_args),
            $this->path
        );
        
        return $edge_url;
    }

    /**
     * Get the URL pattern used to identify transformed images.
     *
     * Used to detect if an image has already been transformed by Bunny CDN.
     * This method:
     * - Checks subdomain configuration
     * - Generates the pattern dynamically
     * - Returns empty if not configured
     * - Supports URL validation
     *
     * @since      4.1.0
     * 
     * @return string The Bunny CDN URL pattern for transformed images.
     */
    public static function get_url_pattern(): string {
        $subdomain = self::get_subdomain();
        if (empty($subdomain)) {
            return '';
        }
        return sprintf('https://%s%s/', $subdomain, self::EDGE_ROOT);
    }

    /**
     * Get the transformation pattern for the provider.
     *
     * Used to detect transformation parameters in the URL.
     * This method:
     * - Provides regex for parameter detection
     * - Matches all supported parameters
     * - Validates parameter formats
     * - Supports URL parsing
     *
     * @since      4.5.4
     * 
     * @return string The regex pattern to match Bunny CDN parameters.
     */
    public static function get_transform_pattern(): string {
        return '/(?:width|height|aspect_ratio|quality|format|gravity|blur|sharpen|brightness|contrast)=[-\d]+/';
    }

    /**
     * Convert standard transform args to Bunny CDN format.
     *
     * Maps our standardized parameters to Bunny CDN's specific format.
     * This method:
     * - Converts width and height parameters
     * - Maps resize modes and fit options
     * - Handles quality settings
     * - Manages format conversion
     * - Processes gravity/focus points
     * - Applies image adjustments
     *
     * Reference: https://support.bunny.net/hc/en-us/articles/360027448392
     *
     * @since      4.1.0
     * 
     * @return array<string> Array of formatted Bunny CDN parameters.
     */
    private function get_bunny_transform_args(): array {
        $args = $this->get_transform_args();
        $bunny_args = [];

        // Map width and height
        if (isset($args['w'])) {
            $bunny_args[] = "width={$args['w']}";
        }
        if (isset($args['h'])) {
            $bunny_args[] = "height={$args['h']}";
        }

        // Map fit/resize mode
        if (isset($args['fit'])) {
            $bunny_args[] = 'aspect_ratio=' . $this->map_fit_mode($args['fit']);
        }

        // Map quality (Bunny accepts 0-100)
        if (isset($args['q'])) {
            $bunny_args[] = "quality={$args['q']}";
        }

        // Map format
        if (isset($args['f'])) {
            if ($args['f'] === 'auto') {
                // Let Bunny choose best format
                $bunny_args[] = 'format=auto';
            } else {
                $bunny_args[] = "format={$args['f']}";
            }
        }

        // Map gravity/focus point
        if (isset($args['g'])) {
            $bunny_args[] = 'gravity=' . $this->map_gravity($args['g']);
        }

        // Map blur (Bunny accepts 0-100)
        if (isset($args['blur'])) {
            $value = min(100, max(0, intval($args['blur'])));
            $bunny_args[] = "blur={$value}";
        }

        // Map sharpen (Bunny accepts 0-100)
        if (isset($args['sharpen'])) {
            $value = min(100, max(0, intval($args['sharpen'])));
            $bunny_args[] = "sharpen={$value}";
        }

        // Map brightness (Bunny accepts -100 to 100)
        if (isset($args['brightness'])) {
            $value = min(100, max(-100, intval($args['brightness'])));
            $bunny_args[] = "brightness={$value}";
        }

        // Map contrast (Bunny accepts -100 to 100)
        if (isset($args['contrast'])) {
            $value = min(100, max(-100, intval($args['contrast'])));
            $bunny_args[] = "contrast={$value}";
        }

        return $bunny_args;
    }

    /**
     * Map standard fit modes to Bunny CDN modes.
     *
     * Converts our standardized fit modes to Bunny CDN's specific options.
     * This method:
     * - Maps common resize modes
     * - Provides fallback options
     * - Ensures consistent behavior
     * - Maintains compatibility
     *
     * Reference: https://support.bunny.net/hc/en-us/articles/360027448392
     *
     * @since      4.1.0
     * 
     * @param  string $fit The standard fit mode to convert.
     * @return string      The corresponding Bunny CDN mode.
     */
    private function map_fit_mode(string $fit): string {
        $mode_map = [
            'cover'      => 'force',    // Force resize and crop to exact dimensions
            'contain'    => 'contain',   // Resize to fit within dimensions
            'scale-down' => 'contain',   // Same as contain for Bunny
            'crop'       => 'force',     // Force exact dimensions
            'pad'        => 'stretch',   // Stretch to fill dimensions
        ];

        return $mode_map[$fit] ?? 'force';
    }

    /**
     * Map standard gravity options to Bunny CDN options.
     *
     * Converts our standardized gravity options to Bunny CDN's specific options.
     * This method:
     * - Maps directional values
     * - Provides fallback options
     * - Ensures consistent behavior
     * - Maintains compatibility
     *
     * Reference: https://support.bunny.net/hc/en-us/articles/360027448392
     *
     * @since      4.1.0
     * 
     * @param  string $gravity The standard gravity option to convert.
     * @return string         The corresponding Bunny CDN gravity option.
     */
    private function map_gravity(string $gravity): string {
        $gravity_map = [
            'auto'   => 'center',  // Bunny doesn't have auto, default to center
            'center' => 'center',
            'north'  => 'top',
            'south'  => 'bottom',
            'east'   => 'right',
            'west'   => 'left',
            'left'   => 'left',
            'right'  => 'right',
        ];

        return $gravity_map[$gravity] ?? 'center';
    }
} 