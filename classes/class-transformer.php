<?php
/**
 * Markup and URL transformation for edge images.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Rewrites attachment and content images through the configured edge provider.
 */
class Transformer {

	/**
	 * Whether the transformer has been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Pending picture attributes from wp_get_attachment_image $attr['picture'].
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $pending_picture_attrs = [];

	/**
	 * Request-scoped URL to attachment ID cache.
	 *
	 * @var array<string, int>
	 */
	private array $url_to_attachment_id_cache = [];

	/**
	 * Register filters.
	 *
	 * @return void
	 */
	public static function register(): void {

		if ( self::$registered ) {
			return;
		}

		if ( ! Helpers::should_transform_images() ) {
			return;
		}

		$instance = new self();

		\add_filter( 'wp_get_attachment_image_attributes', [ $instance, 'filter_attachment_image_attributes' ], 10, 3 );
		\add_filter( 'wp_get_attachment_image', [ $instance, 'filter_attachment_image' ], 20, 5 );
		\add_filter( 'wp_calculate_image_srcset', [ $instance, 'disable_wp_srcset' ], 10, 5 );
		\add_filter( 'wp_get_attachment_image_srcset', '__return_false', 10, 3 );
		\add_filter( 'the_content', [ $instance, 'filter_content_images' ], 20 );
		\add_filter( 'the_excerpt', [ $instance, 'filter_content_images' ], 20 );
		\add_filter( 'widget_text_content', [ $instance, 'filter_content_images' ], 20 );
		\add_filter( 'get_avatar', [ $instance, 'filter_avatar' ], 20, 6 );
		\add_action( 'wp_enqueue_scripts', [ $instance, 'enqueue_styles' ] );

		self::$registered = true;
	}

	/**
	 * Build an edge URL for a source image.
	 *
	 * @param string               $src    Source URL.
	 * @param int                  $width  Width.
	 * @param int                  $height Height.
	 * @param array<string, mixed> $args   Extra transform args (fit, quality, etc.).
	 * @param float                $dpr    Device pixel ratio.
	 * @return string
	 */
	public static function url( string $src, int $width, int $height, array $args = [], float $dpr = 1.0 ): string {

		if ( $width <= 0 || $height <= 0 || Helpers::is_non_transformable_format( $src ) ) {
			return $src;
		}

		if ( Helpers::should_disable_transform( $src ) ) {
			return $src;
		}

		$properties = Layout::normalize_properties( $args );
		$edge_args  = Layout::to_edge_args( $properties, $width, $height, $dpr );

		return Helpers::edge_src( $src, $edge_args );
	}

	/**
	 * Queue picture attributes and strip them from the img.
	 *
	 * @param array<string, mixed> $attr       Attributes.
	 * @param \WP_Post             $attachment Attachment.
	 * @param string|array         $size       Size.
	 * @return array<string, mixed>
	 */
	public function filter_attachment_image_attributes( array $attr, $attachment, $size ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		$picture = [];
		if ( isset( $attr['picture'] ) && is_array( $attr['picture'] ) ) {
			$picture = $attr['picture'];
		}
		unset( $attr['picture'] );

		$this->pending_picture_attrs[] = $picture;

		foreach ( Layout::PROPERTY_MAP as $short => $full ) {
			unset( $attr[ $short ], $attr[ $full ] );
		}

		return $attr;
	}

	/**
	 * Transform attachment image HTML.
	 *
	 * @param string               $html          HTML.
	 * @param int                  $attachment_id Attachment ID.
	 * @param string|array         $size          Size.
	 * @param bool                 $icon          Whether icon.
	 * @param string|array         $attr          Attributes.
	 * @return string
	 */
	public function filter_attachment_image( string $html, $attachment_id, $size, bool $icon, $attr ): string {

		$picture_attrs = $this->consume_picture_attrs();
		$html          = $this->strip_picture_attr_from_img( $html );

		$attachment_id = is_numeric( $attachment_id ) ? (int) $attachment_id : 0;
		if ( $attachment_id <= 0 || $icon || ! is_array( $attr ) ) {
			return $html;
		}

		$width  = 0;
		$height = 0;
		if ( is_array( $size ) && isset( $size[0], $size[1] ) ) {
			$width  = (int) $size[0];
			$height = (int) $size[1];
		} else {
			$processor = new \WP_HTML_Tag_Processor( $html );
			if ( $processor->next_tag( 'img' ) ) {
				$width  = (int) $processor->get_attribute( 'width' );
				$height = (int) $processor->get_attribute( 'height' );
			}
		}

		if ( $width <= 0 || $height <= 0 ) {
			return $this->wrap_in_picture( $html, $width, $height, $picture_attrs );
		}

		$mime = \get_post_mime_type( $attachment_id );
		if ( 'image/svg+xml' === $mime ) {
			$processor = new \WP_HTML_Tag_Processor( $html );
			if ( $processor->next_tag( 'img' ) ) {
				$processor->set_attribute( 'width', (string) $width );
				$processor->set_attribute( 'height', (string) $height );
				$this->remove_transform_attrs( $processor );
				$html = $processor->get_updated_html();
			}

			return $this->wrap_in_picture( $html, $width, $height, $picture_attrs );
		}

		$properties  = Layout::normalize_properties( $attr );
		$original_url = \wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $original_url ) {
			return $this->wrap_in_picture( $html, $width, $height, $picture_attrs );
		}

		$max_width = $this->attachment_max_width( $attachment_id );
		$srcset    = $this->build_edge_srcset( $original_url, $width, $height, $properties, $max_width );
		$src       = self::url( $original_url, $width, $height, $properties, 1.0 );

		if ( Settings::is_localization_enabled() ) {
			$local = Localizer::maybe_localize( $attachment_id, $width, $height, $properties, $original_url, $max_width );
			if ( is_array( $local ) ) {
				$src    = $local['src'];
				$srcset = $local['srcset'];
			}
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( $processor->next_tag( 'img' ) ) {
			$this->remove_transform_attrs( $processor );
			$processor->set_attribute( 'src', $src );
			$processor->remove_attribute( 'srcset' );
			if ( $srcset ) {
				$processor->set_attribute( 'srcset', $srcset );
			}
			$processor->set_attribute( 'width', (string) $width );
			$processor->set_attribute( 'height', (string) $height );
			$this->maybe_set_sizes( $processor, $width, false );
			$html = $processor->get_updated_html();
		}

		return $this->wrap_in_picture( $html, $width, $height, $picture_attrs );
	}

	/**
	 * Transform images in content HTML.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function filter_content_images( string $content ): string {

		if ( ! is_string( $content ) || false === stripos( $content, '<img' ) ) {
			return $content;
		}

		if ( Helpers::should_disable_transform( $content ) ) {
			return $content;
		}

		$processor = new \WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag( 'img' ) ) {
			$src = $processor->get_attribute( 'src' );
			if ( ! is_string( $src ) || '' === $src ) {
				continue;
			}

			if ( Helpers::is_svg( $src ) ) {
				$this->remove_transform_attrs( $processor );
				continue;
			}

			if ( Helpers::is_non_transformable_format( $src ) ) {
				continue;
			}

			$attachment_id = $this->attachment_id_from_img( $processor, $src );
			$attr_width    = (int) $processor->get_attribute( 'width' );
			$attr_height   = (int) $processor->get_attribute( 'height' );

			if ( ( $attr_width <= 0 || $attr_height <= 0 ) && $attachment_id > 0 ) {
				$meta = \wp_get_attachment_metadata( $attachment_id );
				if ( is_array( $meta ) ) {
					$attr_width  = $attr_width > 0 ? $attr_width : (int) ( $meta['width'] ?? 0 );
					$attr_height = $attr_height > 0 ? $attr_height : (int) ( $meta['height'] ?? 0 );
				}
			}

			if ( $attr_width <= 0 || $attr_height <= 0 ) {
				continue;
			}

			$gallery = $this->gallery_context( $content, $processor );
			$style_w = Layout::pixel_width_from_style( $processor->get_attribute( 'style' ) );
			if ( $style_w <= 0 && empty( $gallery ) ) {
				$style_w = $this->figure_style_width( $content, $processor );
			}

			$width  = $attr_width;
			$height = $attr_height;
			$force_sizes = false;

			if ( $style_w > 0 && $style_w !== $attr_width ) {
				$width       = $style_w;
				$height      = Layout::scale_height( $width, $attr_width, $attr_height );
				$force_sizes = true;
			}

			if ( ! empty( $gallery ) ) {
				$width  = Layout::gallery_layout_width( $attr_width, $gallery['columns'] );
				$height = Layout::scale_height( $width, $attr_width, $attr_height );
			}

			$attr = [];
			foreach ( Layout::PROPERTY_MAP as $short => $full ) {
				$value = $processor->get_attribute( $short );
				if ( null !== $value ) {
					$attr[ $short ] = $value;
				}
				$value = $processor->get_attribute( $full );
				if ( null !== $value ) {
					$attr[ $full ] = $value;
				}
			}
			$properties = Layout::normalize_properties( $attr );
			$this->remove_transform_attrs( $processor );

			$original_url = $attachment_id > 0
				? ( \wp_get_attachment_image_url( $attachment_id, 'full' ) ?: $src )
				: Helpers::strip_dimensions_from_url( $src );

			$max_width = $attachment_id > 0 ? $this->attachment_max_width( $attachment_id ) : $attr_width;
			$srcset    = $this->build_edge_srcset( $original_url, $width, $height, $properties, $max_width );
			$edge_src  = self::url( $original_url, $width, $height, $properties, 1.0 );

			if ( $attachment_id > 0 && Settings::is_localization_enabled() ) {
				$local = Localizer::maybe_localize( $attachment_id, $width, $height, $properties, $original_url, $max_width );
				if ( is_array( $local ) ) {
					$edge_src = $local['src'];
					$srcset   = $local['srcset'];
				}
			}

			$processor->set_attribute( 'src', $edge_src );
			$processor->remove_attribute( 'srcset' );
			if ( $srcset ) {
				$processor->set_attribute( 'srcset', $srcset );
			}
			$processor->set_attribute( 'width', (string) $width );
			$processor->set_attribute( 'height', (string) $height );

			if ( ! empty( $gallery ) && $gallery['columns'] > 1 ) {
				$processor->set_attribute( 'sizes', Layout::sizes_for_gallery( $gallery['columns'] ) );
			} else {
				$this->maybe_set_sizes( $processor, $width, $force_sizes );
			}

			if ( empty( $gallery ) ) {
				$processor->set_attribute( 'data-edge-wrap', '1' );
			}
		}

		return $this->wrap_content_images( $processor->get_updated_html() );
	}

	/**
	 * Transform a local avatar.
	 *
	 * @param string $avatar      Avatar HTML.
	 * @param mixed  $id_or_email Identifier.
	 * @param int    $size        Size.
	 * @param string $default     Default.
	 * @param string $alt         Alt.
	 * @param array  $args        Args.
	 * @return string
	 */
	public function filter_avatar( string $avatar, $id_or_email, $size, $default, $alt, $args ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		if ( false === stripos( $avatar, '<img' ) ) {
			return $avatar;
		}

		$processor = new \WP_HTML_Tag_Processor( $avatar );
		if ( ! $processor->next_tag( 'img' ) ) {
			return $avatar;
		}

		$src = $processor->get_attribute( 'src' );
		if ( ! is_string( $src ) || ! Helpers::is_local_url( $src ) || Helpers::is_non_transformable_format( $src ) ) {
			return $avatar;
		}

		$size   = (int) ( $args['size'] ?? $size ?: 96 );
		$width  = $size;
		$height = $size;
		$edge   = self::url( Helpers::strip_dimensions_from_url( $src ), $width, $height, [ 'fit' => 'cover' ], 1.0 );
		$srcset = $this->build_edge_srcset( Helpers::strip_dimensions_from_url( $src ), $width, $height, Layout::normalize_properties( [ 'fit' => 'cover' ] ), 0 );

		$processor->set_attribute( 'src', $edge );
		$processor->remove_attribute( 'srcset' );
		if ( $srcset ) {
			$processor->set_attribute( 'srcset', $srcset );
		}
		$processor->set_attribute( 'width', (string) $width );
		$processor->set_attribute( 'height', (string) $height );
		$this->maybe_set_sizes( $processor, $width, false );

		return $processor->get_updated_html();
	}

	/**
	 * Disable WordPress srcset so we can supply our own.
	 *
	 * @param mixed $sources Sources.
	 * @return false
	 */
	public function disable_wp_srcset( $sources ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return false;
	}

	/**
	 * Enqueue picture container styles.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {

		\wp_enqueue_style(
			'edge-images',
			Helpers::STYLES_URL . '/images.min.css',
			[],
			EDGE_IMAGES_VERSION
		);
	}

	/**
	 * Build an edge srcset string.
	 *
	 * @param string               $original_url Original URL.
	 * @param int                  $width        Layout width.
	 * @param int                  $height       Layout height.
	 * @param array<string, mixed> $properties   Properties.
	 * @param int                  $max_width    Max file width.
	 * @return string
	 */
	private function build_edge_srcset( string $original_url, int $width, int $height, array $properties, int $max_width ): string {

		$parts = [];
		foreach ( Layout::srcset_candidates( $width, $max_width ) as $candidate ) {
			$url     = self::url( $original_url, $width, $height, $properties, $candidate['dpr'] );
			$parts[] = \esc_url_raw( $url ) . ' ' . $candidate['width'] . 'w';
		}

		return implode( ', ', $parts );
	}

	/**
	 * Set sizes when missing, or when forced by a layout-width change.
	 *
	 * @param \WP_HTML_Tag_Processor $processor Processor.
	 * @param int                    $width     Width.
	 * @param bool                   $force     Force replace.
	 * @return void
	 */
	private function maybe_set_sizes( \WP_HTML_Tag_Processor $processor, int $width, bool $force ): void {

		if ( $width <= 0 ) {
			return;
		}

		$sizes   = $processor->get_attribute( 'sizes' );
		$loading = $processor->get_attribute( 'loading' );
		$lazy    = is_string( $loading ) && 'lazy' === strtolower( $loading );

		if ( ! $force && is_string( $sizes ) && '' !== $sizes ) {
			if ( $lazy && 0 === strcasecmp( trim( $sizes ), 'auto' ) ) {
				$processor->set_attribute( 'sizes', Layout::sizes_for_width( $width, true ) );
			}
			return;
		}

		$processor->set_attribute( 'sizes', Layout::sizes_for_width( $width, $lazy ) );
	}

	/**
	 * Wrap an img in a picture element.
	 *
	 * @param string               $html   HTML.
	 * @param int                  $width  Width.
	 * @param int                  $height Height.
	 * @param array<string, mixed> $attrs  Extra picture attrs.
	 * @return string
	 */
	private function wrap_in_picture( string $html, int $width, int $height, array $attrs = [] ): string {

		if ( false !== stripos( $html, '<picture' ) ) {
			return $html;
		}

		$classes = [ 'edge-images-container' ];
		if ( ! empty( $attrs['class'] ) && is_scalar( $attrs['class'] ) ) {
			$extra = preg_split( '/\s+/', trim( (string) $attrs['class'] ) );
			if ( is_array( $extra ) ) {
				$classes = array_merge( $classes, $extra );
			}
		}
		unset( $attrs['class'], $attrs['style'] );

		$params = [ '--content-visibility: auto' ];
		if ( $width > 0 && $height > 0 ) {
			$params[] = '--max-width: ' . $width . 'px';
			$params[] = '--width: ' . $width . 'px';
			$params[] = '--height: ' . $height . 'px';
		}

		$attr_html  = ' class="' . \esc_attr( implode( ' ', array_unique( array_filter( $classes ) ) ) ) . '"';
		$attr_html .= ' style="' . \esc_attr( implode( '; ', $params ) ) . '"';

		$hidden = $attrs['hidden'] ?? null;
		unset( $attrs['hidden'] );
		if ( true === $hidden || 1 === $hidden || '1' === $hidden || 'hidden' === $hidden || 'true' === $hidden ) {
			$attr_html .= ' hidden';
		}

		foreach ( $attrs as $name => $value ) {
			if ( ! is_string( $name ) || ! is_scalar( $value ) || false === $value ) {
				continue;
			}
			$safe = preg_replace( '/[^a-zA-Z0-9:_-]/', '', $name );
			if ( ! is_string( $safe ) || '' === $safe ) {
				continue;
			}
			$attr_html .= ' ' . $safe . '="' . \esc_attr( (string) $value ) . '"';
		}

		return '<picture' . $attr_html . '>' . $html . '</picture>';
	}

	/**
	 * Picture-wrap content images marked with data-edge-wrap.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private function wrap_content_images( string $content ): string {

		if ( false === strpos( $content, 'data-edge-wrap' ) ) {
			return $content;
		}

		if ( ! preg_match_all( '/<img\b[^>]*\bdata-edge-wrap=("|\')1\1[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			// Attribute order may vary; fall back to a looser match.
			if ( ! preg_match_all( '/<img\b[^>]*data-edge-wrap=[\'"]1[\'"][^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				return $content;
			}
		}

		for ( $i = count( $matches[0] ) - 1; $i >= 0; $i-- ) {
			$img_html = $matches[0][ $i ][0];
			$offset   = $matches[0][ $i ][1];

			$before = substr( $content, 0, $offset );
			if ( preg_match( '/<picture\b[^>]*>\s*$/i', $before ) ) {
				continue;
			}

			$processor = new \WP_HTML_Tag_Processor( $img_html );
			if ( ! $processor->next_tag( 'img' ) ) {
				continue;
			}

			$processor->remove_attribute( 'data-edge-wrap' );
			$clean  = $processor->get_updated_html();
			$width  = (int) $processor->get_attribute( 'width' );
			$height = (int) $processor->get_attribute( 'height' );
			$wrapped = $this->wrap_in_picture( $clean, $width, $height );
			$content = substr_replace( $content, $wrapped, $offset, strlen( $img_html ) );
		}

		return $content;
	}

	/**
	 * Whether a content offset sits inside a gallery figure or list.
	 *
	 * @param string $content Content.
	 * @param int    $offset  Offset.
	 * @return bool
	 */
	private function offset_inside_gallery( string $content, int $offset ): bool {

		$before = substr( $content, 0, $offset );
		if ( preg_match_all( '/<figure\b[^>]*\bwp-block-gallery\b[^>]*>/i', $before, $opens ) ) {
			$open_count = count( $opens[0] );
			$close_count = preg_match_all( '/<\/figure>/i', $before );
			// Rough: if a gallery open remains unmatched by figure closes after its position, treat as inside.
			if ( $open_count > 0 ) {
				$last_gallery = strripos( $before, 'wp-block-gallery' );
				$after_gallery = substr( $before, $last_gallery );
				$closes = preg_match_all( '/<\/figure>/i', $after_gallery );
				// Gallery figure itself plus nested figures: if fewer closes than opens after gallery start, still inside.
				$opens_after = preg_match_all( '/<figure\b/i', $after_gallery );
				if ( $closes < $opens_after ) {
					return true;
				}
			}
		}

		if ( preg_match( '/<ul\b[^>]*\bblocks-gallery-grid\b[^>]*>[\s\S]*$/i', $before ) && ! preg_match( '/<\/ul>\s*$/i', $before ) ) {
			$last_ul = strripos( $before, 'blocks-gallery-grid' );
			$slice   = substr( $before, $last_ul );
			if ( ! str_contains( $slice, '</ul>' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect gallery context for the current img using surrounding markup.
	 *
	 * @param string                 $content   Full content.
	 * @param \WP_HTML_Tag_Processor $processor Processor on img.
	 * @return array{columns: int}|array{}
	 */
	private function gallery_context( string $content, \WP_HTML_Tag_Processor $processor ): array {

		// Use a bookmark position approximate via src uniqueness.
		$src = (string) $processor->get_attribute( 'src' );
		$pos = $src !== '' ? strpos( $content, $src ) : false;
		if ( false === $pos ) {
			return [];
		}

		if ( ! $this->offset_inside_gallery( $content, $pos ) ) {
			return [];
		}

		$before = substr( $content, 0, $pos );
		$has_nested = (bool) preg_match( '/\bhas-nested-images\b/', $before );
		$class = '';
		if ( preg_match_all( '/<figure\b([^>]*)\bwp-block-gallery\b([^>]*)>/i', $before, $matches ) ) {
			$last = end( $matches[0] );
			if ( preg_match( '/class=("|\')(.*?)\1/i', (string) $last, $class_match ) ) {
				$class = $class_match[2];
			}
		} elseif ( preg_match_all( '/<ul\b([^>]*)\bblocks-gallery-grid\b([^>]*)>/i', $before, $matches ) ) {
			$last = end( $matches[0] );
			if ( preg_match( '/class=("|\')(.*?)\1/i', (string) $last, $class_match ) ) {
				$class = $class_match[2];
			}
			$has_nested = false;
		}

		return [
			'columns' => Layout::gallery_columns( $class, $has_nested ),
		];
	}

	/**
	 * Read pixel width from the nearest figure.wp-block-image style.
	 *
	 * @param string                 $content   Content.
	 * @param \WP_HTML_Tag_Processor $processor Processor.
	 * @return int
	 */
	private function figure_style_width( string $content, \WP_HTML_Tag_Processor $processor ): int {

		$src = (string) $processor->get_attribute( 'src' );
		$pos = $src !== '' ? strpos( $content, $src ) : false;
		if ( false === $pos ) {
			return 0;
		}

		$before = substr( $content, 0, $pos );
		if ( ! preg_match_all( '/<figure\b([^>]*)\bwp-block-image\b([^>]*)>/i', $before, $matches ) ) {
			return 0;
		}

		$last = end( $matches[0] );
		if ( ! preg_match( '/style=("|\')(.*?)\1/i', (string) $last, $style_match ) ) {
			return 0;
		}

		return Layout::pixel_width_from_style( html_entity_decode( $style_match[2], ENT_QUOTES ) );
	}

	/**
	 * Resolve attachment ID from img class or URL.
	 *
	 * @param \WP_HTML_Tag_Processor $processor Processor.
	 * @param string                 $src       Src.
	 * @return int
	 */
	private function attachment_id_from_img( \WP_HTML_Tag_Processor $processor, string $src ): int {

		$class = (string) $processor->get_attribute( 'class' );
		if ( preg_match( '/\bwp-image-(\d+)\b/', $class, $matches ) ) {
			return (int) $matches[1];
		}

		return $this->get_attachment_id_from_url( $src );
	}

	/**
	 * Cached attachment_url_to_postid.
	 *
	 * @param string $url URL.
	 * @return int
	 */
	private function get_attachment_id_from_url( string $url ): int {

		$parts = \wp_parse_url( $url );
		if ( is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) ) {
			$url = $parts['scheme'] . '://' . $parts['host'] . ( $parts['path'] ?? '' );
		}

		if ( isset( $this->url_to_attachment_id_cache[ $url ] ) ) {
			return $this->url_to_attachment_id_cache[ $url ];
		}

		$id = (int) \attachment_url_to_postid( $url );
		if ( $id <= 0 ) {
			$stripped = Helpers::strip_dimensions_from_url( $url );
			if ( $stripped !== $url ) {
				$id = (int) \attachment_url_to_postid( $stripped );
			}
		}

		$this->url_to_attachment_id_cache[ $url ] = $id;

		return $id;
	}

	/**
	 * Max width of an attachment file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int
	 */
	private function attachment_max_width( int $attachment_id ): int {

		$meta = \wp_get_attachment_metadata( $attachment_id );
		return is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
	}

	/**
	 * Remove transformation attributes from the current img.
	 *
	 * @param \WP_HTML_Tag_Processor $processor Processor.
	 * @return void
	 */
	private function remove_transform_attrs( \WP_HTML_Tag_Processor $processor ): void {

		foreach ( Layout::PROPERTY_MAP as $short => $full ) {
			if ( 'width' === $full || 'height' === $full ) {
				continue;
			}
			$processor->remove_attribute( $short );
			$processor->remove_attribute( $full );
		}
	}

	/**
	 * Shift queued picture attrs.
	 *
	 * @return array<string, mixed>
	 */
	private function consume_picture_attrs(): array {

		if ( empty( $this->pending_picture_attrs ) ) {
			return [];
		}

		$attrs = array_shift( $this->pending_picture_attrs );

		return is_array( $attrs ) ? $attrs : [];
	}

	/**
	 * Strip a leaked picture attribute.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function strip_picture_attr_from_img( string $html ): string {

		if ( '' === $html || false === stripos( $html, 'picture' ) ) {
			return $html;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( $processor->next_tag( 'img' ) ) {
			$processor->remove_attribute( 'picture' );
			return $processor->get_updated_html();
		}

		return $html;
	}
}
