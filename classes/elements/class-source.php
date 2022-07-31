<?php

namespace Edge_Images\Elements;

use Edge_Images\Helpers;

/**
 * Define how an <source> should behave.
 */
class Source {

	/**
	 * The src attribute
	 *
	 * @var string
	 */
	public string $src;

	/**
	 * The media attribute
	 *
	 * @var string|array
	 */
	public string|array $media;

	/**
	 * The height attribute
	 *
	 * @var int
	 */
	public int $height;

	/**
	 * The width attribute
	 *
	 * @var int
	 */
	public int $width;

	/**
	 * The srcset attribute
	 *
	 * @var string|array
	 */
	public string|array $srcset;

	/**
	 * Create our image
	 *
	 * @return void
	 */
	public function __construct( array $args = array() ) {
		// Silence is golden.
	}

}
