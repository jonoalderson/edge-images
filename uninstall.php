<?php
/**
 * Edge Images uninstall functionality.
 *
 * This file runs when the plugin is uninstalled via the WordPress admin.
 * It is responsible for cleaning up any plugin-specific data and settings.
 * This includes:
 * - Removing plugin options from the database
 * - Cleaning up any stored data
 * - Ensuring no residual settings remain
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      4.5.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options.
delete_option( 'edge_images_settings' ); 