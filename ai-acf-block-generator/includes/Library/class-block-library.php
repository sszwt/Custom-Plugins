<?php
/**
 * Block library management.
 *
 * @package AABG
 */

namespace AABG\Library;

use AABG\Utils\File_Manager;
use AABG\Utils\Slug_Helper;
use AABG\Utils\Theme_Integrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block_Library
 */
class Block_Library {

	/**
	 * Get all blocks with metadata.
	 *
	 * @param array $args Filter args.
	 * @return array
	 */
	public function get_blocks( $args = array() ) {
		$search   = sanitize_text_field( $args['search'] ?? '' );
		$category = sanitize_text_field( $args['category'] ?? '' );
		$slugs    = Theme_Integrator::is_enabled() ? Theme_Integrator::list_blocks() : File_Manager::list_blocks();
		$blocks   = array();

		foreach ( $slugs as $slug ) {
			$manifest = Theme_Integrator::is_enabled()
				? Theme_Integrator::get_manifest( $slug )
				: File_Manager::get_block_manifest( $slug );

			$block = array(
				'slug'        => $slug,
				'title'       => $manifest['title'] ?? $slug,
				'description' => $manifest['description'] ?? '',
				'category'    => $manifest['category'] ?? 'custom-blocks',
				'icon'        => $manifest['icon'] ?? 'layout',
				'created'     => $manifest['created'] ?? '',
				'field_count' => $manifest['field_count'] ?? 0,
				'has_js'      => $manifest['has_js'] ?? false,
				'layout'      => $manifest['layout'] ?? '',
				'theme_mode'  => Theme_Integrator::is_enabled(),
				'files'       => $this->get_block_files( $slug ),
			);

			if ( $search && false === stripos( $block['title'] . ' ' . $block['description'] . ' ' . $slug, $search ) ) {
				continue;
			}

			if ( $category && $block['category'] !== $category ) {
				continue;
			}

			$blocks[] = $block;
		}

		return $blocks;
	}

	/**
	 * Get files in a block directory.
	 *
	 * @param string $slug Block slug.
	 * @return array
	 */
	public function get_block_files( $slug ) {
		if ( Theme_Integrator::is_enabled() ) {
			return Theme_Integrator::get_block_files( $slug );
		}

		$dir   = File_Manager::get_block_path( $slug );
		$files = array();

		if ( ! is_dir( $dir ) ) {
			return $files;
		}

		foreach ( scandir( $dir ) as $item ) {
			if ( ! in_array( $item, array( '.', '..' ), true ) && is_file( $dir . $item ) ) {
				$files[] = $item;
			}
		}

		return $files;
	}

	/**
	 * Delete a block.
	 *
	 * @param string $slug Block slug.
	 * @return bool|\WP_Error
	 */
	public function delete( $slug ) {
		$slug = sanitize_title( $slug );

		if ( Theme_Integrator::is_enabled() ) {
			if ( ! in_array( $slug, Theme_Integrator::list_blocks(), true ) ) {
				return new \WP_Error( 'not_found', __( 'Block not found.', 'ai-acf-block-generator' ) );
			}
			return Theme_Integrator::delete_block( $slug );
		}

		if ( ! in_array( $slug, File_Manager::list_blocks(), true ) ) {
			return new \WP_Error( 'not_found', __( 'Block not found.', 'ai-acf-block-generator' ) );
		}

		return File_Manager::delete_block( $slug );
	}

	/**
	 * Duplicate a block.
	 *
	 * @param string $slug Block slug.
	 * @return array|\WP_Error
	 */
	public function duplicate( $slug ) {
		$slug = sanitize_title( $slug );

		if ( Theme_Integrator::is_enabled() ) {
			return new \WP_Error( 'not_supported', __( 'Duplicate is not yet supported for theme blocks. Regenerate with a new name.', 'ai-acf-block-generator' ) );
		}

		if ( ! in_array( $slug, File_Manager::list_blocks(), true ) ) {
			return new \WP_Error( 'not_found', __( 'Block not found.', 'ai-acf-block-generator' ) );
		}

		$new_slug = Slug_Helper::duplicate_slug( $slug );

		if ( ! File_Manager::copy_block( $slug, $new_slug ) ) {
			return new \WP_Error( 'copy_failed', __( 'Failed to duplicate block.', 'ai-acf-block-generator' ) );
		}

		$this->update_slug_in_files( $new_slug, $slug );

		$manifest = File_Manager::get_block_manifest( $new_slug );
		if ( $manifest ) {
			$manifest['slug']    = $new_slug;
			$manifest['title']   = ( $manifest['title'] ?? '' ) . ' (Copy)';
			$manifest['created'] = current_time( 'mysql' );
			File_Manager::write_block_file( $new_slug, 'meta.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
		}

		return array(
			'success' => true,
			'slug'    => $new_slug,
			'message' => __( 'Block duplicated successfully.', 'ai-acf-block-generator' ),
		);
	}

	/**
	 * Update slug references in copied block files.
	 *
	 * @param string $new_slug New slug.
	 * @param string $old_slug Old slug.
	 */
	private function update_slug_in_files( $new_slug, $old_slug ) {
		$block_json = File_Manager::read_block_file( $new_slug, 'block.json' );
		if ( $block_json ) {
			$block_json = str_replace( 'acf/' . $old_slug, 'acf/' . $new_slug, $block_json );
			File_Manager::write_block_file( $new_slug, 'block.json', $block_json );
		}

		$fields_json = File_Manager::read_block_file( $new_slug, 'fields.json' );
		if ( $fields_json ) {
			$fields_json = str_replace( 'acf/' . $old_slug, 'acf/' . $new_slug, $fields_json );
			File_Manager::write_block_file( $new_slug, 'fields.json', $fields_json );
		}
	}

	/**
	 * Get unique categories from library.
	 *
	 * @return array
	 */
	public function get_categories() {
		$blocks     = $this->get_blocks();
		$categories = array();

		foreach ( $blocks as $block ) {
			if ( ! empty( $block['category'] ) ) {
				$categories[ $block['category'] ] = $block['category'];
			}
		}

		return array_values( $categories );
	}
}
