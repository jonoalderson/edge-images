<?php

namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Helpers as Helpers;

/**
 * Configures the og:image to use the CF rewriter.
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
