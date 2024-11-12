<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Handles image dimension calculations and retrieval.
 */
class Image_Dimensions {
    /**
     * Get dimensions from HTML attributes
     *
     * @param \WP_HTML_Tag_Processor $processor The HTML processor.
     * 
     * @return array|null Array with width and height, or null if not found
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
     * Get dimensions from attachment metadata
     *
     * @param int    $attachment_id The attachment ID.
     * @param string $size         Optional size name.
     * 
     * @return array|null Array with width and height, or null if not found
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
     * Get attachment ID from image classes
     *
     * @param \WP_HTML_Tag_Processor $processor The HTML processor.
     * 
     * @return int|null Attachment ID or null if not found
     */
    public static function get_attachment_id( \WP_HTML_Tag_Processor $processor ): ?int {
        $classes = $processor->get_attribute( 'class' );
        if ( ! $classes || ! preg_match( '/wp-image-(\d+)/', $classes, $matches ) ) {
            return null;
        }

        $attachment_id = (int) $matches[1];
        return $attachment_id;
    }

    /**
     * Get image dimensions, trying multiple sources
     *
     * @param \WP_HTML_Tag_Processor $processor     The HTML processor.
     * @param int|null              $attachment_id The attachment ID.
     * @param string               $size          Optional size name.
     * 
     * @return array|null Array with width and height, or null if not found
     */
    public static function get( \WP_HTML_Tag_Processor $processor, ?int $attachment_id = null, string $size = 'full' ): ?array {
        // Try HTML first
        $dimensions = self::from_html( $processor );
        if ( $dimensions ) {
            return $dimensions;
        }

        // Try attachment ID from parameter
        if ( $attachment_id ) {
            $dimensions = self::from_attachment( $attachment_id, $size );
            if ( $dimensions ) {
                return $dimensions;
            }
        }

        // Try getting attachment ID from classes
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
     * Constrain dimensions to content width
     *
     * @param array $dimensions The original dimensions.
     * 
     * @return array The constrained dimensions
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
} 