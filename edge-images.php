<?php
/**
 * Edge Images - Routes images through edge providers for optimization and transformation.
 *
 * @package   Edge_Images
 * @author    Jono Alderson
 * @license   GPL-2.0-or-later
 * @link      https://www.jonoalderson.com/
 * @copyright 2024 Jono Alderson
 *
 * @wordpress-plugin
 * Plugin Name:       Edge Images
 * Plugin URI:        https://www.jonoalderson.com/
 * Description:       Routes images through edge providers (like Cloudflare or Accelerated Domains) for automatic optimization and transformation. Improves page speed and image loading performance.
 * Version:           4.0.0
 * Requires PHP:      7.4
 * Requires at least: 5.6
 * Tested up to:      6.4
 * Author:           Jono Alderson
 * Author URI:       https://www.jonoalderson.com/
 * Text Domain:      edge-images
 * Domain Path:      /languages
 * License:          GPL v2 or later
 * License URI:      http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * Edge Images is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Edge Images is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Edge Images. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 *
 * @since      1.0.0
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * 
 * This plugin requires one of the following edge providers:
 * - Cloudflare (Pro plan or higher) with Image Resizing feature enabled
 * - Accelerated Domains with Image Processing feature enabled
 */

namespace Edge_Images;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'EDGE_IMAGES_VERSION', '4.0.0' );
define( 'EDGE_IMAGES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDGE_IMAGES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load autoloader.
require_once EDGE_IMAGES_PLUGIN_DIR . 'autoload.php';

// Initialize plugin on 'init' hook.
add_action( 'wp', [ Handler::class, 'register' ] );
