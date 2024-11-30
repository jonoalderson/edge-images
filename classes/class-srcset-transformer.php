<?php
/**
 * Srcset transformation functionality.
 *
 * Handles the generation of responsive image srcset values by creating
 * multiple image variants at different sizes through edge providers.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      1.0.0
 */

namespace Edge_Images;

/**
 * Handles srcset transformations.
 *
 * @since 4.0.0
 */
class Srcset_Transformer {

    /**
     * Width multipliers for srcset generation.
     *
     * These values determine the relative sizes of images in the srcset.
     * For example, 0.5 creates an image at half the original width.
     *
     * @since 4.0.0
     * @var array<float>
     */
    public static array $width_multipliers = [0.25, 0.5, 1, 1.5, 2, 2.5];

    /**
     * Maximum width for srcset values.
     *
     * Prevents generation of unnecessarily large images.
     *
     * @since 4.0.0
     * @var int
     */
    public static int $max_srcset_width = 2400;

    /**
     * Minimum width for srcset values.
     *
     * Prevents generation of unnecessarily small images.
     *
     * @since 4.0.0
     * @var int
     */
    public static int $min_srcset_width = 300;

    /**
     * Default edge transformation arguments.
     *
     * @since 4.0.0
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
     *
     * @since 4.0.0
     * 
     * @param string $src           The source URL.
     * @param array  $dimensions    The image dimensions.
     * @param string $sizes         The sizes attribute value.
     * @param array  $transform_args Additional transformation arguments.
     * @return string The transformed srcset.
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
            
            // Constrain to max content width
            global $content_width;
            if ($content_width && $width > $content_width) {
                $width = $content_width;
            }
            
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
            $height = round($width * $aspect_ratio);
            
            // Build edge arguments.
            $edge_args = array_merge(
                self::$default_edge_args,
                $transform_args,
                [
                    'w' => $width,
                    'h' => $height,
                ]
            );
            
            // Generate transformed URL.
            $transformed_url = Helpers::edge_src($original_src, $edge_args);
            $srcset_parts[] = "{$transformed_url} {$width}w";
        }

        return implode(', ', $srcset_parts);
    }
} 