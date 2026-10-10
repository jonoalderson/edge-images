<?php
/**
 * Pure layout helpers for edge image markup.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Layout calculations that do not require WordPress.
 */
class Layout {

	/**
	 * DPR multipliers for srcset candidates.
	 *
	 * @var array<int, float>
	 */
	public const DPR_VARIANTS = [ 1.0, 1.5, 2.0 ];

	/**
	 * Transformation attribute map: shortcode => full name.
	 *
	 * @var array<string, string>
	 */
	public const PROPERTY_MAP = [
		'f'       => 'format',
		'q'       => 'quality',
		'fit'     => 'fit',
		'gravity' => 'gravity',
		'sharpen' => 'sharpen',
		'w'       => 'width',
		'h'       => 'height',
	];

	/**
	 * Parse a pixel width from a CSS style attribute.
	 *
	 * @param string|null $style Style attribute value.
	 * @return int Pixel width, or 0 if none.
	 */
	public static function pixel_width_from_style( ?string $style ): int {

		if ( null === $style || '' === $style ) {
			return 0;
		}

		if ( preg_match( '/(?:^|;)\s*width\s*:\s*(\d+(?:\.\d+)?)px\b/i', $style, $matches ) ) {
			return (int) round( (float) $matches[1] );
		}

		return 0;
	}

	/**
	 * Read a columns-N class from a gallery class string.
	 *
	 * @param string $class_attr Class attribute.
	 * @param bool   $has_nested Whether the gallery uses has-nested-images.
	 * @return int Column count.
	 */
	public static function gallery_columns( string $class_attr, bool $has_nested = true ): int {

		if ( preg_match( '/\bcolumns-(\d+)\b/', $class_attr, $matches ) ) {
			$cols = (int) $matches[1];
			return $cols > 0 ? $cols : ( $has_nested ? 3 : 2 );
		}

		return $has_nested ? 3 : 2;
	}

	/**
	 * Layout width for a gallery image.
	 *
	 * @param int $image_width File or attribute width.
	 * @param int $columns     Column count.
	 * @return int Layout width in CSS pixels.
	 */
	public static function gallery_layout_width( int $image_width, int $columns ): int {

		if ( $image_width <= 0 ) {
			return 0;
		}

		if ( $columns <= 1 ) {
			return $image_width;
		}

		return max( 1, (int) round( $image_width / $columns ) );
	}

	/**
	 * Scale height to match a new width, preserving aspect ratio.
	 *
	 * @param int $width         New width.
	 * @param int $source_width  Source width.
	 * @param int $source_height Source height.
	 * @return int Scaled height.
	 */
	public static function scale_height( int $width, int $source_width, int $source_height ): int {

		if ( $width <= 0 || $source_width <= 0 || $source_height <= 0 ) {
			return 0;
		}

		return max( 1, (int) round( $width * ( $source_height / $source_width ) ) );
	}

	/**
	 * Build srcset candidate widths for a layout width.
	 *
	 * @param int $layout_width Layout width in CSS pixels.
	 * @param int $max_width    Original file width (no upscale past this).
	 * @return array<int, array{dpr: float, width: int}>
	 */
	public static function srcset_candidates( int $layout_width, int $max_width = 0 ): array {

		if ( $layout_width <= 0 ) {
			return [];
		}

		$candidates = [];
		$seen       = [];

		foreach ( self::DPR_VARIANTS as $dpr ) {
			$width = (int) round( $layout_width * $dpr );

			if ( $max_width > 0 && $width > $max_width ) {
				$width = $max_width;
			}

			if ( $width <= 0 || isset( $seen[ $width ] ) ) {
				continue;
			}

			$seen[ $width ] = true;
			$candidates[]   = [
				'dpr'   => $dpr,
				'width' => $width,
			];
		}

		return $candidates;
	}

	/**
	 * Build a sizes attribute for a single image.
	 *
	 * @param int  $width Layout width.
	 * @param bool $lazy  Whether loading is lazy.
	 * @return string
	 */
	public static function sizes_for_width( int $width, bool $lazy = false ): string {

		if ( $width <= 0 ) {
			return '';
		}

		$explicit = sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $width );

		if ( $lazy ) {
			return 'auto, ' . $explicit;
		}

		return $explicit;
	}

	/**
	 * Build a sizes attribute for a gallery image.
	 *
	 * @param int $columns Column count.
	 * @return string
	 */
	public static function sizes_for_gallery( int $columns ): string {

		if ( $columns <= 1 ) {
			return '';
		}

		$desktop_vw = (int) round( 100 / $columns );

		return sprintf( '(max-width: 599px) 50vw, %dvw', $desktop_vw );
	}

	/**
	 * Normalize transformation properties from attributes.
	 *
	 * @param array<string, mixed> $attr Raw attributes.
	 * @return array<string, mixed>
	 */
	public static function normalize_properties( array $attr ): array {

		$properties = [];

		foreach ( self::PROPERTY_MAP as $short => $full ) {
			if ( isset( $attr[ $short ] ) && '' !== $attr[ $short ] && null !== $attr[ $short ] ) {
				$properties[ $full ] = $attr[ $short ];
			} elseif ( isset( $attr[ $full ] ) && '' !== $attr[ $full ] && null !== $attr[ $full ] ) {
				$properties[ $full ] = $attr[ $full ];
			}
		}

		if ( ! isset( $properties['format'] ) ) {
			$properties['format'] = 'auto';
		}
		if ( ! isset( $properties['fit'] ) ) {
			$properties['fit'] = 'cover';
		}
		if ( ! isset( $properties['quality'] ) ) {
			$properties['quality'] = 85;
		}

		ksort( $properties );

		return $properties;
	}

	/**
	 * Convert normalized properties to edge provider args (short keys).
	 *
	 * @param array<string, mixed> $properties Normalized properties.
	 * @param int                  $width      Width.
	 * @param int                  $height     Height.
	 * @param float                $dpr        Device pixel ratio.
	 * @return array<string, mixed>
	 */
	public static function to_edge_args( array $properties, int $width, int $height, float $dpr = 1.0 ): array {

		$args = [
			'w'   => $width,
			'h'   => $height,
			'dpr' => $dpr,
			'fit' => (string) ( $properties['fit'] ?? 'cover' ),
			'f'   => (string) ( $properties['format'] ?? 'auto' ),
			'q'   => (int) ( $properties['quality'] ?? 85 ),
		];

		if ( isset( $properties['gravity'] ) && '' !== (string) $properties['gravity'] ) {
			$args['g'] = (string) $properties['gravity'];
		}

		if ( ! empty( $properties['sharpen'] ) ) {
			$sharpen = (int) $properties['sharpen'];
			$args['sharpen'] = $sharpen > 0 ? $sharpen : 1;
		}

		return $args;
	}
}
