<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Manages our admin interfaces.
 */
class Admin {

	const OPTION_PREFIX = 'edge_images_';

	/**
	 * Register the integration
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
		add_action( 'admin_menu', array( $instance, 'add_menu' ) );
	}

	/**
	 * Add our custom menu option to the media section
	 *
	 * @return void
	 */
	public function add_menu() : void {
		add_submenu_page( 'upload.php', 'Edge Images', 'Edge Images', 'manage_options', 'edge_images', array( $this, 'render' ), 99 );
	}

	/**
	 * Render our view
	 *
	 * @return void
	 */
	public function render() : void {
		include_once 'views/admin.php';
	}

}
