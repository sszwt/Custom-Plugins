<?php
/**
 * Settings admin page.
 *
 * @package AABG
 */

namespace AABG\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings_Page
 */
class Settings_Page {

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-acf-block-generator' ) );
		}

		if ( isset( $_POST['aabg_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aabg_settings_nonce'] ) ), 'aabg_save_settings' ) ) {
			$this->save_settings();
		}

		$use_openai      = get_option( 'aabg_use_openai', '0' );
		$ai_provider     = get_option( 'aabg_ai_provider', 'openai' );
		$api_key         = get_option( 'aabg_openai_api_key', '' );
		$gemini_key      = get_option( 'aabg_gemini_api_key', '' );
		$model           = get_option( 'aabg_openai_model', 'gpt-4o-mini' );
		$gemini_model    = get_option( 'aabg_gemini_model', 'gemini-2.5-flash' );
		$theme_path      = get_option( 'aabg_theme_path', '' );
		$diagnostics     = \AABG\Utils\Theme_Integrator::get_diagnostics();

		include AABG_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Save plugin settings.
	 */
	private function save_settings() {
		$use_openai  = isset( $_POST['aabg_use_openai'] ) ? '1' : '0';
		$ai_provider = sanitize_text_field( wp_unslash( $_POST['aabg_ai_provider'] ?? 'openai' ) );
		$new_openai  = isset( $_POST['aabg_openai_api_key'] ) ? trim( wp_unslash( $_POST['aabg_openai_api_key'] ) ) : '';
		$new_gemini  = isset( $_POST['aabg_gemini_api_key'] ) ? trim( wp_unslash( $_POST['aabg_gemini_api_key'] ) ) : '';
		$model       = sanitize_text_field( wp_unslash( $_POST['aabg_openai_model'] ?? 'gpt-4o-mini' ) );
		$gemini_model = sanitize_text_field( wp_unslash( $_POST['aabg_gemini_model'] ?? 'gemini-2.5-flash' ) );
		$theme_path  = sanitize_text_field( wp_unslash( $_POST['aabg_theme_path'] ?? '' ) );

		if ( ! in_array( $ai_provider, array( 'openai', 'gemini' ), true ) ) {
			$ai_provider = 'openai';
		}

		if ( ! empty( $theme_path ) ) {
			$theme_path = wp_normalize_path( $theme_path );
		}

		update_option( 'aabg_use_openai', $use_openai );
		update_option( 'aabg_ai_provider', $ai_provider );

		if ( ! empty( $new_openai ) ) {
			update_option( 'aabg_openai_api_key', $new_openai );
		}

		if ( ! empty( $new_gemini ) ) {
			update_option( 'aabg_gemini_api_key', $new_gemini );
		}

		update_option( 'aabg_openai_model', $model );
		update_option( 'aabg_gemini_model', $gemini_model );
		update_option( 'aabg_theme_path', $theme_path );

		add_settings_error(
			'aabg_settings',
			'settings_saved',
			__( 'Settings saved.', 'ai-acf-block-generator' ),
			'success'
		);
	}
}
