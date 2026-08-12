<?php
/**
 * Main block generation orchestrator.
 *
 * @package AABG
 */

namespace AABG\Generator;

use AABG\AI\Prompt_Analyzer;
use AABG\Utils\File_Manager;
use AABG\Utils\Logger;
use AABG\Utils\PHP_Validator;
use AABG\Utils\Sanitizer;
use AABG\Utils\Slug_Helper;
use AABG\Utils\Theme_Integrator;
use AABG\Utils\Theme_Asset_Compiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block_Generator
 */
class Block_Generator {

	/**
	 * Generate a complete block package.
	 *
	 * @param array $input User input from form.
	 * @return array|\WP_Error
	 */
	public function generate( $input ) {
		$health = Theme_Integrator::health_check();
		if ( is_wp_error( $health ) ) {
			return $health;
		}

		$config = $this->parse_input( $input );

		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$design_image = $this->handle_design_image( $input );

		if ( is_wp_error( $design_image ) ) {
			return $design_image;
		}

		if ( $design_image ) {
			$config['design_image_path'] = $design_image;
		}

		$analyzer = new Prompt_Analyzer();
		$spec     = $analyzer->analyze( $config['prompt'], $config );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$slug = $config['slug'];

		if ( Theme_Integrator::block_exists( $slug ) ) {
			return new \WP_Error(
				'block_exists',
				sprintf(
					__( 'Block "%s" already exists in theme. Choose a different name or delete it first.', 'ai-acf-block-generator' ),
					$slug
				)
			);
		}

		$result = $this->write_theme_files( $slug, $spec, $config, $design_image );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$compile = Theme_Asset_Compiler::compile();

		$manifest = array(
			'slug'        => $slug,
			'title'       => $config['name'],
			'description' => $config['description'],
			'category'    => $config['category'],
			'icon'        => $config['icon'],
			'prompt'      => $config['prompt'],
			'layout'      => $spec['layout'] ?? 'content',
			'has_js'      => ! empty( $spec['needs_javascript'] ),
			'source'      => $spec['source'] ?? 'unknown',
			'theme'       => Theme_Integrator::get_theme_dir(),
			'created'     => current_time( 'mysql' ),
			'field_count' => count( $spec['fields'] ?? array() ),
			'suggestions' => $spec['suggestions'] ?? array(),
			'design_image'=> ! empty( $design_image ),
			'design_tokens' => $spec['design_tokens'] ?? array(),
			'layout_structure' => $spec['layout_structure'] ?? array(),
			'fields'        => $spec['fields'] ?? array(),
		);

		Theme_Integrator::write_manifest( $slug, $manifest );
		$files = Theme_Integrator::get_block_files( $slug );

		return array(
			'success'     => true,
			'slug'        => $slug,
			'title'       => $config['name'],
			'spec'        => $spec,
			'manifest'    => $manifest,
			'files'       => $files,
			'preview_url' => $this->get_preview_url( $slug ),
			'theme_mode'  => true,
			'build_note'  => $compile['success']
				? __( 'Theme SCSS compiled via npm. Block CSS is also available at assets/css/blocks/.', 'ai-acf-block-generator' )
				: __( 'Block CSS saved to assets/css/blocks/ (loads immediately). Run npm run build in theme for SCSS bundle.', 'ai-acf-block-generator' ),
			'compile'     => $compile,
			'warning'     => $spec['warning'] ?? '',
			'message'     => sprintf(
				__( 'Block "%s" generated successfully in your theme!', 'ai-acf-block-generator' ),
				$config['name']
			),
		);
	}

	/**
	 * Write files into active theme.
	 *
	 * @param string $slug         Block slug.
	 * @param array  $spec         Block spec.
	 * @param array  $config       Config.
	 * @param string $design_image Design image path.
	 * @return true|\WP_Error
	 */
	private function write_theme_files( $slug, $spec, $config, $design_image ) {
		$fields_gen   = new Field_Group_Generator();
		$render_gen   = new Theme_Render_Generator();
		$scss_gen     = new Theme_SCSS_Generator();
		$css_gen      = new Theme_CSS_Generator();
		$register_gen = new Theme_Block_Register_Generator();
		$js_gen      = new JS_Generator();

		// ACF JSON with group key.
		$group_json = $fields_gen->generate( $spec );
		$group_data = json_decode( $group_json, true );
		$group_key  = $group_data['key'] ?? Slug_Helper::group_key();

		if ( empty( $group_data['key'] ) ) {
			$group_data['key'] = $group_key;
			$group_json = wp_json_encode( $group_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		// Generate and validate the render template BEFORE touching the theme.
		$render_php = $render_gen->generate( $spec );
		$render_valid = PHP_Validator::validate( $render_php, $slug . '.php' );

		if ( is_wp_error( $render_valid ) ) {
			Logger::log( 'Render template validation failed for block: ' . $slug );
			return $render_valid;
		}

		Theme_Integrator::write_acf_json( $group_key, $group_json );
		Theme_Integrator::write_block_template( $slug, $render_php );
		Theme_Integrator::write_scss( $slug, $scss_gen->generate( $spec ) );
		Theme_Integrator::link_scss_in_core( $slug );

		if ( ! Theme_Integrator::write_block_css( $slug, $css_gen->generate( $spec ) ) ) {
			return new \WP_Error(
				'css_write_failed',
				sprintf(
					/* translators: %s: folder path */
					__( 'Could not write CSS to %sassets/css/blocks/. Check theme folder permissions.', 'ai-acf-block-generator' ),
					Theme_Integrator::get_theme_dir()
				)
			);
		}

		if ( ! empty( $spec['needs_javascript'] ) ) {
			$script = $js_gen->generate_script( $spec );
			if ( $script ) {
				// Source module (for the theme's own build pipeline) …
				Theme_Integrator::write_js_module( $slug, $script );
				// … and a compiled copy the plugin enqueues directly, so the
				// block works immediately without running the theme's npm build.
				Theme_Integrator::write_block_js( $slug, $script );
			}
		}

		// Preview image — use design image if provided, else placeholder.
		if ( $design_image && file_exists( $design_image ) ) {
			Theme_Integrator::write_preview_image( $slug, $design_image );
		} else {
			$this->create_preview_placeholder( $slug, $spec, true );
		}

		$registered = $register_gen->register( $spec );

		if ( is_wp_error( $registered ) ) {
			return $registered;
		}

		Theme_Integrator::patch_block_css_enqueue( $slug );

		return true;
	}

	/**
	 * Write files into plugin generated-blocks folder.
	 *
	 * @param string $slug Block slug.
	 * @param array  $spec Block spec.
	 * @return true
	 */
	private function write_plugin_files( $slug, $spec ) {
		$block_json = new Block_JSON_Generator();
		$fields_gen = new Field_Group_Generator();
		$render_gen = new Render_Template_Generator();
		$css_gen    = new CSS_Generator();
		$js_gen     = new JS_Generator();
		$readme_gen = new README_Generator();

		File_Manager::write_block_file( $slug, 'block.json', $block_json->generate( $spec ) );
		File_Manager::write_block_file( $slug, 'fields.json', $fields_gen->generate( $spec ) );
		File_Manager::write_block_file( $slug, 'render.php', $render_gen->generate( $spec ) );
		File_Manager::write_block_file( $slug, 'style.css', $css_gen->generate_style( $spec ) );
		File_Manager::write_block_file( $slug, 'editor.css', $css_gen->generate_editor( $spec ) );

		$script = $js_gen->generate_script( $spec );
		if ( $script ) {
			File_Manager::write_block_file( $slug, 'script.js', $script );
		}

		File_Manager::write_block_file( $slug, 'editor.js', $js_gen->generate_editor_script( $spec ) );
		File_Manager::write_block_file( $slug, 'README.md', $readme_gen->generate( $spec ) );
		$this->create_preview_placeholder( $slug, $spec, false );

		return true;
	}

	/**
	 * Handle uploaded design reference image.
	 *
	 * @param array $input Input.
	 * @return string|\WP_Error|null Path or null.
	 */
	private function handle_design_image( $input ) {
		if ( empty( $_FILES['design_image'] ) || empty( $_FILES['design_image']['tmp_name'] ) ) {
			return null;
		}

		$file = $_FILES['design_image']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! empty( $file['error'] ) ) {
			return new \WP_Error( 'upload_error', __( 'Design image upload failed.', 'ai-acf-block-generator' ) );
		}

		$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
		$type    = wp_check_filetype( $file['name'] );

		if ( ! in_array( $file['type'], $allowed, true ) && ! in_array( 'image/' . $type['ext'], $allowed, true ) ) {
			return new \WP_Error( 'invalid_image', __( 'Invalid image format. Use JPG, PNG, or WebP.', 'ai-acf-block-generator' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			return new \WP_Error( 'upload_error', $upload['error'] );
		}

		return $upload['file'];
	}

	/**
	 * Parse and validate form input.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	private function parse_input( $input ) {
		$name   = Sanitizer::block_name( $input['block_name'] ?? '' );
		$prompt = Sanitizer::prompt( $input['prompt'] ?? '' );
		$slug   = ! empty( $input['block_slug'] ) ? Sanitizer::block_slug( $input['block_slug'] ) : Slug_Helper::from_name( $name );

		if ( empty( $name ) ) {
			return new \WP_Error( 'missing_name', __( 'Title is required.', 'ai-acf-block-generator' ) );
		}

		$has_image = ! empty( $input['design_image']['tmp_name'] ) && (int) ( $input['design_image']['error'] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_OK;

		if ( empty( $prompt ) && ! $has_image ) {
			return new \WP_Error( 'missing_prompt', __( 'Prompt or design image is required.', 'ai-acf-block-generator' ) );
		}

		if ( empty( $prompt ) ) {
			$prompt = 'Analyze the uploaded design mockup. Create ACF fields for every visible element: headings, body text, buttons, images, icons, and repeaters for cards/slides. Match the exact layout, colors, and spacing. OCR all visible text into default_value fields.';
		}

		if ( empty( $slug ) ) {
			return new \WP_Error( 'invalid_slug', __( 'Could not generate a valid block slug.', 'ai-acf-block-generator' ) );
		}

		// Auto defaults — category, icon, description not shown in the form.
		$description = $name;
		$first_line  = strtok( $prompt, "\n" );
		if ( $first_line && strlen( $first_line ) > 10 ) {
			$description = sanitize_text_field( substr( $first_line, 0, 120 ) );
		}

		return array(
			'name'        => $name,
			'slug'        => $slug,
			'category'    => 'custom-blocks',
			'icon'        => 'layout',
			'description' => $description,
			'prompt'      => $prompt,
		);
	}

	/**
	 * Create preview placeholder image.
	 *
	 * @param string $slug  Block slug.
	 * @param array  $spec  Spec.
	 * @param bool   $theme Theme mode.
	 */
	private function create_preview_placeholder( $slug, $spec, $theme = false ) {
		$tmp = wp_tempnam( 'aabg-preview' );
		if ( ! $tmp ) {
			return;
		}

		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$img    = imagecreatetruecolor( 800, 450 );
			$bg     = imagecolorallocate( $img, 240, 240, 240 );
			$text   = imagecolorallocate( $img, 100, 100, 100 );
			$accent = imagecolorallocate( $img, 34, 113, 177 );
			imagefill( $img, 0, 0, $bg );
			imagefilledrectangle( $img, 0, 0, 800, 60, $accent );
			$title = $spec['block_name'] ?? $slug;
			imagestring( $img, 5, 20, 20, substr( $title, 0, 40 ), imagecolorallocate( $img, 255, 255, 255 ) );
			imagestring( $img, 3, 20, 200, 'Block Preview', $text );
			imagepng( $img, $tmp );
			imagedestroy( $img );
		}

		if ( $theme ) {
			Theme_Integrator::write_preview_image( $slug, $tmp );
		} else {
			copy( $tmp, File_Manager::get_block_path( $slug ) . 'preview.png' );
		}

		wp_delete_file( $tmp );
	}

	/**
	 * List plugin-generated files.
	 *
	 * @param string $slug Block slug.
	 * @return array
	 */
	private function list_plugin_files( $slug ) {
		$dir   = File_Manager::get_block_path( $slug );
		$files = array();
		if ( is_dir( $dir ) ) {
			foreach ( scandir( $dir ) as $item ) {
				if ( ! in_array( $item, array( '.', '..' ), true ) ) {
					$files[] = $item;
				}
			}
		}
		return $files;
	}

	/**
	 * Get preview URL.
	 *
	 * @param string $slug Block slug.
	 * @return string
	 */
	private function get_preview_url( $slug ) {
		return add_query_arg(
			array(
				'action' => 'aabg_preview_block',
				'slug'   => $slug,
				'nonce'  => wp_create_nonce( 'aabg_preview_' . $slug ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}
}
