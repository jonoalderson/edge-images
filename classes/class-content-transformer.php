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
        $content = $this->transform_figure_images($content);
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
        $pattern = '/<figure\s+class="[^"]*wp-block-([^"\s]+)[^"]*".*?<\/figure>/s';
        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        // Process matches in reverse order to maintain offsets
        $matches = array_reverse($matches);
        
        foreach ($matches as $match) {
            $block_html = $match[0][0];
            $block_type = $match[1][0];
            $offset = $match[0][1];
            $length = strlen($block_html);

            // If we have a handler for this block type, use it
            if (Blocks::has_handler($block_type)) {
                $handler = Blocks::get_handler($block_type);
                $block = [
                    'blockName' => 'core/' . $block_type,
                    'innerHTML' => $block_html,
                ];
                $transformed = $handler->transform($block_html, $block);
                $content = substr_replace($content, $transformed, $offset, $length);
            }
        }

        return $content;
    }

    /**
     * Transform images within figures.
     *
     * @since 4.5.0
     * 
     * @param string $content The content to transform.
     * @return string Modified content.
     */
    private function transform_figure_images(string $content): string {
        if (!preg_match_all('/<figure[^>]*>.*?<img[^>]+>.*?<\/figure>/s', $content, $figure_matches)) {
            return $content;
        }

        foreach ($figure_matches[0] as $figure_html) {
            // Skip if already processed
            if (str_contains($figure_html, '<picture') || str_contains($figure_html, 'edge-images-processed')) {
                continue;
            }

            // Extract the image
            if (!preg_match('/<img[^>]+>/', $figure_html, $img_matches)) {
                continue;
            }
            $img_html = $img_matches[0];

            // Transform the image
            $transformed = $this->transform_single_image($img_html, 'content');

            // If picture wrapping is enabled
            if (Features::is_feature_enabled('picture_wrap')) {
                $dimensions = Image_Dimensions::from_html(new \WP_HTML_Tag_Processor($transformed));
                if ($dimensions) {
                    // Create picture element with figure's classes
                    $picture = Picture::create($transformed, $dimensions);
                    if (preg_match('/class="([^"]+)"/', $figure_html, $class_matches)) {
                        $picture = str_replace('class="edge-images-container"', 'class="' . $class_matches[1] . ' edge-images-container"', $picture);
                    }
                    // Replace the entire figure with the picture
                    $content = str_replace($figure_html, $picture, $content);
                    continue;
                }
            }

            // If we get here, just replace the image within the figure
            $content = str_replace($img_html, $transformed, $content);
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

        // Transform the image
        $processor = Images::transform_image_tag($processor, null, $img_html, $context);
        return $processor->get_updated_html();
    }
} 