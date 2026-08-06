<?php
/**
 * Admin UI and AJAX handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIATG_Admin {

	/**
	 * @var AIATG_Settings
	 */
	private $settings;

	/**
	 * @var AIATG_Generator
	 */
	private $generator;

	/**
	 * @param AIATG_Settings  $settings  Plugin settings.
	 * @param AIATG_Generator $generator Generator service.
	 */
	public function __construct( AIATG_Settings $settings, AIATG_Generator $generator ) {
		$this->settings  = $settings;
		$this->generator = $generator;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_field' ), 10, 2 );
		add_filter( 'bulk_actions-upload', array( $this, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_action' ), 10, 3 );

		add_action( 'wp_ajax_aiatg_generate_alt', array( $this, 'ajax_generate_alt' ) );
		add_action( 'wp_ajax_aiatg_bulk_generate', array( $this, 'ajax_bulk_generate' ) );
		add_action( 'wp_ajax_aiatg_test_api', array( $this, 'ajax_test_api' ) );

		add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'bulk_admin_notice' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_options_page(
			__( 'AI Image ALT Text', 'ai-image-alt-text-generator' ),
			__( 'AI Image ALT Text', 'ai-image-alt-text-generator' ),
			'manage_options',
			'ai-image-alt-text-generator',
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
			'settings_page_ai-image-alt-text-generator',
			'upload.php',
			'post.php',
			'post-new.php',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'aiatg-admin',
			AIATG_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AIATG_VERSION
		);

		wp_enqueue_script(
			'aiatg-admin',
			AIATG_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AIATG_VERSION,
			true
		);

		wp_localize_script(
			'aiatg-admin',
			'aiatgAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aiatg_generate_alt' ),
				'i18n'    => array(
					'generating' => __( 'Generating ALT text...', 'ai-image-alt-text-generator' ),
					'success'    => __( 'ALT text generated!', 'ai-image-alt-text-generator' ),
					'error'      => __( 'Could not generate ALT text.', 'ai-image-alt-text-generator' ),
					'bulkDone'   => __( 'Bulk generation complete.', 'ai-image-alt-text-generator' ),
					'testing'    => __( 'Testing API...', 'ai-image-alt-text-generator' ),
				),
			)
		);
	}

	/**
	 * Add generate button to attachment edit screen.
	 *
	 * @param array<string, array<string, mixed>> $fields  Attachment fields.
	 * @param WP_Post                             $post    Attachment post.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_attachment_field( $fields, $post ) {
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}

		$fields['aiatg_generate'] = array(
			'label' => __( 'AI ALT Text', 'ai-image-alt-text-generator' ),
			'input' => 'html',
			'html'  => sprintf(
				'<button type="button" class="button button-secondary aiatg-generate-btn" data-attachment-id="%1$d">%2$s</button>
				<p class="description aiatg-generate-status" id="aiatg-status-%1$d"></p>',
				(int) $post->ID,
				esc_html__( 'Generate with AI', 'ai-image-alt-text-generator' )
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
		$actions['aiatg_generate_alt'] = __( 'Generate AI ALT Text', 'ai-image-alt-text-generator' );

		return $actions;
	}

	/**
	 * Handle bulk action (redirect with count).
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action name.
	 * @param int[]  $post_ids    Selected attachment IDs.
	 * @return string
	 */
	public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'aiatg_generate_alt' !== $action ) {
			return $redirect_to;
		}

		$count = 0;

		foreach ( $post_ids as $post_id ) {
			$result = $this->generator->generate_for_attachment( (int) $post_id, false );

			if ( ! empty( $result['success'] ) && ! empty( $result['alt_text'] ) ) {
				++$count;
			}
		}

		return add_query_arg( 'aiatg_bulk_done', $count, $redirect_to );
	}

	/**
	 * AJAX: generate ALT for single attachment.
	 */
	public function ajax_generate_alt() {
		check_ajax_referer( 'aiatg_generate_alt', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-image-alt-text-generator' ) ), 403 );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$force         = ! empty( $_POST['force'] );

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'ai-image-alt-text-generator' ) ) );
		}

		$result = $this->generator->generate_for_attachment( $attachment_id, $force );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? __( 'Generation failed.', 'ai-image-alt-text-generator' ) ) );
		}

		wp_send_json_success(
			array(
				'alt_text' => $result['alt_text'],
				'message'  => $result['message'],
			)
		);
	}

	/**
	 * AJAX: bulk generate from settings page.
	 */
	public function ajax_bulk_generate() {
		check_ajax_referer( 'aiatg_generate_alt', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-image-alt-text-generator' ) ), 403 );
		}

		$batch  = isset( $_POST['batch'] ) ? absint( $_POST['batch'] ) : 5;
		$batch  = min( max( 1, $batch ), 20 );

		$ids = $this->generator->get_missing_attachment_ids( $batch );

		$processed = 0;
		$updated   = 0;

		foreach ( $ids as $attachment_id ) {
			++$processed;
			$result = $this->generator->generate_for_attachment( (int) $attachment_id, false );

			if ( ! empty( $result['success'] ) && ! empty( $result['alt_text'] ) && ( $result['source'] ?? '' ) !== 'existing' ) {
				++$updated;
			}
		}

		wp_send_json_success(
			array(
				'processed' => $processed,
				'updated'   => $updated,
				'remaining' => $this->generator->count_missing_alt_text(),
				'done'      => 0 === $processed,
			)
		);
	}

	/**
	 * AJAX: test API connection.
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'aiatg_generate_alt', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-image-alt-text-generator' ) ), 403 );
		}

		$result = $this->generator->test_api_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: provider name */
					__( '%s API key works!', 'ai-image-alt-text-generator' ),
					$this->settings->get_provider_label()
				),
			)
		);
	}

	/**
	 * Add ALT status column to media library.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_media_column( $columns ) {
		$columns['aiatg_alt'] = __( 'ALT Text', 'ai-image-alt-text-generator' );
		return $columns;
	}

	/**
	 * Render ALT status column.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Attachment ID.
	 */
	public function render_media_column( $column_name, $post_id ) {
		if ( 'aiatg_alt' !== $column_name || ! wp_attachment_is_image( $post_id ) ) {
			return;
		}

		$alt = get_post_meta( $post_id, '_wp_attachment_image_alt', true );

		if ( ! empty( $alt ) ) {
			echo '<span class="aiatg-badge aiatg-badge--ok" title="' . esc_attr( $alt ) . '">' . esc_html__( 'Set', 'ai-image-alt-text-generator' ) . '</span>';
			return;
		}

		echo '<span class="aiatg-badge aiatg-badge--missing">' . esc_html__( 'Missing', 'ai-image-alt-text-generator' ) . '</span>';
		echo ' <button type="button" class="button button-small aiatg-generate-btn" data-attachment-id="' . esc_attr( (string) $post_id ) . '">' . esc_html__( 'Generate', 'ai-image-alt-text-generator' ) . '</button>';
	}

	/**
	 * Show notice after media bulk action.
	 */
	public function bulk_admin_notice() {
		if ( ! isset( $_GET['aiatg_bulk_done'] ) ) {
			return;
		}

		$count = absint( $_GET['aiatg_bulk_done'] );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of images */
					_n( 'Generated ALT text for %d image.', 'Generated ALT text for %d images.', $count, 'ai-image-alt-text-generator' ),
					$count
				)
			)
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = $this->settings->get_all();
		$missing   = $this->generator->count_missing_alt_text();
		$configured = $this->settings->is_configured();

		include AIATG_PLUGIN_DIR . 'templates/settings-page.php';
	}
}
