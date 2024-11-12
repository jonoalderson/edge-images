<?php
/**
 * Edge Images uninstall file.
 *
 * @package Edge_Images
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options.
delete_option( 'edge_images_settings' ); 