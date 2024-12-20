<?php
/**
 * Image dimensions functionality.
 *
 * Provides methods for retrieving and calculating image dimensions from various sources.
 * Handles dimension extraction from HTML attributes, attachment metadata, and content width constraints.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      1.0.0
 */

namespace Edge_Images;

/**
 * Handles image dimension calculations and retrieval.
 *
 * @since 4.0.0
 */
class Image_Dimensions {

    /**
     * Get dimensions from HTML attributes.
     *
     * Extracts width and height values from an image tag's attributes
     * using the WordPress HTML Tag Processor.
     *
     * @since 4.0.0
     * 
     * @param \WP_HTML_Tag_Processor $processor The HTML processor.
     * @return array<string,string>|null Array with width and height, or null if not found.
     */
    public static function from_html( \WP_HTML_Tag_Processor $processor ): ?array {
        if ( ! $processor->next_tag( 'img' ) ) {
            return null;
        }

        $width = $processor->get_attribute( 'width' );
        $height = $processor->get_attribute( 'height' );

        if ( ! $width || ! $height ) {
            return null;
        }

        return [
            'width' => (string) $width,
            'height' => (string) $height,
        ];
    }

    /**
     * Get dimensions from attachment metadata.
     *
     * Retrieves image dimensions from WordPress attachment metadata for a given size.
     *
     * @since 4.0.0
     * 
     * @param int    $attachment_id The attachment ID.
     * @param string $size          Optional size name.
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
     * @since 4.0.0
     * 
     * @param \WP_HTML_Tag_Processor $processor The HTML processor.
     * @return int|null Attachment ID or null if not found.
     */
    public static function get_attachment_id( \WP_HTML_Tag_Processor $processor ): ?int {
        return Helpers::get_attachment_id_from_classes($processor);
    }

    /**
     * Get image dimensions, trying multiple sources.
     *
     * Attempts to retrieve image dimensions from various sources in order of preference:
     * 1. HTML attributes
     * 2. Attachment metadata
     * 3. Attachment ID from classes
     *
     * @since 4.0.0
     * 
     * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
     * @param int|null              $attachment_id The attachment ID.
     * @param string               $size          Optional size name.
     * @return array<string,string>|null Array with width and height, or null if not found.
     */
    public static function get( \WP_HTML_Tag_Processor $processor, ?int $attachment_id = null, string $size = 'full' ): ?array {
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
     * while maintaining aspect ratio.
     *
     * @since 4.0.0
     * 
     * @param array<string,string> $dimensions The original dimensions.
     * @return array<string,string> The constrained dimensions.
     */
    public static function constrain_to_content_width( array $dimensions ): array {
        global $content_width;
        
        if ( ! $content_width || (int) $dimensions['width'] <= $content_width ) {
            return $dimensions;
        }

        $ratio = (int) $dimensions['height'] / (int) $dimensions['width'];
        return [
            'width' => (string) $content_width,
            'height' => (string) round( $content_width * $ratio ),
        ];
    }

    /**
     * Reduce an aspect ratio to its lowest terms.
     *
     * Takes a width and height and returns them reduced to their lowest common denominator.
     * For example, 1920/1080 becomes 16/9.
     *
     * @since 4.1.0
     * 
     * @param int $width  The width value.
     * @param int $height The height value.
     * @return array{width: int, height: int} The reduced ratio.
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
} 