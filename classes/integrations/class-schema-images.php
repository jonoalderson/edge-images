<?php

namespace Yoast_CF_Images\Integrations;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;

/**
 * Configures our schema output to use the CF rewriter.
 */
class Schema_Images {



	/**
	 * Register the Integration
	 *
	 * @return void
	 */
	public static function register() : void {
		$instance = new self();
	}

}
