<?php
/**
 * Lightweight debug logger for the plugin.
 *
 * @package AABG
 */

namespace AABG\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 *
 * Writes diagnostic messages to the WordPress debug log (only when WP_DEBUG
 * is enabled) and persists invalid generated code to a dedicated log directory
 * so broken AI output can be inspected after a failed generation.
 */
class Logger {

	/**
	 * Whether logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Log a message to the PHP error log when debugging is enabled.
	 *
	 * @param string $message Message.
	 * @param mixed  $context Optional context data.
	 */
	public static function log( $message, $context = null ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$line = '[AI ACF Block Generator] ' . $message;

		if ( null !== $context ) {
			$line .= ' ' . wp_json_encode( $context );
		}

		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Persist invalid generated code to a log file for debugging.
	 *
	 * @param string $label   Identifier (e.g. slug or file name).
	 * @param string $code    The invalid code that was generated.
	 * @param string $message The parser/error message.
	 * @return string|false Path to the written log file, or false on failure.
	 */
	public static function log_invalid_code( $label, $code, $message ) {
		$dir = self::get_log_dir();

		if ( ! $dir ) {
			self::log( 'Invalid generated code (no log dir): ' . $label, array( 'error' => $message ) );
			return false;
		}

		$safe_label = sanitize_file_name( $label );
		$file        = trailingslashit( $dir ) . $safe_label . '-' . gmdate( 'Ymd-His' ) . '.log';

		$contents  = "Generated: " . gmdate( 'c' ) . "\n";
		$contents .= "Label: {$label}\n";
		$contents .= "Error: {$message}\n";
		$contents .= "----------------------------------------\n";
		$contents .= $code;

		$written = file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		self::log( 'Invalid generated code saved for ' . $label, array( 'error' => $message, 'file' => $file ) );

		return false !== $written ? $file : false;
	}

	/**
	 * Get (and lazily create) the debug log directory inside uploads.
	 *
	 * @return string|false
	 */
	private static function get_log_dir() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}

		$dir = trailingslashit( $uploads['basedir'] ) . 'aabg-debug';

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		// Prevent public listing / direct access to logged code.
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $dir;
	}
}
