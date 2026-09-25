<?php
/**
 * Main plugin bootstrap.
 *
 * @package CPTFLM
 */

namespace CPTFLM;

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
		add_action( 'init', array( CPT_Registry::class, 'register_all' ), 5 );

		new Admin\Admin();
		new Ajax();
		new Shortcode();
		new Assets();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cpt-filter-load-more', false, dirname( plugin_basename( CPTFLM_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'post_types'  => array(
				array(
					'slug'          => 'project',
					'label'         => 'Projects',
					'singular'      => 'Project',
					'menu_icon'     => 'dashicons-portfolio',
					'public'        => 1,
					'has_archive'   => 1,
					'show_in_rest'  => 1,
					'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
					'enabled'       => 1,
				),
			),
			'taxonomies'  => array(
				array(
					'slug'         => 'project_category',
					'label'        => 'Project Categories',
					'singular'     => 'Project Category',
					'post_types'   => array( 'project' ),
					'hierarchical' => 1,
					'show_in_rest' => 1,
					'enabled'      => 1,
				),
			),
			'frontend'    => array(
				'per_page'       => 6,
				'columns'        => 3,
				'button_text'    => 'Load More',
				'all_label'      => 'All',
				'show_excerpt'   => 1,
				'show_image'     => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			),
		);
	}

	/**
	 * Get settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( CPTFLM_OPTION_KEY, array() );
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
		return update_option( CPTFLM_OPTION_KEY, $settings );
	}
}
