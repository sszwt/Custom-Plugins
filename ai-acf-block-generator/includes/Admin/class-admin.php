<?php
/**
 * Admin menu and asset registration.
 *
 * @package AABG
 */

namespace AABG\Admin;

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
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menus.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'AI ACF Block Generator', 'ai-acf-block-generator' ),
			__( 'AI ACF Blocks', 'ai-acf-block-generator' ),
			'manage_options',
			'aabg-generator',
			array( $this, 'render_generator_page' ),
			'dashicons-layout',
			58
		);

		// First submenu must use a different slug than parent, or WordPress renders the page twice.
		add_submenu_page(
			'aabg-generator',
			__( 'Block Library', 'ai-acf-block-generator' ),
			__( 'Block Library', 'ai-acf-block-generator' ),
			'manage_options',
			'aabg-library',
			array( new Library_Page(), 'render' )
		);

		add_submenu_page(
			'aabg-generator',
			__( 'Settings', 'ai-acf-block-generator' ),
			__( 'Settings', 'ai-acf-block-generator' ),
			'manage_options',
			'aabg-settings',
			array( new Settings_Page(), 'render' )
		);
	}

	/**
	 * Render generator page (single callback — prevents duplicate output).
	 */
	public function render_generator_page() {
		$page = new Generator_Page();
		$page->render();
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( empty( $page ) || 0 !== strpos( $page, 'aabg-' ) ) {
			return;
		}

		wp_enqueue_style(
			'aabg-admin',
			AABG_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AABG_VERSION
		);

		wp_enqueue_script(
			'aabg-admin',
			AABG_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AABG_VERSION,
			true
		);

		wp_localize_script(
			'aabg-admin',
			'aabgAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aabg_admin_nonce' ),
				'i18n'    => array(
					'generating'    => __( 'Generating block...', 'ai-acf-block-generator' ),
					'success'       => __( 'Block generated successfully!', 'ai-acf-block-generator' ),
					'error'         => __( 'An error occurred.', 'ai-acf-block-generator' ),
					'confirmDelete' => __( 'Are you sure you want to delete this block?', 'ai-acf-block-generator' ),
					'duplicating'   => __( 'Duplicating...', 'ai-acf-block-generator' ),
					'exporting'     => __( 'Exporting...', 'ai-acf-block-generator' ),
					'importing'     => __( 'Importing...', 'ai-acf-block-generator' ),
					'testing'       => __( 'Testing API key...', 'ai-acf-block-generator' ),
					'noBlocks'      => __( 'No blocks found.', 'ai-acf-block-generator' ),
					'desktop'       => __( 'Desktop', 'ai-acf-block-generator' ),
					'tablet'        => __( 'Tablet', 'ai-acf-block-generator' ),
					'mobile'        => __( 'Mobile', 'ai-acf-block-generator' ),
				),
			)
		);
	}
}
