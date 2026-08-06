<?php
/**
 * Block export functionality.
 *
 * @package AABG
 */

namespace AABG\Library;

use AABG\Utils\File_Manager;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Exporter
 */
class Exporter {

	/**
	 * Export block as ZIP.
	 *
	 * @param string $slug Block slug.
	 * @return array|\WP_Error
	 */
	public function export_zip( $slug ) {
		$slug = sanitize_title( $slug );
		$dir  = File_Manager::get_block_path( $slug );

		if ( ! is_dir( $dir ) ) {
			return new \WP_Error( 'not_found', __( 'Block not found.', 'ai-acf-block-generator' ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'no_zip', __( 'ZipArchive is not available on this server.', 'ai-acf-block-generator' ) );
		}

		$upload_dir = wp_upload_dir();
		$zip_path   = $upload_dir['basedir'] . '/aabg-exports/' . $slug . '-' . time() . '.zip';
		$zip_url    = $upload_dir['baseurl'] . '/aabg-exports/' . $slug . '-' . time() . '.zip';

		wp_mkdir_p( dirname( $zip_path ) );

		$zip = new ZipArchive();

		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new \WP_Error( 'zip_failed', __( 'Could not create ZIP file.', 'ai-acf-block-generator' ) );
		}

		$files = scandir( $dir );

		foreach ( $files as $file ) {
			if ( in_array( $file, array( '.', '..' ), true ) ) {
				continue;
			}

			$file_path = $dir . $file;

			if ( is_file( $file_path ) ) {
				$zip->addFile( $file_path, $slug . '/' . $file );
			}
		}

		$zip->close();

		return array(
			'success'  => true,
			'url'      => $zip_url,
			'filename' => basename( $zip_path ),
			'type'     => 'zip',
		);
	}

	/**
	 * Export block as JSON package.
	 *
	 * @param string $slug Block slug.
	 * @return array|\WP_Error
	 */
	public function export_json( $slug ) {
		$slug = sanitize_title( $slug );
		$dir  = File_Manager::get_block_path( $slug );

		if ( ! is_dir( $dir ) ) {
			return new \WP_Error( 'not_found', __( 'Block not found.', 'ai-acf-block-generator' ) );
		}

		$package = array(
			'version' => AABG_VERSION,
			'slug'    => $slug,
			'files'   => array(),
		);

		$manifest = File_Manager::get_block_manifest( $slug );
		if ( $manifest ) {
			$package['manifest'] = $manifest;
		}

		foreach ( scandir( $dir ) as $file ) {
			if ( in_array( $file, array( '.', '..' ), true ) ) {
				continue;
			}

			$file_path = $dir . $file;

			if ( is_file( $file_path ) && 'preview.png' !== $file ) {
				$content = file_get_contents( $file_path );
				$package['files'][ $file ] = in_array( pathinfo( $file, PATHINFO_EXTENSION ), array( 'json', 'css', 'js', 'md', 'php' ), true )
					? $content
					: base64_encode( $content );
			}
		}

		$upload_dir = wp_upload_dir();
		$json_path  = $upload_dir['basedir'] . '/aabg-exports/' . $slug . '.json';
		$json_url   = $upload_dir['baseurl'] . '/aabg-exports/' . $slug . '.json';

		wp_mkdir_p( dirname( $json_path ) );
		file_put_contents( $json_path, wp_json_encode( $package, JSON_PRETTY_PRINT ) );

		return array(
			'success'  => true,
			'url'      => $json_url,
			'filename' => basename( $json_path ),
			'type'     => 'json',
			'data'     => $package,
		);
	}

	/**
	 * Export a single file.
	 *
	 * @param string $slug     Block slug.
	 * @param string $filename File name.
	 * @return array|\WP_Error
	 */
	public function export_file( $slug, $filename ) {
		$slug     = sanitize_title( $slug );
		$filename = sanitize_file_name( $filename );
		$content  = File_Manager::read_block_file( $slug, $filename );

		if ( false === $content ) {
			return new \WP_Error( 'not_found', __( 'File not found.', 'ai-acf-block-generator' ) );
		}

		return array(
			'success'  => true,
			'filename' => $filename,
			'content'  => $content,
			'type'     => 'file',
		);
	}
}
