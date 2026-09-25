<?php
/**
 * Admin menu + pages.
 *
 * @package AIFAQ
 */

namespace AIFAQ\Admin;

use AIFAQ\Plugin;
use AIFAQ\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'AI FAQ Generator', 'ai-faq-generator-publisher' ),
			__( 'AI FAQ', 'ai-faq-generator-publisher' ),
			'manage_options',
			'aifaq',
			array( $this, 'render' ),
			'dashicons-editor-help',
			58
		);
	}

	/**
	 * Dashboard.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Plugin::get_settings();
		$sets     = Post_Type::list_sets();
		$view     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'generate'; // phpcs:ignore
		if ( ! in_array( $view, array( 'generate', 'library', 'settings' ), true ) ) {
			$view = 'generate';
		}

		include AIFAQ_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}
}
