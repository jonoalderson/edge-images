<?php
/**
 * Admin page functionality.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Handles the admin settings page UI and functionality.
 *
 * @since 4.0.9
 */
class Admin_Page {
	/**
	 * The option group name.
	 *
	 * @var string
	 */
	private const OPTION_GROUP = 'edge_images_settings';

	/**
	 * The provider option name.
	 *
	 * @var string
	 */
	private const PROVIDER_OPTION = 'edge_images_provider';

	/**
	 * The picture wrap option name.
	 *
	 * @var string
	 */
	private const PICTURE_WRAP_OPTION = 'edge_images_disable_picture_wrap';

	/**
	 * Registers the admin page and its hooks.
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
	 * @return void
	 */
	public static function register_settings(): void {
		// Register provider setting
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

		// Register picture wrap setting
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

		// Add main section
		add_settings_section(
			'edge_images_main_section',
			'',
			'__return_false',
			'edge_images'
		);

		// Add provider field
		add_settings_field(
			'edge_images_provider',
			__( 'Edge Provider', 'edge-images' ),
			[ self::class, 'render_provider_field' ],
			'edge_images',
			'edge_images_main_section'
		);

		// Add picture wrap field
		add_settings_field(
			'edge_images_picture_wrap',
			__( 'Image Wrapping', 'edge-images' ),
			[ self::class, 'render_picture_wrap_field' ],
			'edge_images',
			'edge_images_main_section'
		);
	}

	/**
	 * Sanitizes the provider option.
	 *
	 * @param string $value The value to sanitize.
	 * @return string
	 */
	public static function sanitize_provider( string $value ): string {
		return Provider_Registry::is_valid_provider( $value ) ? $value : 'none';
	}

	/**
	 * Sanitizes a boolean option.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return bool
	 */
	public static function sanitize_boolean( $value ): bool {
		return (bool) $value;
	}

	/**
	 * Enqueues admin assets.
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
	 * @return void
	 */
	public static function render_admin_page(): void {
		?>
		<div class="wrap edge-images-wrap">
			<h1><?php esc_html_e( 'Edge Images Settings', 'edge-images' ); ?></h1>
			
			<div class="edge-images-container">
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
	 * @return void
	 */
	public static function render_provider_field(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_provider = get_option( self::PROVIDER_OPTION, 'none' );
		$providers       = self::get_providers();

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
	 * Gets the list of available providers.
	 *
	 * @return array<string, string>
	 */
	private static function get_providers(): array {
		return Provider_Registry::get_providers();
	}
} 