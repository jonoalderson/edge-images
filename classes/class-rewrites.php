<?php
/**
 * Rewrite rules functionality.
 *
 * Handles URL rewrite rules for native image transformation.
 * This class:
 * - Manages rewrite rules for native image transformation
 * - Adds query vars for width/height parameters
 * - Handles rewrite rule flushing
 * - Manages .htaccess in uploads directory
 * - Loads early in the WordPress lifecycle
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      5.4.0
 */

namespace Edge_Images;

use Edge_Images\Edge_Providers\Native;

class Rewrites {

	/**
	 * Register the rewrite functionality.
	 *
	 * @since 5.4.0
	 * @return void
	 */
	public static function register(): void {
		
		// Handle htaccess on settings update - this needs to run regardless of current provider
		add_action('update_option_edge_images_provider', [self::class, 'handle_provider_change'], 10, 2);

		// Only proceed with rewrite rules if native provider is active
		if (!self::should_handle_rewrites()) {
			return;
		}

		// Add query vars
		add_filter('query_vars', [Native::class, 'add_query_vars']);

		// Add rewrite rules
		add_action('init', [Native::class, 'add_rewrite_rules']);

		// Maybe flush rewrite rules
		add_action('init', [self::class, 'maybe_flush_rules']);

		// Add admin notice for NGINX users
		if (Helpers::is_nginx()) {
			add_action('admin_notices', [self::class, 'show_nginx_notice']);
		}
	}

	/**
	 * Check if we should handle rewrites.
	 *
	 * @since 5.4.0
	 * @return bool Whether rewrites should be handled.
	 */
	private static function should_handle_rewrites(): bool {
		
		// Get the selected provider from settings
		$selected_provider = Settings::get_option('edge_images_provider', 'none');

		// Check if native provider is selected and image transformation is enabled
		return $selected_provider === 'native';
	}

	/**
	 * Maybe flush rewrite rules.
	 *
	 * @since 5.4.0
	 * @return void
	 */
	public static function maybe_flush_rules(): void {
		// Check if rules need to be flushed
		if (get_option('edge_images_rewrite_rules_flushed') !== EDGE_IMAGES_VERSION) {
			flush_rewrite_rules();
			update_option('edge_images_rewrite_rules_flushed', EDGE_IMAGES_VERSION);
		}
	}

	/**
	 * Display admin notice for NGINX configuration.
	 *
	 * @since 5.4.0
	 * @return void
	 */
	public static function show_nginx_notice(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		// Only show if native provider is selected
		if (!self::should_handle_rewrites()) {
			return;
		}

		// Check if rules appear to be working
		if (self::are_nginx_rules_working()) {
			return;
		}

		?>
		<div class="notice notice-info">
			<h3 style="margin-top: 0.5em;"><?php esc_html_e('NGINX Configuration Required', 'edge-images'); ?></h3>
			<p>
				<?php 
				esc_html_e(
					'Your site is running on NGINX, which requires manual configuration to enable image transformation. ' .
					'Please add the following rules to your NGINX configuration file (usually found at /etc/nginx/sites-available/your-site):', 
					'edge-images'
				); 
				?>
			</p>
			<pre style="background: #f6f7f7; padding: 15px; overflow: auto; margin-bottom: 1em;"><?php echo esc_html(Native::get_nginx_rules()); ?></pre>
			<p>
				<?php 
				printf(
					/* translators: %1$s: Opening link tag, %2$s: Closing link tag */
					esc_html__(
						'After adding these rules, remember to test your configuration with %1$snginx -t%2$s and reload NGINX with %1$ssudo service nginx reload%2$s.', 
						'edge-images'
					),
					'<code>',
					'</code>'
				); 
				?>
			</p>
			<p>
				<?php 
				esc_html_e(
					'These rules tell NGINX to process image requests with transformation parameters through WordPress, ' .
					'allowing Edge Images to resize and optimize them on demand.',
					'edge-images'
				); 
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if NGINX rules appear to be working.
	 *
	 * Tests if image transformation is working by making a test request
	 * to a known image and checking for expected response headers.
	 *
	 * @since 5.4.0
	 * @return bool Whether the rules appear to be working.
	 */
	private static function are_nginx_rules_working(): bool {
		static $rules_working = null;

		// Return cached result if available
		if ($rules_working !== null) {
			return $rules_working;
		}

		// Only run this check once per hour
		$transient_key = 'edge_images_nginx_rules_check';
		$cached_result = get_transient($transient_key);
		if ($cached_result !== false) {
			$rules_working = (bool) $cached_result;
			return $rules_working;
		}

		// Find a test image to use
		$test_image = self::get_test_image_url();
		if (!$test_image) {
			set_transient($transient_key, '0', HOUR_IN_SECONDS);
			$rules_working = false;
			return false;
		}

		// Add transformation parameters
		$test_url = add_query_arg([
			Native::TRANSFORM_PARAM => 'true',
			'width' => '100',
			'height' => '100'
		], $test_image);

		// Make the request
		$response = wp_remote_get($test_url, [
			'timeout' => 5,
			'sslverify' => false,
			'headers' => [
				'X-Edge-Images-Test' => '1'
			]
		]);

		// Check if request was successful and has expected headers
		$rules_working = !is_wp_error($response) 
			&& wp_remote_retrieve_response_code($response) === 200
			&& strpos(wp_remote_retrieve_header($response, 'content-type'), 'image/') === 0
			&& wp_remote_retrieve_header($response, 'cache-control') === 'public, max-age=31536000';

		// Cache the result
		set_transient($transient_key, $rules_working ? '1' : '0', HOUR_IN_SECONDS);

		return $rules_working;
	}

	/**
	 * Get a test image URL from the media library.
	 *
	 * @since 5.4.0
	 * @return string|null URL of a test image or null if none found.
	 */
	private static function get_test_image_url(): ?string {
		// Try to get a random image attachment
		$args = [
			'post_type' => 'attachment',
			'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
			'post_status' => 'inherit',
			'posts_per_page' => 1,
			'orderby' => 'rand'
		];

		$query = new \WP_Query($args);
		if (!$query->have_posts()) {
			return null;
		}

		$image = wp_get_attachment_url($query->posts[0]->ID);
		return $image ?: null;
	}

	/**
	 * Handle provider changes in settings.
	 *
	 * @since 5.4.0
	 * 
	 * @param string $old_value The previous provider value.
	 * @param string $new_value The new provider value.
	 * @return void
	 */
	public static function handle_provider_change(string $old_value, string $new_value): void {
		// For Apache servers, handle .htaccess updates
		if (Helpers::is_apache()) {
			if ($new_value === 'native') {
				Native::maybe_update_htaccess();
			} elseif ($old_value === 'native' && $new_value !== 'native') {
				Native::maybe_remove_htaccess();
			}
		}
	}
} 