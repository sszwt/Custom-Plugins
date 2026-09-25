<?php
/**
 * Frontend assets.
 *
 * @package CPTFLM
 */

namespace CPTFLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 */
class Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_front' ) );
	}

	/**
	 * Register (enqueue on demand from shortcode).
	 */
	public function register_front() {
		wp_register_style(
			'cptflm-front',
			CPTFLM_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			CPTFLM_VERSION
		);

		wp_register_script(
			'cptflm-front',
			CPTFLM_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			CPTFLM_VERSION,
			true
		);

		wp_localize_script(
			'cptflm-front',
			'cptflmFront',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cptflm_front' ),
				'i18n'    => array(
					'loading' => __( 'Loading…', 'cpt-filter-load-more' ),
					'error'   => __( 'Something went wrong. Please try again.', 'cpt-filter-load-more' ),
					'empty'   => __( 'No items found.', 'cpt-filter-load-more' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 */
	public static function enqueue_front() {
		wp_enqueue_style( 'cptflm-front' );
		wp_enqueue_script( 'cptflm-front' );
	}
}
