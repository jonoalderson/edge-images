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
	 * Register the admin page and related hooks.
	 *
	 * @return void
	 */
	public static function register(): void {

		$instance = new self();
		\add_action( 'admin_menu', [ $instance, 'add_menu_page' ] );
		\add_action( 'admin_init', [ $instance, 'register_fields' ] );
		\add_action( 'admin_enqueue_scripts', [ $instance, 'enqueue_assets' ] );
		\add_action( 'admin_notices', [ $instance, 'maybe_show_global_notice' ] );
		\add_filter( 'plugin_action_links_' . \plugin_basename( EDGE_IMAGES_PLUGIN_FILE ), [ $instance, 'add_settings_link' ] );
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
	 * Enqueue admin assets on our settings screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {

		if ( EDGE_IMAGES_ADMIN_SCREEN_ID !== $hook ) {
			return;
		}

		\wp_enqueue_style(
			'edge-images-admin',
			EDGE_IMAGES_PLUGIN_URL . 'assets/css/admin.css',
			[],
			EDGE_IMAGES_VERSION
		);

		\wp_enqueue_script(
			'edge-images-admin',
			EDGE_IMAGES_PLUGIN_URL . 'assets/js/admin.js',
			[],
			EDGE_IMAGES_VERSION,
			true
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array<int, string> $links Links.
	 * @return array<int, string>
	 */
	public function add_settings_link( array $links ): array {

		$url = \admin_url( 'options-general.php?page=' . EDGE_IMAGES_ADMIN_PAGE_SLUG );
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				\esc_url( $url ),
				\esc_html__( 'Settings', 'edge-images' )
			)
		);

		return $links;
	}

	/**
	 * Soft notice elsewhere in admin when transformations cannot run.
	 *
	 * @return void
	 */
	public function maybe_show_global_notice(): void {

		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = \get_current_screen();
		if ( ! $screen || EDGE_IMAGES_ADMIN_SCREEN_ID === $screen->id ) {
			return;
		}

		// Only nudge on the dashboard and plugins list.
		if ( ! in_array( $screen->id, [ 'dashboard', 'plugins' ], true ) ) {
			return;
		}

		$status = $this->get_status();
		if ( 'ready' === $status['state'] ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
			\esc_attr( 'error' === $status['state'] ? 'error' : 'warning' ),
			\esc_html( $status['summary'] ),
			\esc_url( \admin_url( 'options-general.php?page=' . EDGE_IMAGES_ADMIN_PAGE_SLUG ) ),
			\esc_html__( 'Review Edge Images settings', 'edge-images' )
		);
	}

	/**
	 * Register settings fields.
	 *
	 * @return void
	 */
	public function register_fields(): void {

		\add_settings_section(
			'edge_images_provider',
			__( 'Provider', 'edge-images' ),
			[ $this, 'render_provider_section' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG
		);

		\add_settings_field(
			Settings::PROVIDER_OPTION,
			__( 'Edge provider', 'edge-images' ),
			[ $this, 'render_provider_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_provider'
		);

		\add_settings_field(
			Settings::DOMAIN_OPTION,
			__( 'Transform domain', 'edge-images' ),
			[ $this, 'render_domain_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_provider',
			[
				'class' => 'edge-images-conditional edge-images-show-cloudflare edge-images-show-accelerated_domains',
			]
		);

		\add_settings_field(
			'edge_images_bunny_subdomain',
			__( 'Bunny subdomain', 'edge-images' ),
			[ $this, 'render_bunny_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_provider',
			[
				'class' => 'edge-images-conditional edge-images-show-bunny',
			]
		);

		\add_settings_field(
			'edge_images_imgix_subdomain',
			__( 'Imgix subdomain', 'edge-images' ),
			[ $this, 'render_imgix_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_provider',
			[
				'class' => 'edge-images-conditional edge-images-show-imgix',
			]
		);

		\add_settings_field(
			'edge_images_imgproxy_url',
			__( 'Imgproxy URL', 'edge-images' ),
			[ $this, 'render_imgproxy_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_provider',
			[
				'class' => 'edge-images-conditional edge-images-show-imgproxy',
			]
		);

		\add_settings_section(
			'edge_images_delivery',
			__( 'Delivery', 'edge-images' ),
			[ $this, 'render_delivery_section' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG
		);

		\add_settings_field(
			Settings::LOCALIZE_OPTION,
			__( 'Local copies', 'edge-images' ),
			[ $this, 'render_localize_field' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_delivery'
		);

		\add_settings_section(
			'edge_images_integrations',
			__( 'SEO integrations', 'edge-images' ),
			[ $this, 'render_integrations_section' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG
		);

		\add_settings_field(
			'edge_images_integrations_status',
			__( 'Detected plugins', 'edge-images' ),
			[ $this, 'render_integrations_status' ],
			EDGE_IMAGES_ADMIN_PAGE_SLUG,
			'edge_images_integrations'
		);

		$this->register_integration_fields();
	}

	/**
	 * Register Yoast / Rank Math toggles.
	 *
	 * Inactive plugins still render their rows (greyed out) so detection is visible.
	 * Their inputs are omitted from the form so saving does not wipe stored options.
	 *
	 * @return void
	 */
	private function register_integration_fields(): void {

		$groups = [
			'yoast-seo' => [
				'yoast_social' => __( 'Yoast social images', 'edge-images' ),
				'yoast_schema' => __( 'Yoast schema images', 'edge-images' ),
				'yoast_xml'    => __( 'Yoast XML sitemap images', 'edge-images' ),
			],
			'rank-math' => [
				'rank_math_social' => __( 'Rank Math social images', 'edge-images' ),
				'rank_math_schema' => __( 'Rank Math schema images', 'edge-images' ),
				'rank_math_xml'    => __( 'Rank Math XML sitemap images', 'edge-images' ),
			],
		];

		foreach ( $groups as $group => $fields ) {
			$active = Integrations::is_enabled( $group );

			foreach ( $fields as $key => $label ) {
				$option = 'edge_images_integration_' . $key;

				\add_settings_field(
					$option,
					$label,
					[ $this, 'render_integration_checkbox' ],
					EDGE_IMAGES_ADMIN_PAGE_SLUG,
					'edge_images_integrations',
					[
						'option' => $option,
						'group'  => $group,
						'active' => $active,
						'class'  => 'edge-images-integration-row' . ( $active ? ' is-active' : ' is-inactive' ),
					]
				);
			}
		}
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

		$status = $this->get_status();
		?>
		<div class="wrap edge-images-settings" data-edge-images-admin>
			<h1><?php echo \esc_html__( 'Edge Images', 'edge-images' ); ?></h1>
			<p class="edge-images-lead">
				<?php echo \esc_html__( 'Rewrite image markup through your edge provider. Choose a provider, confirm it is configured, then optionally keep local copies of transformed files.', 'edge-images' ); ?>
			</p>

			<?php $this->render_status_panel( $status ); ?>

			<form action="options.php" method="post" class="edge-images-form">
				<?php
				\settings_fields( Settings::OPTION_GROUP );
				\do_settings_sections( EDGE_IMAGES_ADMIN_PAGE_SLUG );
				\submit_button( __( 'Save settings', 'edge-images' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Provider section intro.
	 *
	 * @return void
	 */
	public function render_provider_section(): void {

		echo '<p class="edge-images-section-intro">' . \esc_html__( 'Image Resizing / transformation must already be enabled on the provider account. This plugin only rewrites URLs and markup.', 'edge-images' ) . '</p>';
	}

	/**
	 * Delivery section intro.
	 *
	 * @return void
	 */
	public function render_delivery_section(): void {

		echo '<p class="edge-images-section-intro">' . \esc_html__( 'By default every page request uses edge URLs. Localization is optional and off until you enable it.', 'edge-images' ) . '</p>';
	}

	/**
	 * Integrations section intro.
	 *
	 * @return void
	 */
	public function render_integrations_section(): void {

		echo '<p class="edge-images-section-intro">' . \esc_html__( 'Social, schema, and sitemap images are rewritten at 1200×675 when the matching plugin is active. These always use edge URLs (not localized files).', 'edge-images' ) . '</p>';
	}

	/**
	 * Detection status for Yoast / Rank Math.
	 *
	 * @return void
	 */
	public function render_integrations_status(): void {

		$plugins = [
			'yoast-seo' => __( 'Yoast SEO', 'edge-images' ),
			'rank-math' => __( 'Rank Math SEO', 'edge-images' ),
		];

		echo '<ul class="edge-images-detection">';
		foreach ( $plugins as $id => $label ) {
			$active = Integrations::is_enabled( $id );
			printf(
				'<li class="%1$s"><span class="dashicons %2$s edge-images-detection__mark" aria-hidden="true"></span><span class="edge-images-detection__label">%3$s</span><span class="edge-images-detection__state">%4$s</span></li>',
				\esc_attr( $active ? 'is-detected' : 'is-missing' ),
				\esc_attr( $active ? 'dashicons-yes-alt' : 'dashicons-marker' ),
				\esc_html( $label ),
				\esc_html( $active ? __( 'Detected — integrated', 'edge-images' ) : __( 'Not detected', 'edge-images' ) )
			);
		}
		echo '</ul>';
	}

	/**
	 * Status panel at the top of the page.
	 *
	 * @param array{state:string,summary:string,details:array<int,string>} $status Status.
	 * @return void
	 */
	private function render_status_panel( array $status ): void {

		$state_class = match ( $status['state'] ) {
			'ready'   => 'is-ready',
			'error'   => 'is-error',
			default   => 'is-warning',
		};

		$label = match ( $status['state'] ) {
			'ready'   => __( 'Active', 'edge-images' ),
			'error'   => __( 'Needs attention', 'edge-images' ),
			default   => __( 'Inactive', 'edge-images' ),
		};
		?>
		<div class="edge-images-status <?php echo \esc_attr( $state_class ); ?>" role="status">
			<div class="edge-images-status__badge"><?php echo \esc_html( $label ); ?></div>
			<div class="edge-images-status__body">
				<p class="edge-images-status__summary"><?php echo \esc_html( $status['summary'] ); ?></p>
				<?php if ( ! empty( $status['details'] ) ) : ?>
					<ul class="edge-images-status__details">
						<?php foreach ( $status['details'] as $detail ) : ?>
							<li><?php echo \esc_html( $detail ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Compute configuration status for notices and the status panel.
	 *
	 * @return array{state:string,summary:string,details:array<int,string>}
	 */
	private function get_status(): array {

		$provider = Settings::get_provider();
		$labels   = Providers::get_providers();
		$label    = $labels[ $provider ] ?? $provider;
		$details  = [];

		if ( 'none' === $provider || ! Providers::is_valid_provider( $provider ) ) {
			return [
				'state'   => 'warning',
				'summary' => __( 'Image transformations are disabled. Select an edge provider to begin.', 'edge-images' ),
				'details' => [
					__( 'Front-end images will not be rewritten until a provider is selected and configured.', 'edge-images' ),
				],
			];
		}

		$provider_class = Providers::get_provider_class( $provider );
		if ( ! class_exists( $provider_class ) || ! $provider_class::is_configured() ) {
			$hint = match ( $provider ) {
				'bunny'    => __( 'Enter your Bunny CDN subdomain (without .b-cdn.net).', 'edge-images' ),
				'imgix'    => __( 'Enter your Imgix subdomain (without .imgix.net).', 'edge-images' ),
				'imgproxy' => __( 'Enter the full Imgproxy base URL.', 'edge-images' ),
				default    => __( 'Complete the provider settings below.', 'edge-images' ),
			};

			return [
				'state'   => 'error',
				'summary' => sprintf(
					/* translators: %s: provider label */
					__( '%s is selected but not fully configured.', 'edge-images' ),
					$label
				),
				'details' => [ $hint ],
			];
		}

		$domain = Settings::get_domain();
		if ( '' === $domain ) {
			$domain = \get_site_url();
		}

		$details[] = sprintf(
			/* translators: %s: provider label */
			__( 'Provider: %s', 'edge-images' ),
			$label
		);

		if ( in_array( $provider, [ 'cloudflare', 'accelerated_domains' ], true ) ) {
			$details[] = sprintf(
				/* translators: %s: domain URL */
				__( 'Transform domain: %s', 'edge-images' ),
				$domain
			);
		}

		$details[] = Settings::is_localization_enabled()
			? __( 'Local copies: on (edge URLs until files are fetched)', 'edge-images' )
			: __( 'Local copies: off (edge URLs only)', 'edge-images' );

		return [
			'state'   => 'ready',
			'summary' => sprintf(
				/* translators: %s: provider label */
				__( 'Images are being rewritten through %s.', 'edge-images' ),
				$label
			),
			'details' => $details,
		];
	}

	/**
	 * Provider select field.
	 *
	 * @return void
	 */
	public function render_provider_field(): void {

		$current = Settings::get_provider();
		?>
		<select name="<?php echo \esc_attr( Settings::PROVIDER_OPTION ); ?>" id="<?php echo \esc_attr( Settings::PROVIDER_OPTION ); ?>" class="edge-images-provider-select">
			<?php foreach ( Providers::get_providers() as $slug => $label ) : ?>
				<option value="<?php echo \esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>>
					<?php echo \esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php echo \esc_html__( 'Choose “None” to disable rewriting without deactivating the plugin.', 'edge-images' ); ?>
		</p>
		<?php
	}

	/**
	 * Domain field.
	 *
	 * @return void
	 */
	public function render_domain_field(): void {

		printf(
			'<input type="url" class="regular-text" name="%1$s" id="%1$s" value="%2$s" placeholder="https://example.com" autocomplete="off" />
			<p class="description">%3$s</p>',
			\esc_attr( Settings::DOMAIN_OPTION ),
			\esc_attr( Settings::get_domain() ),
			\esc_html__( 'Optional. Defaults to the site URL. Used when the provider transforms via a path on your domain (Cloudflare, Accelerated Domains). Leave blank on most production sites.', 'edge-images' )
		);
	}

	/**
	 * Bunny subdomain field.
	 *
	 * @return void
	 */
	public function render_bunny_field(): void {

		printf(
			'<input type="text" class="regular-text" name="edge_images_bunny_subdomain" id="edge_images_bunny_subdomain" value="%1$s" placeholder="your-zone" autocomplete="off" />
			<p class="description">%2$s</p>',
			\esc_attr( (string) Settings::get_option( 'edge_images_bunny_subdomain', '' ) ),
			\esc_html__( 'Required for Bunny. Enter the zone subdomain only — not the full URL, and without .b-cdn.net.', 'edge-images' )
		);
	}

	/**
	 * Imgix subdomain field.
	 *
	 * @return void
	 */
	public function render_imgix_field(): void {

		printf(
			'<input type="text" class="regular-text" name="edge_images_imgix_subdomain" id="edge_images_imgix_subdomain" value="%1$s" placeholder="your-site" autocomplete="off" />
			<p class="description">%2$s</p>',
			\esc_attr( (string) Settings::get_option( 'edge_images_imgix_subdomain', '' ) ),
			\esc_html__( 'Required for Imgix. Enter the source subdomain only — without .imgix.net.', 'edge-images' )
		);
	}

	/**
	 * Imgproxy URL field.
	 *
	 * @return void
	 */
	public function render_imgproxy_field(): void {

		printf(
			'<input type="url" class="regular-text" name="edge_images_imgproxy_url" id="edge_images_imgproxy_url" value="%1$s" placeholder="https://imgproxy.example.com" autocomplete="off" />
			<p class="description">%2$s</p>',
			\esc_attr( (string) Settings::get_option( 'edge_images_imgproxy_url', '' ) ),
			\esc_html__( 'Required for Imgproxy. Full base URL of your Imgproxy instance.', 'edge-images' )
		);
	}

	/**
	 * Localization checkbox with stronger guidance.
	 *
	 * @return void
	 */
	public function render_localize_field(): void {

		printf(
			'<input type="hidden" name="%1$s" value="0" />
			<label class="edge-images-checkbox">
				<input type="checkbox" name="%1$s" value="1" %2$s />
				<span>%3$s</span>
			</label>
			<div class="edge-images-callout edge-images-callout--warn">
				<p><strong>%4$s</strong> %5$s</p>
				<p>%6$s</p>
				<p>%7$s</p>
			</div>',
			\esc_attr( Settings::LOCALIZE_OPTION ),
			\checked( Settings::is_localization_enabled(), true, false ),
			\esc_html__( 'Store transformed images locally under Uploads', 'edge-images' ),
			\esc_html__( 'Off by default.', 'edge-images' ),
			\esc_html__( 'While files are missing, pages still use edge URLs. A background cron job fetches the 1× / 1.5× / 2× variants and swaps the markup once they exist.', 'edge-images' ),
			\esc_html__( 'This adds origin storage and fetch traffic. Prefer leaving it off when the edge CDN already caches transformed images well.', 'edge-images' ),
			\esc_html__( 'On Cloudflare, local copies might reduce cost. Image Resizing is billed on unique transformations per month, so a stored variant is only requested once instead of counting again each month.', 'edge-images' )
		);
	}

	/**
	 * Integration checkbox field.
	 *
	 * @param array<string, mixed> $args Field args.
	 * @return void
	 */
	public function render_integration_checkbox( array $args ): void {

		$option = (string) ( $args['option'] ?? '' );
		$active = ! empty( $args['active'] );
		if ( '' === $option ) {
			return;
		}

		$checked = (bool) Settings::get_option( $option, true );

		if ( ! $active ) {
			printf(
				'<label class="edge-images-checkbox is-muted">
					<input type="checkbox" %1$s disabled="disabled" />
					<span>%2$s</span>
				</label>
				<p class="description">%3$s</p>',
				\checked( $checked, true, false ),
				\esc_html__( 'Rewrite through the edge provider', 'edge-images' ),
				\esc_html__( 'Activate the plugin to enable this integration.', 'edge-images' )
			);
			return;
		}

		printf(
			'<input type="hidden" name="%1$s" value="0" />
			<label class="edge-images-checkbox">
				<input type="checkbox" name="%1$s" value="1" %2$s />
				<span>%3$s</span>
			</label>',
			\esc_attr( $option ),
			\checked( $checked, true, false ),
			\esc_html__( 'Rewrite through the edge provider', 'edge-images' )
		);
	}
}
