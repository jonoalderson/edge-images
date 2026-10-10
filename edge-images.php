<?php
/**
 * Edge Images - Routes images through edge providers for optimization and transformation.
 *
 * @package   Edge_Images
 * @author    Jono Alderson <https://www.jonoalderson.com/>
 * @license   GPL-2.0-or-later
 * @link      https://github.com/jonoalderson/edge-images/
 * @since     1.0.0
 * @version   6.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Edge Images
 * Plugin URI:        https://github.com/jonoalderson/edge-images/
 * Description:       Routes images through edge providers (like Cloudflare or Accelerated Domains) for automatic optimization and transformation. Improves page speed and image loading performance.
 * Version:           6.0.0
 * Requires PHP:      8.0
 * Requires at least: 6.4
 * Tested up to:      6.8
 * Author:            Jono Alderson
 * Author URI:        https://www.jonoalderson.com/
 * Text Domain:       edge-images
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace Edge_Images;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'EDGE_IMAGES_VERSION', '6.0.0' );
define( 'EDGE_IMAGES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDGE_IMAGES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EDGE_IMAGES_PLUGIN_FILE', __FILE__ );
define( 'EDGE_IMAGES_ADMIN_PAGE_SLUG', 'edge-images' );
define( 'EDGE_IMAGES_ADMIN_SCREEN_ID', 'settings_page_' . EDGE_IMAGES_ADMIN_PAGE_SLUG );

require_once EDGE_IMAGES_PLUGIN_DIR . 'autoload.php';

\register_activation_hook( __FILE__, [ Activation::class, 'activate' ] );

// Updates do not re-run activation. Copy a saved picture choice first.
\add_action( 'plugins_loaded', [ Settings::class, 'migrate_legacy_picture_option' ], 1 );

/**
 * Initialize admin.
 *
 * @return void
 */
if ( \is_admin() ) {
	\add_action( 'init', [ Admin_Page::class, 'register' ] );
	\add_action( 'admin_init', [ Settings::class, 'register_settings' ] );
}

/**
 * Initialize front-end transformation.
 *
 * @return void
 */
\add_action( 'init', [ Transformer::class, 'register' ], 5 );
\add_action( 'init', [ Localizer::class, 'register' ], 5 );

/**
 * Initialize integrations after plugins load.
 *
 * @return void
 */
\add_action( 'plugins_loaded', [ Integrations::class, 'register' ], 5 );

/**
 * Convert an image URL into an Edge Image URL.
 *
 * @param string              $src  The image URL to convert.
 * @param mixed               $size Size string or [width, height].
 * @param array<string,mixed> $args Additional arguments.
 * @return string
 */
function convert_src( string $src, mixed $size, array $args = [] ): string {

	$width  = 0;
	$height = 0;

	if ( is_string( $size ) ) {
		$size_data = \wp_get_registered_image_subsizes();
		if ( ! isset( $size_data[ $size ] ) ) {
			return $src;
		}
		$width  = (int) $size_data[ $size ]['width'];
		$height = (int) $size_data[ $size ]['height'];
	} elseif ( is_array( $size ) && isset( $size[0], $size[1] ) ) {
		$width  = (int) $size[0];
		$height = (int) $size[1];
	} else {
		return $src;
	}

	return Transformer::url( $src, $width, $height, $args );
}
