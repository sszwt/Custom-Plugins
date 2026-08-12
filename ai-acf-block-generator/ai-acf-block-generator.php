<?php
/**
 * Plugin Name:       AI ACF Block Generator
 * Plugin URI:        https://example.com/ai-acf-block-generator
 * Description:       Generate complete ACF Gutenberg Blocks from natural language prompts using AI.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Custom Plugin
 * Author URI:        https://github.com/sszwt/Custom-Plugins
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-acf-block-generator
 *
 * @package AABG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AABG_VERSION', '1.1.0' );
define( 'AABG_PLUGIN_FILE', __FILE__ );
define( 'AABG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AABG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AABG_BLOCKS_DIR', AABG_PLUGIN_DIR . 'generated-blocks/' );
define( 'AABG_BLOCKS_URL', AABG_PLUGIN_URL . 'generated-blocks/' );

require_once AABG_PLUGIN_DIR . 'includes/class-autoloader.php';

AABG\Autoloader::register();

/**
 * Initialize the plugin.
 *
 * @return AABG\Plugin
 */
function aabg() {
	return AABG\Plugin::instance();
}

add_action( 'plugins_loaded', 'aabg' );

register_activation_hook( __FILE__, array( 'AABG\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AABG\Plugin', 'deactivate' ) );
