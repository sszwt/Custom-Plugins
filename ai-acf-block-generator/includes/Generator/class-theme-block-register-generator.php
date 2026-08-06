<?php
/**
 * Generates and appends ACF block registration to theme acf-block-register.php.
 *
 * @package AABG
 */

namespace AABG\Generator;

use AABG\Utils\Theme_Integrator;
use AABG\Utils\PHP_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Theme_Block_Register_Generator
 */
class Theme_Block_Register_Generator {

	/**
	 * Register block in theme acf-block-register.php.
	 *
	 * @param array $spec Block specification.
	 * @return bool|\WP_Error
	 */
	public function register( $spec ) {
		$paths = Theme_Integrator::paths();
		$file  = $paths['acf_register'];

		if ( ! file_exists( $file ) ) {
			return new \WP_Error( 'no_register_file', __( 'Theme acf-block-register.php not found.', 'ai-acf-block-generator' ) );
		}

		$slug        = $spec['block_slug'] ?? 'custom-block';
		$title       = $spec['block_name'] ?? ucwords( str_replace( '-', ' ', $slug ) );
		$description = $spec['block_description'] ?? __( 'A dynamically rendered ACF block.', 'ai-acf-block-generator' );
		$category    = $spec['block_category'] ?? 'custom-blocks';
		$icon        = $spec['block_icon'] ?? 'layout';
		$has_js      = ! empty( $spec['needs_javascript'] );

		$content = file_get_contents( $file );

		if ( false !== strpos( $content, "// AABG:BLOCK:{$slug}" ) ) {
			return true; // Already registered.
		}

		$snippet  = "\n        // AABG:BLOCK:{$slug}\n";
		$snippet .= "        acf_register_block_type(array(\n";
		$snippet .= "            'name'              => '{$slug}',\n";
		$snippet .= "            'title'             => __('" . addslashes( $title ) . "','textdomain'),\n";
		$snippet .= "            'description'       => __('" . addslashes( $description ) . "','textdomain'),\n";
		$snippet .= "            'render_callback'   => 'theme_acf_block_render_callback',\n";
		$snippet .= "            'category'          => '{$category}',\n";
		$snippet .= "            'icon'              => '{$icon}',\n";
		$snippet .= "            'keywords'          => array('{$slug}', 'acf', 'custom'),\n";
		$snippet .= "            'supports'          => array(\n";
		$snippet .= "                'align' => true\n";
		$snippet .= "            ),\n";
		$snippet .= "            'mode'              => 'edit',\n";
		$snippet .= "            'example'           => array(\n";
		$snippet .= "                'attributes' => array(\n";
		$snippet .= "                    'mode' => 'preview',\n";
		$snippet .= "                    'data' => array(\n";
		$snippet .= "                        'is_example' => true\n";
		$snippet .= "                    ),\n";
		$snippet .= "                ),\n";
		$snippet .= "            ),\n";
		$snippet .= "            'enqueue_assets' => function () use (\$theme_version, \$separate_folder, \$manifest) {\n";

		if ( $has_js && in_array( $spec['javascript_type'] ?? '', array( 'slider', 'carousel' ), true ) ) {
			$snippet .= "                themeJS('library/swiper.js');\n";
		}

		if ( $has_js ) {
			$snippet .= "                \$aabg_js = get_template_directory() . '/assets/js/blocks/{$slug}.js';\n";
			$snippet .= "                if ( file_exists( \$aabg_js ) ) {\n";
			$snippet .= "                    wp_enqueue_script(\n";
			$snippet .= "                        THEME_PREFIX . '-{$slug}',\n";
			$snippet .= "                        get_template_directory_uri() . '/assets/js/blocks/{$slug}.js',\n";
			$snippet .= "                        array(),\n";
			$snippet .= "                        filemtime( \$aabg_js ),\n";
			$snippet .= "                        true\n";
			$snippet .= "                    );\n";
			$snippet .= "                } elseif ( isset(\$manifest['modules/{$slug}.js']) && !empty(\$manifest['modules/{$slug}.js']) ) {\n";
			$snippet .= "                    wp_enqueue_script(\n";
			$snippet .= "                        THEME_PREFIX . '-{$slug}',\n";
			$snippet .= "                        \$separate_folder . \$manifest['modules/{$slug}.js'],\n";
			$snippet .= "                        array(),\n";
			$snippet .= "                        _THEME_VERSION,\n";
			$snippet .= "                        true\n";
			$snippet .= "                    );\n";
			$snippet .= "                }\n";
		}

		$snippet .= Theme_Integrator::css_enqueue_snippet( $slug );

		$snippet .= "            },\n";
		$snippet .= "        ));\n";
		$snippet .= "        // AABG:END:{$slug}\n";

		// Insert before closing of acf_register_block_type if block.
		$marker = '// AABG:AUTO_BLOCKS';
		if ( false !== strpos( $content, $marker ) ) {
			$content = str_replace( $marker, $snippet . '        ' . $marker, $content );
		} else {
			// Fallback: insert before closing brace of register_acf_blocks.
			$search  = "        ));\n    }\n}";
			$replace = "        ));\n" . $snippet . "    }\n}";
			if ( false !== strpos( $content, $search ) ) {
				$content = str_replace( $search, "        ));\n" . $snippet . "    }\n}", $content );
			} else {
				return new \WP_Error( 'register_insert_failed', __( 'Could not find insertion point in acf-block-register.php. Add // AABG:AUTO_BLOCKS marker.', 'ai-acf-block-generator' ) );
			}
		}

		// Never write a broken registration file — it would fatal the whole site.
		$valid = PHP_Validator::validate( $content, 'acf-block-register.php' );
		if ( is_wp_error( $valid ) ) {
			return new \WP_Error(
				'register_invalid_php',
				sprintf(
					/* translators: %s: parser error message */
					__( 'Block registration was aborted because it would have produced invalid PHP: %s', 'ai-acf-block-generator' ),
					$valid->get_error_message()
				)
			);
		}

		return false !== file_put_contents( $file, $content );
	}
}
