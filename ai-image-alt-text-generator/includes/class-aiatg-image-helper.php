<?php
/**
 * Image helpers for vision API requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIATG_Image_Helper {

	/**
	 * Get base64 image data from attachment for vision APIs.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{path:string,mime:string,data:string}|WP_Error
	 */
	public static function get_attachment_image_data( $attachment_id ) {
		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			return new WP_Error( 'aiatg_no_file', __( 'Image file not found on server.', 'ai-image-alt-text-generator' ) );
		}

		$mime = get_post_mime_type( $attachment_id );

		if ( ! $mime || 0 !== strpos( $mime, 'image/' ) ) {
			return new WP_Error( 'aiatg_invalid_mime', __( 'Invalid image type.', 'ai-image-alt-text-generator' ) );
		}

		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return new WP_Error( 'aiatg_read_failed', __( 'Could not read image file.', 'ai-image-alt-text-generator' ) );
		}

		// Resize large images to reduce API cost (max 1024px).
		$prepared = self::maybe_resize( $path, $mime, $contents );

		return array(
			'path' => $path,
			'mime' => $prepared['mime'],
			'data' => base64_encode( $prepared['contents'] ),
		);
	}

	/**
	 * Resize image if larger than max dimension.
	 *
	 * @param string $path     File path.
	 * @param string $mime     MIME type.
	 * @param string $contents File contents.
	 * @return array{contents:string,mime:string}
	 */
	private static function maybe_resize( $path, $mime, $contents ) {
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			return array(
				'contents' => $contents,
				'mime'     => $mime,
			);
		}

		$size = @getimagesize( $path );

		if ( ! $size || max( $size[0], $size[1] ) <= 1024 ) {
			return array(
				'contents' => $contents,
				'mime'     => $mime,
			);
		}

		$editor = wp_get_image_editor( $path );

		if ( is_wp_error( $editor ) ) {
			return array(
				'contents' => $contents,
				'mime'     => $mime,
			);
		}

		$editor->resize( 1024, 1024, false );
		$saved = $editor->save();

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return array(
				'contents' => $contents,
				'mime'     => $mime,
			);
		}

		$resized = file_get_contents( $saved['path'] );

		if ( $saved['path'] !== $path && file_exists( $saved['path'] ) ) {
			@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		return array(
			'contents' => false !== $resized ? $resized : $contents,
			'mime'     => $saved['mime-type'] ?? $mime,
		);
	}
}
