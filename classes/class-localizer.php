<?php
/**
 * Optional local caching of edge-transformed image variants.
 *
 * @package Edge_Images
 */

namespace Edge_Images;

/**
 * Fetches edge variants and serves local files when ready.
 */
class Localizer {

	/**
	 * Meta key for stored variants.
	 */
	public const SIZES_META_KEY = '_edge_images_sizes';

	/**
	 * Non-autoloaded queue option.
	 */
	public const QUEUE_OPTION = 'edge_images_localize_queue';

	/**
	 * Cron hook for the queue worker.
	 */
	public const CRON_HOOK = 'edge_images_process_localize_queue';

	/**
	 * Whether registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Request buffer of jobs.
	 *
	 * @var array<string, array>
	 */
	private static array $queue_buffer = [];

	/**
	 * Register localization hooks when enabled.
	 *
	 * @return void
	 */
	public static function register(): void {

		if ( self::$registered ) {
			return;
		}

		$instance = new self();

		\add_filter( 'cron_schedules', [ $instance, 'add_cron_schedule' ] );
		\add_action( self::CRON_HOOK, [ $instance, 'process_queue' ] );
		\add_action( 'delete_attachment', [ $instance, 'delete_variants' ] );
		\add_action( 'edit_attachment', [ $instance, 'delete_variants' ] );

		if ( Settings::is_localization_enabled() ) {
			\add_action( 'shutdown', [ self::class, 'flush_queue' ] );
		}

		self::$registered = true;
	}

	/**
	 * Serve local variants when ready, otherwise queue generation.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param int                  $width         Width.
	 * @param int                  $height        Height.
	 * @param array<string, mixed> $properties    Properties.
	 * @param string               $original_url  Original URL.
	 * @param int                  $max_width     Max file width.
	 * @return array{src: string, srcset: string}|null Local URLs, or null to keep edge URLs.
	 */
	public static function maybe_localize( int $attachment_id, int $width, int $height, array $properties, string $original_url, int $max_width ): ?array {

		if ( ! Settings::is_localization_enabled() || $attachment_id <= 0 ) {
			return null;
		}

		$size_hash = self::size_hash( $width, $height, $properties );
		$bundle    = self::get_ready_bundle( $attachment_id, $size_hash, $width );

		if ( is_array( $bundle ) ) {
			return $bundle;
		}

		self::queue_job(
			[
				$attachment_id,
				$width,
				$height,
				$properties,
				$size_hash,
				$original_url,
				$max_width,
			]
		);

		return null;
	}

	/**
	 * Add a short cron schedule.
	 *
	 * @param array<string, array> $schedules Schedules.
	 * @return array<string, array>
	 */
	public function add_cron_schedule( array $schedules ): array {

		if ( ! isset( $schedules['edge_images_minute'] ) ) {
			$schedules['edge_images_minute'] = [
				'interval' => 60,
				'display'  => __( 'Every Minute (Edge Images)', 'edge-images' ),
			];
		}

		return $schedules;
	}

	/**
	 * Flush buffered jobs to the durable queue.
	 *
	 * @return void
	 */
	public static function flush_queue(): void {

		if ( empty( self::$queue_buffer ) ) {
			return;
		}

		$queue = \get_option( self::QUEUE_OPTION, [] );
		if ( ! is_array( $queue ) ) {
			$queue = [];
		}

		foreach ( self::$queue_buffer as $key => $job ) {
			if ( ! isset( $queue[ $key ] ) ) {
				$queue[ $key ] = $job;
			}
		}

		self::$queue_buffer = [];
		\update_option( self::QUEUE_OPTION, $queue, false );
		( new self() )->ensure_cron();
	}

	/**
	 * Process a batch of queued jobs.
	 *
	 * @return void
	 */
	public function process_queue(): void {

		if ( ! Settings::is_localization_enabled() ) {
			return;
		}

		$queue = \get_option( self::QUEUE_OPTION, [] );
		if ( ! is_array( $queue ) || [] === $queue ) {
			$this->clear_cron();
			return;
		}

		$batch = array_slice( $queue, 0, 3, true );
		foreach ( $batch as $key => $job ) {
			unset( $queue[ $key ] );
			if ( ! is_array( $job ) || count( $job ) < 6 ) {
				continue;
			}

			[ $attachment_id, $width, $height, $properties, $size_hash, $original_url ] = $job;
			$max_width = isset( $job[6] ) ? (int) $job[6] : 0;
			$this->generate_variants( (int) $attachment_id, (int) $width, (int) $height, (array) $properties, (string) $size_hash, (string) $original_url, $max_width );
		}

		\update_option( self::QUEUE_OPTION, $queue, false );

		if ( [] !== $queue ) {
			$this->ensure_cron();
		} else {
			$this->clear_cron();
		}
	}

	/**
	 * Delete stored variants for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function delete_variants( int $attachment_id ): void {

		$sizes = \get_post_meta( $attachment_id, self::SIZES_META_KEY, true );
		if ( ! is_array( $sizes ) ) {
			\delete_post_meta( $attachment_id, self::SIZES_META_KEY );
			return;
		}

		$uploads = \wp_upload_dir();
		foreach ( $sizes as $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}
			$path = trailingslashit( $uploads['basedir'] ) . $size['file'];
			if ( is_file( $path ) ) {
				\unlink( $path );
			}
		}

		\delete_post_meta( $attachment_id, self::SIZES_META_KEY );
	}

	/**
	 * Queue a generation job.
	 *
	 * @param array $job Job args.
	 * @return void
	 */
	private static function queue_job( array $job ): void {

		$key = md5( wp_json_encode( $job ) );
		if ( isset( self::$queue_buffer[ $key ] ) ) {
			return;
		}

		self::$queue_buffer[ $key ] = $job;
	}

	/**
	 * Return local src/srcset when all DPR variants exist.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size_hash     Size hash.
	 * @param int    $base_width    Base width.
	 * @return array{src: string, srcset: string}|null
	 */
	private static function get_ready_bundle( int $attachment_id, string $size_hash, int $base_width ): ?array {

		$sizes = \get_post_meta( $attachment_id, self::SIZES_META_KEY, true );
		if ( ! is_array( $sizes ) ) {
			return null;
		}

		$uploads = \wp_upload_dir();
		$parts   = [];
		$src_1x  = '';

		foreach ( Layout::DPR_VARIANTS as $dpr ) {
			$variant_hash = $size_hash . '_' . str_replace( '.', '', (string) $dpr ) . 'x';
			if ( empty( $sizes[ $variant_hash ]['file'] ) ) {
				return null;
			}

			$url   = trailingslashit( $uploads['baseurl'] ) . $sizes[ $variant_hash ]['file'];
			$width = (int) ( $sizes[ $variant_hash ]['width'] ?? round( $base_width * $dpr ) );
			$parts[] = \esc_url_raw( $url ) . ' ' . $width . 'w';

			if ( 1.0 === $dpr ) {
				$src_1x = $url;
			}
		}

		if ( '' === $src_1x ) {
			return null;
		}

		return [
			'src'    => $src_1x,
			'srcset' => implode( ', ', $parts ),
		];
	}

	/**
	 * Fetch and store DPR variants.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param int                  $width         Width.
	 * @param int                  $height        Height.
	 * @param array<string, mixed> $properties    Properties.
	 * @param string               $size_hash     Size hash.
	 * @param string               $original_url  Original URL.
	 * @param int                  $max_width     Max width.
	 * @return void
	 */
	private function generate_variants( int $attachment_id, int $width, int $height, array $properties, string $size_hash, string $original_url, int $max_width ): void {

		$original_file = \get_attached_file( $attachment_id );
		if ( ! $original_file ) {
			return;
		}

		$uploads = \wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}

		$sizes = \get_post_meta( $attachment_id, self::SIZES_META_KEY, true );
		if ( ! is_array( $sizes ) ) {
			$sizes = [];
		}

		$file_info    = pathinfo( $original_file );
		$original_ext = $file_info['extension'] ?? 'jpg';
		$dir          = dirname( $original_file );

		foreach ( Layout::srcset_candidates( $width, $max_width ) as $candidate ) {
			$dpr          = $candidate['dpr'];
			$variant_hash = $size_hash . '_' . str_replace( '.', '', (string) $dpr ) . 'x';
			$edge_url     = Transformer::url( $original_url, $width, $height, $properties, $dpr );

			$response = \wp_remote_get(
				$edge_url,
				[
					'timeout' => 15,
					'headers' => [
						'Accept' => 'image/avif,image/webp,image/*,*/*;q=0.8',
					],
				]
			);

			if ( \is_wp_error( $response ) || 200 !== (int) \wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$body = \wp_remote_retrieve_body( $response );
			if ( '' === $body ) {
				continue;
			}

			$dpr_suffix = 1.0 === $dpr ? '' : '-' . str_replace( '.', '', (string) $dpr ) . 'x';
			$filename   = basename( $original_file, '.' . $original_ext ) . '-' . $width . 'x' . $height . $dpr_suffix . '-edge.' . $original_ext;
			$path       = $dir . '/' . $filename;

			if ( false === file_put_contents( $path, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				continue;
			}

			$relative = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $path ), '/' );
			$info     = @\getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			$sizes[ $variant_hash ] = [
				'file'   => $relative,
				'width'  => $info[0] ?? $candidate['width'],
				'height' => $info[1] ?? (int) round( $height * $dpr ),
				'edge'   => $edge_url,
			];
		}

		\update_post_meta( $attachment_id, self::SIZES_META_KEY, $sizes );
	}

	/**
	 * Hash for a size/property combo.
	 *
	 * @param int                  $width      Width.
	 * @param int                  $height     Height.
	 * @param array<string, mixed> $properties Properties.
	 * @return string
	 */
	private static function size_hash( int $width, int $height, array $properties ): string {

		ksort( $properties );

		return 'ei_' . md5( $width . 'x' . $height . wp_json_encode( $properties ) );
	}

	/**
	 * Ensure the queue worker is scheduled.
	 *
	 * @return void
	 */
	private function ensure_cron(): void {

		if ( ! \wp_next_scheduled( self::CRON_HOOK ) ) {
			\wp_schedule_event( time() + 30, 'edge_images_minute', self::CRON_HOOK );
		}
	}

	/**
	 * Clear the queue worker.
	 *
	 * @return void
	 */
	private function clear_cron(): void {

		\wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
