<?php
/**
 * None provider implementation.
 *
 * A fallback provider that returns unmodified URLs when no edge provider is selected.
 * This provider:
 * - Returns original image URLs without transformation
 * - Acts as a safe fallback when no provider is configured
 * - Maintains original image paths and dimensions
 * - Provides null pattern matching for URL identification
 * - Ensures graceful degradation of image handling
 * - Supports system testing and debugging
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.5.4
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

class None extends Edge_Provider {

    /**
     * Get the edge URL for an image.
     *
     * Returns the original URL without any transformation.
     * This method:
     * - Combines the rewrite domain with the original path
     * - Maintains original image dimensions and format
     * - Preserves URL structure and parameters
     * - Ensures consistent URL handling
     *
     * @since      4.5.4
     * 
     * @return string The original URL without transformation.
     */
    public function get_edge_url(): string {
        return Helpers::get_rewrite_domain() . $this->path;
    }

    /**
     * Get the URL pattern used to identify transformed images.
     *
     * Since this provider doesn't transform images, returns an empty string.
     * This method:
     * - Returns an empty pattern for URL matching
     * - Ensures no false positives in URL identification
     * - Maintains consistent provider interface
     * - Supports URL transformation detection
     *
     * @since      4.5.4
     * 
     * @return string Empty string for pattern matching.
     */
    public static function get_url_pattern(): string {
        return '';
    }

    /**
     * Get the transformation pattern for the provider.
     *
     * Since this provider doesn't transform images, returns a pattern that won't match.
     * This method:
     * - Returns a regex pattern that never matches
     * - Ensures proper pattern matching behavior
     * - Maintains consistent provider interface
     * - Supports URL transformation detection
     *
     * @since      4.5.4
     * 
     * @return string A regex pattern that never matches.
     */
    public static function get_transform_pattern(): string {
        return '/(?!)$/';
    }
} 