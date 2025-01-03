<?php
/**
 * Image dimensions functionality.
 *
 * Provides methods for retrieving and calculating image dimensions from various sources.
 * This class handles:
 * - Dimension extraction from HTML attributes
 * - Dimension retrieval from attachment metadata
 * - Dimension calculation from content width constraints
 * - Aspect ratio calculations and reductions
 * - Size-specific dimension handling
 * - Dimension constraints and scaling
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

namespace Edge_Images;

class Image_Dimensions {

    /**
     * Get dimensions from HTML attributes.
     *
     * Extracts width and height values from an image tag's attributes
     * using the WordPress HTML Tag Processor. This method:
     * - Uses the processor's current position
     * - Returns both width and height if both are present
     * - Returns null if either dimension is missing
     * - Ensures dimensions are returned as strings
     * - Maintains original attribute values
     * - Does not modify the processor's state
     *
     * @since      4.0.0
     * 
     * @param \WP_HTML_Tag_Processor $processor The HTML processor instance.
     * @return array<string,string>|null Array with width and height, or null if not found.
     */
    public static function from_html( \WP_HTML_Tag_Processor $processor ): ?array {
        $width = $processor->get_attribute( 'width' );
        $height = $processor->get_attribute( 'height' );

        $numeric_width = is_numeric($width) ? (float)$width : 0;
        $numeric_height = is_numeric($height) ? (float)$height : 0;

        if ($numeric_width > 0 && $numeric_height > 0) {
            return [
                'width' => (string)$numeric_width,
                'height' => (string)$numeric_height,
            ];
        }

        return null;
    }

    /**
     * Get dimensions from attachment metadata.
     *
     * Retrieves image dimensions from WordPress attachment metadata for a given size.
     * This method:
     * - Handles both full-size and registered image sizes
     * - Returns dimensions as strings for consistency
     * - Returns null if metadata is missing or invalid
     * - Validates metadata structure before extraction
     * - Supports custom image sizes
     *
     * @since      4.0.0
     * 
     * @param int    $attachment_id The attachment ID to get dimensions for.
     * @param string $size          Optional. The image size to retrieve. Default 'full'.
     * @return array<string,string>|null Array with width and height, or null if not found.
     */
    public static function from_attachment( int $attachment_id, string $size = 'full' ): ?array {
        $metadata = wp_get_attachment_metadata( $attachment_id );

        if ( ! $metadata ) {
            return null;
        }

        if ( $size === 'full' ) {
            if ( ! isset( $metadata['width'], $metadata['height'] ) ) {
                return null;
            }
            return [
                'width' => (string) $metadata['width'],
                'height' => (string) $metadata['height'],
            ];
        }

        if ( ! isset( $metadata['sizes'][$size] ) ) {
            return null;
        }

        $size_data = $metadata['sizes'][$size];
        if ( ! isset( $size_data['width'], $size_data['height'] ) ) {
            return null;
        }

        return [
            'width' => (string) $size_data['width'],
            'height' => (string) $size_data['height'],
        ];
    }

    /**
     * Get attachment ID from image classes.
     *
     * Extracts the WordPress attachment ID from an image's class attributes.
     * This method:
     * - Uses the WordPress HTML Tag Processor
     * - Searches for wp-image-{ID} class pattern
     * - Returns null if no valid ID is found
     * - Validates the extracted ID
     * - Delegates to Helpers class for actual extraction
     *
     * @since      4.0.0
     * 
     * @param \WP_HTML_Tag_Processor $processor The HTML processor instance.
     * @return int|null Attachment ID if found, null otherwise.
     */
    public static function get_attachment_id( \WP_HTML_Tag_Processor $processor ): ?int {
        return Helpers::get_attachment_id_from_classes($processor);
    }

    /**
     * Get image dimensions, trying multiple sources.
     *
     * Attempts to retrieve image dimensions from various sources in order of preference.
     * This method follows a hierarchical approach:
     * 1. HTML attributes (most reliable for current context)
     * 2. Attachment metadata (if attachment ID provided)
     * 3. Attachment ID from classes (fallback)
     * 
     * The method will:
     * - Try each source in sequence until dimensions are found
     * - Return null only if all sources fail
     * - Validate dimensions at each step
     * - Handle both full-size and specific image sizes
     * - Return consistent string-based dimension values
     *
     * @since      4.0.0
     * 
     * @param \WP_HTML_Tag_Processor $processor     The HTML processor instance.
     * @param int|null               $attachment_id Optional. The attachment ID to check. Default null.
     * @param string|array          $size          Optional. The image size to retrieve. Default 'full'.
     * @return array<string,string>|null Array with width and height, or null if not found.
     */
    public static function get( \WP_HTML_Tag_Processor $processor, ?int $attachment_id = null, $size = 'full' ): ?array {
        // If we have a size array, use those dimensions directly
        if (is_array($size) && isset($size[0], $size[1])) {
            return [
                'width' => (string) $size[0],
                'height' => (string) $size[1]
            ];
        }

        // Try HTML first.
        $dimensions = self::from_html( $processor );
        if ( $dimensions ) {
            return $dimensions;
        }

        // Try attachment ID from parameter.
        if ( $attachment_id ) {
            $dimensions = self::from_attachment( $attachment_id, $size );
            if ( $dimensions ) {
                return $dimensions;
            }
        }

        // Try getting attachment ID from classes.
        $found_id = self::get_attachment_id( $processor );
        if ( $found_id ) {
            $dimensions = self::from_attachment( $found_id, $size );
            if ( $dimensions ) {
                return $dimensions;
            }
        }

        return null;
    }

    /**
     * Constrain dimensions to content width.
     *
     * Adjusts image dimensions to fit within the theme's content width
     * while maintaining aspect ratio. This method:
     * - Uses WordPress global $content_width
     * - Falls back to plugin's max width setting if content width not set
     * - Maintains original dimensions if smaller than content width
     * - Calculates proportional height when width is constrained
     * - Returns dimensions as strings for consistency
     *
     * @since      4.0.0
     * 
     * @param array<string,string> $dimensions The original width and height values.
     * @return array<string,string> The constrained dimensions, maintaining aspect ratio.
     */
    public static function constrain_to_content_width( array $dimensions ): array {
        global $content_width;
        
        // Get max width from content width or plugin setting
        $max_width = $content_width ?: Settings::get_max_width();
        
        if ( ! $max_width || (int) $dimensions['width'] <= $max_width ) {
            return $dimensions;
        }
        
        $ratio = (int) $dimensions['height'] / (int) $dimensions['width'];
        return [
            'width' => (string) $max_width,
            'height' => (string) round( $max_width * $ratio ),
        ];
    }

    /**
     * Reduce an aspect ratio to its lowest terms.
     *
     * Takes a width and height and returns them reduced to their lowest common denominator.
     * This method:
     * - Uses the Euclidean algorithm for GCD calculation
     * - Handles any positive integer dimensions
     * - Returns integer values for both dimensions
     * - Maintains the original aspect ratio exactly
     * - Example: 1920/1080 becomes 16/9
     *
     * @since      4.1.0
     * 
     * @param int $width  The original width value.
     * @param int $height The original height value.
     * @return array{width: int, height: int} The reduced ratio as integers.
     */
    public static function reduce_ratio( int $width, int $height ): array {
        // Find the greatest common divisor using Euclidean algorithm
        $gcd = function( int $a, int $b ) use ( &$gcd ): int {
            return $b ? $gcd( $b, $a % $b ) : $a;
        };
        
        $divisor = $gcd( $width, $height );
        
        return [
            'width'  => (int) ( $width / $divisor ),
            'height' => (int) ( $height / $divisor ),
        ];
    }

    /**
     * Get dimensions for a specific registered image size.
     *
     * Retrieves dimensions for a WordPress registered image size or size array.
     * This method:
     * - Handles both named sizes and dimension arrays
     * - Validates size input format
     * - Returns dimensions as strings for consistency
     * - Supports custom registered image sizes
     * - Falls back to attachment metadata if needed
     * - Returns null for invalid sizes or attachments
     *
     * @since      4.1.0
     * 
     * @param string|array $size          The size name or array of width/height values.
     * @param int         $attachment_id The attachment ID to get dimensions for.
     * @return array<string,string>|null Array with width and height, or null if not found.
     */
    public static function from_size( $size, int $attachment_id ): ?array {
        if ( is_array( $size ) && isset( $size[0], $size[1] ) ) {
            return [
                'width' => (string) $size[0],
                'height' => (string) $size[1],
            ];
        }

        $size_data = wp_get_attachment_image_src( $attachment_id, $size );
        if ( ! $size_data ) {
            return null;
        }

        return [
            'width' => (string) $size_data[1],
            'height' => (string) $size_data[2],
        ];
    }

    /**
     * Get full size dimensions for an attachment.
     *
     * Retrieves the original, full-size dimensions of an attachment.
     * This method:
     * - Reads directly from attachment metadata
     * - Returns dimensions as strings for consistency
     * - Validates metadata structure
     * - Returns null for invalid attachments
     * - Handles missing or corrupt metadata
     * - Does not calculate or estimate dimensions
     *
     * @since      4.1.0
     * 
     * @param int $attachment_id The attachment ID to get dimensions for.
     * @return array<string,string>|null Array with width and height, or null if not found.
     */
    public static function get_full_size( int $attachment_id ): ?array {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! $metadata || ! isset( $metadata['width'], $metadata['height'] ) ) {
            return null;
        }

        return [
            'width' => (string) $metadata['width'],
            'height' => (string) $metadata['height'],
        ];
    }

    /**
     * Constrain dimensions to maximum values while maintaining aspect ratio.
     *
     * Scales dimensions down to fit within maximum bounds if necessary.
     * This method:
     * - Maintains original aspect ratio
     * - Returns original dimensions if already within bounds
     * - Calculates the optimal scale factor
     * - Rounds dimensions to whole pixels
     * - Returns dimensions as strings for consistency
     * - Handles both width and height constraints
     *
     * @since      4.1.0
     * 
     * @param array<string,string> $dimensions     The original width and height values.
     * @param array<string,string> $max_dimensions The maximum allowed width and height.
     * @return array<string,string> The constrained dimensions, maintaining aspect ratio.
     */
    public static function constrain( array $dimensions, array $max_dimensions ): array {
        $width = (int) $dimensions['width'];
        $height = (int) $dimensions['height'];
        $max_width = (int) $max_dimensions['width'];
        $max_height = (int) $max_dimensions['height'];

        // If image is smaller than max dimensions, return original
        if ( $width <= $max_width && $height <= $max_height ) {
            return $dimensions;
        }

        // Calculate the ratio to scale down to the maximum dimensions
        $ratio = min( $max_width / $width, $max_height / $height );
        
        // Return the scaled dimensions
        return [
            'width' => (string) round( $width * $ratio ),
            'height' => (string) round( $height * $ratio ),
        ];
    }

} 