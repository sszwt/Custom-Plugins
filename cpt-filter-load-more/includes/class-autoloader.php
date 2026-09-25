<?php
/**
 * Autoloader.
 *
 * @package CPTFLM
 */

namespace CPTFLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Register.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload.
	 *
	 * @param string $class Class.
	 */
	public static function autoload( $class ) {
		$prefix = 'CPTFLM\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative   = substr( $class, strlen( $prefix ) );
		$relative   = str_replace( '\\', '/', $relative );
		$parts      = explode( '/', $relative );
		$class_name = array_pop( $parts );
		$file_name  = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

		if ( ! empty( $parts ) ) {
			$file = CPTFLM_PLUGIN_DIR . 'includes/' . implode( '/', $parts ) . '/' . $file_name;
		} else {
			$file = CPTFLM_PLUGIN_DIR . 'includes/' . $file_name;
		}

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
