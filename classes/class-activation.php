<?php
/**
 * Activation functionality.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Sets default options on activation.
 */
class Activation {

	/**
	 * Default option values.
	 *
	 * @var array<string, mixed>
	 */
	private static array $defaults = [
		'edge_images_provider'                      => 'none',
		'edge_images_domain'                        => '',
		'edge_images_localize'                      => false,
		'edge_images_bunny_subdomain'               => '',
		'edge_images_imgix_subdomain'               => '',
		'edge_images_imgproxy_url'                  => '',
		'edge_images_integration_yoast_schema'      => true,
		'edge_images_integration_yoast_social'      => true,
		'edge_images_integration_yoast_xml'         => true,
		'edge_images_integration_rank_math_schema'  => true,
		'edge_images_integration_rank_math_social'  => true,
		'edge_images_integration_rank_math_xml'     => true,
	];

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate(): void {

		foreach ( self::$defaults as $option => $default ) {
			if ( false === \get_option( $option ) ) {
				\update_option( $option, $default );
			}
		}
	}
}
