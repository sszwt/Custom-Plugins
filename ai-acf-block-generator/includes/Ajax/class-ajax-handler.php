<?php
/**
 * AJAX request handlers.
 *
 * @package AABG
 */

namespace AABG\Ajax;

use AABG\Generator\Block_Generator;
use AABG\AI\AI_Provider_Factory;
use AABG\Library\Block_Library;
use AABG\Library\Exporter;
use AABG\Library\Importer;
use AABG\Utils\File_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ajax_Handler
 */
class Ajax_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$actions = array(
			'aabg_generate_block'   => 'generate_block',
			'aabg_test_openai'      => 'test_openai',
			'aabg_sync_block_css'   => 'sync_block_css',
			'aabg_delete_block'     => 'delete_block',
			'aabg_duplicate_block'  => 'duplicate_block',
			'aabg_export_block'     => 'export_block',
			'aabg_import_block'     => 'import_block',
			'aabg_get_blocks'       => 'get_blocks',
			'aabg_preview_block'    => 'preview_block',
			'aabg_get_block_files'  => 'get_block_files',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Verify nonce and capability.
	 *
	 * @param string $nonce_action Optional custom nonce action.
	 * @return bool
	 */
	private function verify_request( $nonce_action = 'aabg_admin_nonce' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-acf-block-generator' ) ), 403 );
		}

		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $nonce_action ) && ! wp_verify_nonce( $nonce, 'aabg_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'ai-acf-block-generator' ) ), 403 );
		}

		return true;
	}

	/**
	 * Generate a new block.
	 */
	public function generate_block() {
		$this->verify_request();

		try {
			$input = array(
				'block_name'        => $_POST['block_name'] ?? '',
				'block_slug'        => $_POST['block_slug'] ?? '',
				'block_category'    => $_POST['block_category'] ?? 'custom-blocks',
				'block_icon'        => $_POST['block_icon'] ?? 'layout',
				'block_description' => $_POST['block_description'] ?? '',
				'prompt'            => $_POST['prompt'] ?? '',
			);

			$generator = new Block_Generator();
			$result    = $generator->generate( $input );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( $result );
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Generation failed: %s', 'ai-acf-block-generator' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Regenerate block CSS files in theme assets/css/blocks/.
	 */
	public function sync_block_css() {
		$this->verify_request();

		$result = \AABG\Utils\Theme_Integrator::sync_all_block_styles();
		update_option( 'aabg_assets_sync_version', AABG_VERSION );

		if ( empty( $result['synced'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No block CSS files were written. Check theme path and permissions.', 'ai-acf-block-generator' ),
				)
			);
		}

		$message = sprintf(
			/* translators: %d: number of CSS files */
			_n( '%d block CSS file synced.', '%d block CSS files synced.', (int) $result['synced'], 'ai-acf-block-generator' ),
			(int) $result['synced']
		);

		if ( ! empty( $result['failed'] ) ) {
			$message .= ' ' . sprintf(
				/* translators: %s: comma-separated slugs */
				__( 'Failed: %s', 'ai-acf-block-generator' ),
				implode( ', ', $result['failed'] )
			);
		}

		wp_send_json_success(
			array(
				'message' => $message,
				'result'  => $result,
			)
		);
	}

	/**
	 * Test OpenAI API key connection.
	 */
	public function test_openai() {
		$this->verify_request();

		if ( ! AI_Provider_Factory::has_api_key() ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: provider label */
						__( 'No %s API key saved. Paste your key and click Save Settings first.', 'ai-acf-block-generator' ),
						AI_Provider_Factory::get_provider_label()
					),
				)
			);
		}

		$provider = AI_Provider_Factory::create();
		$result   = $provider->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$model = 'gemini' === AI_Provider_Factory::get_provider_slug()
			? get_option( 'aabg_gemini_model', 'gemini-2.5-flash' )
			: get_option( 'aabg_openai_model', 'gpt-4o-mini' );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: provider label, 2: model name */
					__( '%1$s API key works! Model: %2$s', 'ai-acf-block-generator' ),
					AI_Provider_Factory::get_provider_label(),
					$model
				),
			)
		);
	}

	/**
	 * Delete a block.
	 */
	public function delete_block() {
		$this->verify_request();

		$slug    = sanitize_title( $_POST['slug'] ?? '' );
		$library = new Block_Library();
		$result  = $library->delete( $slug );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Block deleted.', 'ai-acf-block-generator' ) ) );
	}

	/**
	 * Duplicate a block.
	 */
	public function duplicate_block() {
		$this->verify_request();

		$slug    = sanitize_title( $_POST['slug'] ?? '' );
		$library = new Block_Library();
		$result  = $library->duplicate( $slug );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Export a block.
	 */
	public function export_block() {
		$this->verify_request();

		$slug   = sanitize_title( $_POST['slug'] ?? '' );
		$format = sanitize_text_field( $_POST['format'] ?? 'zip' );
		$exporter = new Exporter();

		switch ( $format ) {
			case 'json':
				$result = $exporter->export_json( $slug );
				break;
			case 'file':
				$filename = sanitize_file_name( $_POST['filename'] ?? '' );
				$result   = $exporter->export_file( $slug, $filename );
				break;
			default:
				$result = $exporter->export_zip( $slug );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Import a block.
	 */
	public function import_block() {
		$this->verify_request();

		$importer = new Importer();

		if ( ! empty( $_FILES['import_file'] ) ) {
			$file   = $_FILES['import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ext    = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

			if ( 'zip' === $ext ) {
				$result = $importer->import_zip( $file );
			} else {
				$content = file_get_contents( $file['tmp_name'] );
				$result  = $importer->import_json( $content );
			}
		} elseif ( ! empty( $_POST['import_json'] ) ) {
			$json   = wp_unslash( $_POST['import_json'] );
			$result = $importer->import_json( $json );
		} else {
			wp_send_json_error( array( 'message' => __( 'No import data provided.', 'ai-acf-block-generator' ) ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get blocks list.
	 */
	public function get_blocks() {
		$this->verify_request();

		$library = new Block_Library();
		$blocks  = $library->get_blocks(
			array(
				'search'   => sanitize_text_field( $_GET['search'] ?? $_POST['search'] ?? '' ),
				'category' => sanitize_text_field( $_GET['category'] ?? $_POST['category'] ?? '' ),
			)
		);

		wp_send_json_success( array( 'blocks' => $blocks ) );
	}

	/**
	 * Live preview of a block.
	 */
	public function preview_block() {
		$slug  = sanitize_title( $_GET['slug'] ?? $_POST['slug'] ?? '' );
		$nonce = sanitize_text_field( $_GET['nonce'] ?? $_POST['nonce'] ?? '' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ai-acf-block-generator' ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'aabg_preview_' . $slug ) && ! wp_verify_nonce( $nonce, 'aabg_admin_nonce' ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'ai-acf-block-generator' ) );
		}

		$render_path = '';
		$style_path  = '';
		$script_path = '';

		if ( \AABG\Utils\Theme_Integrator::is_enabled() ) {
			$paths       = \AABG\Utils\Theme_Integrator::paths();
			$render_path = $paths['blocks_php'] . $slug . '.php';
			$scss_path   = $paths['scss_components'] . '_' . $slug . '.scss';
			$script_path = $paths['js_modules'] . $slug . '.js';
			$style_path  = file_exists( $scss_path ) ? $scss_path : '';
		} else {
			$render_path = File_Manager::get_block_path( $slug ) . 'render.php';
			$style_path  = File_Manager::get_block_path( $slug ) . 'style.css';
			$script_path = File_Manager::get_block_path( $slug ) . 'script.js';
		}

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $slug ); ?> — Preview</title>
			<?php if ( file_exists( $style_path ) && ! \AABG\Utils\Theme_Integrator::is_enabled() ) : ?>
				<style><?php echo wp_kses_post( file_get_contents( $style_path ) ); ?></style>
			<?php endif; ?>
			<?php if ( \AABG\Utils\Theme_Integrator::is_enabled() ) : ?>
				<?php
				$preview_css = \AABG\Utils\Theme_Integrator::paths()['block_css'] . $slug . '.css';
				if ( file_exists( $preview_css ) ) :
					?>
					<link rel="stylesheet" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/css/blocks/' . $slug . '.css?v=' . filemtime( $preview_css ) ); ?>" />
				<?php endif; ?>
			<?php endif; ?>
			<style>
				body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
				.aabg-preview-notice { background: #f0f6fc; padding: 12px 16px; font-size: 13px; color: #1d2327; border-bottom: 1px solid #c3c4c7; }
			</style>
		</head>
		<body>
			<div class="aabg-preview-notice">
				<?php esc_html_e( 'Live Preview — placeholder content shown where ACF fields are empty.', 'ai-acf-block-generator' ); ?>
			</div>
			<?php
			if ( file_exists( $render_path ) ) {
				$block = array(
					'id'   => 'preview',
					'name' => 'acf/' . $slug,
					'data' => array(),
				);

				if ( ! function_exists( 'get_field' ) ) {
					/**
					 * Fallback get_field for preview without ACF.
					 *
					 * @param string $name Field name.
					 * @return string
					 */
					function get_field( $name ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
						return 'Preview: ' . $name;
					}
				} else {
					add_filter(
						'acf/pre_load_value',
					function ( $null, $post_id, $field ) {
						$name = $field['name'] ?? '';
						$type = $field['type'] ?? 'text';

						if ( empty( $name ) ) {
							return $null;
						}

						if ( in_array( $type, array( 'image' ), true ) || false !== strpos( $name, 'image' ) ) {
							return array(
								'url' => 'https://via.placeholder.com/600x400/e0e0e0/666666?text=Image',
								'alt' => 'Placeholder',
							);
						}

						if ( in_array( $type, array( 'link' ), true ) || false !== strpos( $name, 'button' ) ) {
							return array(
								'url'    => '#',
								'title'  => 'Learn More',
								'target' => '',
							);
						}

						if ( 'repeater' === $type || false !== strpos( $name, 'team' ) || false !== strpos( $name, 'items' ) ) {
							return array(
								array(
									'image'        => array( 'url' => 'https://via.placeholder.com/300x300/e0e0e0/666666?text=Photo', 'alt' => 'Team' ),
									'name'         => 'Jane Smith',
									'designation'  => 'Designer',
									'title'        => 'Accordion Item',
									'content'      => 'Sample accordion content for preview.',
									'social_links' => array(
										array( 'icon' => 'LinkedIn', 'url' => 'https://linkedin.com' ),
									),
								),
								array(
									'image'        => array( 'url' => 'https://via.placeholder.com/300x300/e0e0e0/666666?text=Photo', 'alt' => 'Team' ),
									'name'         => 'John Doe',
									'designation'  => 'Developer',
									'title'        => 'Second Item',
									'content'      => 'More placeholder content.',
									'social_links' => array(
										array( 'icon' => 'Twitter', 'url' => 'https://twitter.com' ),
									),
								),
							);
						}

						if ( false !== strpos( $name, 'title' ) && false === strpos( $name, 'subtitle' ) ) {
							return 'Sample Title';
						}

						if ( false !== strpos( $name, 'subtitle' ) ) {
							return 'Sample Subtitle';
						}

						if ( false !== strpos( $name, 'description' ) || false !== strpos( $name, 'content' ) ) {
							return 'This is placeholder content for the live preview.';
						}

						if ( false !== strpos( $name, 'name' ) ) {
							return 'John Doe';
						}

						if ( false !== strpos( $name, 'designation' ) ) {
							return 'Senior Developer';
						}

						return 'Preview value';
					},
					10,
					3
				);
				}

				include $render_path;
			} else {
				echo '<p>' . esc_html__( 'Render template not found.', 'ai-acf-block-generator' ) . '</p>';
			}
			?>
			<?php if ( file_exists( $script_path ) ) : ?>
				<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
				<script><?php echo wp_kses_post( file_get_contents( $script_path ) ); ?></script>
			<?php endif; ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Get block file contents.
	 */
	public function get_block_files() {
		$this->verify_request();

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		$library = new Block_Library();
		$files = $library->get_block_files( $slug );
		$contents = array();

		foreach ( $files as $file ) {
			$content = File_Manager::read_block_file( $slug, $file );
			if ( false !== $content && pathinfo( $file, PATHINFO_EXTENSION ) !== 'png' ) {
				$contents[ $file ] = $content;
			}
		}

		wp_send_json_success( array( 'files' => $contents ) );
	}
}
