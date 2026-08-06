<?php
/**
 * Loads compiled block CSS from theme assets/css/blocks/.
 *
 * @package AABG
 */

namespace AABG\Blocks;

use AABG\Utils\Theme_Integrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block_Assets_Loader
 */
class Block_Assets_Loader {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! Theme_Integrator::is_enabled() ) {
			return;
		}

		add_action( 'init', array( $this, 'maybe_sync_styles' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_styles' ), 20 );
		add_action( 'acf/input/admin_enqueue_scripts', array( $this, 'enqueue_styles' ), 20 );
	}

	/**
	 * Sync CSS files when plugin version changes or folder is missing.
	 */
	public function maybe_sync_styles() {
		$paths   = Theme_Integrator::paths();
		$version = get_option( 'aabg_assets_sync_version', '' );
		$missing = ! is_dir( $paths['block_css'] );

		if ( $missing || $version !== AABG_VERSION ) {
			Theme_Integrator::sync_all_block_styles();
			update_option( 'aabg_assets_sync_version', AABG_VERSION );
		}
	}

	/**
	 * Enqueue all generated block CSS files.
	 */
	public function enqueue_styles() {
		$paths = Theme_Integrator::paths();
		$dir   = $paths['block_css'];

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$theme_uri = get_stylesheet_directory_uri();
		$slugs     = Theme_Integrator::list_blocks();

		$js_dir = $paths['block_js'];

		foreach ( $slugs as $slug ) {
			$file = $dir . $slug . '.css';

			if ( file_exists( $file ) ) {
				wp_enqueue_style(
					'aabg-block-' . $slug,
					$theme_uri . '/assets/css/blocks/' . $slug . '.css',
					array(),
					(string) filemtime( $file )
				);
			}

			$js_file = $js_dir . $slug . '.js';

			if ( file_exists( $js_file ) ) {
				wp_enqueue_script(
					'aabg-block-' . $slug,
					$theme_uri . '/assets/js/blocks/' . $slug . '.js',
					array(),
					(string) filemtime( $js_file ),
					true
				);
			}
		}
	}
}
