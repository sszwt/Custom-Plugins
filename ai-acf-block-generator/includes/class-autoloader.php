<?php
/**
 * PSR-4 style autoloader for the plugin.
 *
 * @package AABG
 */

namespace AABG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class Class name.
	 */
	public static function autoload( $class ) {
		$prefix = 'AABG\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = str_replace( '\\', '/', $relative );
		$parts    = explode( '/', $relative );

		$class_name = array_pop( $parts );
		$file_name  = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

		if ( ! empty( $parts ) ) {
			// Sub-namespaces map directly to directory names (e.g. AABG\Utils => includes/Utils).
			// Do NOT lowercase: case-sensitive filesystems (Linux) require an exact match.
			$subdir = implode( '/', $parts );
			$file   = AABG_PLUGIN_DIR . 'includes/' . $subdir . '/' . $file_name;
		} else {
			$file = AABG_PLUGIN_DIR . 'includes/' . $file_name;
		}

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
