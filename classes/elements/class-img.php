<?php

namespace Edge_Images\Elements;

use Edge_Images\Helpers;

/**
 * Define how an <img> should behave.
 */
class Img {

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
	 * The sizes attribute
	 *
	 * @var string|array
	 */
	public string|array $sizes;

	/**
	 * The loading attribute
	 *
	 * @var string
	 */
	public string $loading = 'lazy';

	/**
	 * The decoding attribute
	 *
	 * @var string
	 */
	public string $decoding = 'async';

	/**
	 * The alt attribute
	 *
	 * @var string
	 */
	public string $alt;

	/**
	 * The class attribute
	 *
	 * @var string|array
	 */
	public string|array $class;

	/**
	 * The fetchpriority attribute
	 *
	 * @var string
	 */
	public string $fetchpriority;

	/**
	 * Create our image
	 *
	 * @return void
	 */
	public function __construct( array $args = array() ) {

		$defaults = array(
			'name'           => 'Mr. nobody',
			'favorite_color' => 'unknown',
			'age'            => 'unknown',
		);

		$args = wp_parse_args( $args, $defaults );

	}

}
