<?php
/**
 * Srcset transformation functionality.
 *
 * Handles the generation of responsive image srcset values.
 * This class manages:
 * - Responsive image variant generation
 * - Srcset string construction
 * - Image dimension calculations
 * - Width multiplier management
 * - Edge provider integration
 * - Image size constraints
 * - Aspect ratio preservation
 * - URL transformation
 * - Performance optimization
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

namespace Edge_Images;

class Srcset_Transformer {

    /**
     * Width multipliers for srcset generation.
     *
     * These values determine the relative sizes of images in the srcset.
     * Each multiplier creates an image variant at that proportion of
     * the original width. For example:
     * - 0.25 creates an image at quarter width
     * - 0.5 creates an image at half width
     * - 1.0 maintains original width
     * - 2.0 doubles the width
     *
     * @since      4.0.0
     * @var array<float>
     */
    public static array $width_multipliers = [0.25, 0.5, 1, 1.5, 2];

    /**
     * Get the width multipliers.
     *
     * Retrieves the width multipliers array, allowing for filtering.
     * This method:
     * - Returns the default multipliers
     * - Allows for filtering via 'edge_images_width_multipliers'
     * - Ensures values are floats
     * - Maintains array structure
     *
     * @since      5.3.0
     * 
     * @return array<float> Array of width multipliers.
     */
    public static function get_width_multipliers(): array {

        $multipliers = apply_filters('edge_images_width_multipliers', self::$width_multipliers);

        // Ensure all values are floats
        return array_map('floatval', $multipliers);
    }

    /**
     * Maximum width for srcset values.
     *
     * Defines the upper limit for generated image widths.
     * This prevents creation of unnecessarily large images
     * which could impact performance and bandwidth usage.
     * The value is set to 2400 pixels as a reasonable maximum
     * for most modern displays and use cases.
     *
     * @since      4.0.0
     * @var int
     */
    public static int $max_srcset_width = 2400;

    /**
     * Minimum width for srcset values.
     *
     * Defines the lower limit for generated image widths.
     * This prevents creation of overly small images that
     * would not provide meaningful value. The value is
     * set to 300 pixels as a reasonable minimum for
     * modern web usage.
     *
     * @since      4.0.0
     * @var int
     */
    public static int $min_srcset_width = 300;


    /**
     * Transform a URL into a srcset string.
     *
     * @since 4.0.0
     * 
     * @param string $src            The source URL to transform.
     * @param array  $dimensions     Array containing width and height of the image.
     * @param array  $transform_args Optional. Additional transformation arguments to apply.
     * @return string The complete srcset string with multiple image variants.
     */
    public static function transform(
        string $src, 
        array $dimensions,
        array $transform_args = []
    ): string {
        // Bail if non-transformable format
        if (Helpers::is_non_transformable_format($src)) {
            return '';
        }

        // Bail if no dimensions.
        if (!isset($dimensions['width'], $dimensions['height'])) {
            return '';
        }

        // Bail if already transformed
        if (Helpers::is_transformed_url($src)) {
            return '';
        }

        // Get original dimensions.
        $original_width = (int) $dimensions['width'];
        $original_height = (int) $dimensions['height'];
        
        // Use provided aspect ratio if available, otherwise calculate it
        $aspect_ratio = isset($dimensions['aspect_ratio']) ? 
            (float) $dimensions['aspect_ratio'] : 
            $original_height / $original_width;

        // Calculate srcset widths.
        $widths = [];
        
        // Always include original width
        $widths[] = $original_width;
        
        // Add widths for multipliers greater than 1
        foreach (self::get_width_multipliers() as $multiplier) {
            if ($multiplier > 1) {
                $width = round($original_width * $multiplier);
                if ($width <= self::$max_srcset_width) {
                    $widths[] = $width;
                }
            }
        }

        // Sort and remove duplicates.
        $widths = array_unique($widths);
        sort($widths);

        // Get the original URL
        $original_src = Helpers::get_original_url($src);
        if (empty($original_src)) {
            return '';
        }

        // Clean the source URL once and get a provider instance
        $cleaned_path = Helpers::clean_url($original_src);
        if (empty($cleaned_path)) {
            return '';
        }

        // Get a provider instance
        $provider = Helpers::get_provider();
        if (!$provider) {
            return '';
        }

        // Generate srcset entries.
        $srcset_parts = [];
        foreach ($widths as $width) {
            
            // Calculate height maintaining aspect ratio
            $height = round($width * $aspect_ratio);
            
            // Add dimensions to transform args, preserving any explicitly set args
            $edge_args = array_merge(
                $provider->get_default_args(),
                $transform_args,
                [
                    'w' => $width,
                    'h' => $height,
                ]
            );
            
            // Set the path and args on the provider
            $provider->set_path($cleaned_path);
            $provider->set_args($edge_args);
            
            // Get transformed URL
            $transformed_url = $provider->get_edge_url();
            if (!empty($transformed_url)) {
                $srcset_parts[] = "{$transformed_url} {$width}w";
            }
        }

        return implode(', ', $srcset_parts);
    }

    /**
     * Fill gaps in srcset widths array.
     *
     * @since 4.5.0
     * 
     * @param array $widths Array of widths.
     * @param int   $max_gap Maximum allowed gap between widths.
     * @return array Modified array of widths.
     */
    public static function fill_srcset_gaps(array $widths, int $max_gap = 200): array {
        $filled = [];
        $count = count($widths);
        
        for ($i = 0; $i < $count - 1; $i++) {
            $filled[] = $widths[$i];
            $gap = $widths[$i + 1] - $widths[$i];
            
            // If gap is larger than max_gap, add intermediate values
            if ($gap > $max_gap) {
                $steps = ceil($gap / $max_gap);
                $step_size = $gap / $steps;
                
                for ($j = 1; $j < $steps; $j++) {
                    $intermediate = round($widths[$i] + ($j * $step_size));
                    $filled[] = $intermediate;
                }
            }
        }
        
        // Add the last width
        $filled[] = end($widths);
        
        return $filled;
    }

    /**
     * Get srcset widths and DPR variants based on sizes attribute.
     *
     * @since 4.5.0
     * 
     * @param string $sizes    The sizes attribute value.
     * @param int    $max_width The maximum width of the image.
     * @return array Array of widths for srcset.
     */
    public static function get_srcset_widths_from_sizes(string $sizes, int $max_width): array {
        
        // Get DPR multipliers from Srcset_Transformer
        $dprs = self::get_width_multipliers();
        
        // Generate variants based on the original width
        $variants = [];

        // Always include minimum width if the image is large enough
        if ($max_width >= self::$min_srcset_width * 2) {
            $variants[] = self::$min_srcset_width;
        }
        
        foreach ($dprs as $dpr) {
            $scaled_width = round($max_width * $dpr);
            
            // If scaled width would exceed max_srcset_width
            if ($scaled_width > self::$max_srcset_width) {
                // Add max_srcset_width if we don't already have it
                if (!in_array(self::$max_srcset_width, $variants)) {
                    $variants[] = self::$max_srcset_width;
                }
            } 
            // Otherwise add the scaled width if it meets our min/max criteria
            elseif ($scaled_width >= self::$min_srcset_width) {
                $variants[] = $scaled_width;
            }
        }

        // Sort and remove duplicates
        $variants = array_unique($variants);
        sort($variants);

        // Fill in any large gaps
        $variants = self::fill_srcset_gaps($variants);

        return $variants;
    }

    /**
     * Generate srcset string based on image dimensions and sizes.
     *
     * @since 4.5.0
     * 
     * @param string $src     Original image URL.
     * @param array  $dimensions Image dimensions.
     * @param string $sizes    The sizes attribute value.
     * @param array  $edge_args Default edge arguments.
     * @return string Generated srcset.
     */
    public static function generate_srcset(string $src, array $dimensions, string $sizes, array $edge_args): string {
        $max_width = (int) $dimensions['width'];
        $ratio = $dimensions['height'] / $dimensions['width'];
        
        $widths = self::get_srcset_widths_from_sizes($sizes, $max_width);
        
        $srcset_parts = [];
        foreach ($widths as $width) {
            $height = round($width * $ratio);
            $edge_args = array_merge(
                $edge_args,
                [
                    'width' => $width,
                    'height' => $height,
                ]
            );
            $edge_url = Helpers::edge_src($src, $edge_args);
            $srcset_parts[] = "$edge_url {$width}w";
        }
        
        return implode(', ', $srcset_parts);
    }
} 
