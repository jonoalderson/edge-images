<?php
/**
 * Edge Images uninstall cleanup.
 *
 * Removes plugin options. Localized image files stored under uploads remain
 * as normal media files; delete them manually if desired. Attachment meta
 * `_edge_images_sizes` is left in place (uninstall runs without loading the
 * plugin) and is harmless once the plugin is gone.
 *
 * @package Edge_Images
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = [
	'edge_images_provider',
	'edge_images_domain',
	'edge_images_localize',
	'edge_images_picture',
	'edge_images_bunny_subdomain',
	'edge_images_imgix_subdomain',
	'edge_images_imgproxy_url',
	'edge_images_localize_queue',
	'edge_images_integration_yoast_schema',
	'edge_images_integration_yoast_social',
	'edge_images_integration_yoast_xml',
	'edge_images_integration_rank_math_schema',
	'edge_images_integration_rank_math_social',
	'edge_images_integration_rank_math_xml',
	// Legacy options from pre-6.0 releases.
	'edge_images_settings',
	'edge_images_max_width',
	'edge_images_width_multipliers',
	'edge_images_feature_picture',
	'edge_images_feature_picture_wrap',
	'edge_images_feature_cache',
	'edge_images_feature_htaccess_cache',
	'edge_images_feature_avatars',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

wp_clear_scheduled_hook( 'edge_images_process_localize_queue' );
