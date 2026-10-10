<?php
/**
 * Admin settings page.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Registers and renders the settings screen.
 */
class Admin_Page {

	/**
	 * Register the admin page.
	 *
	 * @return void
	 */
	public static function register(): void {

		$instance = new self();
		\add_action( 'admin_menu', [ $instance, 'add_menu_page' ] );
		\add_action( 'admin_init', [ $instance, 'register_fields' ] );
	}

	/**
	 * Add the settings page under Settings.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {

		\add_options_page(
			__( 'Edge Images', 'edge-images' ),
			__( 'Edge Images', 'edge-images' ),
			'manage_options',
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Register settings fields.
	 *
	 * @return void
	 */
	public function register_fields(): void {

		\add_settings_section(
			'edge_images_main',
			__( 'Provider', 'edge-images' ),
			static function (): void {
				echo '<p>' . \esc_html__( 'Choose an edge provider with image transformation enabled, then optionally store local copies of transformed images.', 'edge-images' ) . '</p>';
			},
			EDGE_IMAGES_ADMIN_PAGE_SLUG
		);

		\add_settings_field(
			Settings::PROVIDER_OPTION,
			__( 'Edge provider', 'edge-images' ),
			[ $this, 'render_provider_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_main'
		);

		\add_settings_field(
			Settings::DOMAIN_OPTION,
			__( 'Transform domain', 'edge-images' ),
			[ $this, 'render_domain_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_main'
		);

		\add_settings_field(
			'edge_images_bunny_subdomain',
			__( 'Bunny subdomain', 'edge-images' ),
			[ $this, 'render_bunny_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_main'
		);

		\add_settings_field(
			'edge_images_imgix_subdomain',
			__( 'Imgix subdomain', 'edge-images' ),
			[ $this, 'render_imgix_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_main'
		);

		\add_settings_field(
			'edge_images_imgproxy_url',
			__( 'Imgproxy URL', 'edge-images' ),
			[ $this, 'render_imgproxy_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_main'
		);

		\add_settings_field(
			Settings::LOCALIZE_OPTION,
			__( 'Localize images', 'edge-images' ),
			[ $this, 'render_localize_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_main'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {

		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo \esc_html__( 'Edge Images', 'edge-images' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				\settings_fields( Settings::OPTION_GROUP );
				\do_settings_sections( EDGE_IMAGES_ADMIN_PAGE_SLUG );
				\submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Provider select field.
	 *
	 * @return void
	 */
	public function render_provider_field(): void {

		$current = Settings::get_provider();
		echo '<select name="' . \esc_attr( Settings::PROVIDER_OPTION ) . '" id="' . \esc_attr( Settings::PROVIDER_OPTION ) . '">';
		foreach ( Providers::get_providers() as $slug => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				\esc_attr( $slug ),
				\selected( $current, $slug, false ),
				\esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Domain field.
	 *
	 * @return void
	 */
	public function render_domain_field(): void {

		printf(
			'<input type="url" class="regular-text" name="%1$s" id="%1$s" value="%2$s" placeholder="https://example.com" />
			<p class="description">%3$s</p>',
			\esc_attr( Settings::DOMAIN_OPTION ),
			\esc_attr( Settings::get_domain() ),
			\esc_html__( 'Optional. Defaults to the site URL. Used by Cloudflare and Accelerated Domains.', 'edge-images' )
		);
	}

	/**
	 * Bunny subdomain field.
	 *
	 * @return void
	 */
	public function render_bunny_field(): void {

		printf(
			'<input type="text" class="regular-text" name="edge_images_bunny_subdomain" id="edge_images_bunny_subdomain" value="%1$s" placeholder="your-zone" />
			<p class="description">%2$s</p>',
			\esc_attr( (string) Settings::get_option( 'edge_images_bunny_subdomain', '' ) ),
			\esc_html__( 'Required when using Bunny CDN (without .b-cdn.net).', 'edge-images' )
		);
	}

	/**
	 * Imgix subdomain field.
	 *
	 * @return void
	 */
	public function render_imgix_field(): void {

		printf(
			'<input type="text" class="regular-text" name="edge_images_imgix_subdomain" id="edge_images_imgix_subdomain" value="%1$s" placeholder="your-site" />
			<p class="description">%2$s</p>',
			\esc_attr( (string) Settings::get_option( 'edge_images_imgix_subdomain', '' ) ),
			\esc_html__( 'Required when using Imgix (without .imgix.net).', 'edge-images' )
		);
	}

	/**
	 * Imgproxy URL field.
	 *
	 * @return void
	 */
	public function render_imgproxy_field(): void {

		printf(
			'<input type="url" class="regular-text" name="edge_images_imgproxy_url" id="edge_images_imgproxy_url" value="%1$s" placeholder="https://imgproxy.example.com" />
			<p class="description">%2$s</p>',
			\esc_attr( (string) Settings::get_option( 'edge_images_imgproxy_url', '' ) ),
			\esc_html__( 'Required when using Imgproxy.', 'edge-images' )
		);
	}

	/**
	 * Localization checkbox.
	 *
	 * @return void
	 */
	public function render_localize_field(): void {

		printf(
			'<input type="hidden" name="%1$s" value="0" />
			<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>
			<p class="description">%4$s</p>',
			\esc_attr( Settings::LOCALIZE_OPTION ),
			\checked( Settings::is_localization_enabled(), true, false ),
			\esc_html__( 'Store transformed images locally', 'edge-images' ),
			\esc_html__( 'When enabled, edge URLs are used until local 1x/1.5x/2x copies are fetched and saved under uploads. Off by default.', 'edge-images' )
		);
	}
}
