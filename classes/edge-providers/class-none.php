<?php
/**
 * None provider implementation.
 *
 * A fallback provider that returns unmodified URLs when no edge provider is selected.
 *
 * @package    Edge_Images\Edge_Providers
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      4.5.4
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * None provider class.
 *
 * @since 4.5.4
 */
class None extends Edge_Provider {

    /**
     * Get the edge URL for an image.
     *
     * Returns the original URL without any transformation.
     *
     * @since 4.5.4
     * 
     * @return string The original URL.
     */
    public function get_edge_url(): string {
        return Helpers::get_rewrite_domain() . $this->path;
    }

    /**
     * Get the URL pattern used to identify transformed images.
     *
     * Since this provider doesn't transform images, returns an empty string.
     *
     * @since 4.5.4
     * 
     * @return string Empty string.
     */
    public static function get_url_pattern(): string {
        return '';
    }

    /**
     * Get the transformation pattern for the provider.
     *
     * Since this provider doesn't transform images, returns a pattern that won't match.
     *
     * @since 4.5.4
     * 
     * @return string A pattern that won't match.
     */
    public static function get_transform_pattern(): string {
        return '/(?!)$/';
    }
} 