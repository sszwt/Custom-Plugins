<?php
/**
 * Registers and loads generated ACF blocks.
 *
 * @package AABG
 */

namespace AABG\Blocks;

use AABG\Utils\File_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block_Loader
 */
class Block_Loader {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Theme handles registration when theme integration is enabled.
		if ( \AABG\Utils\Theme_Integrator::is_enabled() ) {
			return;
		}

		add_action( 'acf/init', array( $this, 'register_blocks' ) );
		add_action( 'acf/init', array( $this, 'register_field_groups' ) );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );
	}

	/**
	 * Register custom block category.
	 *
	 * @param array $categories Categories.
	 * @return array
	 */
	public function register_block_category( $categories ) {
		$exists = false;

		foreach ( $categories as $cat ) {
			if ( 'custom-blocks' === ( $cat['slug'] ?? '' ) || 'aabg-blocks' === ( $cat['slug'] ?? '' ) ) {
				$exists = true;
				break;
			}
		}

		if ( ! $exists ) {
			array_unshift(
				$categories,
				array(
					'slug'  => 'aabg-blocks',
					'title' => __( 'AI Generated Blocks', 'ai-acf-block-generator' ),
					'icon'  => 'layout',
				)
			);
		}

		return $categories;
	}

	/**
	 * Register all generated blocks.
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$slugs = File_Manager::list_blocks();

		foreach ( $slugs as $slug ) {
			$block_dir = File_Manager::get_block_path( $slug );

			if ( file_exists( $block_dir . 'block.json' ) ) {
				register_block_type( $block_dir );
			}
		}
	}

	/**
	 * Register ACF field groups from fields.json.
	 */
	public function register_field_groups() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$slugs = File_Manager::list_blocks();

		foreach ( $slugs as $slug ) {
			$content = File_Manager::read_block_file( $slug, 'fields.json' );

			if ( ! $content ) {
				continue;
			}

			$group = json_decode( $content, true );

			if ( is_array( $group ) && ! empty( $group['key'] ) ) {
				acf_add_local_field_group( $group );
			}
		}
	}
}
