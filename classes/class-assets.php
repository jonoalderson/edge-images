<?php

namespace Edge_Images;

use Edge_Images\Helpers;

/**
 * Enqueues our assets
 */
class Assets {

	/**
	 * Register the integration
	 *
	 * @return void
	 */
	public static function register() : void {

		// Bail if we shouldn't be transforming images.
		if ( ! Helpers::should_transform_images() ) {
			return;
		}

		$instance = new self();
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_css' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_js' ), 2 );
	}

	/**
	 * Enqueue our CSS (and render it inline)
	 *
	 * @return void
	 */
	public function enqueue_css() : void {

		// Get our stylesheet.
		$stylesheet_path = Helpers::STYLES_PATH . '/images.css';
		if ( ! file_exists( $stylesheet_path ) ) {
			return; // Bail if we couldn't find it.
		}

		// Enqueue a dummy style to attach our inline styles to.
		wp_register_style( 'edge-images', false, array(), EDGE_IMAGES_VERSION );
		wp_enqueue_style( 'edge-images' );

		// Output the stylesheet inline.
		$stylesheet = file_get_contents( $stylesheet_path );
		wp_add_inline_style( 'edge-images', $stylesheet );
	}

	/**
	 * Enqueue our JS (and output it inline)
	 *
	 * @return void
	 */
	public function enqueue_js() : void {

		// Get our script.
		$script_path = Helpers::SCRIPTS_PATH . '/main.min.js';
		if ( ! file_exists( $script_path ) ) {
			return; // Bail if we couldn't find it.
		}

		// Enqueue a dummy style to attach our inline styles to.
		wp_register_script( 'edge-images', false, array(), EDGE_IMAGES_VERSION, true );
		wp_enqueue_script( 'edge-images' );

		// Output the stylesheet inline.
		$script = file_get_contents( $script_path );
		wp_add_inline_script( 'edge-images', $script );
	}

}
