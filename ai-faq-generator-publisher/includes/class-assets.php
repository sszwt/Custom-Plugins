<?php
/**
 * Assets.
 *
 * @package AIFAQ
 */

namespace AIFAQ;

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
		add_action( 'wp_enqueue_scripts', array( $this, 'front' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
	}

	/**
	 * Frontend.
	 */
	public function front() {
		wp_register_style(
			'aifaq-front',
			AIFAQ_PLUGIN_URL . 'assets/css/front.css',
			array(),
			AIFAQ_VERSION
		);
		wp_register_script(
			'aifaq-front',
			AIFAQ_PLUGIN_URL . 'assets/js/front.js',
			array(),
			AIFAQ_VERSION,
			true
		);
	}

	/**
	 * Admin.
	 *
	 * @param string $hook Hook.
	 */
	public function admin( $hook ) {
		if ( false === strpos( $hook, 'aifaq' ) ) {
			return;
		}

		wp_enqueue_style(
			'aifaq-fonts',
			'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Sora:wght@500;600;700;800&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'aifaq-admin',
			AIFAQ_PLUGIN_URL . 'assets/css/admin.css',
			array( 'aifaq-fonts' ),
			AIFAQ_VERSION
		);
		wp_enqueue_script(
			'aifaq-admin',
			AIFAQ_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			AIFAQ_VERSION,
			true
		);

		$settings = Plugin::get_settings();
		wp_localize_script(
			'aifaq-admin',
			'AIFAQ',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'aifaq_admin' ),
				'sets'     => Post_Type::list_sets(),
				'settings' => array(
					'provider'      => $settings['provider'],
					'openai_model'  => $settings['openai_model'],
					'gemini_model'  => $settings['gemini_model'],
					'has_openai'    => ! empty( $settings['openai_key'] ),
					'has_gemini'    => ! empty( $settings['gemini_key'] ),
					'default_count' => (int) $settings['default_count'],
					'default_tone'  => $settings['default_tone'],
					'accordion'     => $settings['accordion'],
				),
				'i18n'     => array(
					'generating' => __( 'Generating FAQs…', 'ai-faq-generator-publisher' ),
					'saving'     => __( 'Saving…', 'ai-faq-generator-publisher' ),
					'saved'      => __( 'Saved', 'ai-faq-generator-publisher' ),
					'error'      => __( 'Something went wrong.', 'ai-faq-generator-publisher' ),
					'confirmDel' => __( 'Delete this FAQ set?', 'ai-faq-generator-publisher' ),
					'emptyLib'   => __( 'No FAQ sets yet. Generate your first one.', 'ai-faq-generator-publisher' ),
					'copied'     => __( 'Shortcode copied', 'ai-faq-generator-publisher' ),
				),
			)
		);
	}
}
