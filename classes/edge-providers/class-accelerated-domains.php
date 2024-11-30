<?php
/**
 * Accelerated Domains edge provider implementation.
 *
 * Handles image transformation through Accelerated Domains' image resizing service.
 * Documentation: https://accelerateddomains.com/docs/image-optimization/
 *
 * @package    Edge_Images\Edge_Providers
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      1.0.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers};

/**
 * Accelerated Domains edge provider class.
 *
 * @since 4.0.0
 */
class Accelerated_Domains extends Edge_Provider {

	/**
	 * The root of the Accelerated Domains edge URL.
	 *
	 * This path identifies Accelerated Domains' image transformation endpoint.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public const EDGE_ROOT = '/acd-cgi/img/v1';

	/**
	 * Get the edge URL for an image.
	 *
	 * Transforms the image URL into an Accelerated Domains-compatible format with
	 * transformation parameters. Format:
	 * /acd-cgi/img/v1/path-to-image.jpg?width=200&height=200
	 *
	 * @since 4.0.0
	 * 
	 * @return string The transformed edge URL.
	 */
	public function get_edge_url(): string {
		$edge_prefix = Helpers::get_rewrite_domain() . self::EDGE_ROOT;

		// Build the URL with query parameters.
		$edge_url = sprintf(
			'%s%s?%s',
			$edge_prefix,
			$this->path,
			http_build_query(
				$this->get_full_transform_args(),
				'',
				'&'
			)
		);

		return esc_attr( $edge_url ); // Escape the ampersands to match WP's image handling.
	}

	/**
	 * Get the URL pattern used to identify transformed images.
	 *
	 * Used to detect if an image has already been transformed by Accelerated Domains.
	 *
	 * @since 4.0.0
	 * 
	 * @return string The URL pattern.
	 */
	public static function get_url_pattern(): string {
		return self::EDGE_ROOT;
	}

	/**
	 * Get full transformation arguments with full parameter names.
	 *
	 * @return array The transformation arguments with full names.
	 */
	private function get_full_transform_args(): array {
		$args = $this->get_transform_args();

		// Map short args to full names
		$full_args = [];
		foreach ($args as $key => $value) {
			switch ($key) {
				case 'w':
					$full_args['width'] = $value;
					break;
				case 'h':
					$full_args['height'] = $value;
					break;
				case 'g':
					$full_args['gravity'] = $value;
					break;
				default:
					$full_args[$key] = $value;
					break;
			}
		}

		return $full_args;
	}
}
