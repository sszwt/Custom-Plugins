<?php
/**
 * Integrates generated blocks into the active theme structure.
 *
 * @package AABG
 */

namespace AABG\Utils;

use AABG\AI\AI_Provider_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Theme_Integrator
 */
class Theme_Integrator {

	/**
	 * Check if theme integration mode is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return true;
	}

	/**
	 * Get target theme directory.
	 *
	 * @return string
	 */
	public static function get_theme_dir() {
		$custom = get_option( 'aabg_theme_path', '' );

		if ( ! empty( $custom ) && is_dir( $custom ) ) {
			return trailingslashit( wp_normalize_path( $custom ) );
		}

		return trailingslashit( get_stylesheet_directory() );
	}

	/**
	 * Get all theme integration paths.
	 *
	 * @return array
	 */
	public static function paths() {
		$base = self::get_theme_dir();

		return array(
			'base'           => $base,
			'blocks_php'     => $base . 'template-parts/blocks/',
			'acf_json'       => $base . 'includes/acf-json/',
			'acf_register'   => $base . 'includes/acf-block-register.php',
			'scss_components'=> $base . 'sources/scss/components/',
			'scss_core'      => $base . 'sources/scss/components/_core.scss',
			'block_css'      => $base . 'assets/css/blocks/',
			'block_js'       => $base . 'assets/js/blocks/',
			'js_modules'     => $base . 'sources/js/modules/',
			'preview'        => $base . 'includes/acf-block-preview/',
			'manifest'       => $base . 'includes/aabg-generated/',
		);
	}

	/**
	 * Check if block already exists in theme.
	 *
	 * @param string $slug Block slug.
	 * @return bool
	 */
	public static function block_exists( $slug ) {
		$paths = self::paths();
		return file_exists( $paths['blocks_php'] . Sanitizer::block_slug( $slug ) . '.php' );
	}

	/**
	 * Write block PHP template to theme.
	 *
	 * @param string $slug    Block slug.
	 * @param string $content PHP content.
	 * @return bool
	 */
	public static function write_block_template( $slug, $content ) {
		$paths = self::paths();
		wp_mkdir_p( $paths['blocks_php'] );
		return false !== file_put_contents( $paths['blocks_php'] . Sanitizer::block_slug( $slug ) . '.php', $content );
	}

	/**
	 * Write ACF field group JSON to theme acf-json folder.
	 *
	 * @param string $group_key Group key.
	 * @param string $content   JSON content.
	 * @return bool
	 */
	public static function write_acf_json( $group_key, $content ) {
		$paths = self::paths();
		wp_mkdir_p( $paths['acf_json'] );
		$filename = sanitize_file_name( $group_key ) . '.json';
		return false !== file_put_contents( $paths['acf_json'] . $filename, $content );
	}

	/**
	 * Write SCSS component file.
	 *
	 * @param string $slug    Block slug.
	 * @param string $content SCSS content.
	 * @return bool
	 */
	public static function write_scss( $slug, $content ) {
		$paths = self::paths();
		wp_mkdir_p( $paths['scss_components'] );
		return false !== file_put_contents( $paths['scss_components'] . '_' . Sanitizer::block_slug( $slug ) . '.scss', $content );
	}

	/**
	 * Write compiled block CSS (works without npm build).
	 *
	 * @param string $slug    Block slug.
	 * @param string $content CSS content.
	 * @return bool
	 */
	public static function write_block_css( $slug, $content ) {
		$paths = self::paths();
		$dir   = $paths['block_css'];

		if ( ! wp_mkdir_p( $dir ) && ! is_dir( $dir ) ) {
			return false;
		}

		$file   = $dir . Sanitizer::block_slug( $slug ) . '.css';
		$result = file_put_contents( $file, $content );

		if ( false === $result ) {
			global $wp_filesystem;

			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( ! empty( $wp_filesystem ) ) {
				$result = $wp_filesystem->put_contents( $file, $content, FS_CHMOD_FILE );
			}
		}

		if ( false !== $result && ! file_exists( $paths['block_css'] . 'index.php' ) ) {
			file_put_contents( $paths['block_css'] . 'index.php', "<?php\n// Silence is golden.\n" );
		}

		return false !== $result;
	}

	/**
	 * Generate and write CSS for all theme blocks (or one block).
	 *
	 * @param string $slug Optional single block slug.
	 * @return array { synced: int, failed: array }
	 */
	public static function sync_all_block_styles( $slug = '' ) {
		$css_gen = new \AABG\Generator\Theme_CSS_Generator();
		$js_gen  = new \AABG\Generator\JS_Generator();
		$synced  = 0;
		$failed  = array();
		$slugs   = $slug ? array( Sanitizer::block_slug( $slug ) ) : self::list_blocks();

		foreach ( $slugs as $block_slug ) {
			$manifest = self::get_manifest( $block_slug );
			$layout   = $manifest['layout'] ?? 'content';
			$spec     = array(
				'block_slug'       => $block_slug,
				'layout'           => $layout,
				'design_tokens'    => $manifest['design_tokens'] ?? array(),
				'layout_structure' => $manifest['layout_structure'] ?? array(),
				'fields'           => $manifest['fields'] ?? array(),
			);

			$css = $css_gen->generate( $spec );

			if ( self::write_block_css( $block_slug, $css ) ) {
				++$synced;
				self::patch_block_css_enqueue( $block_slug );
			} else {
				$failed[] = $block_slug;
			}

			// Regenerate the compiled JS too, so existing interactive blocks
			// pick up slider/accordion fixes without being regenerated.
			if ( ! empty( $manifest['has_js'] ) ) {
				$js_type = in_array( $layout, array( 'slider', 'accordion', 'tabs' ), true ) ? $layout : 'slider';
				$js_spec = array_merge(
					$spec,
					array(
						'needs_javascript' => true,
						'javascript_type'  => $js_type,
						'bem_block'        => $block_slug,
					)
				);
				$script = $js_gen->generate_script( $js_spec );
				if ( $script ) {
					self::write_block_js( $block_slug, $script );
					self::write_js_module( $block_slug, $script );
				}
			}
		}

		return array(
			'synced' => $synced,
			'failed' => $failed,
		);
	}

	/**
	 * PHP snippet to enqueue per-block CSS in acf-block-register.php.
	 *
	 * @param string $slug Block slug.
	 * @return string
	 */
	public static function css_enqueue_snippet( $slug ) {
		$slug = Sanitizer::block_slug( $slug );
		return "                \$aabg_css = get_template_directory() . '/assets/css/blocks/{$slug}.css';\n"
			. "                if ( file_exists( \$aabg_css ) ) {\n"
			. "                    wp_enqueue_style(\n"
			. "                        THEME_PREFIX . '-{$slug}',\n"
			. "                        get_template_directory_uri() . '/assets/css/blocks/{$slug}.css',\n"
			. "                        array(),\n"
			. "                        filemtime( \$aabg_css )\n"
			. "                    );\n"
			. "                }\n";
	}

	/**
	 * Ensure existing block registration enqueues compiled CSS.
	 *
	 * @param string $slug Block slug.
	 * @return bool
	 */
	public static function patch_block_css_enqueue( $slug ) {
		$slug  = Sanitizer::block_slug( $slug );
		$paths = self::paths();
		$file  = $paths['acf_register'];

		if ( ! file_exists( $file ) ) {
			return false;
		}

		$content = file_get_contents( $file );
		$marker  = "assets/css/blocks/{$slug}.css";

		if ( false !== strpos( $content, $marker ) ) {
			return true;
		}

		$snippet = self::css_enqueue_snippet( $slug );
		$pattern = '/(\/\/ AABG:BLOCK:' . preg_quote( $slug, '/' ) . '.*?\'enqueue_assets\'\s*=>\s*function\s*\(\)\s*use\s*\([^)]*\)\s*\{)(.*?)(\},\s*\n\s*\)\);)/s';

		if ( ! preg_match( $pattern, $content, $matches ) ) {
			return false;
		}

		$replacement = $matches[1] . "\n" . $snippet . $matches[2] . $matches[3];
		$content       = preg_replace( $pattern, $replacement, $content, 1 );

		return false !== file_put_contents( $file, $content );
	}

	/**
	 * Link SCSS in _core.scss.
	 *
	 * @param string $slug Block slug.
	 * @return bool
	 */
	public static function link_scss_in_core( $slug ) {
		$paths   = self::paths();
		$core    = $paths['scss_core'];
		$use_line = '@use "' . Sanitizer::block_slug( $slug ) . '";';

		if ( ! file_exists( $core ) ) {
			return false;
		}

		$content = file_get_contents( $core );

		if ( false !== strpos( $content, $use_line ) ) {
			return true;
		}

		$marker = '/* AABG:AUTO_IMPORTS */';
		if ( false !== strpos( $content, $marker ) ) {
			$content = str_replace( $marker, $use_line . "\n" . $marker, $content );
		} else {
			$content = rtrim( $content ) . "\n" . $use_line . "\n";
		}

		return false !== file_put_contents( $core, $content );
	}

	/**
	 * Write JS module to theme.
	 *
	 * @param string $slug    Block slug.
	 * @param string $content JS content.
	 * @return bool
	 */
	public static function write_js_module( $slug, $content ) {
		$paths = self::paths();
		wp_mkdir_p( $paths['js_modules'] );
		return false !== file_put_contents( $paths['js_modules'] . Sanitizer::block_slug( $slug ) . '.js', $content );
	}

	/**
	 * Write compiled, ready-to-enqueue block JS (works without npm build).
	 *
	 * @param string $slug    Block slug.
	 * @param string $content JS content.
	 * @return bool
	 */
	public static function write_block_js( $slug, $content ) {
		$paths = self::paths();
		$dir   = $paths['block_js'];

		if ( ! wp_mkdir_p( $dir ) && ! is_dir( $dir ) ) {
			return false;
		}

		$file   = $dir . Sanitizer::block_slug( $slug ) . '.js';
		$result = file_put_contents( $file, $content );

		if ( false !== $result && ! file_exists( $dir . 'index.php' ) ) {
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" );
		}

		return false !== $result;
	}

	/**
	 * Write block preview image.
	 *
	 * @param string $slug Block slug.
	 * @param string $source_path Source image path.
	 * @return bool
	 */
	public static function write_preview_image( $slug, $source_path ) {
		$paths = self::paths();
		wp_mkdir_p( $paths['preview'] );

		$ext  = pathinfo( $source_path, PATHINFO_EXTENSION );
		$dest = $paths['preview'] . Sanitizer::block_slug( $slug ) . '.' . ( $ext ? $ext : 'png' );

		if ( ! copy( $source_path, $dest ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Save block manifest metadata.
	 *
	 * @param string $slug Block slug.
	 * @param array  $data Manifest data.
	 * @return bool
	 */
	public static function write_manifest( $slug, $data ) {
		$paths = self::paths();
		wp_mkdir_p( $paths['manifest'] );
		return false !== file_put_contents(
			$paths['manifest'] . Sanitizer::block_slug( $slug ) . '.json',
			wp_json_encode( $data, JSON_PRETTY_PRINT )
		);
	}

	/**
	 * Get manifest for a block.
	 *
	 * @param string $slug Block slug.
	 * @return array|null
	 */
	public static function get_manifest( $slug ) {
		$paths = self::paths();
		$file  = $paths['manifest'] . Sanitizer::block_slug( $slug ) . '.json';

		if ( ! file_exists( $file ) ) {
			return null;
		}

		$data = json_decode( file_get_contents( $file ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * List all plugin-generated blocks in theme.
	 *
	 * @return array
	 */
	public static function list_blocks() {
		$paths  = self::paths();
		$slugs  = array();

		if ( is_dir( $paths['manifest'] ) ) {
			foreach ( glob( $paths['manifest'] . '*.json' ) as $file ) {
				$slugs[] = basename( $file, '.json' );
			}
		}

		sort( $slugs );
		return $slugs;
	}

	/**
	 * Delete block files from theme.
	 *
	 * @param string $slug Block slug.
	 * @return bool
	 */
	public static function delete_block( $slug ) {
		$slug  = Sanitizer::block_slug( $slug );
		$paths = self::paths();

		$files = array(
			$paths['blocks_php'] . $slug . '.php',
			$paths['scss_components'] . '_' . $slug . '.scss',
			$paths['block_css'] . $slug . '.css',
			$paths['block_js'] . $slug . '.js',
			$paths['js_modules'] . $slug . '.js',
			$paths['manifest'] . $slug . '.json',
		);

		// Preview images (multiple extensions).
		foreach ( array( 'png', 'jpg', 'jpeg', 'webp' ) as $ext ) {
			$files[] = $paths['preview'] . $slug . '.' . $ext;
		}

		// ACF JSON — find by block location.
		if ( is_dir( $paths['acf_json'] ) ) {
			foreach ( glob( $paths['acf_json'] . 'group_*.json' ) as $json_file ) {
				$content = file_get_contents( $json_file );
				if ( false !== strpos( $content, 'acf/' . $slug ) ) {
					$files[] = $json_file;
				}
			}
		}

		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}

		self::unlink_scss_from_core( $slug );
		self::remove_block_registration( $slug );

		return true;
	}

	/**
	 * Remove @use from _core.scss.
	 *
	 * @param string $slug Block slug.
	 */
	public static function unlink_scss_from_core( $slug ) {
		$paths    = self::paths();
		$core     = $paths['scss_core'];
		$use_line = '@use "' . Sanitizer::block_slug( $slug ) . '";';

		if ( ! file_exists( $core ) ) {
			return;
		}

		$content = file_get_contents( $core );
		$content = str_replace( $use_line . "\n", '', $content );
		$content = str_replace( $use_line, '', $content );
		file_put_contents( $core, $content );
	}

	/**
	 * Remove block registration from acf-block-register.php.
	 *
	 * @param string $slug Block slug.
	 */
	public static function remove_block_registration( $slug ) {
		$paths = self::paths();
		$file  = $paths['acf_register'];

		if ( ! file_exists( $file ) ) {
			return;
		}

		$content = file_get_contents( $file );
		$pattern = '/\s*\/\/ AABG:BLOCK:' . preg_quote( $slug, '/' ) . '.*?\/\/ AABG:END:' . preg_quote( $slug, '/' ) . '\s*/s';
		$content = preg_replace( $pattern, '', $content );
		file_put_contents( $file, $content );
	}

	/**
	 * Get list of generated files for a block.
	 *
	 * @param string $slug Block slug.
	 * @return array
	 */
	public static function get_block_files( $slug ) {
		$slug  = Sanitizer::block_slug( $slug );
		$paths = self::paths();
		$files = array();

		$map = array(
			'template-parts/blocks/' . $slug . '.php',
			'sources/scss/components/_' . $slug . '.scss',
			'assets/css/blocks/' . $slug . '.css',
			'sources/js/modules/' . $slug . '.js',
			'includes/aabg-generated/' . $slug . '.json',
			'includes/acf-block-register.php (updated)',
			'sources/scss/components/_core.scss (updated)',
		);

		foreach ( $map as $rel ) {
			$files[] = $rel;
		}

		if ( is_dir( $paths['acf_json'] ) ) {
			foreach ( glob( $paths['acf_json'] . 'group_*.json' ) as $json_file ) {
				$content = file_get_contents( $json_file );
				if ( false !== strpos( $content, 'acf/' . $slug ) ) {
					$files[] = 'includes/acf-json/' . basename( $json_file );
				}
			}
		}

		return $files;
	}

	/**
	 * Run pre-flight health checks before generation.
	 *
	 * @return true|\WP_Error
	 */
	public static function health_check() {
		if ( ! class_exists( 'ACF' ) ) {
			return new \WP_Error( 'no_acf', __( 'ACF Pro is not active. Please install and activate ACF Pro first.', 'ai-acf-block-generator' ) );
		}

		$paths = self::paths();
		$base  = $paths['base'];

		if ( ! is_dir( $base ) ) {
			return new \WP_Error(
				'invalid_theme',
				sprintf(
					/* translators: %s: theme path */
					__( 'Theme path not found: %s. Leave Theme Path empty or fix the path in Settings.', 'ai-acf-block-generator' ),
					$base
				)
			);
		}

		if ( ! is_writable( $paths['blocks_php'] ) ) {
			return new \WP_Error(
				'not_writable',
				sprintf(
					/* translators: %s: folder path */
					__( 'Theme folder is not writable: %s', 'ai-acf-block-generator' ),
					$paths['blocks_php']
				)
			);
		}

		if ( ! file_exists( $paths['acf_register'] ) ) {
			return new \WP_Error( 'missing_register', __( 'acf-block-register.php not found in theme includes folder.', 'ai-acf-block-generator' ) );
		}

		$register = file_get_contents( $paths['acf_register'] );
		if ( false === strpos( $register, 'AABG:AUTO_BLOCKS' ) ) {
			return new \WP_Error(
				'missing_marker',
				__( 'Theme acf-block-register.php is missing // AABG:AUTO_BLOCKS marker. Add it before generating blocks.', 'ai-acf-block-generator' )
			);
		}

		if ( ! file_exists( $paths['scss_core'] ) ) {
			return new \WP_Error( 'missing_scss_core', __( 'Theme _core.scss not found.', 'ai-acf-block-generator' ) );
		}

		$core = file_get_contents( $paths['scss_core'] );
		if ( false === strpos( $core, 'AABG:AUTO_IMPORTS' ) ) {
			return new \WP_Error(
				'missing_scss_marker',
				__( 'Theme _core.scss is missing /* AABG:AUTO_IMPORTS */ marker.', 'ai-acf-block-generator' )
			);
		}

		return true;
	}

	/**
	 * Get diagnostic status for settings UI.
	 *
	 * @return array
	 */
	public static function get_diagnostics() {
		$paths   = self::paths();
		$api_key = trim( (string) get_option( 'aabg_openai_api_key', '' ) );
		$css_dir = $paths['block_css'];
		$css_files = is_dir( $css_dir ) ? glob( $css_dir . '*.css' ) : array();

		return array(
			'theme_path'      => $paths['base'],
			'theme_exists'    => is_dir( $paths['base'] ),
			'writable'        => is_writable( $paths['blocks_php'] ),
			'block_css_dir'   => $css_dir,
			'block_css_count' => is_array( $css_files ) ? count( $css_files ) : 0,
			'acf_active'      => class_exists( 'ACF' ),
			'api_key_set'     => AI_Provider_Factory::has_api_key(),
			'ai_provider'     => AI_Provider_Factory::get_provider_slug(),
			'ai_provider_label' => AI_Provider_Factory::get_provider_label(),
			'markers_ok'      => ! is_wp_error( self::health_check() ),
			'health'          => self::health_check(),
		);
	}
}
