<?php
/**
 * Native provider implementation.
 *
 * Handles image transformation through WordPress's own image processing.
 * This provider:
 * - Uses WordPress's built-in image processing
 * - Transforms image URLs to use query parameters
 * - Supports basic width and height transformations
 * - Provides efficient local image processing
 * - Requires no external services
 * - Processes and serves transformed images
 *
 * @package    Edge_Images
 * @author     Jono Alderson <https://www.jonoalderson.com/>
 * @license    GPL-2.0-or-later
 * @since      5.4.0
 */

namespace Edge_Images\Edge_Providers;

use Edge_Images\{Edge_Provider, Helpers, Settings};

class Native extends Edge_Provider {

	/**
	 * The transform parameter name.
	 *
	 * @since 5.4.0
	 * @var string
	 */
	public const TRANSFORM_PARAM = 'edge_images';

	/**
	 * Get the upload directory path pattern for rewrite rules.
	 *
	 * @since 5.4.0
	 * @return string The upload directory path pattern.
	 */
	private static function get_upload_path_pattern(): string {
		$upload_dir = wp_upload_dir();
		$site_url = site_url('/');
		$upload_path = str_replace($site_url, '', $upload_dir['baseurl']);
		return trim($upload_path, '/');
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_action('parse_request', [$this, 'maybe_transform_image']);
		add_filter('redirect_canonical', [$this, 'alter_canonical_redirect'], 10, 2);
	}

	/**
	 * Prevent canonical redirects for image transformation requests.
	 *
	 * @since 5.4.0
	 * 
	 * @param string|false $redirect_url The redirect URL, or false if no redirect needed.
	 * @param string      $requested_url The requested URL.
	 * @return string|false The redirect URL or false to prevent redirect.
	 */
	public function alter_canonical_redirect($redirect_url, string $requested_url) {

		// Check if this is a transform request
		if (isset($_GET[self::TRANSFORM_PARAM]) && $_GET[self::TRANSFORM_PARAM] === 'true') {
			return false;
		}

		return true;
	}

	/**
	 * Get the .htaccess rules for this provider.
	 *
	 * @since 5.4.0
	 * @return string The .htaccess rules.
	 */
	public static function get_htaccess_rules(): string {
		$upload_path = self::get_upload_path_pattern();
		return sprintf('
# BEGIN Edge Images
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteCond %%{REQUEST_FILENAME} -f
RewriteCond %%{REQUEST_URI} ^/%s/.*\.(jpg|jpeg|png|gif|webp)$ [NC]
RewriteCond %%{QUERY_STRING} edge_images=true
RewriteRule ^(.*)$ /index.php?edge_images=true&file=$1 [L,QSA]
</IfModule>
# END Edge Images

', $upload_path);
	}

	/**
	 * Get the NGINX rules for this provider.
	 *
	 * @since 5.4.0
	 * @return string The NGINX rules.
	 */
	public static function get_nginx_rules(): string {
		$upload_path = self::get_upload_path_pattern();
		return sprintf('
# Edge Images rules
location ~* ^/%s/.*\.(jpg|jpeg|png|gif|webp)$ {
    if ($args ~* "edge_images=true") {
        rewrite ^(.*)$ /index.php?edge_images=true&file=$1 last;
    }
}
', $upload_path);
	}

	/**
	 * Add query vars for image transformation.
	 *
	 * @since 5.4.0
	 * 
	 * @param array $vars The existing query vars.
	 * @return array Modified query vars.
	 */
	public static function add_query_vars(array $vars): array {
		$vars[] = self::TRANSFORM_PARAM;
		$vars[] = 'width';
		$vars[] = 'height';
		$vars[] = 'file';
		return $vars;
	}

	/**
	 * Add rewrite rules for image transformation.
	 *
	 * @since 5.4.0
	 * @return void
	 */
	public static function add_rewrite_rules(): void {
		$upload_path = self::get_upload_path_pattern();
		
		// Add rewrite rules for common image extensions
		$image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		
		foreach ($image_extensions as $ext) {
			add_rewrite_rule(
				$upload_path . "/(.+)\." . $ext . "$",
				'index.php?' . self::TRANSFORM_PARAM . '=true&file=$0',
				'top'
			);
		}
	}

	/**
	 * Maybe update the .htaccess file in the root directory.
	 *
	 * @since 5.4.0
	 * @return void
	 */
	public static function maybe_update_htaccess(): void {
		// Get root .htaccess path
		$htaccess_path = ABSPATH . '.htaccess';

		// Initialize WP_Filesystem if needed
		if (!function_exists('WP_Filesystem')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		if (!$wp_filesystem) {
			return;
		}

		// Get our dynamic rules
		$rules = self::get_htaccess_rules();

		// Create .htaccess if it doesn't exist
		if (!$wp_filesystem->exists($htaccess_path)) {
			if (!$wp_filesystem->put_contents($htaccess_path, $rules, FS_CHMOD_FILE)) {
				return;
			}
			return;
		}

		// Check if .htaccess is writable
		if (!$wp_filesystem->is_writable($htaccess_path)) {
			return;
		}

		// Read existing content
		$content = $wp_filesystem->get_contents($htaccess_path);
		if ($content === false) {
			return;
		}

		// Remove any existing Edge Images rules
		$content = preg_replace('/# BEGIN Edge Images.*# END Edge Images\n?/s', '', $content);

		// Add our rules BEFORE WordPress rules
		$wp_rules_pos = strpos($content, '# BEGIN WordPress');
		if ($wp_rules_pos !== false) {
			$content = substr_replace($content, $rules, $wp_rules_pos, 0);
		} else {
			$content = $rules . $content;
		}

		// Write the updated content
		$write_result = $wp_filesystem->put_contents($htaccess_path, $content, FS_CHMOD_FILE);
		if (!$write_result) {
			return;
		}

		// Verify the write was successful
		$final_content = $wp_filesystem->get_contents($htaccess_path);
		if ($final_content === false || strpos($final_content, '# BEGIN Edge Images') === false) {
			return;
		}
	}

	/**
	 * Maybe remove the .htaccess rules when deactivating.
	 *
	 * @since 5.4.0
	 * @return void
	 */
	public static function maybe_remove_htaccess(): void {
		// Get root .htaccess path
		$htaccess_path = ABSPATH . '.htaccess';

		// Initialize WP_Filesystem if needed
		if (!function_exists('WP_Filesystem')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		if (!$wp_filesystem) {
			return;
		}

		if (!$wp_filesystem->exists($htaccess_path) || !$wp_filesystem->is_writable($htaccess_path)) {
			return;
		}

		// Read existing content
		$content = $wp_filesystem->get_contents($htaccess_path);
		if ($content === false) {
			return;
		}

		// Remove our rules
		$content = preg_replace('/# BEGIN Edge Images.*# END Edge Images\n?/s', '', $content);

		// Write the updated content
		if (!$wp_filesystem->put_contents($htaccess_path, $content, FS_CHMOD_FILE)) {
			return;
		}
	}

	/**
	 * Get the edge URL for an image.
	 *
	 * Transforms the image URL by adding width and height query parameters.
	 * This method:
	 * - Adds edge_images=true parameter
	 * - Adds width and height parameters if set
	 * - Maintains original URL structure
	 *
	 * @since 5.4.0
	 * 
	 * @return string The transformed URL with query parameters.
	 */
	public function get_edge_url(): string {
		$args = $this->get_transform_args();
		
		// Start with the base URL
		$url = Helpers::get_rewrite_domain() . $this->path;

		// Add our transform parameter
		$url = add_query_arg(self::TRANSFORM_PARAM, 'true', $url);

		// Only add width and height parameters
		if (isset($args['w'])) {
			$url = add_query_arg('width', $args['w'], $url);
		}
		if (isset($args['h'])) {
			$url = add_query_arg('height', $args['h'], $url);
		}

		return $url;
	}

	/**
	 * Get the URL pattern used to identify transformed images.
	 *
	 * Returns a pattern that matches URLs with our transform parameter.
	 *
	 * @since 5.4.0
	 * 
	 * @return string The URL pattern for matching transformed images.
	 */
	public static function get_url_pattern(): string {
		return '?' . self::TRANSFORM_PARAM . '=true';
	}

	/**
	 * Get the URL pattern used to transform images.
	 *
	 * @since 5.4.0
	 * 
	 * @return string The transformation pattern.
	 */
	public static function get_transform_pattern(): string {
		return '\?' . self::TRANSFORM_PARAM . '=true(&[^?]*)*$';
	}

	/**
	 * Check if this provider uses a hosted subdomain.
	 *
	 * @since 5.4.0
	 * 
	 * @return bool Whether this provider uses a hosted subdomain.
	 */
	public static function uses_hosted_subdomain(): bool {
		return false;
	}

	/**
	 * Check if the provider is properly configured.
	 *
	 * The native provider is always configured since it uses
	 * WordPress's built-in image processing.
	 *
	 * @since 5.4.0
	 * 
	 * @return bool Whether the provider is configured.
	 */
	public static function is_configured(): bool {
		return true;
	}

	/**
	 * Check if we should transform the current request.
	 *
	 * @since 5.4.0
	 * 
	 * @param \WP $wp The WordPress request object.
	 * @return void
	 */
	public function maybe_transform_image(\WP $wp): void {
		
		// Check if this is a transform request
		if (empty($wp->query_vars[self::TRANSFORM_PARAM]) || $wp->query_vars[self::TRANSFORM_PARAM] !== 'true') {
			return;
		}

		// Get the requested file path
		$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		if (!$request_path) {
			status_header(404);
			return;
		}

		// Convert the request path to a local file path
		$file_path = ABSPATH . ltrim($request_path, '/');

		// Check if the file exists and is an image
		if (!file_exists($file_path)) {
			status_header(404);
			return;
		}

		$mime_type = wp_get_image_mime($file_path);
		if (!$mime_type) {
			status_header(404);
			return;
		}

		// Clear WordPress's query vars to prevent post matching
		$wp->query_vars = [];
		$wp->query_vars[self::TRANSFORM_PARAM] = 'true';
		$wp->query_vars['width'] = isset($_GET['width']) ? absint($_GET['width']) : null;
		$wp->query_vars['height'] = isset($_GET['height']) ? absint($_GET['height']) : null;

		// Prevent WordPress from processing this as a normal request
		$wp->is_404 = false;
		$wp->is_singular = false;
		$wp->is_archive = false;
		$wp->is_home = false;
		
		// Set proper status
		status_header(200);

		// Get dimensions from query vars
		$width = $wp->query_vars['width'];
		$height = $wp->query_vars['height'];

		// Get original dimensions without loading editor
		$image_size = getimagesize($file_path);
		if ($image_size === false) {
			return;
		}

		$orig_width = (int) $image_size[0];
		$orig_height = (int) $image_size[1];

		// Ensure we have valid dimensions
		if ($orig_width <= 0 || $orig_height <= 0) {
			return;
		}

		// Check if we're requesting the original size or would end up with original size after scaling
		if (
			// Exact match of both dimensions
			($width === $orig_width && $height === $orig_height) ||
			// Width match and no height specified
			($width === $orig_width && !$height) ||
			// Height match and no width specified
			($height === $orig_height && !$width) ||
			// Would scale to original size due to aspect ratio
			($width && $height && $width >= $orig_width && $height >= $orig_height)
		) {
			// Set headers
			header('Content-Type: ' . $mime_type);
			header('Cache-Control: public, max-age=31536000');
			header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
			
			// Stream the original file
			readfile($file_path);
			exit;
		}

		// Calculate target dimensions without upscaling
		$target_width = null;
		$target_height = null;

		// If both dimensions are specified
		if ($width && $height) {
			$orig_ratio = $orig_width / $orig_height;
			$target_ratio = $width / $height;

			// If ratios don't match, we need to crop
			if (abs($orig_ratio - $target_ratio) > 0.00001) {
				$target_width = min($width, $orig_width);
				$target_height = min($height, $orig_height);
			} else {
				// Ratios match, maintain proportions
				if ($target_ratio > $orig_ratio) {
					// Height is the constraining dimension
					$target_height = min($height, $orig_height);
					$target_width = round($target_height * $orig_ratio);
				} else {
					// Width is the constraining dimension
					$target_width = min($width, $orig_width);
					$target_height = round($target_width / $orig_ratio);
				}
			}
		} else {
			// If only width is specified
			if ($width) {
				$target_width = min($width, $orig_width);
				$target_height = round(($target_width / $orig_width) * $orig_height);
			}
			// If only height is specified
			elseif ($height) {
				$target_height = min($height, $orig_height);
				$target_width = round(($target_height / $orig_height) * $orig_width);
			}
		}

		// Validate final dimensions
		if (!$target_width || !$target_height || $target_width <= 0 || $target_height <= 0) {
			return;
		}

		// If target dimensions match original, serve the original file
		if ($target_width === $orig_width && $target_height === $orig_height) {
			// Set headers
			header('Content-Type: ' . $mime_type);
			header('Cache-Control: public, max-age=31536000');
			header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
			
			// Stream the original file
			readfile($file_path);
			exit;
		}

		// Only initialize editor if we need to resize
		$editor = wp_get_image_editor($file_path);
		if (is_wp_error($editor)) {
			return;
		}

		// Set quality to 90
		$editor->set_quality(90);

		// Resize to calculated dimensions
		$resize_result = $editor->resize($target_width, $target_height, true);
		if (is_wp_error($resize_result)) {
			return;
		}

		// Set headers
		header('Content-Type: ' . $mime_type);
		header('Cache-Control: public, max-age=31536000');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

		// Stream the image directly to output
		$stream_result = $editor->stream();
		if (is_wp_error($stream_result)) {
			return;
		}
		exit;
	}
} 