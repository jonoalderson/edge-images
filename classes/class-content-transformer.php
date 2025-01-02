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
        // Debug for specific image
        if (strpos($content, '51WkQa3KNRL') !== false) {
            error_log('DEBUG 51WkQa3KNRL - Content contains target image');
            error_log('Content: ' . substr($content, 0, 1000)); // Log first 1000 chars to avoid huge logs
        }

        // Bail if we don't have any images
        if (!str_contains($content, '<img')) {
            if (strpos($content, '51WkQa3KNRL') !== false) {
                error_log('DEBUG 51WkQa3KNRL - No img tags found in content');
            }
            return $content;
        }

        $content = $this->transform_block_images($content);
        if (strpos($content, '51WkQa3KNRL') !== false) {
            error_log('DEBUG 51WkQa3KNRL - After block transformation');
        }

        $content = $this->transform_standalone_images($content);
        if (strpos($content, '51WkQa3KNRL') !== false) {
            error_log('DEBUG 51WkQa3KNRL - After standalone transformation');
        }

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
        
        if (strpos($content, '51WkQa3KNRL') !== false) {
            error_log('DEBUG 51WkQa3KNRL - Starting block transformation');
            error_log('Registered handlers: ' . implode(', ', array_keys($handlers)));
        }
        
        foreach ($handlers as $block_type => $handler) {
            // Get the block pattern from the Blocks class
            $pattern = Blocks::get_block_pattern($block_type);
            if (!$pattern) {
                if (strpos($content, '51WkQa3KNRL') !== false) {
                    error_log('DEBUG 51WkQa3KNRL - No pattern for block type: ' . $block_type);
                }
                continue;
            }

            if (strpos($content, '51WkQa3KNRL') !== false) {
                error_log('DEBUG 51WkQa3KNRL - Checking pattern for ' . $block_type . ': ' . $pattern);
            }

            // If we have a pattern, use it to find blocks
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                if (strpos($content, '51WkQa3KNRL') !== false) {
                    error_log('DEBUG 51WkQa3KNRL - Found matches for ' . $block_type . ': ' . count($matches[0]));
                }

                // Process matches in reverse order to maintain offsets
                $matches[0] = array_reverse($matches[0]);

                foreach ($matches[0] as $match) {
                    $block_html = $match[0];
                    $start = $match[1];
                    
                    if (strpos($block_html, '51WkQa3KNRL') !== false) {
                        error_log('DEBUG 51WkQa3KNRL - Found target image in block: ' . $block_type);
                        error_log('Block HTML: ' . $block_html);
                    }

                    // Skip if this block has already been processed
                    if (strpos($block_html, 'edge-images-processed') !== false || 
                        strpos($block_html, 'edge-images-no-picture') !== false) {
                        if (strpos($block_html, '51WkQa3KNRL') !== false) {
                            error_log('DEBUG 51WkQa3KNRL - Block already processed');
                        }
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
                        
                        if (strpos($block_html, '51WkQa3KNRL') !== false) {
                            error_log('DEBUG 51WkQa3KNRL - Block transformed: ' . $transformed);
                        }

                        // Replace the block content
                        $content = substr_replace($content, $transformed, $start, strlen($block_html));
                    }
                }
            }
        }

        // Also handle wp-block-image divs that aren't caught by block handlers
        if (preg_match_all('/<div[^>]*class="[^"]*\bwp-block-image\b[^"]*"[^>]*>.*?<\/div>/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            if (strpos($content, '51WkQa3KNRL') !== false) {
                error_log('DEBUG 51WkQa3KNRL - Found wp-block-image divs: ' . count($matches[0]));
            }

            $matches[0] = array_reverse($matches[0]);
            foreach ($matches[0] as $match) {
                $block_html = $match[0];
                $start = $match[1];

                if (strpos($block_html, '51WkQa3KNRL') !== false) {
                    error_log('DEBUG 51WkQa3KNRL - Found target image in wp-block-image div');
                    error_log('Block HTML: ' . $block_html);
                }

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

                    if (strpos($block_html, '51WkQa3KNRL') !== false) {
                        error_log('DEBUG 51WkQa3KNRL - Transformed wp-block-image: ' . $transformed_block);
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
        // Find all img tags that aren't inside a figure
        preg_match_all('/<img[^>]+>(?![^<]*<\/figure>)/', $content, $matches);
        if (empty($matches[0])) {
            if (strpos($content, '51WkQa3KNRL') !== false) {
                error_log('DEBUG 51WkQa3KNRL - No standalone images found');
            }
            return $content;
        }

        foreach ($matches[0] as $img_html) {
            if (strpos($img_html, '51WkQa3KNRL') !== false) {
                error_log('DEBUG 51WkQa3KNRL - Found target image as standalone');
                error_log('Original img HTML: ' . $img_html);
            }

            // Skip if already processed
            if (Helpers::is_image_processed($img_html)) {
                if (strpos($img_html, '51WkQa3KNRL') !== false) {
                    error_log('DEBUG 51WkQa3KNRL - Image already processed');
                }
                continue;
            }

            // Transform the image
            $transformed = $this->transform_single_image($img_html, 'content');
            if (strpos($img_html, '51WkQa3KNRL') !== false) {
                error_log('DEBUG 51WkQa3KNRL - After transformation: ' . $transformed);
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
        if (strpos($img_html, '51WkQa3KNRL') !== false) {
            error_log('DEBUG 51WkQa3KNRL - In transform_single_image');
            error_log('Context: ' . $context);
            error_log('Original img HTML: ' . $img_html);
        }

        // Get original dimensions
        $processor = new \WP_HTML_Tag_Processor($img_html);
        if (!$processor->next_tag('img')) {
            return $img_html;
        }
        $dimensions = Image_Dimensions::from_html($processor);

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