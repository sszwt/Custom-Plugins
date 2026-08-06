<?php
/**
 * Generates block.json for ACF blocks.
 *
 * @package AABG
 */

namespace AABG\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block_JSON_Generator
 */
class Block_JSON_Generator {

	/**
	 * Generate block.json content.
	 *
	 * @param array $spec Block specification.
	 * @return string
	 */
	public function generate( $spec ) {
		$slug        = $spec['block_slug'] ?? 'custom-block';
		$title       = $spec['block_name'] ?? ucwords( str_replace( '-', ' ', $slug ) );
		$description = $spec['block_description'] ?? ( $spec['purpose'] ?? '' );
		$category    = $spec['block_category'] ?? 'custom-blocks';
		$icon        = $spec['block_icon'] ?? 'layout';
		$needs_js    = ! empty( $spec['needs_javascript'] );

		$block = array(
			'$schema'     => 'https://schemas.wp.org/trunk/block.json',
			'apiVersion'  => 3,
			'name'        => 'acf/' . $slug,
			'title'       => $title,
			'description' => $description,
			'category'    => $category,
			'icon'        => $icon,
			'keywords'    => array( $slug, 'acf', 'custom' ),
			'acf'         => array(
				'mode'           => 'preview',
				'renderTemplate' => 'render.php',
			),
			'supports'    => array(
				'align'      => array( 'wide', 'full' ),
				'anchor'     => true,
				'spacing'    => array(
					'margin'  => true,
					'padding' => true,
				),
				'typography' => array(
					'fontSize' => true,
				),
				'html'       => false,
			),
			'style'       => 'file:./style.css',
			'editorStyle' => 'file:./editor.css',
			'textdomain'  => 'ai-acf-block-generator',
		);

		if ( $needs_js ) {
			$block['script'] = 'file:./script.js';
		}

		$block['editorScript'] = 'file:./editor.js';

		return wp_json_encode( $block, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}
}
