<?php
/**
 * Third-party integrations.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Loads optional SEO integrations.
 */
class Integrations {

	/**
	 * Available integrations.
	 *
	 * @var array<string, array>
	 */
	private static array $integrations = [
		'yoast-seo' => [
			'check'   => 'WPSEO_VERSION',
			'type'    => 'constant',
			'name'    => 'Yoast SEO',
			'classes' => [
				'Integrations\Yoast_SEO\Schema_Images',
				'Integrations\Yoast_SEO\Social_Images',
				'Integrations\Yoast_SEO\XML_Sitemaps',
			],
		],
		'rank-math' => [
			'check'   => 'RANK_MATH_VERSION',
			'type'    => 'constant',
			'name'    => 'Rank Math SEO',
			'classes' => [
				'Integrations\Rank_Math\Schema_Images',
				'Integrations\Rank_Math\Social_Images',
				'Integrations\Rank_Math\XML_Sitemaps',
			],
		],
	];

	/**
	 * Loaded integrations.
	 *
	 * @var array<string, bool>
	 */
	private static array $loaded_integrations = [];

	/**
	 * Get integrations.
	 *
	 * @return array<string, array>
	 */
	public static function get_integrations(): array {

		return self::$integrations;
	}

	/**
	 * Register integrations.
	 *
	 * @return void
	 */
	public static function register(): void {

		if ( ! Helpers::should_transform_images() ) {
			return;
		}

		foreach ( self::$integrations as $integration => $config ) {
			if ( ! isset( self::$loaded_integrations[ $integration ] ) ) {
				self::maybe_register_integration( $integration, $config );
				self::$loaded_integrations[ $integration ] = true;
			}
		}
	}

	/**
	 * Whether an integration is available.
	 *
	 * @param string $integration Integration id.
	 * @return bool
	 */
	public static function is_enabled( string $integration ): bool {

		if ( ! isset( self::$integrations[ $integration ] ) ) {
			return false;
		}

		return self::meets_requirements( self::$integrations[ $integration ] );
	}

	/**
	 * Register one integration.
	 *
	 * @param string $integration Id.
	 * @param array  $config      Config.
	 * @return void
	 */
	private static function maybe_register_integration( string $integration, array $config ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		if ( ! self::meets_requirements( $config ) ) {
			return;
		}

		foreach ( $config['classes'] as $class ) {
			$fqcn = 'Edge_Images\\' . $class;
			if ( class_exists( $fqcn ) && method_exists( $fqcn, 'register' ) ) {
				$fqcn::register();
			}
		}
	}

	/**
	 * Check integration requirements.
	 *
	 * @param array $config Config.
	 * @return bool
	 */
	private static function meets_requirements( array $config ): bool {

		$check = $config['check'] ?? '';
		$type  = $config['type'] ?? 'constant';

		return match ( $type ) {
			'constant' => defined( $check ),
			'class'    => class_exists( $check ),
			'function' => function_exists( $check ),
			default    => false,
		};
	}
}
