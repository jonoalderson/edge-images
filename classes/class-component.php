<?php

namespace Yoast\Plugins\CF_Images;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a UI component
 */
class Component {

	/**
	 * The component's HTML
	 *
	 * @var string
	 */
	public $html;

	/**
	 * The component's template file
	 *
	 * @var string
	 */
	public $template;

	/**
	 * Set the component's HTML
	 *
	 * @param string $html The HTML.
	 *
	 * @return self
	 */
	public function set_html( $html ) : self {
		$this->html = $html;
		return $this;
	}

	/**
	 * Get the component's HTML
	 *
	 * @return false|string The HTML
	 */
	protected function get_html() {
		if ( ! $this->has_html() ) {
			return false;
		}
		return $this->html;
	}

	/**
	 * Checks if the component has HTML
	 *
	 * @return bool
	 */
	private function has_html() : bool {
		if ( ! $this->html ) {
			return false;
		}
		return true;
	}

	/**
	 * Get the component's template filepath
	 *
	 * @return false|string The template filepath
	 */
	protected function get_template() {
		if ( ! $this->has_template() ) {
			return false;
		}
		return $this->template;
	}

	/**
	 * Checks if the component has a template
	 *
	 * @return bool
	 */
	private function has_template() : bool {
		if ( ! $this->template ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the component's template file
	 *
	 * @param string $template The template file.
	 *
	 * @return self
	 */
	public function set_template( string $template ) : self {
		$this->template = $template;
		return $this;
	}

	/**
	 * Check if the component's template file exists
	 *
	 * @return bool
	 */
	private function template_exists() : bool {
		if ( ! $this->has_template() ) {
			return false;
		}
		$template = $this->get_template();
		$exists   = locate_template( 'partials/' . $template . '.php' );
		$filepath = YOAST_CF_IMAGES_PLUGIN_DIR . '/partials/' . $this->get_template() . '.php';
		$exists   = file_exists( $filepath );
		if ( ! $exists ) {
			return false;
		}
		return true;
	}

	/**
	 * Echo the component's HTML
	 *
	 * @param bool $echo If the HTML should be echo'd.
	 *
	 * @return string|void
	 */
	public function render( $echo = true ) {
		$this->set_html_from_template();
		$this->replace_variables();
		$html = $this->get_html();
		$html = preg_replace( '/[ \t]+/', ' ', preg_replace( '/\s*$^\s*/m', "\n", $html ) );
		if ( ! $echo ) {
			return $html;
		}
		echo $html;
	}

	/**
	 * Sets the component's HTML from a template file
	 *
	 * @return void
	 */
	private function set_html_from_template() : void {
		if ( ! $this->template_exists() ) {
			return;
		}
		ob_start();
		$filepath = YOAST_CF_IMAGES_PLUGIN_DIR . '/partials/' . $this->get_template() . '.php';
		include $filepath;
		$html = ob_get_clean();
		if ( ! $html ) {
			return;
		}
		$this->set_html( $html );
	}

	/**
	 * Replace variables in the template HTML
	 *
	 * @return void
	 */
	private function replace_variables() : void {
		$html         = $this->get_html();
		$replacements = $this->get_replacement_variables();
		if ( ! $html || ! $replacements ) {
			return;
		}
		foreach ( $replacements as $k => $v ) {
			$html = str_replace( $k, $v, $html );
		}
		$this->set_html( $html );
	}

}
