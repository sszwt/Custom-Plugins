<?php
namespace FrontendBlockVisibility;

use FrontendBlockVisibility\Admin\Settings;
use FrontendBlockVisibility\Frontend\BlockRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Singleton Plugin Bootstrapper.
 */
class Plugin {

	/**
	 * Instance store.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_textdomain();
		$this->init_components();
		$this->register_hooks();
	}

	/**
	 * Load translation files.
	 *
	 * @return void
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'frontend-block-visibility-control',
			false,
			dirname( FBV_CONTROL_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize components.
	 *
	 * @return void
	 */
	private function init_components() {
		$settings = new Settings();
		$settings->register();

		$renderer = new BlockRenderer();
		$renderer->register();
	}

	/**
	 * Register global hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue Gutenberg block inspector scripts & styles.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script(
			'fbv-block-visibility-js',
			FBV_CONTROL_URL . 'assets/js/block-visibility.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-compose', 'wp-i18n' ),
			FBV_CONTROL_VERSION,
			true
		);

		wp_enqueue_style(
			'fbv-admin-css',
			FBV_CONTROL_URL . 'assets/css/admin.css',
			array(),
			FBV_CONTROL_VERSION
		);

		// Get all editable WordPress User Roles.
		global $wp_roles;
		$roles_data = array();
		if ( isset( $wp_roles->roles ) ) {
			foreach ( $wp_roles->roles as $role_key => $role_info ) {
				$roles_data[] = array(
					'label' => translate_user_role( $role_info['name'] ),
					'value' => $role_key,
				);
			}
		}

		$settings = get_option( 'fbv_control_settings', array() );

		wp_localize_script(
			'fbv-block-visibility-js',
			'FBVData',
			array(
				'roles'    => $roles_data,
				'settings' => $settings,
				'i18n'     => array(
					'panelTitle' => __( 'Block Visibility', 'frontend-block-visibility-control' ),
					'enable'     => __( 'Enable visibility rules', 'frontend-block-visibility-control' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend CSS for responsive device hiding.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'fbv-frontend-css',
			FBV_CONTROL_URL . 'assets/css/frontend.css',
			array(),
			FBV_CONTROL_VERSION
		);
	}
}
