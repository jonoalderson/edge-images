<?php
/**
 * Content image transformation functionality.
 *
 * Handles the transformation of images within post content.
 * This class:
 * - Transforms block images
 * - Transforms figure-wrapped images
 * - Transforms standalone images
 * - Handles picture element wrapping
 * - Maintains image attributes
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-3.0-or-later
 * @since      4.5.0
 */

namespace Edge_Images;

class Content_Transformer {

    /**
     * Transform content images.
     *
     * @since 4.5.0
     * 
     * @param string $content The post content.
     * @return string Modified content.
     */
    public function transform(string $content): string {
        // Bail if we don't have any images
        if (!str_contains($content, '<img')) {
            return $content;
        }

        $content = $this->transform_block_images($content);
        $content = $this->transform_standalone_images($content);

        return $content;
    }

    /**
     * Check if a block should be excluded from transformation.
     *
     * @since 4.5.0
     * 
     * @param string $block_html The block HTML.
     * @param string $content    The full content.
     * @param string $class      The block's class attribute.
     * @return bool Whether the block should be excluded.
     */
    private function should_exclude_block(string $block_html, string $content, string $class): bool {
        // Exclude images within gallery blocks
        if (str_contains($content, 'wp-block-gallery') && 
            preg_match('/<figure[^>]*class="[^"]*\bwp-block-gallery\b[^"]*"[^>]*>.*' . preg_quote($block_html, '/') . '/s', $content)) {
            return true;
        }

        // Add more exclusion rules here
        // For example:
        // - Exclude specific block types
        // - Exclude based on classes
        // - Exclude based on attributes
        // - Exclude based on parent block types

        return false;
    }

    /**
     * Transform images within blocks.
     *
     * @since 4.5.0
     * 
     * @param string $content The content to transform.
     * @return string Modified content.
     */
    private function transform_block_images(string $content): string {
        // Get all registered block handlers
        $handlers = Blocks::get_handlers();
        
        // First pass: identify blocks to transform using regex to get positions
        $blocks_to_transform = [];
        foreach ($handlers as $block_type => $handler) {
            // Skip gallery blocks - we don't transform images within galleries
            if ($block_type === 'gallery') {
                continue;
            }

            $pattern = sprintf(
                '/<figure[^>]*class="[^"]*\bwp-block-%s\b[^"]*"[^>]*>.*?<\/figure>/s',
                preg_quote($block_type, '/')
            );

            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $block_html = $match[0];
                    $start = $match[1];
                    
                    // Create a processor to extract classes
                    $processor = new \WP_HTML_Tag_Processor($block_html);
                    if ($processor->next_tag('figure')) {
                        $class = $processor->get_attribute('class');
                        
                        // Skip if block should be excluded
                        if ($this->should_exclude_block($block_html, $content, $class)) {
                            continue;
                        }
                        
                        $blocks_to_transform[] = [
                            'html' => $block_html,
                            'class' => $class,
                            'block_type' => $block_type,
                            'handler' => $handler,
                            'start' => $start,
                            'length' => strlen($block_html),
                        ];
                    }
                }
            }
        }

        // Process blocks in reverse order to maintain offsets
        $blocks_to_transform = array_reverse($blocks_to_transform);

        // Second pass: transform identified blocks
        foreach ($blocks_to_transform as $block_data) {
            $block = [
                'blockName' => 'core/' . $block_data['block_type'],
                'innerHTML' => $block_data['html'],
                'attrs' => ['className' => $block_data['class']],
            ];

            // Extract the image tag and check for links
            $has_link = false;
            $link_html = '';
            if (preg_match('/<a[^>]*>.*?<img[^>]+>.*?<\/a>/s', $block_data['html'], $link_matches)) {
                $has_link = true;
                $link_html = $link_matches[0];
                if (!preg_match('/<img[^>]+>/', $link_html, $img_matches)) {
                    continue;
                }
            } elseif (!preg_match('/<img[^>]+>/', $block_data['html'], $img_matches)) {
                continue;
            }

            // Transform the image using the block handler
            $transformed = $block_data['handler']->transform($img_matches[0], $block);

            // If we have a link and picture wrapping is enabled, wrap the picture in the link
            if ($has_link && Features::is_feature_enabled('picture_wrap')) {
                if (preg_match('/<a[^>]*>/', $link_html, $link_open)) {
                    $transformed = str_replace('<img', $link_open[0] . '<img', $transformed);
                    $transformed = str_replace('</picture>', '</a></picture>', $transformed);
                }
            }

            // If picture wrapping is disabled, preserve the figure structure
            if (!Features::is_feature_enabled('picture_wrap')) {
                $transformed = str_replace($img_matches[0], $transformed, $block_data['html']);
            }
            
            // Replace the block content
            $content = substr_replace($content, $transformed, $block_data['start'], $block_data['length']);
        }

        return $content;
    }

    /**
     * Transform standalone images.
     *
     * @since 4.5.0
     * 
     * @param string $content The content to transform.
     * @return string Modified content.
     */
    private function transform_standalone_images(string $content): string {

        if (!preg_match_all('/<img[^>]+>/', $content, $matches)) {
            return $content;
        }

        foreach ($matches[0] as $img_html) {
            // Skip if already processed
            if (Helpers::is_image_processed($img_html)) {
                continue;
            }

            // Transform the image
            $transformed = $this->transform_single_image($img_html, 'content');

            // If picture wrapping is enabled
            if (Features::is_feature_enabled('picture_wrap')) {
                $dimensions = Image_Dimensions::from_html(new \WP_HTML_Tag_Processor($transformed));
                if ($dimensions) {
                    $transformed = Picture::create($transformed, $dimensions);
                }
            }

            // Replace the original image with the transformed one
            $content = str_replace($img_html, $transformed, $content);
        }

        return $content;
    }

    /**
     * Transform a single image.
     *
     * @since 4.5.0
     * 
     * @param string $img_html The image HTML.
     * @param string $context  The transformation context.
     * @return string Transformed image HTML.
     */
    private function transform_single_image(string $img_html, string $context): string {
        $processor = new \WP_HTML_Tag_Processor($img_html);
        if (!$processor->next_tag('img')) {
            return $img_html;
        }

        // Get original dimensions before transformation
        $original_dimensions = Image_Dimensions::from_html($processor);

        // Transform the image
        $processor = Images::transform_image_tag($processor, null, $img_html, $context);

        // If we have original dimensions but they weren't preserved in the transformation,
        // add them back to ensure picture wrapping works
        if ($original_dimensions) {
            $transformed_processor = new \WP_HTML_Tag_Processor($processor->get_updated_html());
            if ($transformed_processor->next_tag('img')) {
                if (!$transformed_processor->get_attribute('width')) {
                    $transformed_processor->set_attribute('width', (string)$original_dimensions['width']);
                }
                if (!$transformed_processor->get_attribute('height')) {
                    $transformed_processor->set_attribute('height', (string)$original_dimensions['height']);
                }
                return $transformed_processor->get_updated_html();
            }
        }

        return $processor->get_updated_html();
    }
} 