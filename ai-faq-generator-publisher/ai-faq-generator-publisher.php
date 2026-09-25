<?php
/**
 * Plugin Name:       AI FAQ Generator & Publisher
 * Plugin URI:        https://github.com/sszwt/Custom-Plugins
 * Description:       Generate FAQs with AI, edit & publish them, and display with a smooth slide-up / slide-down accordion.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Custom Plugin
 * Author URI:        https://github.com/sszwt/Custom-Plugins
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-faq-generator-publisher
 *
 * @package AIFAQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIFAQ_VERSION', '1.0.3' );
define( 'AIFAQ_PLUGIN_FILE', __FILE__ );
define( 'AIFAQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIFAQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIFAQ_OPTION_KEY', 'aifaq_settings' );

require_once AIFAQ_PLUGIN_DIR . 'includes/class-autoloader.php';

AIFAQ\Autoloader::register();

/**
 * Plugin instance.
 *
 * @return AIFAQ\Plugin
 */
function aifaq() {
	return AIFAQ\Plugin::instance();
}

aifaq();

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( AIFAQ_OPTION_KEY, false ) ) {
			update_option( AIFAQ_OPTION_KEY, AIFAQ\Plugin::default_settings() );
		}
		AIFAQ\Post_Type::register();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
