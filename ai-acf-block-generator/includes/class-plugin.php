<?php
/**
 * Main plugin bootstrap class.
 *
 * @package AABG
 */

namespace AABG;

use AABG\Admin\Admin;
use AABG\Ajax\Ajax_Handler;
use AABG\Blocks\Block_Loader;
use AABG\Blocks\Block_Assets_Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register hooks.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			new Admin();
			new Ajax_Handler();
		}

		new Block_Loader();
		new Block_Assets_Loader();
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ai-acf-block-generator',
			false,
			dirname( plugin_basename( AABG_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		if ( ! file_exists( AABG_BLOCKS_DIR ) ) {
			wp_mkdir_p( AABG_BLOCKS_DIR );
		}

		// Protect generated blocks directory.
		$htaccess = AABG_BLOCKS_DIR . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Options -Indexes\n" );
		}

		$index = AABG_BLOCKS_DIR . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		flush_rewrite_rules();

		if ( class_exists( 'AABG\Utils\Theme_Integrator' ) ) {
			\AABG\Utils\Theme_Integrator::sync_all_block_styles();
			update_option( 'aabg_assets_sync_version', AABG_VERSION );
		}
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
