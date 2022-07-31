<?php

namespace Edge_Images\Elements;

use Edge_Images\Helpers;

/**
 * Define how an <picture> should behave.
 */
class Picture {

	/**
	 * Storage for attribute states & calculations
	 *
	 * @var array
	 */
	private array $internals = array();

	/**
	 * Storage for any child elements (<source(s)> and <img>)
	 *
	 * @var array
	 */
	private array $children = array();

	/**
	 * The class attribute
	 *
	 * @var string
	 */
	public string $class;

	/**
	 * The style attribute
	 *
	 * @var string
	 */
	public string $style;

	/**
	 * Create our image
	 *
	 * @return void
	 */
	public function __construct( array $args = array() ) {

		$defaults = array(
			'internals' => array(
				'layout'  => 'responsive',
				'ratio'   => array(
					'width'  => 1,
					'height' => 1,
				),
				'width'   => null,
				'height'  => null,
				'classes' => array(),
				'styles'  => array(),
			),
			'children'  => array(),
			'class'     => '',
			'style'     => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$this->internals = $args['internals'];
		$this->class     = $args['class'];
		$this->style     = $args['style'];

		$this->init_internals();
		$this->init_attrs();
	}

	/**
	 * Init internal processes and values
	 *
	 * @return void
	 */
	private function init_internals() : void {
		$this->init_ratio_style();
		$this->init_max_dimensions_for_fixed_layouts();
		$this->init_classes();
	}

	private function init_attrs() : void {
		$this->init_class();
		$this->init_style();
	}

	/**
	 * Init the class attr
	 *
	 * @return void
	 */
	private function init_class() : void {
		$this->class = Helpers::classes_array_to_string( $this->internals['classes'] );
	}

	/**
	 * Init the style attr
	 *
	 * @return void
	 */
	private function init_style() : void {
		$this->style = implode( ';', $this->internals['styles'] );
	}

	/**
	 * Init the internal classes values
	 *
	 * @return void
	 */
	private function init_classes() : void {
		if ( ! ( $this->has_class() && $this->validate_class() ) ) {
			return;
		}

		$classes = $this->class;
		if ( is_string( $this->class ) ) {
			$classes = explode( ' ', $this->class );
		}

		$this->internals['classes'] = $classes;
	}

	/**
	 * Checks if a class is set
	 *
	 * @return bool
	 */
	private function has_class() : bool {
		if ( ! isset( $this->class ) || ! $this->class ) {
			return false;
		}
		if ( is_array( $this->class ) && empty( $this->class ) ) {
			return false;
		}
		if ( is_string( $this->class ) && $this->class === '' ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if a style is set
	 *
	 * @return bool
	 */
	private function has_style() : bool {
		if ( ! isset( $this->style ) || ! $this->style ) {
			return false;
		}
		if ( is_array( $this->style ) && empty( $this->style ) ) {
			return false;
		}
		if ( is_string( $this->style ) && $this->style === '' ) {
			return false;
		}
		return true;
	}


	/**
	 * Add the ratio to the style attr
	 *
	 * @return void
	 */
	private function init_ratio_style() : void {
		if ( ! ( $this->has_ratio() && $this->validate_ratio() ) ) {
			return;
		}

		$this->internals['styles'][] = '--aspect-ratio:' . $this->internals['ratio']['width'] . '/' . $this->internals['ratio']['height'];
	}

	/**
	 * When the image has a fixed layout, we add inline max height and width values.
	 *
	 * @return void
	 */
	private function init_max_dimensions_for_fixed_layouts() : void {
		if ( ! ( $this->has_layout() && $this->validate_layout() ) ) {
			return;
		}

		// Bail if this isn't a fixed layout image.
		if ( $this->internals['layout'] !== 'fixed' ) {
			return;
		}

		if ( $this->has_width() ) {
			$this->internals['styles'][] = sprintf( 'max-width:%dpx', $this->internals['width'] );
		}

		if ( $this->has_height() ) {
			$this->internals['styles'][] = sprintf( 'max-height:%dpx', $this->internals['height'] );
		}
	}

	/**
	 * Checks if a width is set
	 *
	 * @return bool
	 */
	private function has_width() : bool {
		if ( ! $this->internals['width'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if a height is set
	 *
	 * @return bool
	 */
	private function has_height() : bool {
		if ( ! $this->internals['height'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if a layout is set
	 *
	 * @return bool
	 */
	private function has_layout() : bool {
		if ( ! isset( $this->internals['layout'] ) || ! $this->internals['layout'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Check if a ratio attr is set.
	 *
	 * @return bool
	 */
	private function has_ratio() : bool {
		if ( ! isset( $this->ratio ) || ! $this->ratio ) {
			return false;
		}
		return true;
	}

	/**
	 * Validate that a ratio attr is correctly formed
	 *
	 * @return bool
	 */
	private function validate_ratio() : bool {

		$ratio = $this->internals['ratio'];

		// Bail if this isn't an array.
		if ( ! is_array( $ratio ) ) {
			return false;
		}

		// Bail if the array was empty.
		if ( empty( $ratio ) ) {
			return false;
		}

		// Bail if the array was an unexpted size.
		if ( count( $ratio ) != 2 ) {
			return false;
		}

		// Bail if we're missing a width.
		if ( ! isset( $ratio['width'] ) || ! $ratio['width'] ) {
			return false;
		}

		// Bail if we're missing a height.
		if ( ! isset( $ratio['height'] ) || ! $ratio['height'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate that a layout attr is correctly formed
	 *
	 * @return bool
	 */
	private function validate_layout() : bool {

		// Bail if this isn't a valid value
		if ( ! in_array(
			$this->internals['layout'],
			array( 'responsive', 'fixed' ),
			true
		) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate that a class attr is correctly formed
	 *
	 * @return bool
	 */
	private function validate_class() : bool {

		$class = $this->class;

		// Bail if this isn't a string or an array
		if ( ! ( is_string( $class ) || is_array( $class ) ) ) {
			return false;
		}

		// Bail if it's an empty array.
		if ( is_array( $class ) && empty( $class ) ) {
			return false;
		}

		// Bail if it's an empty string
		if ( is_string( $class ) && $class === '' ) {
			return false;
		}

		return true;
	}

}
