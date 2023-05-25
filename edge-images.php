<?php
/**
 * Edge Images
 *
 * @package   Edge_Images
 * @copyright Copyright (C) 2008-2022, Yoast BV - support@yoast.com
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name: Edge Images
 * Description: Provides support for transforming images on the edge, via Cloudflare or Accelerated Domains.
 *
 * Author: Jono Alderson
 * Author URI: https://www.jonoalderson.com
 * Plugin URI:  https://www.jonoalderson.com/plugins/edge-images/
 * Donate link: https://www.jonoalderson.com
 * Contributors: jonoaldersonwp
 *
 * Version: 3.2
 * Requires at least: 6.0
 * Tested up to: 6.1.1
 * Stable tag: 3.1
 * Requires PHP: 7.4
 *
 * Text Domain: edge-images
 * Tags: images, cloudflare, accelerated domains, performance
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Edge_Images;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set our constants.
if ( ! defined( 'EDGE_IMAGES_VERSION' ) ) {
	define( 'EDGE_IMAGES_VERSION', '3.2' );
}

if ( ! defined( 'EDGE_IMAGES_PLUGIN_DIR' ) ) {
	define( 'EDGE_IMAGES_PLUGIN_DIR', __DIR__ );
}

if ( ! defined( 'EDGE_IMAGES_PLUGIN_URL' ) ) {
	define( 'EDGE_IMAGES_PLUGIN_URL', trailingslashit( plugins_url() ) . trailingslashit( plugin_basename( __DIR__ ) ) );
}

if ( ! defined( 'EDGE_IMAGES_PLUGIN_FILE' ) ) {
	define( 'EDGE_IMAGES_PLUGIN_FILE', __FILE__ );
}

/**
 * Init the plugin
 */
( function() {

	// Load our autoloaders.
	require_once 'autoload.php';
	spl_autoload_register( __NAMESPACE__ . '\autoloader' );

	// Register activation & deactivation functions.
	register_activation_hook( __NAMESPACE__, 'activate_plugin' );
	register_deactivation_hook( __NAMESPACE__, 'deactivate_plugin' );

	// Load our core functionality.
	Assets::register();
	Handler::register();

	// Load admin interface.
	Admin::register();

	// Load features.
	Features\Preloads::register();

	// Load integrations.
	Integrations\Yoast_SEO\Social_Images::register();
	// Integrations\Yoast_SEO\Schema_Images::register();
	Integrations\Yoast_SEO\XML_Sitemaps::register();

} )();

/**
 * Runs our plugin activation routine.
 *
 * @return void
 */
function activate_plugin() : void {

}

/**
 * Runs our plugin deactivation routine
 *
 * @return void
 */
function deactivate_plugin() : void {

}

/**
 * Returns an edge image
 *
 * @param  int          $id    The attachment ID.
 * @param  array        $atts  The atts to pass (see wp_get_attachment_image).
 * @param  string|array $size  The image size.
 * @param  bool         $echo  If the image should be echo'd.
 *
 * @return false|string  The image HTML
 */
function get_edge_image( int $id, array $atts = array(), $size = 'large', bool $echo = true ) {

	// Bail if this isn't a valid image ID.
	if ( get_post_type( $id ) !== 'attachment' ) {
		return;
	}

	// Get the image object.
	$image = get_edge_image_object( $id, $atts, $size );

	// Try to fall back to a normal WP image if we didn't get an image object.
	if ( ! $image ) {
		$image = wp_get_attachment_image( $id, $size, false, $atts );
		if ( $echo ) {
			echo wp_kses( $image, array( 'img' ) );
			return;
		}
		return $image;
	}

	// Construct the <img>, and wrap it in a <picture>.
	$html = $image->construct_img_el( true );

	// Echo the image.
	if ( $echo ) {
		echo Helpers::sanitize_image_html( $html );
		return;
	}

	// Or just return the HTML.
	return Helpers::sanitize_image_html( $html );
}

/**
 * Get a an edge image as an object
 *
 * @param  int          $id    The attachment ID.
 * @param  array        $atts  The atts to pass (see wp_get_attachment_image).
 * @param  string|array $size  The image size.
 *
 * @return false|object        The image object
 */
function get_edge_image_object( int $id, array $atts = array(), $size = 'large' ) {

	// Fall back to a normal image if we don't have everything we need.
	if (
		! $id || // Maintain native failure conditions for missing/invalid IDs.
		! Helpers::should_transform_image( $id )
	) {
		return false;
	}

	// Get the image.
	$image = new Image( $id, $atts, $size );

	// Fail if we didn't get a valid image.
	if ( ! $image ) {
		return false;
	}

	return $image;
}
