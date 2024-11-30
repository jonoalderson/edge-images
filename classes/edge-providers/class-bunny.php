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
    public const EDGE_ROOT = '/bunny-cdn/';

    /**
     * Get the edge URL for an image.
     *
     * Transforms the image URL into a Bunny CDN-compatible format with
     * transformation parameters. Format:
     * /bunny-cdn/width=200,height=200/path-to-image.jpg
     *
     * @since 4.1.0
     * 
     * @return string The transformed edge URL.
     */
    public function get_edge_url(): string {
        $edge_prefix = Helpers::get_rewrite_domain() . self::EDGE_ROOT;
        
        // Map our standard args to Bunny CDN's parameters
        $transform_args = $this->get_bunny_transform_args();

        // Build the URL with comma-separated parameters
        $edge_url = $edge_prefix . implode(',', $transform_args) . $this->path;
        
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
        return self::EDGE_ROOT;
    }

    /**
     * Convert standard transform args to Bunny CDN format.
     *
     * Maps our standardized parameters to Bunny CDN's specific format.
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
            $bunny_args[] = 'mode=' . $this->map_fit_mode($args['fit']);
        }

        // Map quality
        if (isset($args['q'])) {
            $bunny_args[] = "quality={$args['q']}";
        }

        // Map format
        if (isset($args['f']) && $args['f'] !== 'auto') {
            $bunny_args[] = "format={$args['f']}";
        }

        // Map gravity/focus point
        if (isset($args['g']) && $args['g'] !== 'auto') {
            $bunny_args[] = "gravity={$args['g']}";
        }

        // Map blur
        if (isset($args['blur'])) {
            $bunny_args[] = "blur={$args['blur']}";
        }

        // Map sharpen
        if (isset($args['sharpen'])) {
            $bunny_args[] = "sharpen={$args['sharpen']}";
        }

        return $bunny_args;
    }

    /**
     * Map standard fit modes to Bunny CDN modes.
     *
     * Converts our standardized fit modes to Bunny CDN's specific options.
     *
     * @since 4.1.0
     * 
     * @param string $fit The standard fit mode.
     * @return string The Bunny CDN mode.
     */
    private function map_fit_mode(string $fit): string {
        $mode_map = [
            'cover'      => 'cover',
            'contain'    => 'contain',
            'scale-down' => 'max',
            'crop'       => 'crop',
            'pad'        => 'stretch',
        ];

        return $mode_map[$fit] ?? 'cover';
    }
} 