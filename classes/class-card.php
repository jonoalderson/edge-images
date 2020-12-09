<?php

namespace Yoast\Plugins\CF_Images;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a Card component
 */
class Card extends Component {

	/**
	 * The card image
	 *
	 * @var CF_Image
	 */
	public $image;

	/**
	 * The card's template file
	 *
	 * @var string
	 */
	public $template = 'card';

	/**
	 * Construct the Card
	 */
	public function __construct() {
		// Silence is golden.
	}

	/**
	 * Set the image by passing an attachment ID
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return self
	 */
	public function set_image_by_id( int $attachment_id ) : self {
		$image = new CF_Image( $attachment_id );
		$this->set_image( $image );
		return $this;
	}

	/**
	 * Get the card image
	 *
	 * @return false|CF_Image The image
	 */
	protected function get_image() {
		if ( ! $this->has_image() ) {
			return false;
		}
		return $this->image;
	}

	/**
	 * Sets the card image
	 *
	 * @param CF_Image $image The image.
	 *
	 * @return self
	 */
	public function set_image( CF_Image $image ) : self {
		$this->image = $image;
		return $this;
	}

	/**
	 * Checks if the card has an image
	 *
	 * @return bool
	 */
	private function has_image() : bool {
		if ( ! $this->image ) {
			return false;
		}
		return true;
	}

	/**
	 * Defines template replacement variables
	 *
	 * @return array The variables and their replacements
	 */
	protected function get_replacement_variables() : array {
		$replacements              = array();
		$replacements['{{image}}'] = $this->get_image()->render( false );
		return $replacements;
	}

}
