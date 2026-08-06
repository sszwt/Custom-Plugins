<?php
/**
 * Image utilities for design reference uploads.
 *
 * @package AABG
 */

namespace AABG\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Image_Helper
 */
class Image_Helper {

	const MAX_DIMENSION = 1280;

	/**
	 * Prepare an image for OpenAI vision.
	 *
	 * @param string $image_path Absolute file path.
	 * @return array|\WP_Error
	 */
	public static function prepare_for_vision( $image_path ) {
		if ( ! file_exists( $image_path ) ) {
			return new \WP_Error( 'image_missing', __( 'Design image file not found.', 'ai-acf-block-generator' ) );
		}

		$mime = self::detect_mime( $image_path );
		if ( ! $mime ) {
			return new \WP_Error( 'invalid_image', __( 'Could not detect image type.', 'ai-acf-block-generator' ) );
		}

		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$editor = wp_get_image_editor( $image_path );
		if ( is_wp_error( $editor ) ) {
			return array( 'path' => $image_path, 'mime' => $mime, 'temp' => false );
		}

		$size = $editor->get_size();
		if ( ! is_wp_error( $size ) ) {
			$max = max( (int) $size['width'], (int) $size['height'] );
			if ( $max > self::MAX_DIMENSION ) {
				$editor->resize( self::MAX_DIMENSION, self::MAX_DIMENSION, false );
			}
		}

		$tmp = wp_tempnam( 'aabg-vision' );
		if ( ! $tmp ) {
			return array( 'path' => $image_path, 'mime' => $mime, 'temp' => false );
		}

		$save = $editor->save( $tmp, 'image/jpeg' );
		if ( is_wp_error( $save ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return array( 'path' => $image_path, 'mime' => $mime, 'temp' => false );
		}

		return array(
			'path' => $save['path'],
			'mime' => 'image/jpeg',
			'temp' => true,
		);
	}

	/**
	 * Detect mime type.
	 *
	 * @param string $path File path.
	 * @return string|false
	 */
	public static function detect_mime( $path ) {
		if ( function_exists( 'wp_get_image_mime' ) ) {
			$mime = wp_get_image_mime( $path );
			if ( $mime ) {
				return $mime;
			}
		}

		$checked = wp_check_filetype( $path );
		return ! empty( $checked['type'] ) ? $checked['type'] : false;
	}
}
