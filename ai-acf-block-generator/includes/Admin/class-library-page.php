<?php
/**
 * Block library admin page.
 *
 * @package AABG
 */

namespace AABG\Admin;

use AABG\Library\Block_Library;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Library_Page
 */
class Library_Page {

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-acf-block-generator' ) );
		}

		$library    = new Block_Library();
		$blocks     = $library->get_blocks();
		$categories = $library->get_categories();

		include AABG_PLUGIN_DIR . 'templates/admin/library.php';
	}
}
