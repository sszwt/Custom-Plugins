<?php
/**
 * AJAX handlers.
 *
 * @package AIFAQ
 */

namespace AIFAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ajax
 */
class Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$actions = array(
			'aifaq_generate',
			'aifaq_save_set',
			'aifaq_delete_set',
			'aifaq_get_set',
			'aifaq_save_settings',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'aifaq_', 'handle_', $action ) ) );
		}
	}

	/**
	 * Capability check.
	 */
	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-faq-generator-publisher' ) ), 403 );
		}
		check_ajax_referer( 'aifaq_admin', 'nonce' );
	}

	/**
	 * Generate FAQs.
	 */
	public function handle_generate() {
		$this->guard();

		$topic = isset( $_POST['topic'] ) ? sanitize_textarea_field( wp_unslash( $_POST['topic'] ) ) : '';
		$count = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 8;
		$tone  = isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : 'clear and helpful';
		$extra = isset( $_POST['extra'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra'] ) ) : '';

		$result = AI::generate( $topic, $count, $tone, $extra );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'items' => $result ) );
	}

	/**
	 * Save / publish FAQ set.
	 */
	public function handle_save_set() {
		$this->guard();

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'publish';
		$items  = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore

		if ( is_string( $items ) ) {
			$decoded = json_decode( $items, true );
			$items   = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$status = 'publish';
		}

		if ( '' === $title ) {
			$title = __( 'Untitled FAQ Set', 'ai-faq-generator-publisher' );
		}

		$postarr = array(
			'post_type'   => Post_Type::SLUG,
			'post_title'  => $title,
			'post_status' => $status,
		);

		if ( $id > 0 ) {
			$postarr['ID'] = $id;
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		Post_Type::save_items( (int) $result, $items );

		wp_send_json_success(
			array(
				'id'        => (int) $result,
				'shortcode' => '[ai_faq id="' . (int) $result . '"]',
				'sets'      => Post_Type::list_sets(),
				'message'   => __( 'FAQ set saved.', 'ai-faq-generator-publisher' ),
			)
		);
	}

	/**
	 * Delete set.
	 */
	public function handle_delete_set() {
		$this->guard();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid set.', 'ai-faq-generator-publisher' ) ) );
		}
		wp_delete_post( $id, true );
		wp_send_json_success(
			array(
				'sets'    => Post_Type::list_sets(),
				'message' => __( 'FAQ set deleted.', 'ai-faq-generator-publisher' ),
			)
		);
	}

	/**
	 * Get one set.
	 */
	public function handle_get_set() {
		$this->guard();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$post = get_post( $id );
		if ( ! $post || Post_Type::SLUG !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'FAQ set not found.', 'ai-faq-generator-publisher' ) ) );
		}
		wp_send_json_success(
			array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'items'  => Post_Type::get_items( $post->ID ),
			)
		);
	}

	/**
	 * Save settings.
	 */
	public function handle_save_settings() {
		$this->guard();

		$current = Plugin::get_settings();
		$openai  = isset( $_POST['openai_key'] ) ? trim( wp_unslash( $_POST['openai_key'] ) ) : ''; // phpcs:ignore
		$gemini  = isset( $_POST['gemini_key'] ) ? trim( wp_unslash( $_POST['gemini_key'] ) ) : ''; // phpcs:ignore

		$settings = array(
			'provider'      => sanitize_key( wp_unslash( $_POST['provider'] ?? 'openai' ) ),
			'openai_key'    => '' !== $openai ? $openai : ( $current['openai_key'] ?? '' ),
			'openai_model'  => sanitize_text_field( wp_unslash( $_POST['openai_model'] ?? 'gpt-4o-mini' ) ),
			'gemini_key'    => '' !== $gemini ? $gemini : ( $current['gemini_key'] ?? '' ),
			'gemini_model'  => sanitize_text_field( wp_unslash( $_POST['gemini_model'] ?? 'gemini-2.5-flash' ) ),
			'default_count' => max( 3, min( 20, absint( $_POST['default_count'] ?? 8 ) ) ),
			'default_tone'  => sanitize_text_field( wp_unslash( $_POST['default_tone'] ?? 'clear and helpful' ) ),
			'accordion'     => array(
				'allow_multiple' => ! empty( $_POST['allow_multiple'] ) ? 1 : 0,
				'open_first'     => ! empty( $_POST['open_first'] ) ? 1 : 0,
				'show_schema'    => ! empty( $_POST['show_schema'] ) ? 1 : 0,
			),
		);

		if ( ! in_array( $settings['provider'], array( 'openai', 'gemini' ), true ) ) {
			$settings['provider'] = 'openai';
		}

		Plugin::update_settings( $settings );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'ai-faq-generator-publisher' ) ) );
	}
}
