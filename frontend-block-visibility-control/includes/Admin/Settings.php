<?php
namespace FrontendBlockVisibility\Admin;

use FrontendBlockVisibility\Helpers\Sanitizer;
use WP_Block_Type_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings — Overview + global block-type hide.
 */
class Settings {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Top-level admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'Frontend Block Visibility Control', 'frontend-block-visibility-control' ),
			__( 'Block Visibility', 'frontend-block-visibility-control' ),
			'manage_options',
			'fbv-control-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-visibility',
			58
		);
	}

	/**
	 * Dashboard assets.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_fbv-control-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'fbv-admin-css',
			FBV_CONTROL_URL . 'assets/css/admin.css',
			array(),
			FBV_CONTROL_VERSION
		);

		wp_enqueue_script(
			'fbv-admin-js',
			FBV_CONTROL_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			FBV_CONTROL_VERSION,
			true
		);
	}

	/**
	 * Registered block types list.
	 *
	 * @return array
	 */
	public static function get_all_block_types() {
		$blocks   = array();
		$registry = WP_Block_Type_Registry::get_instance();
		$all      = $registry->get_all_registered();

		if ( ! empty( $all ) ) {
			foreach ( $all as $name => $type ) {
				$title           = ! empty( $type->title ) ? $type->title : $name;
				$blocks[ $name ] = $title . ' (' . $name . ')';
			}
		}

		$core_defaults = array(
			'core/paragraph' => 'Paragraph (core/paragraph)',
			'core/image'     => 'Image (core/image)',
			'core/heading'   => 'Heading (core/heading)',
			'core/gallery'   => 'Gallery (core/gallery)',
			'core/cover'     => 'Cover (core/cover)',
			'core/columns'   => 'Columns (core/columns)',
			'core/button'    => 'Buttons (core/button)',
			'core/quote'     => 'Quote (core/quote)',
			'core/video'     => 'Video (core/video)',
			'core/audio'     => 'Audio (core/audio)',
			'core/table'     => 'Table (core/table)',
			'core/embed'     => 'Embed (core/embed)',
			'core/html'      => 'Custom HTML (core/html)',
		);

		$blocks = array_merge( $core_defaults, $blocks );
		ksort( $blocks );

		return $blocks;
	}

	/**
	 * Save settings (block types only — no rule modules UI).
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! isset( $_POST['fbv_save_settings'] ) || ! isset( $_POST['fbv_nonce'] ) ) {
			return;
		}

		if ( ! Sanitizer::verify_nonce( sanitize_text_field( wp_unslash( $_POST['fbv_nonce'] ) ), 'fbv_save_settings_action' ) ) {
			wp_die( esc_html__( 'Security check failed. Invalid nonce.', 'frontend-block-visibility-control' ) );
		}

		if ( ! Sanitizer::check_capability( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized capability.', 'frontend-block-visibility-control' ) );
		}

		$existing = get_option( 'fbv_control_settings', array() );
		$sanitized = Sanitizer::sanitize_settings( wp_unslash( $_POST ), $existing );
		update_option( 'fbv_control_settings', $sanitized );

		add_settings_error( 'fbv_messages', 'fbv_message', __( 'Settings saved.', 'frontend-block-visibility-control' ), 'updated' );
	}

	/**
	 * Render dashboard.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$settings      = get_option( 'fbv_control_settings', array() );
		$all_blocks    = self::get_all_block_types();
		$hidden_blocks = isset( $settings['globally_hidden_blocks'] ) ? (array) $settings['globally_hidden_blocks'] : array();
		$hidden_count  = count( $hidden_blocks );
		$total_blocks  = count( $all_blocks );
		?>
		<div class="wrap fbv-dash">
			<div class="fbv-shell">
				<aside class="fbv-rail" aria-label="<?php esc_attr_e( 'Plugin sections', 'frontend-block-visibility-control' ); ?>">
					<div class="fbv-brand">
						<span class="fbv-brand__mark" aria-hidden="true"></span>
						<div>
							<strong><?php esc_html_e( 'Frontend Block Visibility Control', 'frontend-block-visibility-control' ); ?></strong>
							<em><?php esc_html_e( 'Show · Hide · Control', 'frontend-block-visibility-control' ); ?></em>
						</div>
					</div>

					<nav class="fbv-nav">
						<button type="button" class="fbv-nav__btn is-active" data-fbv-tab="overview"><?php esc_html_e( 'Overview', 'frontend-block-visibility-control' ); ?></button>
						<button type="button" class="fbv-nav__btn" data-fbv-tab="blocks"><?php esc_html_e( 'Block types', 'frontend-block-visibility-control' ); ?></button>
					</nav>

					<div class="fbv-rail__foot">
						<span><?php echo esc_html( 'v' . FBV_CONTROL_VERSION ); ?></span>
						<span><?php esc_html_e( 'Medgrowth', 'frontend-block-visibility-control' ); ?></span>
					</div>
				</aside>

				<div class="fbv-main">
					<header class="fbv-hero">
						<div class="fbv-hero__copy">
							<p class="fbv-kicker"><?php esc_html_e( 'Dashboard', 'frontend-block-visibility-control' ); ?></p>
							<h1><?php esc_html_e( 'What visitors see', 'frontend-block-visibility-control' ); ?></h1>
							<p class="fbv-lead"><?php esc_html_e( 'Hide blocks and ACF fields on the live site from the editor, or ban whole block types site-wide from this screen.', 'frontend-block-visibility-control' ); ?></p>
						</div>
						<div class="fbv-stats">
							<div class="fbv-stat">
								<span class="fbv-stat__n"><?php echo esc_html( (string) $hidden_count ); ?></span>
								<span class="fbv-stat__l"><?php esc_html_e( 'types hidden', 'frontend-block-visibility-control' ); ?></span>
							</div>
							<div class="fbv-stat">
								<span class="fbv-stat__n"><?php echo esc_html( (string) $total_blocks ); ?></span>
								<span class="fbv-stat__l"><?php esc_html_e( 'blocks known', 'frontend-block-visibility-control' ); ?></span>
							</div>
						</div>
					</header>

					<?php settings_errors( 'fbv_messages' ); ?>

					<form method="post" action="" class="fbv-form" id="fbv-settings-form">
						<?php wp_nonce_field( 'fbv_save_settings_action', 'fbv_nonce' ); ?>

						<section class="fbv-pane is-active" data-fbv-pane="overview">
							<div class="fbv-cards">
								<article class="fbv-card">
									<h2><?php esc_html_e( 'Editor — whole block', 'frontend-block-visibility-control' ); ?></h2>
									<p><?php esc_html_e( 'Select any block → toolbar eye icon → hide or show it on the live site. Hidden blocks stay editable here with a dashed outline.', 'frontend-block-visibility-control' ); ?></p>
								</article>
								<article class="fbv-card">
									<h2><?php esc_html_e( 'Editor — ACF fields', 'frontend-block-visibility-control' ); ?></h2>
									<p><?php esc_html_e( 'On ACF blocks, use the small control next to a real field (Title, Image, etc.). Group titles like “Hero Banner” do not get a control.', 'frontend-block-visibility-control' ); ?></p>
								</article>
								<article class="fbv-card">
									<h2><?php esc_html_e( 'Site-wide block types', 'frontend-block-visibility-control' ); ?></h2>
									<p><?php esc_html_e( 'Open Block types, pick chips to ban a type everywhere on the frontend, then Save. Editors still see those blocks in Gutenberg.', 'frontend-block-visibility-control' ); ?></p>
								</article>
							</div>

							<div class="fbv-panel fbv-panel--howto">
								<div class="fbv-panel__head">
									<h2><?php esc_html_e( 'How to use', 'frontend-block-visibility-control' ); ?></h2>
									<p><?php esc_html_e( 'Four steps — everyday workflow.', 'frontend-block-visibility-control' ); ?></p>
								</div>
								<ol class="fbv-steps">
									<li><?php esc_html_e( 'Edit a page → select a block → click the toolbar eye to hide it on the live site.', 'frontend-block-visibility-control' ); ?></li>
									<li><?php esc_html_e( 'On ACF blocks, click the control next to a field label to hide only that field on the frontend.', 'frontend-block-visibility-control' ); ?></li>
									<li><?php esc_html_e( 'Use Block types here to hide a block type across the whole website.', 'frontend-block-visibility-control' ); ?></li>
									<li><?php esc_html_e( 'Update / Publish the page, then view the frontend as a visitor.', 'frontend-block-visibility-control' ); ?></li>
								</ol>
							</div>
						</section>

						<section class="fbv-pane" data-fbv-pane="blocks">
							<div class="fbv-panel">
								<div class="fbv-panel__head">
									<h2><?php esc_html_e( 'Hide block types everywhere', 'frontend-block-visibility-control' ); ?></h2>
									<p><?php esc_html_e( 'Selected types output nothing on the frontend. They remain available in the editor.', 'frontend-block-visibility-control' ); ?></p>
								</div>
								<div class="fbv-panel__body">
									<div class="fbv-toolbar">
										<input type="search" id="fbv-block-search" class="fbv-search" placeholder="<?php esc_attr_e( 'Search title or slug…', 'frontend-block-visibility-control' ); ?>" autocomplete="off" />
										<span class="fbv-pill" id="fbv-block-count">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %d: selected count */
													_n( '%d selected', '%d selected', $hidden_count, 'frontend-block-visibility-control' ),
													$hidden_count
												)
											);
											?>
										</span>
									</div>
									<div class="fbv-grid" id="fbv-block-grid" role="group" aria-label="<?php esc_attr_e( 'Globally hidden blocks', 'frontend-block-visibility-control' ); ?>">
										<?php foreach ( $all_blocks as $block_name => $block_label ) : ?>
											<?php
											$is_on = in_array( $block_name, $hidden_blocks, true );
											$parts = explode( ' (', $block_label, 2 );
											$title = $parts[0];
											?>
											<label class="fbv-chip<?php echo $is_on ? ' is-on' : ''; ?>" data-search="<?php echo esc_attr( strtolower( $block_label ) ); ?>">
												<input type="checkbox" name="globally_hidden_blocks[]" value="<?php echo esc_attr( $block_name ); ?>" <?php checked( $is_on ); ?> />
												<span class="fbv-chip__dot" aria-hidden="true"></span>
												<span class="fbv-chip__title"><?php echo esc_html( $title ); ?></span>
												<span class="fbv-chip__slug"><?php echo esc_html( $block_name ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</section>

						<footer class="fbv-actions">
							<button type="submit" name="fbv_save_settings" class="fbv-btn" value="1"><?php esc_html_e( 'Save changes', 'frontend-block-visibility-control' ); ?></button>
						</footer>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
