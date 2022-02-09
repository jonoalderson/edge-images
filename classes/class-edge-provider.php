<?php

namespace Edge_Images;

use Edge_Images\Helpers;

/**
 * Describes an edge provider.
 *
 * TODO: Provide methods for validating the various image properties / args.
 */
class Edge_Provider {

	/**
	 * Create the provider
	 *
	 * @param string $path The path to the image.
	 * @param array  $args The arguments.
	 */
	public function __construct( string $path, array $args = array() ) {
		$this->path = $path;
		$this->args = $args;
	}

}
