<?php
/**
 * Admin UI and AJAX handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIIO_Admin {

	/**
	 * @var AIIO_Settings
	 */
	private $settings;

	/**
	 * @var AIIO_Optimizer
	 */
	private $optimizer;

	/**
	 * @var AIIO_Format_Cache
	 */
	private $format_cache;

	/**
	 * @var AIIO_Standardizer
	 */
	private $standardizer;

	/**
	 * @param AIIO_Settings      $settings     Plugin settings.
	 * @param AIIO_Optimizer     $optimizer    Optimizer service.
	 * @param AIIO_Format_Cache  $format_cache Format delivery cache.
	 * @param AIIO_Standardizer  $standardizer SEO/a11y standardizer.
	 */
	public function __construct( AIIO_Settings $settings, AIIO_Optimizer $optimizer, AIIO_Format_Cache $format_cache, AIIO_Standardizer $standardizer ) {
		$this->settings     = $settings;
		$this->optimizer    = $optimizer;
		$this->format_cache = $format_cache;
		$this->standardizer = $standardizer;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_field' ), 10, 2 );
		add_filter( 'bulk_actions-upload', array( $this, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_action' ), 10, 3 );

		add_action( 'wp_ajax_aiio_optimize', array( $this, 'ajax_optimize' ) );
		add_action( 'wp_ajax_aiio_bulk_optimize', array( $this, 'ajax_bulk_optimize' ) );
		add_action( 'wp_ajax_aiio_bulk_alt', array( $this, 'ajax_bulk_alt' ) );
		add_action( 'wp_ajax_aiio_test_api', array( $this, 'ajax_test_api' ) );
		add_action( 'wp_ajax_aiio_clear_cache', array( $this, 'ajax_clear_cache' ) );

		add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'bulk_admin_notice' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'AI Image Optimization', 'ai-image-optimization' ),
			__( 'AI Image Optimize', 'ai-image-optimization' ),
			'manage_options',
			'ai-image-optimization',
			array( $this, 'render_settings_page' ),
			'dashicons-format-image',
			58
		);

		add_submenu_page(
			'ai-image-optimization',
			__( 'Settings', 'ai-image-optimization' ),
			__( 'Settings', 'ai-image-optimization' ),
			'manage_options',
			'ai-image-optimization',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$allowed_hooks = array(
			'toplevel_page_ai-image-optimization',
			'upload.php',
			'post.php',
			'post-new.php',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'aiio-admin',
			AIIO_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AIIO_VERSION
		);

		wp_enqueue_script(
			'aiio-admin',
			AIIO_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AIIO_VERSION,
			true
		);

		wp_localize_script(
			'aiio-admin',
			'aiioAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aiio_optimize' ),
				'i18n'    => array(
					'optimizing'   => __( 'Optimizing image...', 'ai-image-optimization' ),
					'success'      => __( 'Image optimized!', 'ai-image-optimization' ),
					'error'        => __( 'Could not optimize image.', 'ai-image-optimization' ),
					'bulkDone'     => __( 'Bulk optimization complete.', 'ai-image-optimization' ),
					'altRunning'   => __( 'Standardizing ALT text...', 'ai-image-optimization' ),
					'altDone'      => __( 'ALT standardization complete.', 'ai-image-optimization' ),
					'testing'      => __( 'Testing API...', 'ai-image-optimization' ),
					'clearing'     => __( 'Clearing cache...', 'ai-image-optimization' ),
					'cacheCleared' => __( 'Delivery cache cleared.', 'ai-image-optimization' ),
				),
			)
		);
	}

	/**
	 * Add optimize button to attachment edit screen.
	 *
	 * @param array<string, array<string, mixed>> $fields Attachment fields.
	 * @param WP_Post                             $post   Attachment post.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_attachment_field( $fields, $post ) {
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}

		$stats   = get_post_meta( $post->ID, AIIO_Optimizer::META_STATS, true );
		$summary = '';

		if ( is_array( $stats ) && isset( $stats['saved_percent'] ) ) {
			$summary = sprintf(
				/* translators: 1: percent, 2: size */
				__( 'Saved %1$s%% (%2$s)', 'ai-image-optimization' ),
				$stats['saved_percent'],
				size_format( (int) ( $stats['saved_bytes'] ?? 0 ) )
			);
		}

		$fields['aiio_optimize'] = array(
			'label' => __( 'AI Optimize', 'ai-image-optimization' ),
			'input' => 'html',
			'html'  => sprintf(
				'<button type="button" class="button button-secondary aiio-optimize-btn" data-attachment-id="%1$d" data-force="1">%2$s</button>
				<p class="description aiio-optimize-status" id="aiio-status-%1$d">%3$s</p>',
				(int) $post->ID,
				esc_html__( 'Optimize Image', 'ai-image-optimization' ),
				esc_html( $summary )
			),
		);

		return $fields;
	}

	/**
	 * Register bulk action.
	 *
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public function register_bulk_action( $actions ) {
		$actions['aiio_optimize'] = __( 'Optimize with AI Image Optimization', 'ai-image-optimization' );
		return $actions;
	}

	/**
	 * Handle bulk action.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action name.
	 * @param int[]  $post_ids    Selected attachment IDs.
	 * @return string
	 */
	public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'aiio_optimize' !== $action ) {
			return $redirect_to;
		}

		$count = 0;

		foreach ( $post_ids as $post_id ) {
			$result = $this->optimizer->optimize_attachment( (int) $post_id, true );

			if ( ! empty( $result['success'] ) && ( $result['source'] ?? '' ) !== 'existing' ) {
				++$count;
			}
		}

		return add_query_arg( 'aiio_bulk_done', $count, $redirect_to );
	}

	/**
	 * AJAX: optimize single attachment.
	 */
	public function ajax_optimize() {
		check_ajax_referer( 'aiio_optimize', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-image-optimization' ) ), 403 );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$force         = ! empty( $_POST['force'] );

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'ai-image-optimization' ) ) );
		}

		$result = $this->optimizer->optimize_attachment( $attachment_id, $force );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? __( 'Optimization failed.', 'ai-image-optimization' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => $result['message'],
				'stats'   => $result['stats'] ?? array(),
			)
		);
	}

	/**
	 * AJAX: bulk optimize from settings page.
	 */
	public function ajax_bulk_optimize() {
		check_ajax_referer( 'aiio_optimize', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-image-optimization' ) ), 403 );
		}

		$batch = isset( $_POST['batch'] ) ? absint( $_POST['batch'] ) : 5;
		$batch = min( max( 1, $batch ), 10 );

		$ids = $this->optimizer->get_unoptimized_ids( $batch );

		$processed = 0;
		$updated   = 0;
		$saved     = 0;

		foreach ( $ids as $attachment_id ) {
			++$processed;
			$result = $this->optimizer->optimize_attachment( (int) $attachment_id, false );
			$source = $result['source'] ?? '';

			// Count real compressions only (skip/fail still leave the pending queue).
			if ( ! empty( $result['success'] ) && ! in_array( $source, array( 'existing', 'skipped', 'failed' ), true ) ) {
				++$updated;
				$saved += (int) ( $result['stats']['saved_bytes'] ?? 0 );
			}
		}

		$remaining = $this->optimizer->count_unoptimized();

		wp_send_json_success(
			array(
				'processed'  => $processed,
				'updated'    => $updated,
				'saved'      => $saved,
				'savedHuman' => size_format( $saved ),
				'remaining'  => $remaining,
				'done'       => 0 === $processed || 0 === $remaining,
			)
		);
	}

	/**
	 * AJAX: bulk fill missing ALT text.
	 */
	public function ajax_bulk_alt() {
		check_ajax_referer( 'aiio_optimize', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-image-optimization' ) ), 403 );
		}

		$batch = isset( $_POST['batch'] ) ? absint( $_POST['batch'] ) : 5;
		$batch = min( max( 1, $batch ), 10 );

		$ids       = $this->standardizer->get_missing_alt_ids( $batch );
		$processed = 0;
		$updated   = 0;

		foreach ( $ids as $attachment_id ) {
			++$processed;
			$result = $this->standardizer->ensure_alt_text( (int) $attachment_id, false );
			if ( ! empty( $result['success'] ) && ( $result['source'] ?? '' ) !== 'existing' ) {
				++$updated;
			}
		}

		wp_send_json_success(
			array(
				'processed' => $processed,
				'updated'   => $updated,
				'remaining' => $this->standardizer->count_missing_alt(),
				'done'      => 0 === $processed,
			)
		);
	}

	/**
	 * AJAX: clear frontend delivery cache.
	 */
	public function ajax_clear_cache() {
		check_ajax_referer( 'aiio_optimize', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-image-optimization' ) ), 403 );
		}

		$deleted = $this->format_cache->clear_cache();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: deleted file count */
					__( 'Cleared %d cached files. Media Library originals were not touched.', 'ai-image-optimization' ),
					$deleted
				),
				'deleted' => $deleted,
			)
		);
	}

	/**
	 * AJAX: test API connection.
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'aiio_optimize', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-image-optimization' ) ), 403 );
		}

		$result = $this->optimizer->get_analyzer()->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: provider name */
					__( '%s API key works!', 'ai-image-optimization' ),
					$this->settings->get_provider_label()
				),
			)
		);
	}

	/**
	 * Add optimization column to media library.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_media_column( $columns ) {
		$columns['aiio_status'] = __( 'Optimized', 'ai-image-optimization' );
		$columns['aiio_alt']    = __( 'ALT', 'ai-image-optimization' );
		return $columns;
	}

	/**
	 * Render optimization column.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Attachment ID.
	 */
	public function render_media_column( $column_name, $post_id ) {
		if ( ! wp_attachment_is_image( $post_id ) ) {
			return;
		}

		if ( 'aiio_alt' === $column_name ) {
			$alt = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
			if ( '' !== trim( (string) $alt ) ) {
				echo '<span class="aiio-badge aiio-badge--ok" title="' . esc_attr( $alt ) . '">' . esc_html__( 'OK', 'ai-image-optimization' ) . '</span>';
			} else {
				echo '<span class="aiio-badge aiio-badge--pending">' . esc_html__( 'Missing', 'ai-image-optimization' ) . '</span>';
			}
			return;
		}

		if ( 'aiio_status' !== $column_name ) {
			return;
		}

		$done  = get_post_meta( $post_id, AIIO_Optimizer::META_KEY, true );
		$stats = get_post_meta( $post_id, AIIO_Optimizer::META_STATS, true );

		if ( $done && is_array( $stats ) ) {
			printf(
				'<span class="aiio-badge aiio-badge--ok" title="%1$s">-%2$s%%</span>',
				esc_attr(
					sprintf(
						/* translators: %s: human size */
						__( 'Saved %s', 'ai-image-optimization' ),
						size_format( (int) ( $stats['saved_bytes'] ?? 0 ) )
					)
				),
				esc_html( (string) ( $stats['saved_percent'] ?? 0 ) )
			);
			return;
		}

		echo '<span class="aiio-badge aiio-badge--pending">' . esc_html__( 'Pending', 'ai-image-optimization' ) . '</span>';
		echo ' <button type="button" class="button button-small aiio-optimize-btn" data-attachment-id="' . esc_attr( (string) $post_id ) . '" data-force="1">' . esc_html__( 'Optimize', 'ai-image-optimization' ) . '</button>';
	}

	/**
	 * Show notice after media bulk action.
	 */
	public function bulk_admin_notice() {
		if ( ! isset( $_GET['aiio_bulk_done'] ) ) {
			return;
		}

		$count = absint( $_GET['aiio_bulk_done'] );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of images */
					_n( 'Optimized %d image.', 'Optimized %d images.', $count, 'ai-image-optimization' ),
					$count
				)
			)
		);
	}

	/**
	 * Render settings / dashboard page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings       = $this->settings->get_all();
		$pending        = $this->optimizer->count_unoptimized();
		$total_saved    = $this->optimizer->get_total_saved();
		$configured     = $this->settings->is_ai_configured();
		$cached_files   = $this->format_cache->count_cached_files();
		$format_choices = AIIO_Format_Cache::get_format_choices();
		$missing_alt    = $this->standardizer->count_missing_alt();

		include AIIO_PLUGIN_DIR . 'templates/settings-page.php';
	}
}
