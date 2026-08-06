<?php
/**
 * File system operations for generated blocks.
 *
 * @package AABG
 */

namespace AABG\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class File_Manager
 */
class File_Manager {

	/**
	 * Get block directory path.
	 *
	 * @param string $slug Block slug.
	 * @return string
	 */
	public static function get_block_path( $slug ) {
		return trailingslashit( AABG_BLOCKS_DIR . Sanitizer::block_slug( $slug ) );
	}

	/**
	 * Get block directory URL.
	 *
	 * @param string $slug Block slug.
	 * @return string
	 */
	public static function get_block_url( $slug ) {
		return trailingslashit( AABG_BLOCKS_URL . Sanitizer::block_slug( $slug ) );
	}

	/**
	 * Write a file to a block directory.
	 *
	 * @param string $slug     Block slug.
	 * @param string $filename File name.
	 * @param string $content  File content.
	 * @return bool
	 */
	public static function write_block_file( $slug, $filename, $content ) {
		$dir = self::get_block_path( $slug );

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$filename = sanitize_file_name( $filename );
		$result   = file_put_contents( $dir . $filename, $content );

		return false !== $result;
	}

	/**
	 * Read a block file.
	 *
	 * @param string $slug     Block slug.
	 * @param string $filename File name.
	 * @return string|false
	 */
	public static function read_block_file( $slug, $filename ) {
		$path = self::get_block_path( $slug ) . sanitize_file_name( $filename );

		if ( ! file_exists( $path ) ) {
			return false;
		}

		return file_get_contents( $path );
	}

	/**
	 * Delete a block directory.
	 *
	 * @param string $slug Block slug.
	 * @return bool
	 */
	public static function delete_block( $slug ) {
		$dir = self::get_block_path( $slug );

		if ( ! file_exists( $dir ) ) {
			return false;
		}

		return self::delete_directory( $dir );
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 * @return bool
	 */
	public static function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$items = scandir( $dir );

		foreach ( $items as $item ) {
			if ( in_array( $item, array( '.', '..' ), true ) ) {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $item;

			if ( is_dir( $path ) ) {
				self::delete_directory( $path );
			} else {
				wp_delete_file( $path );
			}
		}

		return rmdir( $dir );
	}

	/**
	 * Copy a block directory.
	 *
	 * @param string $source_slug Source slug.
	 * @param string $target_slug Target slug.
	 * @return bool
	 */
	public static function copy_block( $source_slug, $target_slug ) {
		$source = self::get_block_path( $source_slug );
		$target = self::get_block_path( $target_slug );

		if ( ! is_dir( $source ) ) {
			return false;
		}

		return self::copy_directory( $source, $target );
	}

	/**
	 * Recursively copy a directory.
	 *
	 * @param string $source Source path.
	 * @param string $target Target path.
	 * @return bool
	 */
	public static function copy_directory( $source, $target ) {
		if ( ! is_dir( $source ) ) {
			return false;
		}

		if ( ! file_exists( $target ) ) {
			wp_mkdir_p( $target );
		}

		$items = scandir( $source );

		foreach ( $items as $item ) {
			if ( in_array( $item, array( '.', '..' ), true ) ) {
				continue;
			}

			$src  = $source . DIRECTORY_SEPARATOR . $item;
			$dest = $target . DIRECTORY_SEPARATOR . $item;

			if ( is_dir( $src ) ) {
				self::copy_directory( $src, $dest );
			} else {
				copy( $src, $dest );
			}
		}

		return true;
	}

	/**
	 * List all generated block slugs.
	 *
	 * @return array
	 */
	public static function list_blocks() {
		if ( ! is_dir( AABG_BLOCKS_DIR ) ) {
			return array();
		}

		$blocks = array();
		$dirs   = glob( AABG_BLOCKS_DIR . '*', GLOB_ONLYDIR );

		if ( ! $dirs ) {
			return array();
		}

		foreach ( $dirs as $dir ) {
			$slug = basename( $dir );

			if ( file_exists( $dir . '/block.json' ) ) {
				$blocks[] = $slug;
			}
		}

		sort( $blocks );

		return $blocks;
	}

	/**
	 * Get block metadata from block.json.
	 *
	 * @param string $slug Block slug.
	 * @return array|null
	 */
	public static function get_block_meta( $slug ) {
		$content = self::read_block_file( $slug, 'block.json' );

		if ( ! $content ) {
			return null;
		}

		$data = json_decode( $content, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Get block manifest (meta.json) with generation info.
	 *
	 * @param string $slug Block slug.
	 * @return array|null
	 */
	public static function get_block_manifest( $slug ) {
		$content = self::read_block_file( $slug, 'meta.json' );

		if ( ! $content ) {
			$meta = self::get_block_meta( $slug );
			return $meta ? array( 'slug' => $slug, 'title' => $meta['title'] ?? $slug ) : null;
		}

		$data = json_decode( $content, true );

		return is_array( $data ) ? $data : null;
	}
}
