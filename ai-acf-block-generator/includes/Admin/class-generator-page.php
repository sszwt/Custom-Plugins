<?php
/**
 * Generator admin page.
 *
 * @package AABG
 */

namespace AABG\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Generator_Page
 */
class Generator_Page {

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-acf-block-generator' ) );
		}

		include AABG_PLUGIN_DIR . 'templates/admin/generator.php';
	}
}
