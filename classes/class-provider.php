<?php

namespace Edge_Images;

use Edge_Images\Helpers;

/**
 * Describes an edge provider.
 */
class Provider {

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

	public function valid_args() {

	}

}
