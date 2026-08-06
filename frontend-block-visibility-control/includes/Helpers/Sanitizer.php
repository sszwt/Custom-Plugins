<?php
namespace FrontendBlockVisibility\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper for capability checks, nonces, and sanitization.
 */
class Sanitizer {

	/**
	 * Verify user capability.
	 *
	 * @param string $cap Capability string.
	 * @return bool
	 */
	public static function check_capability( $cap = 'manage_options' ) {
		return current_user_can( $cap );
	}

	/**
	 * Verify Nonce safely.
	 *
	 * @param string $nonce_val Nonce string.
	 * @param string $action Nonce action.
	 * @return bool
	 */
	public static function verify_nonce( $nonce_val, $action = 'fbv_control_nonce' ) {
		return ! empty( $nonce_val ) && wp_verify_nonce( $nonce_val, $action );
	}

	/**
	 * Sanitize plugin settings array.
	 *
	 * @param array $input    Raw input.
	 * @param array $existing Previous options (preserves keys not in the form).
	 * @return array Sanitized array.
	 */
	public static function sanitize_settings( $input, $existing = array() ) {
		$existing = is_array( $existing ) ? $existing : array();

		$output = array(
			'enable_user_rules'     => isset( $existing['enable_user_rules'] ) ? (int) ! empty( $existing['enable_user_rules'] ) : 1,
			'enable_device_rules'   => isset( $existing['enable_device_rules'] ) ? (int) ! empty( $existing['enable_device_rules'] ) : 1,
			'enable_schedule_rules' => isset( $existing['enable_schedule_rules'] ) ? (int) ! empty( $existing['enable_schedule_rules'] ) : 1,
			'enable_url_rules'      => isset( $existing['enable_url_rules'] ) ? (int) ! empty( $existing['enable_url_rules'] ) : 1,
			'enable_woo_rules'      => isset( $existing['enable_woo_rules'] ) ? (int) ! empty( $existing['enable_woo_rules'] ) : 1,
			'hide_method'           => isset( $existing['hide_method'] ) && 'css_hide' === $existing['hide_method'] ? 'css_hide' : 'server_side',
		);

		// Form may still post module keys — ignore them; UI removed.
		if ( isset( $input['hide_method'] ) && 'css_hide' === $input['hide_method'] ) {
			$output['hide_method'] = 'css_hide';
		}

		$output['globally_hidden_blocks'] = isset( $input['globally_hidden_blocks'] ) && is_array( $input['globally_hidden_blocks'] )
			? array_map( 'sanitize_text_field', $input['globally_hidden_blocks'] )
			: array();

		return $output;
	}
}
