<?php
/**
 * Plugin Name:       AI Image ALT Text Generator
 * Plugin URI:        https://example.com/ai-image-alt-text-generator
 * Description:       Automatically generate accessible ALT text for images in the Media Library using AI vision.
 * Version:           1.1.2
 * Author:            Medgrowth
 * License:           GPL-2.0-or-later
 * Text Domain:       ai-image-alt-text-generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIATG_VERSION', '1.1.2' );
define( 'AIATG_PLUGIN_FILE', __FILE__ );
define( 'AIATG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIATG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AIATG_PLUGIN_DIR . 'includes/class-aiatg-settings.php';
require_once AIATG_PLUGIN_DIR . 'includes/class-aiatg-image-helper.php';
require_once AIATG_PLUGIN_DIR . 'includes/class-aiatg-generator.php';
require_once AIATG_PLUGIN_DIR . 'includes/class-aiatg-admin.php';

/**
 * Main plugin bootstrap.
 */
final class AI_Image_ALT_Text_Generator {

	/**
	 * @var AI_Image_ALT_Text_Generator|null
	 */
	private static $instance = null;

	/**
	 * @var AIATG_Settings
	 */
	public $settings;

	/**
	 * @var AIATG_Generator
	 */
	public $generator;

	/**
	 * @var AIATG_Admin
	 */
	public $admin;

	/**
	 * Get singleton instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->settings  = new AIATG_Settings();
		$this->generator = new AIATG_Generator( $this->settings );
		$this->admin     = new AIATG_Admin( $this->settings, $this->generator );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ai-image-alt-text-generator',
			false,
			dirname( plugin_basename( AIATG_PLUGIN_FILE ) ) . '/languages'
		);
	}
}

/**
 * Plugin entry point.
 */
function aiatg_plugin() {
	return AI_Image_ALT_Text_Generator::instance();
}

aiatg_plugin();
