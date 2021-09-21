<?php

namespace Yoast_CF_Images\Integrations;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;

/**
 * Configures hero image preload headers (using the CF rewriter).
 */
class Preloads {

	/**
	 * Register the Integration
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
	}

}
