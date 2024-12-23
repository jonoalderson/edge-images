<?php
/**
 * Bunny CDN edge provider implementation.
 *
 * Handles image transformation through Bunny CDN's image optimization service.
 * Documentation: https://docs.bunny.net/docs/stream-image-processing
 *
 * @package    Edge_Images\Edge_Providers
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.1.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * Bunny CDN edge provider class.
 *
 * @since 4.1.0
 */
class Bunny extends Edge_Provider {

    /**
     * The root of the Bunny CDN edge URL.
     *
     * This path identifies Bunny CDN's image transformation endpoint.
     *
     * @since 4.1.0
     * @var string
     */
    public const EDGE_ROOT = '.b-cdn.net';

    /**
     * Get the subdomain from settings.
     *
     * @since 4.5.4
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
     * transformation parameters. Format:
     * https://your-site.b-cdn.net/width=200,height=200/path-to-image.jpg
     *
     * @since 4.1.0
     * 
     * @return string The transformed edge URL.
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
     *
     * @since 4.1.0
     * 
     * @return string The URL pattern.
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
     *
     * @since 4.5.4
     * 
     * @return string The transformation pattern.
     */
    public static function get_transform_pattern(): string {
        return '/(?:width|height|aspect_ratio|quality|format|gravity|blur|sharpen|brightness|contrast)=[-\d]+/';
    }

    /**
     * Convert standard transform args to Bunny CDN format.
     *
     * Maps our standardized parameters to Bunny CDN's specific format.
     * Reference: https://support.bunny.net/hc/en-us/articles/360027448392
     *
     * @since 4.1.0
     * 
     * @return array<string> Array of Bunny CDN parameters.
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
     * Reference: https://support.bunny.net/hc/en-us/articles/360027448392
     *
     * @since 4.1.0
     * 
     * @param string $fit The standard fit mode.
     * @return string The Bunny CDN mode.
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
     * Reference: https://support.bunny.net/hc/en-us/articles/360027448392
     *
     * @since 4.1.0
     * 
     * @param string $gravity The standard gravity option.
     * @return string The Bunny CDN gravity option.
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