<?php
namespace FrontendBlockVisibility\Evaluator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates device breakpoint rules and returns responsive CSS class names.
 */
class DeviceEvaluator {

	/**
	 * Get CSS classes to hide block on specific devices.
	 *
	 * @param array $visibility_settings Block attributes.
	 * @return array Array of CSS classes.
	 */
	public static function get_device_classes( $visibility_settings ) {
		$classes = array();

		if ( ! empty( $visibility_settings['hideOnDesktop'] ) ) {
			$classes[] = 'fbv-hide-desktop';
		}

		if ( ! empty( $visibility_settings['hideOnTablet'] ) ) {
			$classes[] = 'fbv-hide-tablet';
		}

		if ( ! empty( $visibility_settings['hideOnMobile'] ) ) {
			$classes[] = 'fbv-hide-mobile';
		}

		return $classes;
	}
}
