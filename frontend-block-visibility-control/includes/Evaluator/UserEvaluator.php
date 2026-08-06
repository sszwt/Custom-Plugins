<?php
namespace FrontendBlockVisibility\Evaluator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates block visibility based on user login state and roles.
 */
class UserEvaluator {

	/**
	 * Determine if block should be visible based on user rules.
	 *
	 * @param array $visibility_settings Block visibility attributes array.
	 * @return bool True if visible, false if hidden.
	 */
	public static function is_visible( $visibility_settings ) {
		$rule = isset( $visibility_settings['userRule'] ) ? $visibility_settings['userRule'] : 'all';

		if ( 'all' === $rule || empty( $rule ) ) {
			return true;
		}

		$is_user_logged_in = is_user_logged_in();

		if ( 'logged_in' === $rule ) {
			return $is_user_logged_in;
		}

		if ( 'logged_out' === $rule ) {
			return ! $is_user_logged_in;
		}

		if ( 'roles' === $rule ) {
			if ( ! $is_user_logged_in ) {
				return false;
			}

			$allowed_roles = isset( $visibility_settings['allowedRoles'] ) ? (array) $visibility_settings['allowedRoles'] : array();
			if ( empty( $allowed_roles ) ) {
				return true;
			}

			$current_user = wp_get_current_user();
			$user_roles   = (array) $current_user->roles;

			$intersection = array_intersect( $allowed_roles, $user_roles );
			return ! empty( $intersection );
		}

		return true;
	}
}
