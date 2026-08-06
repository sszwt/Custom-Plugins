<?php
namespace FrontendBlockVisibility\Evaluator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates block visibility based on Date and Time schedules.
 */
class ScheduleEvaluator {

	/**
	 * Determine if block is visible according to start & end date schedules.
	 *
	 * @param array $visibility_settings Block attributes.
	 * @return bool True if visible, false if hidden.
	 */
	public static function is_visible( $visibility_settings ) {
		if ( empty( $visibility_settings['scheduleEnabled'] ) ) {
			return true;
		}

		$current_time = current_time( 'timestamp' );
		$start_date   = ! empty( $visibility_settings['startDate'] ) ? strtotime( $visibility_settings['startDate'] ) : false;
		$end_date     = ! empty( $visibility_settings['endDate'] ) ? strtotime( $visibility_settings['endDate'] ) : false;

		// Check start date schedule constraint.
		if ( $start_date && $current_time < $start_date ) {
			return false;
		}

		// Check end date schedule constraint.
		if ( $end_date && $current_time > $end_date ) {
			return false;
		}

		return true;
	}
}
