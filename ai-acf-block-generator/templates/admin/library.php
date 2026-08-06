<?php
/**
 * Block library page template.
 *
 * @package AABG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap aabg-wrap">
	<div class="aabg-header">
		<h1><?php esc_html_e( 'Block Library', 'ai-acf-block-generator' ); ?></h1>
		<p class="aabg-subtitle"><?php esc_html_e( 'Manage, search, export, and import your generated ACF blocks.', 'ai-acf-block-generator' ); ?></p>
	</div>

	<div class="aabg-library-toolbar aabg-card">
		<div class="aabg-toolbar-left">
			<input type="search" id="aabg-library-search" placeholder="<?php esc_attr_e( 'Search blocks...', 'ai-acf-block-generator' ); ?>" />
			<select id="aabg-library-filter">
				<option value=""><?php esc_html_e( 'All Categories', 'ai-acf-block-generator' ); ?></option>
				<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="aabg-toolbar-right">
			<label class="aabg-import-btn button">
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'Import Block', 'ai-acf-block-generator' ); ?>
				<input type="file" id="aabg-import-file" accept=".zip,.json" hidden />
			</label>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aabg-generator' ) ); ?>" class="button button-primary">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'New Block', 'ai-acf-block-generator' ); ?>
			</a>
		</div>
	</div>

	<div id="aabg-library-grid" class="aabg-library-grid">
		<?php if ( empty( $blocks ) ) : ?>
			<div class="aabg-empty-state aabg-card">
				<span class="dashicons dashicons-layout"></span>
				<h3><?php esc_html_e( 'No blocks yet', 'ai-acf-block-generator' ); ?></h3>
				<p><?php esc_html_e( 'Generate your first ACF block to see it here.', 'ai-acf-block-generator' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aabg-generator' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Generate Block', 'ai-acf-block-generator' ); ?>
				</a>
			</div>
		<?php else : ?>
			<?php foreach ( $blocks as $block ) : ?>
				<div class="aabg-block-card aabg-card" data-slug="<?php echo esc_attr( $block['slug'] ); ?>" data-category="<?php echo esc_attr( $block['category'] ); ?>">
					<div class="aabg-block-card__header">
						<span class="dashicons dashicons-<?php echo esc_attr( $block['icon'] ); ?>"></span>
						<h3><?php echo esc_html( $block['title'] ); ?></h3>
					</div>
					<p class="aabg-block-card__desc"><?php echo esc_html( $block['description'] ); ?></p>
					<div class="aabg-block-card__meta">
						<span class="aabg-badge"><?php echo esc_html( $block['category'] ); ?></span>
						<span class="aabg-badge aabg-badge--muted"><?php echo esc_html( $block['slug'] ); ?></span>
						<?php if ( $block['has_js'] ) : ?>
							<span class="aabg-badge aabg-badge--js">JS</span>
						<?php endif; ?>
						<span class="aabg-badge aabg-badge--muted"><?php echo esc_html( $block['field_count'] ); ?> <?php esc_html_e( 'fields', 'ai-acf-block-generator' ); ?></span>
					</div>
					<div class="aabg-block-card__actions">
						<button type="button" class="button aabg-preview-btn" data-slug="<?php echo esc_attr( $block['slug'] ); ?>">
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e( 'Preview', 'ai-acf-block-generator' ); ?>
						</button>
						<button type="button" class="button aabg-duplicate-btn" data-slug="<?php echo esc_attr( $block['slug'] ); ?>">
							<span class="dashicons dashicons-admin-page"></span>
						</button>
						<div class="aabg-dropdown">
							<button type="button" class="button aabg-export-toggle">
								<span class="dashicons dashicons-download"></span>
							</button>
							<div class="aabg-dropdown-menu">
								<button type="button" class="aabg-export-btn" data-format="zip" data-slug="<?php echo esc_attr( $block['slug'] ); ?>">ZIP</button>
								<button type="button" class="aabg-export-btn" data-format="json" data-slug="<?php echo esc_attr( $block['slug'] ); ?>">JSON</button>
							</div>
						</div>
						<button type="button" class="button aabg-delete-btn" data-slug="<?php echo esc_attr( $block['slug'] ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<!-- Preview Modal -->
	<div id="aabg-preview-modal" class="aabg-modal aabg-hidden">
		<div class="aabg-modal__backdrop"></div>
		<div class="aabg-modal__content">
			<div class="aabg-modal__header">
				<h3><?php esc_html_e( 'Block Preview', 'ai-acf-block-generator' ); ?></h3>
				<div class="aabg-device-switcher">
					<button type="button" class="aabg-device-btn active" data-device="desktop"><span class="dashicons dashicons-desktop"></span></button>
					<button type="button" class="aabg-device-btn" data-device="tablet"><span class="dashicons dashicons-tablet"></span></button>
					<button type="button" class="aabg-device-btn" data-device="mobile"><span class="dashicons dashicons-smartphone"></span></button>
				</div>
				<button type="button" class="aabg-modal__close">&times;</button>
			</div>
			<div class="aabg-preview-frame-wrap" data-device="desktop">
				<iframe id="aabg-modal-preview-frame" class="aabg-preview-frame"></iframe>
			</div>
		</div>
	</div>
</div>
