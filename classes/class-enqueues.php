<?php

namespace Yoast\Plugins\CF_Images;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the plugins CSS & JS assets
 */
class Enqueues {

	/**
	 * Register the integration
	 */
	public static function register() {
		$instance = new self();
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue the plugin's styles
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'cf_image', plugin_dir_url( YOAST_CF_IMAGES_PLUGIN_FILE ) . 'assets/style.css', array(), YOAST_CF_IMAGES_VERSION );
	}
}
