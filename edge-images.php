<?php
/**
 * Edge Images - Routes images through edge providers for optimization and transformation.
 *
 * @package   Edge_Images
 * @author    Jono Alderson <https://www.jonoalderson.com/>
 * @license   GPL-2.0-or-later
 * @link      https://github.com/jonoalderson/edge-images/
 * @since     1.0.0
 * @version   5.5.6
 *
 * @wordpress-plugin
 * Plugin Name:       Edge Images
 * Plugin URI:        https://github.com/jonoalderson/edge-images/
 * Description:       Routes images through edge providers (like Cloudflare or Accelerated Domains) for automatic optimization and transformation. Improves page speed and image loading performance.
 * Version:           5.5.6
 * Requires PHP:      7.4
 * Requires at least: 5.6
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
define( 'EDGE_IMAGES_VERSION', '5.5.6' );
define( 'EDGE_IMAGES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDGE_IMAGES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EDGE_IMAGES_PLUGIN_FILE', __FILE__ );
define( 'EDGE_IMAGES_ADMIN_PAGE_SLUG', 'edge-images' );
define( 'EDGE_IMAGES_ADMIN_SCREEN_ID', 'settings_page_' . EDGE_IMAGES_ADMIN_PAGE_SLUG );

// Load autoloader.
require_once EDGE_IMAGES_PLUGIN_DIR . 'autoload.php';

// Add near the top of the file after plugin header
register_activation_hook(__FILE__, ['\Edge_Images\Activation', 'activate']);

/**
 * Initialize admin functionality when in the WordPress admin area.
 *
 * @since 4.0.0
 * @return void
 */
if ( is_admin() ) {
    add_action( 'init', [ Admin_Page::class, 'register' ] );
}

/**
 * Initialize the main plugin functionality.
 *
 * @since 4.0.0
 * @return void
 */
add_action( 'init', [ Handler::class, 'register' ], 5 );

/**
 * Initialize integrations.
 * We use 'plugins_loaded' to ensure all plugins are available.
 *
 * @since 4.0.0
 * @return void
 */
add_action( 'plugins_loaded', [ Integrations::class, 'register' ], 5 );

/**
 * Initialize feature management.
 *
 * @since 4.0.0
 * @return void
 */
add_action('init', [Features::class, 'register'], 5);

/**
 * Initialize block management.
 *
 * @since 4.5.0
 * @return void
 */
add_action('init', [Blocks::class, 'register'], 5);

/**
 * Initialize rewrite functionality.
 *
 * @since 5.4.0
 * @return void
 */
add_action('init', [Rewrites::class, 'register'], 5);

/**
 * Convert an image URL into an Edge Image URL.
 * 
 * @param string $src The image URL to convert.
 * @param mixed $size The size of the image to convert (a string or array of h/w values).
 * @param array $args Additional arguments for the conversion.
 * .
 * @return string The converted image URL, or the original URL if the conversion fails.
 */
function convert_src(string $src, mixed $size, array $args = []) : string {

    // If size is a string, get the h/w values from the registered size.
    if (is_string($size)) {
        $size_data = \wp_get_registered_image_subsizes();
        if (isset($size_data[$size])) {
            $args['w'] = $size_data[$size]['width'];
            $args['h'] = $size_data[$size]['height'];
            return Helpers::edge_src($src, $args);
        } else {
            // If the size is not registered, use the original URL.
            return $src;
        }
    }

   // If size is an array, add the width and height to the args.
   if (is_array($size)) {
        // If we don't have values for [0] and [1], use the original URL.
        if (!isset($size[0]) || !isset($size[1])) {
            return $src;
        }

        // Add the width and height to the args.
        $args['w'] = $size[0];
        $args['h'] = $size[1];
        
        // Return the converted URL.
        return Helpers::edge_src($src, $args);
   }

   // If size is not a string or array, use the original URL.
   return $src;
}