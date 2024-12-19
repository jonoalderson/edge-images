<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images\Integrations\Relevanssi;

use Edge_Images\{Integration, Handler, Helpers, Cache};

/**
 * Handles integration with Relevanssi Live Ajax Search plugin.
 */
class Live_Ajax_Search extends Integration {

	/**
	 * Add integration-specific filters.
	 *
	 * @since 4.5.0
	 * 
	 * @return void
	 */
	protected function add_filters(): void {
		add_filter('relevanssi_live_search_results_template', [$this, 'transform_search_results'], 10, 1);
	}

	/**
	 * Transform images in search results.
	 *
	 * @param string $template The search results template HTML.
	 * @return string Modified template HTML.
	 */
	public function transform_search_results( string $template ): string {
		// Skip if no images.
		if ( ! str_contains( $template, '<img' ) ) {
			return $template;
		}

		// Create HTML processor.
		$processor = new \WP_HTML_Tag_Processor( $template );

		// Track offset adjustments.
		$offset_adjustment = 0;

		// Process each img tag.
		while ( $processor->next_tag( 'img' ) ) {
			// Skip if already processed.
			if ( str_contains( $processor->get_attribute( 'class' ) ?? '', 'edge-images-processed' ) ) {
				continue;
			}

			// Get the original HTML.
			$original_html = $processor->get_updated_html();

			// Transform the image.
			$handler = new Handler();
			$processor = $handler->transform_image_tag( 
				$processor, 
				null, 
				$original_html, 
				'search-results'
			);

			// Get the transformed HTML.
			$transformed_html = $processor->get_updated_html();

			// Update offset adjustment.
			$offset_adjustment += strlen( $transformed_html ) - strlen( $original_html );
		}

		// Get final HTML.
		$template = $processor->get_updated_html();

		// Transform any figures with images.
		if ( str_contains( $template, '<figure' ) ) {
			$template = $this->transform_figures( $template );
		}

		return $template;
	}

	/**
	 * Transform figures containing images.
	 *
	 * @param string $content The content to transform.
	 * @return string Modified content.
	 */
	private function transform_figures( string $content ): string {
		// Match all figure elements.
		if ( ! preg_match_all( '/<figure[^>]*>.*?<\/figure>/s', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $content;
		}

		$offset_adjustment = 0;

		foreach ( $matches[0] as $match ) {
			$figure_html = $match[0];
			$figure_position = $match[1];

			// Skip if no image or already processed.
			if ( ! str_contains( $figure_html, '<img' ) || 
				str_contains( $figure_html, 'edge-images-processed' ) ) {
				continue;
			}

			// Extract the image tag.
			if ( ! preg_match( '/<img[^>]*>/', $figure_html, $img_matches ) ) {
				continue;
			}

			$img_html = $img_matches[0];

			// Transform the image.
			$handler = new Handler();
			$processor = new \WP_HTML_Tag_Processor( $img_html );
			$processor->next_tag( 'img' );
			$processor = $handler->transform_image_tag( 
				$processor, 
				null, 
				$img_html, 
				'search-results'
			);

			// Get dimensions for picture element.
			$dimensions = $this->get_dimensions_from_processor( $processor );
			if ( ! $dimensions ) {
				continue;
			}

			// Create picture element.
			$picture_html = $handler->create_picture_element( 
				$processor->get_updated_html(), 
				$dimensions,
				'search-result-image'
			);

			// Replace the figure content.
			$new_figure_html = str_replace( $img_html, $picture_html, $figure_html );

			// Replace in content.
			$content = substr_replace(
				$content,
				$new_figure_html,
				$figure_position + $offset_adjustment,
				strlen( $figure_html )
			);

			// Update offset.
			$offset_adjustment += strlen( $new_figure_html ) - strlen( $figure_html );
		}

		return $content;
	}

	/**
	 * Get dimensions from HTML processor.
	 *
	 * @param \WP_HTML_Tag_Processor $processor The HTML processor.
	 * @return array|null Array with width and height, or null if not found.
	 */
	private function get_dimensions_from_processor( \WP_HTML_Tag_Processor $processor ): ?array {
		$width = $processor->get_attribute( 'width' );
		$height = $processor->get_attribute( 'height' );

		if ( ! $width || ! $height ) {
			return null;
		}

		return [
			'width' => (int) $width,
			'height' => (int) $height,
		];
	}
} 