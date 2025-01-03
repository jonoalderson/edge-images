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
 * @license    GPL-2.0-or-later
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
                    if (strpos($block_html, 'edge-images-processed') !== false) {
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

                        // Transform using the appropriate block handler
                        $transformed = $handler->transform($block_html, $block);

                        // Replace the block content
                        $content = substr_replace($content, $transformed, $start, strlen($block_html));
                    }
                }
            }
        }

        // Also handle wp-block-image divs that aren't caught by block handlers
        if (preg_match_all('/<div[^>]*class="[^"]*\bwp-block-image\b[^"]*"[^>]*>.*?<\/div>/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $matches[0] = array_reverse($matches[0]);
            foreach ($matches[0] as $match) {
                $block_html = $match[0];
                $start = $match[1];

                // Skip if already processed
                if (strpos($block_html, 'edge-images-processed') !== false) {
                    continue;
                }

                // Extract the img tag and transform it
                if (preg_match('/<figure[^>]*>.*?<img[^>]+>.*?<\/figure>/s', $block_html, $figure_matches)) {
                    $figure_html = $figure_matches[0];
                    $transformed = $this->transform_single_image($figure_html, 'content');
                    $transformed_block = str_replace($figure_html, $transformed, $block_html);
                    $content = substr_replace($content, $transformed_block, $start, strlen($block_html));
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
        // Get original dimensions
        $processor = new \WP_HTML_Tag_Processor($img_html);
        if (!$processor->next_tag('img')) {
            return $img_html;
        }
        $dimensions = Image_Dimensions::from_html($processor);

        // Bail if we don't have dimensions
        if (!$dimensions) {
            return $img_html;
        }

        // Transform the image
        $processor = Images::transform_image_tag($processor, null, $img_html, $context);
        $transformed = $processor->get_updated_html();

        // Try to transform with Picture::transform_figure first
        $picture = Picture::transform_figure($img_html, $transformed, $dimensions);

        if ($picture) {
            return $picture;
        }

        // If not a figure or figure transformation failed, check if we should wrap in picture
        if (Picture::should_wrap($transformed, $context)) {
            $transformed = Picture::create($transformed, $dimensions);
        }

        return $transformed;
    }
} 