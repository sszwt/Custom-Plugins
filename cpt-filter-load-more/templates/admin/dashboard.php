<?php
/**
 * Admin dashboard shell.
 *
 * @package CPTFLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base = admin_url( 'admin.php?page=cptflm' );
$tabs = array(
	'overview'   => array(
		'label' => __( 'Overview', 'cpt-filter-load-more' ),
		'icon'  => '<svg viewBox="0 0 24 24"><path d="M4 11h6V4H4v7zm0 9h6v-7H4v7zm10 0h6V13h-6v7zm0-16v7h6V4h-6z"/></svg>',
	),
	'post-types' => array(
		'label' => __( 'Post Types', 'cpt-filter-load-more' ),
		'icon'  => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
	),
	'taxonomies' => array(
		'label' => __( 'Taxonomies', 'cpt-filter-load-more' ),
		'icon'  => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="13" width="7" height="7" rx="1"/><rect x="14" y="15" width="7" height="3" rx="1"/></svg>',
	),
	'display'    => array(
		'label' => __( 'Display', 'cpt-filter-load-more' ),
		'icon'  => '<svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><rect x="7" y="19" width="10" height="2" rx="1"/></svg>',
	),
	'shortcodes' => array(
		'label' => __( 'Shortcodes', 'cpt-filter-load-more' ),
		'icon'  => '<svg viewBox="0 0 24 24"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>',
	),
);

$cpt_count = count(
	array_filter(
		$settings['post_types'] ?? array(),
		static function ( $c ) {
			return ! empty( $c['enabled'] );
		}
	)
);
$tax_count = count(
	array_filter(
		$settings['taxonomies'] ?? array(),
		static function ( $t ) {
			return ! empty( $t['enabled'] );
		}
	)
);
?>
<div class="wrap cptflm-dash">
	<div class="cptflm-shell">
		<aside class="cptflm-rail">
			<div class="cptflm-brand">
				<span class="cptflm-brand__mark" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'CPT Filter', 'cpt-filter-load-more' ); ?></strong>
					<em><?php esc_html_e( 'Filter & Load More', 'cpt-filter-load-more' ); ?></em>
				</div>
			</div>
			<nav class="cptflm-nav" aria-label="<?php esc_attr_e( 'Plugin sections', 'cpt-filter-load-more' ); ?>">
				<?php foreach ( $tabs as $key => $meta ) : ?>
					<a class="cptflm-nav__link<?php echo $tab === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $base . '&tab=' . $key ); ?>">
						<span class="cptflm-nav__ico"><?php echo $meta['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
						<?php echo esc_html( $meta['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="cptflm-rail__foot">
				<span>v<?php echo esc_html( CPTFLM_VERSION ); ?></span>
			</div>
		</aside>

		<main class="cptflm-main">
			<header class="cptflm-hero">
				<div>
					<p class="cptflm-eyebrow"><?php esc_html_e( 'Custom Post Types', 'cpt-filter-load-more' ); ?></p>
					<h1><?php echo esc_html( $tabs[ $tab ]['label'] ?? __( 'Dashboard', 'cpt-filter-load-more' ) ); ?></h1>
					<p class="cptflm-hero__sub"><?php esc_html_e( 'Register types, attach taxonomy filters, and ship AJAX load-more listings with one shortcode.', 'cpt-filter-load-more' ); ?></p>
				</div>
				<?php if ( in_array( $tab, array( 'post-types', 'taxonomies', 'display' ), true ) ) : ?>
					<button type="button" class="cptflm-btn" id="cptflm-save-btn">
						<?php esc_html_e( 'Save Changes', 'cpt-filter-load-more' ); ?>
					</button>
				<?php endif; ?>
			</header>

			<div id="cptflm-toast" class="cptflm-toast" hidden></div>

			<?php
			$file = CPTFLM_PLUGIN_DIR . 'templates/admin/tab-' . $tab . '.php';
			if ( file_exists( $file ) ) {
				include $file;
			}
			?>
		</main>
	</div>
</div>
