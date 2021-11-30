<?php

namespace Edge_Images\Integrations\Yoast_SEO;

use Edge_Images\Helpers;

/**
 * Configures XML sitemaps to use the CF rewriter.
 */
class XML_Sitemaps {

	/**
	 * Register the Integration
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
	}

}
