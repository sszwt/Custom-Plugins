<?php
/**
 * Bootstrap.
 *
 * @package AIFAQ
 */

namespace AIFAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( Post_Type::class, 'register' ) );

		new Admin\Admin();
		new Ajax();
		new Shortcode();
		new Assets();
	}

	/**
	 * Textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'ai-faq-generator-publisher', false, dirname( plugin_basename( AIFAQ_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'provider'     => 'openai',
			'openai_key'   => '',
			'openai_model' => 'gpt-4o-mini',
			'gemini_key'   => '',
			'gemini_model' => 'gemini-2.5-flash',
			'default_count'=> 8,
			'default_tone' => 'clear and helpful',
			'accordion'    => array(
				'allow_multiple' => 0,
				'open_first'     => 1,
				'show_schema'    => 1,
			),
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( AIFAQ_OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::default_settings() );
	}

	/**
	 * Update settings.
	 *
	 * @param array $settings Settings.
	 * @return bool
	 */
	public static function update_settings( $settings ) {
		return update_option( AIFAQ_OPTION_KEY, $settings );
	}
}
