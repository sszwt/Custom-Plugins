<?php
/**
 * Image optimizer service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIIO_Optimizer {

	const META_KEY     = '_aiio_optimized';
	const META_STATS   = '_aiio_stats';
	const TOTAL_SAVED  = 'aiio_total_bytes_saved';

	/**
	 * Prevent recursive optimize loops.
	 *
	 * @var bool
	 */
	private $processing = false;

	/**
	 * @var AIIO_Settings
	 */
	private $settings;

	/**
	 * @var AIIO_AI_Analyzer
	 */
	private $analyzer;

	/**
	 * @param AIIO_Settings $settings Plugin settings.
	 */
	public function __construct( AIIO_Settings $settings ) {
		$this->settings = $settings;
		$this->analyzer = new AIIO_AI_Analyzer( $settings );

		if ( $this->settings->get( 'auto_on_upload' ) ) {
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'optimize_on_upload' ), 20, 2 );
		}

		// Apply quality defaults for WP image editor saves.
		add_filter( 'jpeg_quality', array( $this, 'filter_jpeg_quality' ), 20 );
		add_filter( 'wp_editor_set_quality', array( $this, 'filter_editor_quality' ), 20, 2 );
	}

	/**
	 * Filter JPEG quality.
	 *
	 * @param int $quality Quality.
	 * @return int
	 */
	public function filter_jpeg_quality( $quality ) {
		$q = (int) $this->settings->get( 'jpeg_quality' );
		return $q > 0 ? $q : $quality;
	}

	/**
	 * Filter editor quality by mime.
	 *
	 * @param int    $quality Quality.
	 * @param string $mime    Mime type.
	 * @return int
	 */
	public function filter_editor_quality( $quality, $mime = '' ) {
		if ( 'image/webp' === $mime ) {
			return (int) $this->settings->get( 'webp_quality' );
		}

		if ( 'image/png' === $mime ) {
			return (int) $this->settings->get( 'png_quality' );
		}

		return (int) $this->settings->get( 'jpeg_quality' );
	}

	/**
	 * Auto-optimize after WordPress generates metadata.
	 *
	 * @param array<string, mixed> $metadata      Attachment metadata.
	 * @param int                  $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function optimize_on_upload( $metadata, $attachment_id ) {
		if ( $this->processing || ! wp_attachment_is_image( $attachment_id ) ) {
			return $metadata;
		}

		$result = $this->optimize_attachment( (int) $attachment_id, false );

		if ( ! empty( $result['success'] ) && ! empty( $result['metadata'] ) ) {
			return $result['metadata'];
		}

		return $metadata;
	}

	/**
	 * Optimize a single attachment.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force         Re-optimize even if already done.
	 * @return array<string, mixed>
	 */
	public function optimize_attachment( $attachment_id, $force = false ) {
		if ( $this->processing ) {
			return array(
				'success' => false,
				'message' => __( 'Optimization already in progress.', 'ai-image-optimization' ),
			);
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Not an image.', 'ai-image-optimization' ),
			);
		}

		if ( ! $force && get_post_meta( $attachment_id, self::META_KEY, true ) ) {
			$stats = get_post_meta( $attachment_id, self::META_STATS, true );
			return array(
				'success' => true,
				'message' => __( 'Already optimized.', 'ai-image-optimization' ),
				'source'  => 'existing',
				'stats'   => is_array( $stats ) ? $stats : array(),
			);
		}

		$this->processing = true;

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			$this->mark_processed(
				$attachment_id,
				array(
					'saved_bytes'  => 0,
					'skipped'      => true,
					'skip_reason'  => 'missing_file',
					'optimized_at' => current_time( 'mysql' ),
				)
			);
			$this->processing = false;
			return array(
				'success' => false,
				'message' => __( 'Image file not found.', 'ai-image-optimization' ),
				'source'  => 'failed',
			);
		}

		$mime = get_post_mime_type( $attachment_id );
		$allowed = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/webp' );

		if ( ! in_array( $mime, $allowed, true ) ) {
			$this->mark_processed(
				$attachment_id,
				array(
					'saved_bytes'  => 0,
					'skipped'      => true,
					'skip_reason'  => 'unsupported',
					'mime'         => $mime,
					'optimized_at' => current_time( 'mysql' ),
				)
			);
			$this->processing = false;
			return array(
				'success' => false,
				'message' => __( 'Unsupported image type. Use JPEG, PNG, or WebP.', 'ai-image-optimization' ),
				'source'  => 'failed',
			);
		}

		$original_size = filesize( $path );
		$skip_kb       = (int) $this->settings->get( 'skip_small_kb' );

		if ( $skip_kb > 0 && $original_size <= ( $skip_kb * 1024 ) && ! $force ) {
			$stats = array(
				'original_bytes'  => $original_size,
				'optimized_bytes' => $original_size,
				'saved_bytes'     => 0,
				'saved_percent'   => 0,
				'quality'         => 0,
				'mime'            => $mime,
				'optimized_at'    => current_time( 'mysql' ),
				'skipped'         => true,
				'skip_reason'     => 'small',
			);
			$this->mark_processed( $attachment_id, $stats );
			$this->processing = false;
			return array(
				'success' => true,
				'message' => __( 'Skipped (file already small).', 'ai-image-optimization' ),
				'source'  => 'skipped',
				'stats'   => $stats,
			);
		}

		$quality = $this->resolve_quality( $attachment_id, $mime );

		$bytes_before = $original_size;
		$file_result  = $this->optimize_file( $path, $mime, $quality );

		if ( is_wp_error( $file_result ) ) {
			// Leave the queue so bulk compress cannot loop forever on the same IDs.
			$this->mark_processed(
				$attachment_id,
				array(
					'saved_bytes'  => 0,
					'skipped'      => true,
					'skip_reason'  => 'optimize_error',
					'error'        => $file_result->get_error_message(),
					'optimized_at' => current_time( 'mysql' ),
				)
			);
			$this->processing = false;
			return array(
				'success' => false,
				'message' => $file_result->get_error_message(),
				'source'  => 'failed',
			);
		}

		$new_path = $file_result['path'];
		$new_mime = $file_result['mime'];

		// Update attachment if converted / path changed.
		if ( $new_path !== $path ) {
			update_attached_file( $attachment_id, $new_path );

			if ( $new_mime !== $mime ) {
				wp_update_post(
					array(
						'ID'             => $attachment_id,
						'post_mime_type' => $new_mime,
					)
				);
			}

			// Remove old original if replaced with WebP.
			if ( ! empty( $this->settings->get( 'replace_original' ) ) && file_exists( $path ) && $path !== $new_path ) {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}

			$path = $new_path;
			$mime = $new_mime;
		}

		clearstatcache( true, $path );
		$bytes_after = file_exists( $path ) ? filesize( $path ) : $bytes_before;

		// Regenerate sizes from optimized original (processing flag blocks recursion).
		$metadata = wp_generate_attachment_metadata( $attachment_id, $path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		if ( ! empty( $this->settings->get( 'optimize_thumbnails' ) ) && ! empty( $metadata['sizes'] ) ) {
			$base_dir = trailingslashit( dirname( $path ) );

			foreach ( $metadata['sizes'] as $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}

				$thumb_path = $base_dir . $size_data['file'];
				$thumb_mime = $size_data['mime-type'] ?? $mime;

				if ( file_exists( $thumb_path ) ) {
					$this->optimize_file( $thumb_path, $thumb_mime, $quality, false );
				}
			}
		}

		clearstatcache( true, $path );
		$bytes_after = file_exists( $path ) ? filesize( $path ) : $bytes_after;
		$saved       = max( 0, $bytes_before - $bytes_after );
		$percent     = $bytes_before > 0 ? round( ( $saved / $bytes_before ) * 100, 1 ) : 0;

		$stats = array(
			'original_bytes'  => $bytes_before,
			'optimized_bytes' => $bytes_after,
			'saved_bytes'     => $saved,
			'saved_percent'   => $percent,
			'quality'         => $quality,
			'mime'            => $mime,
			'optimized_at'    => current_time( 'mysql' ),
			'ai_used'         => (bool) $this->settings->get( 'ai_smart_quality' ) && $this->settings->is_ai_configured(),
		);

		$this->mark_processed( $attachment_id, $stats );

		$total = (int) get_option( self::TOTAL_SAVED, 0 );
		update_option( self::TOTAL_SAVED, $total + $saved, false );

		$this->processing = false;

		return array(
			'success'  => true,
			'message'  => sprintf(
				/* translators: 1: percent saved, 2: human readable bytes */
				__( 'Optimized — saved %1$s%% (%2$s).', 'ai-image-optimization' ),
				$percent,
				size_format( $saved )
			),
			'stats'    => $stats,
			'metadata' => $metadata,
		);
	}

	/**
	 * Mark attachment as processed so it leaves the pending queue.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $stats         Stats payload.
	 * @return void
	 */
	private function mark_processed( $attachment_id, $stats ) {
		update_post_meta( $attachment_id, self::META_KEY, 1 );
		update_post_meta( $attachment_id, self::META_STATS, is_array( $stats ) ? $stats : array() );
	}

	/**
	 * Resolve quality (AI smart or defaults).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $mime          Mime type.
	 * @return int
	 */
	private function resolve_quality( $attachment_id, $mime ) {
		if ( $this->settings->get( 'ai_smart_quality' ) && $this->settings->is_ai_configured() ) {
			$suggested = $this->analyzer->suggest_quality( $attachment_id );

			if ( null !== $suggested ) {
				return $suggested;
			}
		}

		if ( 'image/png' === $mime ) {
			return (int) $this->settings->get( 'png_quality' );
		}

		if ( 'image/webp' === $mime ) {
			return (int) $this->settings->get( 'webp_quality' );
		}

		return (int) $this->settings->get( 'jpeg_quality' );
	}

	/**
	 * Optimize a single file on disk.
	 *
	 * @param string $path            File path.
	 * @param string $mime            Mime type.
	 * @param int    $quality         Quality 1–100.
	 * @param bool   $allow_convert   Whether WebP conversion is allowed.
	 * @return array{path:string,mime:string,ai_used?:bool}|WP_Error
	 */
	private function optimize_file( $path, $mime, $quality, $allow_convert = true ) {
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			return new WP_Error( 'aiio_no_editor', __( 'Image editor not available.', 'ai-image-optimization' ) );
		}

		$editor = wp_get_image_editor( $path );

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$max_w = (int) $this->settings->get( 'max_width' );
		$max_h = (int) $this->settings->get( 'max_height' );

		if ( $max_w > 0 || $max_h > 0 ) {
			$size = $editor->get_size();

			if ( ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
				$needs_resize = ( $max_w > 0 && $size['width'] > $max_w ) || ( $max_h > 0 && $size['height'] > $max_h );

				if ( $needs_resize ) {
					$editor->resize(
						$max_w > 0 ? $max_w : null,
						$max_h > 0 ? $max_h : null,
						false
					);
				}
			}
		}

		$editor->set_quality( $quality );

		$convert_webp = $allow_convert && $this->settings->get( 'convert_webp' );
		$supports_webp = function_exists( 'imagewebp' ) || ( method_exists( $editor, 'supports_mime_type' ) && $editor->supports_mime_type( 'image/webp' ) );

		$dest_path = $path;
		$dest_mime = $mime;

		if ( $convert_webp && $supports_webp && 'image/webp' !== $mime ) {
			$info      = pathinfo( $path );
			$webp_path = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.webp';

			$saved = $editor->save( $webp_path, 'image/webp' );

			if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
				$dest_path = $saved['path'];
				$dest_mime = 'image/webp';

				if ( $this->settings->get( 'replace_original' ) && $path !== $dest_path && file_exists( $path ) ) {
					@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}

				return array(
					'path' => $dest_path,
					'mime' => $dest_mime,
				);
			}
		}

		// Re-save original format compressed.
		$saved = $editor->save( $path, $mime );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'path' => $saved['path'] ?? $path,
			'mime' => $saved['mime-type'] ?? $mime,
		);
	}

	/**
	 * Count images not yet optimized.
	 *
	 * @return int
	 */
	public function count_unoptimized() {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::META_KEY,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Get unoptimized attachment IDs.
	 *
	 * @param int $limit Batch size.
	 * @return int[]
	 */
	public function get_unoptimized_ids( $limit = 5 ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'     => self::META_KEY,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Total bytes saved across site.
	 *
	 * @return int
	 */
	public function get_total_saved() {
		return (int) get_option( self::TOTAL_SAVED, 0 );
	}

	/**
	 * Get AI analyzer instance.
	 *
	 * @return AIIO_AI_Analyzer
	 */
	public function get_analyzer() {
		return $this->analyzer;
	}
}
