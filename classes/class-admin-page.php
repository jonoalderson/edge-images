<?php
/**
 * Admin interface functionality.
 *
 * Handles the creation and management of the plugin's admin settings page.
 * This class manages all aspects of the WordPress admin interface including:
 * - Settings page creation and rendering
 * - Option registration and sanitization
 * - Admin notices and warnings
 * - Asset enqueueing
 * - Integration settings
 * - Feature management
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

namespace Edge_Images;

class Admin_Page {
	/**
	 * The option group name for settings.
	 *
	 * Used to group related settings together in the WordPress options system.
	 * This is used as the first parameter in register_setting().
	 *
	 * @since 4.0.0
	 * @var string
	 */
	private const OPTION_GROUP = 'edge_images_settings';

	/**
	 * The provider option name.
	 *
	 * Stores the selected edge provider (e.g., Cloudflare, Bunny, etc.).
	 * This setting determines which provider is used for image transformation.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	private const PROVIDER_OPTION = 'edge_images_provider';

	/**
	 * The Imgix subdomain option name.
	 *
	 * Stores the Imgix subdomain when Imgix is selected as the provider.
	 * This is required for Imgix integration to function properly.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	private const IMGIX_SUBDOMAIN_OPTION = 'edge_images_imgix_subdomain';

	/**
	 * Option name for Yoast SEO schema integration.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	private const YOAST_SCHEMA_OPTION = 'edge_images_integration_yoast_schema';

	/**
	 * Option name for Yoast SEO social integration.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	private const YOAST_SOCIAL_OPTION = 'edge_images_integration_yoast_social';

	/**
	 * Option name for Yoast SEO sitemap integration.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	private const YOAST_SITEMAP_OPTION = 'edge_images_integration_yoast_xml';

	/**
	 * The max width option name.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	private const MAX_WIDTH_OPTION = 'edge_images_max_width';

	/**
	 * The integrations section ID.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	private const INTEGRATIONS_SECTION = 'edge_images_integrations_section';

	/**
	 * The features section ID.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	private const FEATURES_SECTION = 'edge_images_features_section';

	/**
	 * The Bunny CDN subdomain option name.
	 *
	 * @since 4.5.4
	 * @var string
	 */
	private const BUNNY_SUBDOMAIN_OPTION = 'edge_images_bunny_subdomain';

	/**
	 * The domain option name.
	 *
	 * @since 5.0.0
	 * @var string
	 */
	private const DOMAIN_OPTION = 'edge_images_domain';

	/**
	 * Register the admin functionality.
	 *
	 * Initializes all admin-related hooks and filters. This includes:
	 * - Adding the settings page to the admin menu
	 * - Registering settings
	 * - Adding settings link to plugins page
	 * - Setting up admin notices
	 * - Handling settings updates
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public static function register(): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_action( 'admin_menu', [ self::class, 'add_admin_menu' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
		add_action( 'admin_post_edge_images_update_settings', [ self::class, 'handle_settings_update' ] );
		add_action( 'admin_notices', [ self::class, 'show_no_provider_notice' ] );
		
		// Add settings link to plugins page.
		$plugin_basename = plugin_basename( EDGE_IMAGES_PLUGIN_DIR . 'edge-images.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, [ self::class, 'add_settings_link' ] );
	}

	/**
	 * Display admin notice when no provider is configured.
	 *
	 * Shows a warning notice in the WordPress admin when:
	 * - No edge provider is selected
	 * - The selected provider is not properly configured
	 * This helps administrators identify and fix configuration issues.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public static function show_no_provider_notice(): void {
		
		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get current screen.
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Only show on dashboard, plugins page, and our settings page.
		$allowed_screens = [ 'dashboard', 'plugins', 'settings_page_edge-images' ];
		if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
			return;
		}

		// Check if a provider is selected.
		$current_provider = get_option( self::PROVIDER_OPTION, 'none' );
		if ( $current_provider !== 'none' ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=edge-images' );
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %1$s: Opening link tag, %2$s: Closing link tag */
					esc_html__( 'Edge Images is installed but no provider is selected. Images will not be optimized until you %1$sconfigure a provider%2$s.', 'edge-images' ),
					'<a href="' . esc_url( $settings_url ) . '">',
					'</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add settings link to plugin listing.
	 *
	 * Adds a "Settings" link to the plugin's entry in the WordPress
	 * plugins list. This provides quick access to the plugin settings
	 * directly from the plugins page.
	 *
	 * @since 4.0.0
	 * @param array $links Array of plugin action links.
	 * @return array Modified array of plugin action links.
	 */
	public static function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=edge-images' ) ),
			esc_html__( 'Settings', 'edge-images' )
		);
		
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Handle settings form submission and updates.
	 *
	 * Processes the settings form submission, validates the data,
	 * and updates the options in the database. Also handles any
	 * necessary cleanup or cache invalidation after settings changes.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public static function handle_settings_update(): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'edge-images' ) );
		}

		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'edge_images_settings-options' ) ) {
			wp_die( esc_html__( 'Invalid nonce verification.', 'edge-images' ) );
		}

		// Process provider setting
		if ( isset( $_POST[ self::PROVIDER_OPTION ] ) ) {
			$provider = sanitize_text_field( wp_unslash( $_POST[ self::PROVIDER_OPTION ] ) );
			if ( Providers::is_valid_provider( $provider ) ) {
				update_option( self::PROVIDER_OPTION, $provider );
			}
		}

		// Process Imgix subdomain setting
		if ( isset( $_POST[ self::IMGIX_SUBDOMAIN_OPTION ] ) ) {
			$subdomain = sanitize_text_field( wp_unslash( $_POST[ self::IMGIX_SUBDOMAIN_OPTION ] ) );
			$subdomain = self::sanitize_subdomain( $subdomain );
			if ( $subdomain === null ) {
				delete_option( self::IMGIX_SUBDOMAIN_OPTION );
			} else {
				update_option( self::IMGIX_SUBDOMAIN_OPTION, $subdomain );
			}
		}

		// Process Bunny CDN subdomain setting
		if ( isset( $_POST[ self::BUNNY_SUBDOMAIN_OPTION ] ) ) {
			$subdomain = sanitize_text_field( wp_unslash( $_POST[ self::BUNNY_SUBDOMAIN_OPTION ] ) );
			$subdomain = self::sanitize_subdomain( $subdomain );
			if ( $subdomain === null ) {
				delete_option( self::BUNNY_SUBDOMAIN_OPTION );
			} else {
				update_option( self::BUNNY_SUBDOMAIN_OPTION, $subdomain );
			}
		}

		// Process max width setting
		if ( isset( $_POST[ self::MAX_WIDTH_OPTION ] ) ) {
			$max_width = absint( wp_unslash( $_POST[ self::MAX_WIDTH_OPTION ] ) );
			// Enforce reasonable limits (100px to 5000px)
			if ( $max_width >= 100 && $max_width <= 5000 ) {
				update_option( self::MAX_WIDTH_OPTION, $max_width );
			}
		}

		// Process boolean integration settings with validation
		$boolean_settings = [
			self::YOAST_SCHEMA_OPTION,
			self::YOAST_SOCIAL_OPTION,
			self::YOAST_SITEMAP_OPTION,
		];

		foreach ( $boolean_settings as $option ) {
			$value = isset( $_POST[ $option ] ) ? 
					  filter_var( wp_unslash( $_POST[ $option ] ), FILTER_VALIDATE_BOOLEAN ) : 
					  false;
			update_option( $option, $value );
		}

		// Process feature settings
		foreach ( Features::get_features() as $id => $feature ) {
			$option_name = $feature['option'] ?? "edge_images_feature_{$id}";
			$value = isset( $_POST[ $option_name ] ) ? 
					  filter_var( wp_unslash( $_POST[ $option_name ] ), FILTER_VALIDATE_BOOLEAN ) : 
					  false;
			update_option( $option_name, $value );
		}

		// Process domain setting
		if (isset($_POST[Settings::DOMAIN_OPTION])) {
			$domain = sanitize_text_field(wp_unslash($_POST[Settings::DOMAIN_OPTION]));
			$domain = Settings::sanitize_domain($domain);
			update_option(Settings::DOMAIN_OPTION, $domain);
		}

		// Clear caches after settings update
		Settings::reset_cache();

		// Redirect back to settings page with success message
		wp_safe_redirect( 
			add_query_arg( 
				'settings-updated', 
				'true', 
				admin_url( 'options-general.php?page=edge-images' ) 
			) 
		);
		exit;
	}

	/**
	 * Add the plugin settings page to the admin menu.
	 *
	 * Creates a new menu item under the Settings menu for the plugin's
	 * configuration page. Sets up the page title, menu title, and
	 * necessary capabilities.
	 *
	 * @since 4.0.0
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
	 * Register all plugin settings.
	 *
	 * Sets up all settings fields, sections, and options used by the plugin.
	 * This includes:
	 * - Provider selection
	 * - Provider-specific settings (e.g., subdomains)
	 * - Integration options
	 * - Feature toggles
	 * - Custom domain setting
	 *
	 * @since 4.0.0
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

		// Register Bunny CDN subdomain setting.
		register_setting(
			self::OPTION_GROUP,
			self::BUNNY_SUBDOMAIN_OPTION,
			[
				'type'              => 'string',
				'description'       => __( 'Your Bunny CDN subdomain (e.g., your-site)', 'edge-images' ),
				'sanitize_callback' => function( $value ) {
					$sanitized = self::sanitize_subdomain( $value );
					if ( $sanitized === null ) {
						delete_option( self::BUNNY_SUBDOMAIN_OPTION );
						return false;
					}
					return $sanitized;
				},
				'show_in_rest'     => false,
			]
		);

		// Register Imgix subdomain setting.
		register_setting(
			self::OPTION_GROUP,
			self::IMGIX_SUBDOMAIN_OPTION,
			[
				'type'              => 'string',
				'description'       => __( 'Your Imgix subdomain (e.g., your-site)', 'edge-images' ),
				'sanitize_callback' => function( $value ) {
					$sanitized = self::sanitize_subdomain( $value );
					if ( $sanitized === null ) {
						delete_option( self::IMGIX_SUBDOMAIN_OPTION );
						return false;
					}
					return $sanitized;
				},
				'show_in_rest'     => false,
			]
		);

		// Register Yoast SEO integration settings.
		register_setting(
			self::OPTION_GROUP,
			self::YOAST_SCHEMA_OPTION,
			[
				'type'              => 'boolean',
				'description'       => __( 'Enable Yoast SEO schema image optimization', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_boolean' ],
				'default'          => true,
			]
		);

		register_setting(
			self::OPTION_GROUP,
			self::YOAST_SOCIAL_OPTION,
			[
				'type'              => 'boolean',
				'description'       => __( 'Enable Yoast SEO social image optimization', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_boolean' ],
				'default'          => true,
			]
		);

		register_setting(
			self::OPTION_GROUP,
			self::YOAST_SITEMAP_OPTION,
			[
				'type'              => 'boolean',
				'description'       => __( 'Enable Yoast SEO sitemap image optimization', 'edge-images' ),
				'sanitize_callback' => [ self::class, 'sanitize_boolean' ],
				'default'          => true,
			]
		);

		// Register max width setting.
		register_setting(
			self::OPTION_GROUP,
			self::MAX_WIDTH_OPTION,
			[
				'type'              => 'integer',
				'description'       => __( 'The maximum width for images when content width is not set', 'edge-images' ),
				'sanitize_callback' => 'absint',
				'default'           => 650,
				'update_callback'   => function($old_value, $value) {
					Settings::reset_cache();
				},
			]
		);

		// Register domain setting
		register_setting(
			self::OPTION_GROUP,
			Settings::DOMAIN_OPTION,
			[
				'type'              => 'string',
				'description'       => __('The domain to use for transformed images. You can usually leave this blank, unless this is a staging/development site, or if you serve your images from a different domain.', 'edge-images'),
				'sanitize_callback' => [Settings::class, 'sanitize_domain'],
				'default'           => '',
				'show_in_rest'     => true,
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

		// Add Bunny CDN subdomain field.
		add_settings_field(
			'edge_images_bunny_subdomain',
			__( 'Bunny CDN Subdomain', 'edge-images' ),
			[ self::class, 'render_bunny_subdomain_field' ],
			'edge_images',
			'edge_images_main_section',
			[
				'label_for' => self::BUNNY_SUBDOMAIN_OPTION,
				'class' => 'edge-images-provider-field edge-images-bunny-field'
			]
		);

		// Add Imgix subdomain field.
		add_settings_field(
			'edge_images_imgix_subdomain',
			__( 'Imgix Subdomain', 'edge-images' ),
			[ self::class, 'render_imgix_subdomain_field' ],
			'edge_images',
			'edge_images_main_section',
			[
				'label_for' => self::IMGIX_SUBDOMAIN_OPTION,
				'class' => 'edge-images-provider-field edge-images-imgix-field'
			]
		);

		// Add domain field
		add_settings_field(
			'edge_images_domain',
			__('Transformation Domain', 'edge-images'),
			[self::class, 'render_domain_field'],
			'edge_images',
			'edge_images_main_section'
		);

		// Add max width field.
		add_settings_field(
			'edge_images_max_width',
			__( 'Max Image Width', 'edge-images' ),
			[ self::class, 'render_max_width_field' ],
			'edge_images',
			'edge_images_main_section'
		);

		// Add features section
		add_settings_section(
			self::FEATURES_SECTION,
			__('Features', 'edge-images'),
			[self::class, 'render_features_section'],
			'edge_images'
		);

		// Add integrations section
		add_settings_section(
			self::INTEGRATIONS_SECTION,
			__( 'Integrations', 'edge-images' ),
			[ self::class, 'render_integrations_section' ],
			'edge_images'
		);

		// Register feature settings
		foreach (Features::get_features() as $id => $feature) {
			// Get the option name (either custom or default)
			$option_name = $feature['option'] ?? "edge_images_feature_{$id}";

			register_setting(
				self::OPTION_GROUP,
				$option_name,
				[
					'type' => 'boolean',
					'default' => $feature['default'],
					'sanitize_callback' => [self::class, 'sanitize_boolean'],
				]
			);
		}
	}

	/**
	 * Sanitize the provider option value.
	 *
	 * Ensures that the selected provider is valid and registered
	 * with the plugin. Returns the default provider if an invalid
	 * value is provided.
	 *
	 * @since 4.0.0
	 * @param string $value The provider value to sanitize.
	 * @return string Sanitized provider value.
	 */
	public static function sanitize_provider( string $value ): string {
		return Providers::is_valid_provider( $value ) ? $value : 'none';
	}

	/**
	 * Sanitize boolean option values.
	 *
	 * Ensures that boolean settings are properly sanitized and
	 * converted to the correct type. Handles various input formats
	 * and converts them to true/false.
	 *
	 * @since 4.0.0
	 * @param mixed $value The value to sanitize.
	 * @return bool Sanitized boolean value.
	 */
	public static function sanitize_boolean( $value ): bool {
		return (bool) $value;
	}

	/**
	 * Enqueue admin-specific assets.
	 *
	 * Loads the necessary CSS and JavaScript files for the admin interface.
	 * Only loads assets on the plugin's admin pages to avoid unnecessary
	 * resource loading.
	 *
	 * @since 4.0.0
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

		wp_enqueue_script(
			'edge-images-admin',
			EDGE_IMAGES_PLUGIN_URL . 'assets/js/admin.js',
			['jquery'],
			EDGE_IMAGES_VERSION,
			true
		);
	}

	/**
	 * Render the main admin settings page.
	 *
	 * Outputs the HTML for the plugin's settings page, including:
	 * - Settings form
	 * - Provider selection
	 * - Provider-specific options
	 * - Integration settings
	 * - Feature toggles
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public static function render_admin_page(): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
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

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="edge_images_update_settings">
					<?php
					wp_nonce_field( 'edge_images_settings-options' );
					do_settings_sections( 'edge_images' );
					submit_button();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the provider selection field.
	 *
	 * Outputs the dropdown field for selecting the edge provider.
	 * Shows all available providers and handles the current selection.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public static function render_provider_field(): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_provider = get_option( self::PROVIDER_OPTION, 'none' );
		$providers       = Providers::get_providers();
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
	 * Render the Bunny CDN subdomain field.
	 *
	 * Outputs the input field for the Bunny CDN subdomain setting.
	 * Only displayed when Bunny CDN is selected as the provider.
	 *
	 * @since 4.1.0
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_bunny_subdomain_field( array $args ): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_provider = get_option( self::PROVIDER_OPTION, 'none' );
		$subdomain = get_option( self::BUNNY_SUBDOMAIN_OPTION, '' );
		?>
		<div class="edge-images-settings-field">
			<input 
				type="text" 
				id="<?php echo esc_attr( self::BUNNY_SUBDOMAIN_OPTION ); ?>"
				name="<?php echo esc_attr( self::BUNNY_SUBDOMAIN_OPTION ); ?>"
				value="<?php echo esc_attr( $subdomain ); ?>"
				class="regular-text"
				placeholder="your-subdomain"
			>
			<p class="description">
				<?php 
				printf(
					/* translators: %s: URL to Bunny CDN documentation */
					esc_html__( 'Enter your Bunny CDN subdomain (e.g., if your Bunny CDN URL is "https://your-site.b-cdn.net", enter "your-site"). See the %s for more information.', 'edge-images' ),
					'<a href="https://docs.bunny.net/docs/stream-image-processing" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Bunny CDN documentation', 'edge-images' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the Imgix subdomain field.
	 *
	 * Outputs the input field for the Imgix subdomain setting.
	 * Only displayed when Imgix is selected as the provider.
	 *
	 * @since 4.1.0
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_imgix_subdomain_field( array $args ): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_provider = get_option( self::PROVIDER_OPTION, 'none' );
		$subdomain = get_option( self::IMGIX_SUBDOMAIN_OPTION, '' );
		?>
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
	 * Render the maximum width field.
	 *
	 * Outputs the input field for setting the maximum image width.
	 * This setting affects how images are scaled and transformed.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public static function render_max_width_field(): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$max_width = get_option( self::MAX_WIDTH_OPTION, 650 );
		?>
		<input type="number" 
			   name="<?php echo esc_attr( self::MAX_WIDTH_OPTION ); ?>" 
			   value="<?php echo esc_attr( $max_width ); ?>" 
			   class="small-text" 
			   min="1" 
			   step="1">
		<p class="description">
			<?php esc_html_e( 'If your theme does not specify a content width, this sets the maximum width that images will be scaled to. The default value is 650 pixels.', 'edge-images' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the integrations settings section.
	 *
	 * Outputs the settings section for third-party plugin integrations.
	 * Shows available integrations and their configuration options.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public static function render_integrations_section(): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$integrations = Integrations::get_registered_integrations();
		if ( empty( $integrations ) ) {
			return;
		}

		?>
		<div class="edge-images-integrations">
			<?php foreach ( $integrations as $id => $integration ) : ?>
				<div class="integration-card">
					<div class="integration-header">
						<?php if ( $integration['active'] ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color: #46B450;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-no-alt" style="color: #DC3232;"></span>
						<?php endif; ?>
						<strong><?php echo esc_html( Integrations::get_name( $id ) ); ?></strong>
					</div>

					<?php if ( $integration['active'] && $id === 'yoast-seo' ) : ?>
						<div class="integration-settings">
							<?php self::render_yoast_integration_fields(); ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<p class="description">
				<?php esc_html_e( 'Edge Images automatically integrates with supported plugins when they are active.', 'edge-images' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Yoast SEO integration fields.
	 *
	 * Outputs the settings fields specific to the Yoast SEO integration.
	 * Only displayed when Yoast SEO is active.
	 *
	 * @since 4.1.0
	 * @return void
	 */
	public static function render_yoast_integration_fields(): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$schema_enabled = get_option( self::YOAST_SCHEMA_OPTION, true );
		$social_enabled = get_option( self::YOAST_SOCIAL_OPTION, true );
		$sitemap_enabled = get_option( self::YOAST_SITEMAP_OPTION, true );
		?>
		<fieldset>
			<p>
				<label>
					<input type="checkbox" 
						name="<?php echo esc_attr( self::YOAST_SCHEMA_OPTION ); ?>" 
						value="1" 
						<?php checked( $schema_enabled ); ?>
					>
					<?php esc_html_e( 'Enable schema.org image optimization', 'edge-images' ); ?>
				</label>
			</p>

			<p>
				<label>
					<input type="checkbox" 
						name="<?php echo esc_attr( self::YOAST_SOCIAL_OPTION ); ?>" 
						value="1" 
						<?php checked( $social_enabled ); ?>
					>
					<?php esc_html_e( 'Enable social media image optimization', 'edge-images' ); ?>
				</label>
			</p>

			<p>
				<label>
					<input type="checkbox" 
						name="<?php echo esc_attr( self::YOAST_SITEMAP_OPTION ); ?>" 
						value="1" 
						<?php checked( $sitemap_enabled ); ?>
					>
					<?php esc_html_e( 'Enable XML sitemap image optimization', 'edge-images' ); ?>
				</label>
			</p>

			<p class="description">
				<?php esc_html_e( 'Edge Images can optimize images in Yoast SEO\'s schema.org output, social media tags, and XML sitemaps. Enable or disable these features as needed.', 'edge-images' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render the features section.
	 *
	 * @since      4.5.0
	 * @return void
	 */
	public static function render_features_section(): void {

		// Bail if user doesn't have sufficient permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$features = Features::get_features();
		foreach ($features as $id => $feature) {
			$option_name = $feature['option'] ?? "edge_images_feature_{$id}";
			$is_disabled = false;
			$disabled_reason = '';

			// Check if feature has server requirements
			if (isset($feature['class'])) {
				$class = $feature['class'];
				if (method_exists($class, 'is_available')) {
					$is_disabled = !$class::is_available();
					if ($is_disabled && method_exists($class, 'get_unavailable_reason')) {
						$disabled_reason = $class::get_unavailable_reason();
					}
				}
			}

			$wrapper_class = 'edge-images-settings-field';
			if ($is_disabled) {
				$wrapper_class .= ' edge-images-feature-disabled';
			}

			?>
			<div class="<?php echo esc_attr($wrapper_class); ?>" <?php echo $disabled_reason ? 'data-disabled-reason="' . esc_attr($disabled_reason) . '"' : ''; ?>>
				<label>
					<input type="checkbox" 
						name="<?php echo esc_attr($option_name); ?>" 
						value="1" 
						<?php checked(Features::is_enabled($id)); ?>
						<?php echo $is_disabled ? 'disabled' : ''; ?>
					>
					<?php echo esc_html($feature['name']); ?>
				</label>
				<?php if (!empty($feature['description'])): ?>
					<p class="description"><?php echo esc_html($feature['description']); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Sanitize a subdomain input.
	 *
	 * Cleans and validates subdomain input.
	 * Returns null if the subdomain is empty or invalid.
	 *
	 * @since 4.5.0
	 * @param string|null $value The subdomain value to sanitize.
	 * @return string|null Sanitized subdomain value or null if empty/invalid.
	 */
	public static function sanitize_subdomain( ?string $value ): ?string {
		
		// Return null for any "empty" value
		if ( $value === null || $value === '' || trim( $value ) === '' ) {
			return null;
		}

		$subdomain = trim( sanitize_text_field( $value ) );
		
		// Return null if sanitization resulted in an empty string
		if ( empty( $subdomain ) ) {
			return null;
		}

		return preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain ) ? $subdomain : null;
	}

	/**
	 * Render the domain field.
	 *
	 * Outputs the input field for setting a custom domain for transformed images.
	 * Only displayed when the selected provider does not use a hosted subdomain.
	 *
	 * @since 5.4.0
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_domain_field(array $args): void {

		// Bail if user doesn't have sufficient permissions.
		if (!current_user_can('manage_options')) {
			return;
		}

		$current_provider = Settings::get_provider();
		$provider_class = Providers::get_provider_class($current_provider);

		// Hide field if provider uses a hosted subdomain
		if ($provider_class::uses_hosted_subdomain()) {
			return;
		}

		$domain = Settings::get_domain();
		?>
		<div class="edge-images-settings-field">
			<input 
				type="text" 
				id="<?php echo esc_attr(Settings::DOMAIN_OPTION); ?>"
				name="<?php echo esc_attr(Settings::DOMAIN_OPTION); ?>"
				value="<?php echo esc_attr($domain); ?>"
				class="regular-text"
				placeholder="<?php echo esc_attr(get_site_url()); ?>"
			>
			<p class="description">
				<?php esc_html_e('The domain to use for transformed images. You can usually leave this blank, unless this is a staging/development site, or if you serve your images from a different domain.', 'edge-images'); ?>
			</p>
		</div>
		<?php
	}
} 