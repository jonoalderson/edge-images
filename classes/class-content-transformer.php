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
        
        foreach ($handlers as $block_type => $handler) {

            // Get the block pattern from the Blocks class
            $pattern = Blocks::get_block_pattern($block_type);
            if (!$pattern) {
                continue;
            }

            // If we have a pattern, use it to find blocks
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                // Process matches in reverse order to maintain offsets
                $matches[0] = array_reverse($matches[0]);

                foreach ($matches[0] as $match) {
                    $block_html = $match[0];
                    $start = $match[1];
                    
                    // Skip if this block has already been processed
                    if (strpos($block_html, 'edge-images-processed') !== false || 
                        strpos($block_html, 'edge-images-no-picture') !== false) {
                        continue;
                    }

                    // Create a processor to extract classes
                    $processor = new \WP_HTML_Tag_Processor($block_html);
                    if ($processor->next_tag('figure')) {
                        $class = $processor->get_attribute('class');
                        
                        // Transform the block
                        $block = [
                            'blockName' => 'core/' . $block_type,
                            'innerHTML' => $block_html,
                            'attrs' => ['className' => $class],
                        ];

                        $transformed = $handler->transform($block_html, $block);
                        
                        // Replace the block content
                        $content = substr_replace($content, $transformed, $start, strlen($block_html));
                    }
                }
            }
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