<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Handles srcset transformations.
 */
class Srcset_Transformer {
    /**
     * Width multipliers for srcset generation.
     *
     * @var array
     */
    public static array $width_multipliers = [0.25, 0.5, 1, 1.5, 2, 2.5];

    /**
     * Maximum width for srcset values.
     *
     * @var int
     */
    public static int $max_srcset_width = 2400;

    /**
     * Minimum width for srcset values.
     *
     * @var int
     */
    public static int $min_srcset_width = 300;

    /**
     * Transform a srcset string
     *
     * @param string $srcset           The srcset string.
     * @param array  $dimensions       The image dimensions.
     * @param bool   $should_constrain Whether to constrain to content width.
     * 
     * @return string The transformed srcset
     */
    public static function transform( string $srcset, array $dimensions, bool $should_constrain = true ): string {
        if ( ! isset( $dimensions['width'], $dimensions['height'] ) ) {
            return $srcset;
        }

        // Get the first URL from the srcset to use as our base
        if ( ! preg_match( '/^(.+?)\s+\d+w/', $srcset, $matches ) ) {
            return $srcset;
        }

        // Don't transform SVGs
        if ( Helpers::is_svg( $matches[1] ) ) {
            return $srcset;
        }

        // Get the full-size URL by removing any dimensions from the path
        $base_url = preg_replace( '/-\d+x\d+(?=\.[a-z]+$)/i', '', $matches[1] );

        // Get content width
        global $content_width;
        
        // Determine base width for calculations
        $original_width = (int) $dimensions['width'];
        $base_width = ($should_constrain && $content_width && $original_width > $content_width)
            ? $content_width 
            : $original_width;

        // Calculate aspect ratio
        $aspect_ratio = $dimensions['height'] / $dimensions['width'];

        // Get edge provider for transformations
        $provider = Helpers::get_edge_provider();

        $transformed = [];
        foreach ( self::$width_multipliers as $multiplier ) {
            $width = round( $base_width * $multiplier );
            
            // Skip if width would exceed original image dimensions
            if ( $width > $original_width ) {
                continue;
            }

            $height = round( $width * $aspect_ratio );

            // Create edge args for this size
            $edge_args = array_merge(
                $provider->get_default_args(),
                [
                    'width' => $width,
                    'height' => $height,
                ]
            );

            // Transform URL using full size image
            $transformed_url = Helpers::edge_src( $base_url, $edge_args );
            $transformed[] = sprintf( '%s %dw', $transformed_url, $width );
        }

        return implode( ', ', $transformed );
    }
} 