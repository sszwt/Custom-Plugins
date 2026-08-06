<?php
namespace FrontendBlockVisibility\Evaluator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates block visibility based on URL Query Parameters.
 */
class UrlEvaluator {

	/**
	 * Determine if block is visible according to URL query string rules.
	 *
	 * @param array $visibility_settings Block attributes.
	 * @return bool True if visible, false if hidden.
	 */
	public static function is_visible( $visibility_settings ) {
		$rule = isset( $visibility_settings['urlParamRule'] ) ? $visibility_settings['urlParamRule'] : 'none';

		if ( 'none' === $rule || empty( $rule ) ) {
			return true;
		}

		$key = isset( $visibility_settings['urlParamKey'] ) ? trim( $visibility_settings['urlParamKey'] ) : '';
		if ( empty( $key ) ) {
			return true;
		}

		$has_param = isset( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'has_param' === $rule ) {
			return $has_param;
		}

		if ( 'no_param' === $rule ) {
			return ! $has_param;
		}

		if ( 'param_value' === $rule ) {
			if ( ! $has_param ) {
				return false;
			}
			$expected_val = isset( $visibility_settings['urlParamVal'] ) ? trim( $visibility_settings['urlParamVal'] ) : '';
			$actual_val   = sanitize_text_field( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $expected_val === $actual_val;
		}

		return true;
	}
}
