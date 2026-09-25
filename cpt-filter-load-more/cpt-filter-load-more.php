<?php
/**
 * Plugin Name:       CPT Filter & Load More
 * Plugin URI:        https://github.com/sszwt/Custom-Plugins
 * Description:       Register custom post types & taxonomies, then display them with taxonomy filters and AJAX load more.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Custom Plugin
 * Author URI:        https://github.com/sszwt/Custom-Plugins
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cpt-filter-load-more
 *
 * @package CPTFLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CPTFLM_VERSION', '1.0.3' );
define( 'CPTFLM_PLUGIN_FILE', __FILE__ );
define( 'CPTFLM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CPTFLM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CPTFLM_OPTION_KEY', 'cptflm_settings' );

require_once CPTFLM_PLUGIN_DIR . 'includes/class-autoloader.php';

CPTFLM\Autoloader::register();

/**
 * Main plugin instance.
 *
 * @return CPTFLM\Plugin
 */
function cptflm() {
	return CPTFLM\Plugin::instance();
}

cptflm();

register_activation_hook(
	__FILE__,
	static function () {
		$defaults = CPTFLM\Plugin::default_settings();
		if ( false === get_option( CPTFLM_OPTION_KEY, false ) ) {
			update_option( CPTFLM_OPTION_KEY, $defaults );
		}
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
