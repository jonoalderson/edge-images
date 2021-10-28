<?php

namespace Yoast_CF_Images\Integrations;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;

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
