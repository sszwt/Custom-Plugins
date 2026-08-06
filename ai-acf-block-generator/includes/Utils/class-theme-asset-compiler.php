<?php
/**
 * Compiles theme webpack assets after block generation.
 *
 * @package AABG
 */

namespace AABG\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Theme_Asset_Compiler
 */
class Theme_Asset_Compiler {

	/**
	 * Run npm build in theme directory.
	 *
	 * @return array { success: bool, message: string }
	 */
	public static function compile() {
		$theme_dir = Theme_Integrator::get_theme_dir();

		if ( ! file_exists( $theme_dir . 'package.json' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Theme package.json not found. Skipped npm build.', 'ai-acf-block-generator' ),
			);
		}

		if ( ! self::can_run_npm() ) {
			return array(
				'success' => false,
				'message' => __( 'npm is not available on this server. Block CSS was written to assets/css/blocks/ — run npm run build manually for SCSS bundle.', 'ai-acf-block-generator' ),
			);
		}

		$cmd = self::build_command( $theme_dir );
		$output = array();
		$code   = 0;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( $cmd, $output, $code );

		if ( 0 !== $code ) {
			return array(
				'success' => false,
				'message' => __( 'npm run build failed. Block CSS is still available at assets/css/blocks/. Run build manually in theme folder.', 'ai-acf-block-generator' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Theme assets compiled (npm run build).', 'ai-acf-block-generator' ),
		);
	}

	/**
	 * Check if npm can be executed.
	 *
	 * @return bool
	 */
	private static function can_run_npm() {
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		if ( in_array( 'exec', $disabled, true ) && in_array( 'shell_exec', $disabled, true ) ) {
			return false;
		}

		$which = stripos( PHP_OS, 'WIN' ) === 0 ? 'where npm 2>nul' : 'which npm 2>/dev/null';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( $which, $out, $code );
		return 0 === $code && ! empty( $out );
	}

	/**
	 * Build shell command for npm run build.
	 *
	 * @param string $theme_dir Theme path.
	 * @return string
	 */
	private static function build_command( $theme_dir ) {
		$dir = escapeshellarg( rtrim( $theme_dir, '/\\' ) );

		if ( stripos( PHP_OS, 'WIN' ) === 0 ) {
			return 'cd /d ' . $dir . ' && npm run build 2>&1';
		}

		return 'cd ' . $dir . ' && npm run build 2>&1';
	}
}
