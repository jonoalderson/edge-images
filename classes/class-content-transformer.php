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

use Edge_Images\Features\Picture;

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
        error_log('DEBUG transform_block_images - Starting');
        
        // Get all registered block handlers
        $handlers = Blocks::get_handlers();
        error_log('DEBUG transform_block_images - Got handlers: ' . print_r(array_keys($handlers), true));
        
        foreach ($handlers as $block_type => $handler) {
            error_log('DEBUG transform_block_images - Processing block type: ' . $block_type);
            
            // Get the block pattern from the Blocks class
            $pattern = Blocks::get_block_pattern($block_type);
            if (!$pattern) {
                error_log('DEBUG transform_block_images - No pattern for block type: ' . $block_type);
                continue;
            }

            // If we have a pattern, use it to find blocks
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                error_log('DEBUG transform_block_images - Found ' . count($matches[0]) . ' matches for block type: ' . $block_type);
                
                // Process matches in reverse order to maintain offsets
                $matches[0] = array_reverse($matches[0]);

                foreach ($matches[0] as $match) {
                    $block_html = $match[0];
                    error_log('DEBUG transform_block_images - Processing block HTML: ' . $block_html);
                    $start = $match[1];

                    // Skip if this block has already been processed
                    if (strpos($block_html, 'edge-images-processed') !== false) {
                        error_log('DEBUG transform_block_images - Block already processed');
                        continue;
                    }

                    // Create a processor to extract classes
                    $processor = new \WP_HTML_Tag_Processor($block_html);
                    if ($processor->next_tag('figure')) {
                        $class = $processor->get_attribute('class');
                        error_log('DEBUG transform_block_images - Found figure with class: ' . $class);
                        
                        // Transform the block
                        $block = [
                            'blockName' => 'core/' . $block_type,
                            'innerHTML' => $block_html,
                            'attrs' => ['className' => $class],
                        ];

                        // Transform using the appropriate block handler
                        $transformed = $handler->transform($block_html, $block);
                        error_log('DEBUG transform_block_images - Block transformed to: ' . $transformed);

                        // Replace the block content
                        $content = substr_replace($content, $transformed, $start, strlen($block_html));
                    }
                }
            } else {
                error_log('DEBUG transform_block_images - No matches found for block type: ' . $block_type);
            }
        }

        // Also handle wp-block-image divs that aren't caught by block handlers
        if (preg_match_all('/<div[^>]*class="[^"]*\bwp-block-image\b[^"]*"[^>]*>.*?<\/div>/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            error_log('DEBUG transform_block_images - Found ' . count($matches[0]) . ' wp-block-image divs');
            $matches[0] = array_reverse($matches[0]);
            foreach ($matches[0] as $match) {
                $block_html = $match[0];
                error_log('DEBUG transform_block_images - Processing wp-block-image div: ' . $block_html);
                $start = $match[1];

                // Skip if already processed
                if (strpos($block_html, 'edge-images-processed') !== false) {
                    error_log('DEBUG transform_block_images - Block already processed');
                    continue;
                }

                // Extract the img tag and transform it
                if (preg_match('/<figure[^>]*>.*?<img[^>]+>.*?<\/figure>/s', $block_html, $figure_matches)) {
                    error_log('DEBUG transform_block_images - Found figure in wp-block-image div: ' . $figure_matches[0]);
                    $figure_html = $figure_matches[0];
                    $transformed = $this->transform_single_image($figure_html, 'content');
                    error_log('DEBUG transform_block_images - Transformed figure to: ' . $transformed);
                    $transformed_block = str_replace($figure_html, $transformed, $block_html);
                    $content = substr_replace($content, $transformed_block, $start, strlen($block_html));
                }
            }
        } else {
            error_log('DEBUG transform_block_images - No wp-block-image divs found');
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
        
        // Find all img tags that aren't inside a figure
        preg_match_all('/<img[^>]+>(?![^<]*<\/figure>)/', $content, $matches);
        if (empty($matches[0])) {
            return $content;
        }

        // Transform each image
        foreach ($matches[0] as $img_html) {

            // Skip if already processed
            if (Helpers::is_image_processed($img_html)) {
                continue;
            }

            // Transform the image
            $transformed = $this->transform_single_image($img_html, 'content');

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
        error_log('DEBUG transform_single_image - Starting with HTML: ' . $img_html);
        error_log('DEBUG transform_single_image - Context: ' . $context);

        // Get original dimensions
        $processor = new \WP_HTML_Tag_Processor($img_html);
        if (!$processor->next_tag('img')) {
            error_log('DEBUG transform_single_image - No img tag found');
            return $img_html;
        }
        $dimensions = Image_Dimensions::from_html($processor);

        // Bail if we don't have dimensions
        if (!$dimensions) {
            error_log('DEBUG transform_single_image - No dimensions found');
            return $img_html;
        }

        error_log('DEBUG transform_single_image - Dimensions found: ' . print_r($dimensions, true));

        // Transform the image
        $processor = Images::transform_image_tag($processor, null, $img_html, $context);
        $transformed = $processor->get_updated_html();
        error_log('DEBUG transform_single_image - After transform_image_tag: ' . $transformed);

        // Try to transform with Picture::transform_figure first
        error_log('DEBUG transform_single_image - Attempting Picture::transform_figure');
        $picture = Picture::transform_figure($img_html, $transformed, $dimensions);
        error_log('DEBUG transform_single_image - Picture::transform_figure returned: ' . ($picture === null ? 'null' : $picture));

        if ($picture) {
            error_log('DEBUG transform_single_image - Returning picture element');
            return $picture;
        }

        error_log('DEBUG transform_single_image - Picture::transform_figure returned null, checking should_wrap');
        // If not a figure or figure transformation failed, check if we should wrap in picture
        if (Picture::should_wrap($transformed, $context)) {
            error_log('DEBUG transform_single_image - should_wrap returned true, creating picture element');
            $transformed = Picture::create($transformed, $dimensions);
            error_log('DEBUG transform_single_image - After picture create: ' . $transformed);
        } else {
            error_log('DEBUG transform_single_image - should_wrap returned false, returning transformed HTML: ' . $transformed);
        }

        return $transformed;
    }
} 