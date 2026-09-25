<?php
/**
 * Shortcodes help tab.
 *
 * @package CPTFLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="cptflm-panel">
	<h2><?php esc_html_e( 'Shortcodes', 'cpt-filter-load-more' ); ?></h2>
	<p><?php esc_html_e( 'Paste either shortcode into a page, post, or HTML block.', 'cpt-filter-load-more' ); ?></p>

	<pre class="cptflm-code">[cpt_filter_load_more post_type="project" taxonomy="project_category" per_page="6" columns="3"]</pre>
	<pre class="cptflm-code">[cptflm post_type="project" taxonomy="project_category"]</pre>

	<h3><?php esc_html_e( 'Attributes', 'cpt-filter-load-more' ); ?></h3>
	<table class="cptflm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Attribute', 'cpt-filter-load-more' ); ?></th>
				<th><?php esc_html_e( 'Description', 'cpt-filter-load-more' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><code>post_type</code></td><td><?php esc_html_e( 'CPT slug to query', 'cpt-filter-load-more' ); ?></td></tr>
			<tr><td><code>taxonomy</code></td><td><?php esc_html_e( 'Taxonomy used for filter chips', 'cpt-filter-load-more' ); ?></td></tr>
			<tr><td><code>per_page</code></td><td><?php esc_html_e( 'Items per load', 'cpt-filter-load-more' ); ?></td></tr>
			<tr><td><code>columns</code></td><td><?php esc_html_e( '1–4 grid columns', 'cpt-filter-load-more' ); ?></td></tr>
			<tr><td><code>button_text</code></td><td><?php esc_html_e( 'Load more label', 'cpt-filter-load-more' ); ?></td></tr>
			<tr><td><code>all_label</code></td><td><?php esc_html_e( 'First filter chip label', 'cpt-filter-load-more' ); ?></td></tr>
			<tr><td><code>show_image</code></td><td>1 / 0</td></tr>
			<tr><td><code>show_excerpt</code></td><td>1 / 0</td></tr>
		</tbody>
	</table>

	<?php if ( ! empty( $settings['post_types'] ) ) : ?>
		<h3><?php esc_html_e( 'Ready-made for your types', 'cpt-filter-load-more' ); ?></h3>
		<?php foreach ( $settings['post_types'] as $cpt ) : ?>
			<?php if ( empty( $cpt['enabled'] ) || empty( $cpt['slug'] ) ) { continue; } ?>
			<?php
			$tax_slug = '';
			foreach ( $settings['taxonomies'] as $tax ) {
				if ( ! empty( $tax['enabled'] ) && in_array( $cpt['slug'], (array) ( $tax['post_types'] ?? array() ), true ) ) {
					$tax_slug = $tax['slug'];
					break;
				}
			}
			$code = sprintf(
				'[cpt_filter_load_more post_type="%s"%s]',
				esc_attr( $cpt['slug'] ),
				$tax_slug ? ' taxonomy="' . esc_attr( $tax_slug ) . '"' : ''
			);
			?>
			<div class="cptflm-scode-row">
				<strong><?php echo esc_html( $cpt['label'] ?? $cpt['slug'] ); ?></strong>
				<code class="cptflm-copy" data-copy="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $code ); ?></code>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
