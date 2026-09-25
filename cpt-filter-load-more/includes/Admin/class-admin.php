<?php
/**
 * Admin menus & assets.
 *
 * @package CPTFLM
 */

namespace CPTFLM\Admin;

use CPTFLM\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Register menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'CPT Filter & Load More', 'cpt-filter-load-more' ),
			__( 'CPT Filter', 'cpt-filter-load-more' ),
			'manage_options',
			'cptflm',
			array( $this, 'render' ),
			'dashicons-filter',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'cptflm' ) ) {
			return;
		}

		wp_enqueue_style(
			'cptflm-admin',
			CPTFLM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CPTFLM_VERSION
		);

		wp_enqueue_script(
			'cptflm-admin',
			CPTFLM_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			CPTFLM_VERSION,
			true
		);

		wp_localize_script(
			'cptflm-admin',
			'cptflmAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cptflm_admin' ),
				'settings' => Plugin::get_settings(),
				'i18n'     => array(
					'saved'   => __( 'Saved successfully.', 'cpt-filter-load-more' ),
					'error'   => __( 'Could not save settings.', 'cpt-filter-load-more' ),
					'confirm' => __( 'Remove this item?', 'cpt-filter-load-more' ),
				),
			)
		);
	}

	/**
	 * Render dashboard shell.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab      = sanitize_key( $_GET['tab'] ?? 'overview' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed  = array( 'overview', 'post-types', 'taxonomies', 'display', 'shortcodes' );
		if ( ! in_array( $tab, $allowed, true ) ) {
			$tab = 'overview';
		}

		$settings = Plugin::get_settings();
		include CPTFLM_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}
}
