<?php
namespace FrontendBlockVisibility\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database installation and default plugin options.
 */
class Installer {

	/**
	 * Activate plugin: set default options.
	 *
	 * @return void
	 */
	public static function activate() {
		self::set_default_options();
	}

	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Cleanup tasks if needed.
	}

	/**
	 * Set default options if not existing.
	 *
	 * @return void
	 */
	public static function set_default_options() {
		$default_settings = array(
			'enable_user_rules'     => 1,
			'enable_device_rules'   => 1,
			'enable_schedule_rules' => 1,
			'enable_url_rules'      => 1,
			'enable_woo_rules'      => 1,
			'hide_method'           => 'server_side', // 'server_side' or 'css_hide'
		);

		if ( false === get_option( 'fbv_control_settings' ) ) {
			add_option( 'fbv_control_settings', $default_settings );
		}
	}
}
