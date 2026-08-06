<?php
/**
 * Plugin Name: Frontend Block Visibility Control
 * Plugin URI:  https://medgrowth.in
 * Description: Hide Gutenberg blocks and ACF fields on the live site — toolbar eye, field controls, and site-wide block-type bans.
 * Version:     1.4.0
 * Author:      Medgrowth
 * Author URI:  https://medgrowth.in
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: frontend-block-visibility-control
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package FrontendBlockVisibility
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants.
define( 'FBV_CONTROL_VERSION', '1.4.0' );
define( 'FBV_CONTROL_FILE', __FILE__ );
define( 'FBV_CONTROL_DIR', plugin_dir_path( __FILE__ ) );
define( 'FBV_CONTROL_URL', plugin_dir_url( __FILE__ ) );
define( 'FBV_CONTROL_BASENAME', plugin_basename( __FILE__ ) );

// Require Autoloader.
require_once FBV_CONTROL_DIR . 'includes/Autoloader.php';

/**
 * Register Autoloader for namespace FrontendBlockVisibility.
 */
\FrontendBlockVisibility\Autoloader::register();

/**
 * Activation Hook.
 */
register_activation_hook( __FILE__, function() {
	\FrontendBlockVisibility\Database\Installer::activate();
} );

/**
 * Deactivation Hook.
 */
register_deactivation_hook( __FILE__, function() {
	\FrontendBlockVisibility\Database\Installer::deactivate();
} );

/**
 * Initialize Plugin.
 */
function fbv_control_init() {
	return \FrontendBlockVisibility\Plugin::get_instance();
}

// Launch plugin.
add_action( 'plugins_loaded', 'fbv_control_init' );
