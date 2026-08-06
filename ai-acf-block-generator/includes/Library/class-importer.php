<?php
/**
 * Block import functionality.
 *
 * @package AABG
 */

namespace AABG\Library;

use AABG\Utils\File_Manager;
use AABG\Utils\Sanitizer;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Importer
 */
class Importer {

	/**
	 * Import block from JSON package.
	 *
	 * @param string $json JSON string.
	 * @return array|\WP_Error
	 */
	public function import_json( $json ) {
		$package = json_decode( $json, true );

		if ( ! is_array( $package ) || empty( $package['slug'] ) || empty( $package['files'] ) ) {
			return new \WP_Error( 'invalid_json', __( 'Invalid import package.', 'ai-acf-block-generator' ) );
		}

		$slug = Sanitizer::block_slug( $package['slug'] );

		if ( file_exists( File_Manager::get_block_path( $slug ) ) ) {
			$slug = $slug . '-imported-' . time();
		}

		return $this->write_package( $slug, $package );
	}

	/**
	 * Import block from uploaded ZIP.
	 *
	 * @param array $file $_FILES array item.
	 * @return array|\WP_Error
	 */
	public function import_zip( $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'ai-acf-block-generator' ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'no_zip', __( 'ZipArchive is not available.', 'ai-acf-block-generator' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file['tmp_name'] ) ) {
			return new \WP_Error( 'zip_failed', __( 'Could not open ZIP file.', 'ai-acf-block-generator' ) );
		}

		$slug = sanitize_title( pathinfo( $file['name'], PATHINFO_FILENAME ) );

		// Detect folder name from first entry.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			$parts = explode( '/', $name );
			if ( ! empty( $parts[0] ) ) {
				$slug = sanitize_title( $parts[0] );
				break;
			}
		}

		if ( file_exists( File_Manager::get_block_path( $slug ) ) ) {
			$slug = $slug . '-imported-' . time();
		}

		$dir = File_Manager::get_block_path( $slug );
		wp_mkdir_p( $dir );

		$zip->extractTo( $dir );
		$zip->close();

		// Flatten if extracted into subfolder.
		$sub_dir = $dir . $slug;
		if ( is_dir( $sub_dir ) ) {
			$this->move_contents_up( $sub_dir, $dir );
		}

		return array(
			'success' => true,
			'slug'    => $slug,
			'message' => sprintf(
				/* translators: %s: block slug */
				__( 'Block "%s" imported successfully.', 'ai-acf-block-generator' ),
				$slug
			),
		);
	}

	/**
	 * Write import package files.
	 *
	 * @param string $slug    Block slug.
	 * @param array  $package Package data.
	 * @return array|\WP_Error
	 */
	private function write_package( $slug, $package ) {
		foreach ( $package['files'] as $filename => $content ) {
			$filename = sanitize_file_name( $filename );

			if ( is_string( $content ) && $this->is_base64( $content ) ) {
				$content = base64_decode( $content );
			}

			File_Manager::write_block_file( $slug, $filename, $content );
		}

		if ( ! empty( $package['manifest'] ) ) {
			$manifest = $package['manifest'];
			$manifest['slug']    = $slug;
			$manifest['imported'] = current_time( 'mysql' );
			File_Manager::write_block_file( $slug, 'meta.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
		}

		return array(
			'success' => true,
			'slug'    => $slug,
			'message' => sprintf(
				/* translators: %s: block slug */
				__( 'Block "%s" imported successfully.', 'ai-acf-block-generator' ),
				$slug
			),
		);
	}

	/**
	 * Move files from subfolder to parent.
	 *
	 * @param string $from Source dir.
	 * @param string $to   Target dir.
	 */
	private function move_contents_up( $from, $to ) {
		foreach ( scandir( $from ) as $item ) {
			if ( in_array( $item, array( '.', '..' ), true ) ) {
				continue;
			}
			rename( $from . DIRECTORY_SEPARATOR . $item, $to . $item );
		}
		rmdir( $from );
	}

	/**
	 * Check if string is base64.
	 *
	 * @param string $str String.
	 * @return bool
	 */
	private function is_base64( $str ) {
		if ( preg_match( '/^[\x20-\x7E]+$/', substr( $str, 0, 100 ) ) === 0 ) {
			return true;
		}
		return strlen( $str ) > 100 && base64_encode( base64_decode( $str, true ) ) === $str;
	}
}
