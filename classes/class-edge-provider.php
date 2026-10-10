<?php
/**
 * Edge provider base functionality.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Abstract base for edge image providers.
 */
abstract class Edge_Provider {

	/**
	 * Valid transformation arguments and their aliases.
	 *
	 * @var array<string, array<int, string>|null>
	 */
	protected static array $valid_args = [
		'w'       => [ 'width' ],
		'h'       => [ 'height' ],
		'dpr'     => null,
		'fit'     => null,
		'g'       => [ 'gravity' ],
		'q'       => [ 'quality' ],
		'f'       => [ 'format' ],
		'sharpen' => null,
	];

	/**
	 * Default transformation arguments.
	 *
	 * @var array<string, mixed>
	 */
	protected array $default_edge_args = [
		'fit' => 'cover',
		'dpr' => 1,
		'f'   => 'auto',
		'q'   => 85,
	];

	/**
	 * Image path.
	 *
	 * @var string
	 */
	protected string $path = '';

	/**
	 * Transformation arguments.
	 *
	 * @var array<string, mixed>
	 */
	protected array $args = [];

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->default_edge_args['q'] = $this->get_default_quality();
	}

	/**
	 * Default quality, filterable.
	 *
	 * @return int
	 */
	protected function get_default_quality(): int {

		$quality = \apply_filters( 'edge_images_default_quality', 85 );

		return min( 100, max( 1, (int) $quality ) );
	}

	/**
	 * Set the image path.
	 *
	 * @param string $path Path.
	 * @return self
	 */
	final public function set_path( string $path ): self {

		$this->path = Helpers::clean_url( $path );

		return $this;
	}

	/**
	 * Set transformation arguments.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return self
	 */
	final public function set_args( array $args ): self {

		$this->args = $this->validate_args( $args );
		$this->normalize_args();

		return $this;
	}

	/**
	 * URL pattern that identifies transformed images.
	 *
	 * @return string
	 */
	abstract public static function get_url_pattern(): string;

	/**
	 * Build the transformed edge URL.
	 *
	 * @return string
	 */
	abstract public function get_edge_url(): string;

	/**
	 * Transformation pattern for cleaning URLs.
	 *
	 * @return string
	 */
	abstract public static function get_transform_pattern(): string;

	/**
	 * Default arguments.
	 *
	 * @return array<string, mixed>
	 */
	final public function get_default_args(): array {

		return $this->default_edge_args;
	}

	/**
	 * Valid argument definitions.
	 *
	 * @return array<string, array<int, string>|null>
	 */
	public static function get_valid_args(): array {

		return self::$valid_args;
	}

	/**
	 * Validate and canonicalize arguments.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array<string, mixed>
	 */
	protected function validate_args( array $args ): array {

		$validated = [];

		foreach ( $args as $key => $value ) {
			$key = strtolower( (string) $key );

			if ( array_key_exists( $key, self::$valid_args ) ) {
				if ( $this->is_valid_value( $key, $value ) ) {
					$validated[ $key ] = $value;
				}
				continue;
			}

			foreach ( self::$valid_args as $canonical => $aliases ) {
				if ( $aliases && in_array( $key, array_map( 'strtolower', $aliases ), true ) ) {
					if ( $this->is_valid_value( $canonical, $value ) ) {
						$validated[ $canonical ] = $value;
					}
					break;
				}
			}
		}

		return $validated;
	}

	/**
	 * Whether a value is valid for an argument.
	 *
	 * @param string $arg   Argument name.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	protected function is_valid_value( string $arg, $value ): bool {

		return match ( $arg ) {
			'w', 'h'  => is_numeric( $value ) && $value > 0 && $value <= 5000,
			'dpr'     => is_numeric( $value ) && $value >= 1 && $value <= 3,
			'q'       => is_numeric( $value ) && $value >= 1 && $value <= 100,
			'fit'     => in_array( $value, [ 'scale-down', 'contain', 'cover', 'crop', 'pad' ], true ),
			'g'       => in_array( $value, [ 'auto', 'north', 'south', 'east', 'west', 'center', 'left', 'right', 'top', 'bottom' ], true ),
			'f'       => in_array( $value, [ 'auto', 'webp', 'json', 'jpeg', 'png', 'gif', 'avif' ], true ),
			'sharpen' => is_numeric( $value ) && $value >= 1 && $value <= 10,
			default   => false,
		};
	}

	/**
	 * Merge defaults and cast numerics.
	 *
	 * @return void
	 */
	private function normalize_args(): void {

		$this->args = array_replace( $this->default_edge_args, $this->args );

		foreach ( $this->args as $key => $value ) {
			if ( is_numeric( $value ) && in_array( $key, [ 'w', 'h' ], true ) ) {
				$this->args[ $key ] = (int) $value;
			}
		}
	}

	/**
	 * Sorted transform arguments.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_transform_args(): array {

		$args = $this->args;
		ksort( $args );

		return $args;
	}

	/**
	 * Canonical form of an argument name.
	 *
	 * @param string $arg Argument.
	 * @return string|null
	 */
	public static function get_canonical_arg( string $arg ): ?string {

		$arg = strtolower( $arg );

		if ( isset( self::$valid_args[ $arg ] ) ) {
			return $arg;
		}

		foreach ( self::$valid_args as $canonical => $aliases ) {
			if ( $aliases && in_array( $arg, array_map( 'strtolower', $aliases ), true ) ) {
				return $canonical;
			}
		}

		return null;
	}

	/**
	 * Map a value to a provider-specific form.
	 *
	 * @param string $param Parameter.
	 * @param string $value Value.
	 * @return string
	 */
	public static function get_mapped_value( string $param, string $value ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		return $value;
	}

	/**
	 * Strip a transformed URL back to the original path form.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function clean_transformed_url( string $url ): string {

		$pattern     = '#' . str_replace( '#', '\\#', static::get_transform_pattern() ) . '#';
		$cleaned_url = preg_replace( $pattern, '', $url );

		return null === $cleaned_url ? $url : $cleaned_url;
	}

	/**
	 * Whether the provider is configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {

		return true;
	}

	/**
	 * Whether this provider uses a hosted subdomain.
	 *
	 * @return bool
	 */
	public static function uses_hosted_subdomain(): bool {

		return false;
	}
}
