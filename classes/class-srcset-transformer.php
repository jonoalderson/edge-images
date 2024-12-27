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
 * @license    GPL-3.0-or-later
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
    public static array $width_multipliers = [0.25, 0.5, 1, 1.5, 2, 2.5];

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
     * Default edge transformation arguments.
     *
     * Defines the base configuration for image transformations.
     * These settings ensure consistent image quality and behavior:
     * - fit: How the image should fit its dimensions
     * - dpr: Device pixel ratio (resolution scaling)
     * - f: Image format handling
     * - gravity: Focus point for image cropping
     * - q: JPEG quality level
     *
     * @since      4.0.0
     * @var array<string,mixed>
     */
    private static array $default_edge_args = [
        'fit' => 'cover',
        'dpr' => 1,
        'f' => 'auto',
        'gravity' => 'auto',
        'q' => 85
    ];

    /**
     * Transform a URL into a srcset string.
     *
     * Generates a set of image variants at different sizes and creates
     * a srcset string suitable for responsive images.
     * This method:
     * - Validates input parameters
     * - Handles SVG exclusions
     * - Calculates aspect ratios
     * - Generates appropriate widths
     * - Maintains original dimensions
     * - Creates edge-transformed URLs
     * - Builds srcset strings
     * - Optimizes for performance
     * - Preserves image quality
     * - Supports custom transformations
     *
     * The process involves:
     * 1. Input validation and SVG checking
     * 2. Original dimension extraction
     * 3. Width calculation based on multipliers
     * 4. URL transformation for each width
     * 5. Srcset string construction
     *
     * @since      4.0.0
     * 
     * @param string $src            The source URL to transform.
     * @param array  $dimensions     Array containing width and height of the image.
     * @param string $sizes          The sizes attribute value for responsive images.
     * @param array  $transform_args Optional. Additional transformation arguments to apply.
     * @return string The complete srcset string with multiple image variants.
     */
    public static function transform(
        string $src, 
        array $dimensions, 
        string $sizes,
        array $transform_args = []
    ): string {

        // Bail if SVG.
        if (Helpers::is_svg($src)) {
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
        $aspect_ratio = $original_height / $original_width;

        // Calculate srcset widths.
        $widths = [];
        
        // Always include 300px version if image is large enough.
        if ($original_width >= 150) {
            $widths[] = 300;
        }
        
        // Add standard responsive widths.
        foreach (self::$width_multipliers as $multiplier) {
            $width = round($original_width * $multiplier);
            
            if ($width >= self::$min_srcset_width && $width <= self::$max_srcset_width && !in_array($width, $widths)) {
                $widths[] = $width;
            }
        }

        // Add original width if not already included.
        if (!in_array($original_width, $widths)) {
            $widths[] = $original_width;
        }

        // Sort and remove duplicates.
        $widths = array_unique($widths);
        sort($widths);

        // If only one width, set dpr to 2.
        if (count($widths) === 1) {
            $transform_args['dpr'] = 2;
        }

        // Get the original URL from the upload path.
        $upload_dir = wp_get_upload_dir();
        $upload_path = str_replace(site_url('/'), '', $upload_dir['baseurl']);
        if (preg_match('#' . preg_quote($upload_path) . '/.*$#', $src, $matches)) {
            $original_src = site_url($matches[0]);
        } else {
            $original_src = $src;
        }

        // Generate srcset entries.
        $srcset_parts = [];
        foreach ($widths as $width) {
            // Calculate height maintaining aspect ratio
            $height = round($width * $aspect_ratio);
            
            // Build edge arguments with dimensions first, then allow transform_args to override other properties
            $edge_args = array_merge(
                self::$default_edge_args,
                $transform_args,  // Allow transform_args to set properties like fit, quality, etc.
                [
                    'w' => $width,
                    'h' => $height,  // Always use calculated height based on aspect ratio
                ]
            );
            
            // Generate transformed URL.
            $transformed_url = Helpers::edge_src($original_src, $edge_args);
            $srcset_parts[] = "{$transformed_url} {$width}w";
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
        $dprs = self::$width_multipliers;
        
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
