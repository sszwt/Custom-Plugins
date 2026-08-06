<?php
/**
 * Uninstall Frontend Block Visibility Control.
 *
 * Deletes plugin settings upon uninstallation.
 *
 * @package FrontendBlockVisibility
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'fbv_control_settings' );
