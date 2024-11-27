<?php
/**
 * Admin interface functionality.
 *
 * Handles the creation and management of the plugin's admin settings page.
 * Provides UI for selecting edge providers and configuring image options.
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @since      1.0.0
 */

namespace Edge_Images;

/**
 * Handles the admin settings page UI and functionality.
 *
 * @since 4.0.0
 */
class Admin_Page {
	/**
	 * The option group name for settings.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	private const OPTION_GROUP = 'edge_images_settings';

	/**
	 * The provider option name.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	private const PROVIDER_OPTION = 'edge_images_provider';

	/**
	 * The picture wrap option name.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	private const PICTURE_WRAP_OPTION = 'edge_images_disable_picture_wrap';

	/**
	 * The Imgix subdomain option name.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	private const IMGIX_SUBDOMAIN_OPTION = 'edge_images_imgix_subdomain';

	/**
	 * The Yoast SEO schema integration option name.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	private const YOAST_SCHEMA_OPTION = 'edge_images_yoast_disable_schema_images';

	/**
	 * The Yoast SEO social integration option name.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	private const YOAST_SOCIAL_OPTION = 'edge_images_yoast_disable_social_images';

	/**
	 * The Yoast SEO sitemap integration option name.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	private const YOAST_SITEMAP_OPTION = 'edge_images_yoast_disable_xml_sitemap_images';

	/**
	 * Registers the admin page and its hooks.
	 *
	 * Sets up the admin menu, registers settings, and enqueues assets.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	public static function register(): void {
		// Only load if user has sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_action( 'admin_menu', [ self::class, 'add_admin_menu' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
	}

	/**
	 * Adds the admin menu item.
	 *
	 * Creates a new settings page under the Settings menu.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	public static function add_admin_menu(): void {
		add_options_page(
			__( 'Edge Images Settings', 'edge-images' ),
			__( 'Edge Images', 'edge-images' ),
			'manage_options',
			'edge-images',
			[ self::class, 'render_admin_page' ]
		);
	}

	/**
	 * Registers the plugin settings.
	 *
	 * Sets up settings fields and sections for the admin interface.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	public static function register_settings(): void {
		// Register provider setting.
		register_setting(
			self::OPTION_GROUP,
			self::PROVIDER_OPTION,
			[
				'type'              => 'string',
				'description'       => __( 'The edge provider to use for image optimization', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_provider' ],
				'default'          => 'none',
			]
		);

		// Register picture wrap setting.
		register_setting(
			self::OPTION_GROUP,
			self::PICTURE_WRAP_OPTION,
			[
				'type'              => 'boolean',
				'description'       => __( 'Disable wrapping images in picture element', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_boolean' ],
				'default'          => false,
			]
		);

		// Register Imgix subdomain setting.
		register_setting(
			self::OPTION_GROUP,
			self::IMGIX_SUBDOMAIN_OPTION,
			[
				'type'              => 'string',
				'description'       => __( 'Your Imgix subdomain (e.g., your-site)', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_subdomain' ],
				'default'          => '',
			]
		);

		// Register Yoast SEO integration settings.
		register_setting(
			self::OPTION_GROUP,
			self::YOAST_SCHEMA_OPTION,
			[
				'type'              => 'boolean',
				'description'       => __( 'Disable Yoast SEO schema image optimization', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_boolean' ],
				'default'          => false,
			]
		);

		register_setting(
			self::OPTION_GROUP,
			self::YOAST_SOCIAL_OPTION,
			[
				'type'              => 'boolean',
				'description'       => __( 'Disable Yoast SEO social image optimization', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_boolean' ],
				'default'          => false,
			]
		);

		register_setting(
			self::OPTION_GROUP,
			self::YOAST_SITEMAP_OPTION,
			[
				'type'              => 'boolean',
				'description'       => __( 'Disable Yoast SEO sitemap image optimization', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_boolean' ],
				'default'          => false,
			]
		);

		// Add main section.
		add_settings_section(
			'edge_images_main_section',
			'',
			'__return_false',
			'edge_images'
		);

		// Add provider field.
		add_settings_field(
			'edge_images_provider',
			__( 'Edge Provider', 'edge-images' ),
			[ self::class, 'render_provider_field' ],
			'edge_images',
			'edge_images_main_section'
		);

		// Add picture wrap field.
		add_settings_field(
			'edge_images_picture_wrap',
			__( 'Image Wrapping', 'edge-images' ),
			[ self::class, 'render_picture_wrap_field' ],
			'edge_images',
			'edge_images_main_section'
		);

		// Add Imgix subdomain field.
		add_settings_field(
			'edge_images_imgix_subdomain',
			__( 'Imgix Subdomain', 'edge-images' ),
			[ self::class, 'render_imgix_subdomain_field' ],
			'edge_images',
			'edge_images_main_section',
			[ 'class' => 'edge-images-imgix-field' ]
		);

		// Add Yoast SEO integration settings field.
		add_settings_field(
			'edge_images_yoast_integrations',
			__( 'Yoast SEO Integration', 'edge-images' ),
			[ self::class, 'render_yoast_integration_fields' ],
			'edge_images',
			'edge_images_main_section'
		);
	}

	/**
	 * Sanitizes the provider option.
	 *
	 * Ensures the selected provider is valid.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $value The value to sanitize.
	 * @return string The sanitized value.
	 */
	public static function sanitize_provider( string $value ): string {
		return Provider_Registry::is_valid_provider( $value ) ? $value : 'none';
	}

	/**
	 * Sanitizes a boolean option.
	 *
	 * @since 4.0.0
	 * 
	 * @param mixed $value The value to sanitize.
	 * @return bool The sanitized boolean value.
	 */
	public static function sanitize_boolean( $value ): bool {
		return (bool) $value;
	}

	/**
	 * Sanitizes the subdomain value.
	 *
	 * Ensures the subdomain contains only valid characters.
	 *
	 * @since 4.1.0
	 * 
	 * @param string $value The value to sanitize.
	 * @return string The sanitized subdomain.
	 */
	public static function sanitize_subdomain( string $value ): string {
		return sanitize_key( $value );
	}

	/**
	 * Enqueues admin assets.
	 *
	 * Loads CSS and JavaScript files for the admin interface.
	 *
	 * @since 4.0.0
	 * 
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook ): void {
		if ( 'settings_page_edge-images' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'edge-images-admin',
			EDGE_IMAGES_PLUGIN_URL . 'assets/css/admin.min.css',
			[],
			EDGE_IMAGES_VERSION,
			'all'
		);
	}

	/**
	 * Renders the admin page.
	 *
	 * Outputs the HTML for the settings page interface.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	public static function render_admin_page(): void {
		?>
		<div class="wrap edge-images-wrap">
			<h1><?php esc_html_e( 'Edge Images Settings', 'edge-images' ); ?></h1>
			
			<div class="edge-images-container">
				<div class="edge-images-intro">
					<p>
						<?php esc_html_e( 'Edge Images automatically optimizes your images by routing them through an edge provider. This can significantly improve your page load times and Core Web Vitals scores.', 'edge-images' ); ?>
					</p>
					<p>
						<?php 
						printf(
							/* translators: %s: URL to documentation */
							esc_html__( 'Select your preferred edge provider below. Each provider has different features and requirements. Learn more in our %s.', 'edge-images' ),
							'<a href="https://github.com/jonoalderson/edge-images#readme" target="_blank" rel="noopener noreferrer">' . esc_html__( 'documentation', 'edge-images' ) . '</a>'
						);
						?>
					</p>
				</div>

				<form method="post" action="options.php">
					<?php
					settings_fields( 'edge_images_settings' );
					do_settings_sections( 'edge_images' );
					submit_button();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the provider selection field.
	 *
	 * Creates the radio button interface for selecting an edge provider.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	public static function render_provider_field(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_provider = get_option( self::PROVIDER_OPTION, 'none' );
		$providers       = Provider_Registry::get_providers();

		wp_nonce_field( 'edge_images_provider_update', 'edge_images_nonce' );
		?>
		<div class="edge-images-provider-selector">
			<?php foreach ( $providers as $value => $label ) : ?>
				<label class="edge-images-provider-option">
					<input type="radio" 
						name="<?php echo esc_attr( self::PROVIDER_OPTION ); ?>" 
						value="<?php echo esc_attr( $value ); ?>"
						<?php checked( $current_provider, $value ); ?>
					>
					<span class="edge-images-provider-card">
						<span class="edge-images-provider-name"><?php echo esc_html( $label ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Renders the picture wrap field.
	 *
	 * Creates the checkbox interface for toggling picture element wrapping.
	 *
	 * @since 4.0.0
	 * 
	 * @return void
	 */
	public static function render_picture_wrap_field(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$disabled = get_option( self::PICTURE_WRAP_OPTION, false );
		?>
		<label>
			<input type="checkbox" 
				name="<?php echo esc_attr( self::PICTURE_WRAP_OPTION ); ?>" 
				value="1" 
				<?php checked( $disabled ); ?>
			>
			<?php esc_html_e( 'Disable wrapping images in picture elements', 'edge-images' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'By default, images are wrapped in picture elements to provide better responsive behavior. Disable this if you want simpler markup or if it conflicts with your theme.', 'edge-images' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the Imgix subdomain field.
	 *
	 * Creates the text input for configuring the Imgix subdomain.
	 * Only displays when Imgix is selected as the provider.
	 *
	 * @since 4.1.0
	 * 
	 * @param array $args The field arguments.
	 * @return void
	 */
	public static function render_imgix_subdomain_field( array $args ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_provider = get_option( self::PROVIDER_OPTION, 'none' );
		$subdomain = get_option( self::IMGIX_SUBDOMAIN_OPTION, '' );
		$display = $current_provider === 'imgix' ? 'block' : 'none';
		?>
		<script>
		jQuery(document).ready(function($) {
			// Show/hide Imgix settings based on provider selection
			function toggleImgixSettings() {
				if ($('input[name="<?php echo esc_js( self::PROVIDER_OPTION ); ?>"]:checked').val() === 'imgix') {
					$('.edge-images-imgix-field').show();
				} else {
					$('.edge-images-imgix-field').hide();
				}
			}

			// Initial state
			toggleImgixSettings();

			// On change
			$('input[name="<?php echo esc_js( self::PROVIDER_OPTION ); ?>"]').change(toggleImgixSettings);
		});
		</script>

		<div class="edge-images-settings-field">
			<input 
				type="text" 
				id="<?php echo esc_attr( self::IMGIX_SUBDOMAIN_OPTION ); ?>"
				name="<?php echo esc_attr( self::IMGIX_SUBDOMAIN_OPTION ); ?>"
				value="<?php echo esc_attr( $subdomain ); ?>"
				class="regular-text"
				placeholder="your-subdomain"
			>
			<p class="description">
				<?php 
				printf(
					/* translators: %s: URL to Imgix documentation */
					esc_html__( 'Enter your Imgix subdomain (e.g., if your Imgix URL is "https://your-site.imgix.net", enter "your-site"). See the %s for more information.', 'edge-images' ),
					'<a href="https://docs.imgix.com/setup/quick-start" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Imgix documentation', 'edge-images' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the Yoast SEO integration fields.
	 *
	 * Creates checkboxes for controlling Yoast SEO integration features.
	 *
	 * @since 4.1.0
	 * 
	 * @return void
	 */
	public static function render_yoast_integration_fields(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only show these options if Yoast SEO is active
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			?>
			<p class="description">
				<?php esc_html_e( 'These settings require Yoast SEO to be installed and activated.', 'edge-images' ); ?>
			</p>
			<?php
			return;
		}

		$schema_disabled = get_option( self::YOAST_SCHEMA_OPTION, false );
		$social_disabled = get_option( self::YOAST_SOCIAL_OPTION, false );
		$sitemap_disabled = get_option( self::YOAST_SITEMAP_OPTION, false );
		?>
		<div class="edge-images-settings-field">
			<fieldset>
				<legend class="screen-reader-text">
					<?php esc_html_e( 'Yoast SEO Integration Settings', 'edge-images' ); ?>
				</legend>

				<p>
					<label>
						<input type="checkbox" 
							name="<?php echo esc_attr( self::YOAST_SCHEMA_OPTION ); ?>" 
							value="1" 
							<?php checked( $schema_disabled ); ?>
						>
						<?php esc_html_e( 'Disable schema.org image optimization', 'edge-images' ); ?>
					</label>
				</p>

				<p>
					<label>
						<input type="checkbox" 
							name="<?php echo esc_attr( self::YOAST_SOCIAL_OPTION ); ?>" 
							value="1" 
							<?php checked( $social_disabled ); ?>
						>
						<?php esc_html_e( 'Disable social media image optimization', 'edge-images' ); ?>
					</label>
				</p>

				<p>
					<label>
						<input type="checkbox" 
							name="<?php echo esc_attr( self::YOAST_SITEMAP_OPTION ); ?>" 
							value="1" 
							<?php checked( $sitemap_disabled ); ?>
						>
						<?php esc_html_e( 'Disable XML sitemap image optimization', 'edge-images' ); ?>
					</label>
				</p>

				<p class="description">
					<?php esc_html_e( 'By default, Edge Images optimizes images in Yoast SEO\'s schema.org output, social media tags, and XML sitemaps. Disable any of these features if you want to preserve the original image URLs.', 'edge-images' ); ?>
				</p>
			</fieldset>
		</div>
		<?php
	}
} 