<?php
namespace FrontendBlockVisibility\Evaluator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates block visibility based on WooCommerce Cart & Customer rules.
 */
class WooCommerceEvaluator {

	/**
	 * Determine if block is visible according to WooCommerce rules.
	 *
	 * @param array $visibility_settings Block attributes.
	 * @return bool True if visible, false if hidden.
	 */
	public static function is_visible( $visibility_settings ) {
		$rule = isset( $visibility_settings['wooRule'] ) ? $visibility_settings['wooRule'] : 'none';

		if ( 'none' === $rule || empty( $rule ) || ! class_exists( 'WooCommerce' ) ) {
			return true;
		}

		if ( 'cart_not_empty' === $rule ) {
			return ( WC()->cart && ! WC()->cart->is_empty() );
		}

		if ( 'cart_empty' === $rule ) {
			return ( WC()->cart && WC()->cart->is_empty() );
		}

		if ( 'cart_min_total' === $rule ) {
			if ( ! WC()->cart ) {
				return false;
			}
			$min_total = isset( $visibility_settings['wooMinTotal'] ) ? (float) $visibility_settings['wooMinTotal'] : 0;
			return ( (float) WC()->cart->get_total( 'edit' ) >= $min_total );
		}

		return true;
	}
}
