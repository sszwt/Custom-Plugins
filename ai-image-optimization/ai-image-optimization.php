<?php
/**
 * Plugin Name:       AI Image Optimization
 * Plugin URI:        https://example.com/ai-image-optimization
 * Description:       Standardize images for speed, SEO & accessibility: WebP/JPG/PNG delivery (Media untouched), ALT text, lazy-load, dimensions, and compression.
 * Version:           1.2.3
 * Author:            Custom Plugin
 * License:           GPL-2.0-or-later
 * Text Domain:       ai-image-optimization
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIIO_VERSION', '1.2.3' );
define( 'AIIO_PLUGIN_FILE', __FILE__ );
define( 'AIIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AIIO_PLUGIN_DIR . 'includes/class-aiio-settings.php';
require_once AIIO_PLUGIN_DIR . 'includes/class-aiio-ai-analyzer.php';
require_once AIIO_PLUGIN_DIR . 'includes/class-aiio-format-cache.php';
require_once AIIO_PLUGIN_DIR . 'includes/class-aiio-frontend.php';
require_once AIIO_PLUGIN_DIR . 'includes/class-aiio-standardizer.php';
require_once AIIO_PLUGIN_DIR . 'includes/class-aiio-optimizer.php';
require_once AIIO_PLUGIN_DIR . 'includes/class-aiio-admin.php';

/**
 * Main plugin bootstrap.
 */
final class AI_Image_Optimization {

	/**
	 * @var AI_Image_Optimization|null
	 */
	private static $instance = null;

	/**
	 * @var AIIO_Settings
	 */
	public $settings;

	/**
	 * @var AIIO_Format_Cache
	 */
	public $format_cache;

	/**
	 * @var AIIO_Frontend
	 */
	public $frontend;

	/**
	 * @var AIIO_Standardizer
	 */
	public $standardizer;

	/**
	 * @var AIIO_Optimizer
	 */
	public $optimizer;

	/**
	 * @var AIIO_Admin
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
		$this->settings     = new AIIO_Settings();
		$analyzer           = new AIIO_AI_Analyzer( $this->settings );
		$this->format_cache = new AIIO_Format_Cache( $this->settings );
		$this->frontend     = new AIIO_Frontend( $this->settings, $this->format_cache );
		$this->standardizer = new AIIO_Standardizer( $this->settings, $analyzer );
		$this->optimizer    = new AIIO_Optimizer( $this->settings );
		$this->admin        = new AIIO_Admin( $this->settings, $this->optimizer, $this->format_cache, $this->standardizer );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ai-image-optimization',
			false,
			dirname( plugin_basename( AIIO_PLUGIN_FILE ) ) . '/languages'
		);
	}
}

/**
 * Plugin entry point.
 */
function aiio_plugin() {
	return AI_Image_Optimization::instance();
}

aiio_plugin();
