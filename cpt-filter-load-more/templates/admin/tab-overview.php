<?php
/**
 * Overview tab.
 *
 * @package CPTFLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="cptflm-panel">
	<div class="cptflm-stats">
		<div class="cptflm-stat">
			<span class="cptflm-stat__label"><?php esc_html_e( 'Active Post Types', 'cpt-filter-load-more' ); ?></span>
			<strong class="cptflm-stat__value"><?php echo esc_html( (string) $cpt_count ); ?></strong>
		</div>
		<div class="cptflm-stat">
			<span class="cptflm-stat__label"><?php esc_html_e( 'Active Taxonomies', 'cpt-filter-load-more' ); ?></span>
			<strong class="cptflm-stat__value"><?php echo esc_html( (string) $tax_count ); ?></strong>
		</div>
		<div class="cptflm-stat">
			<span class="cptflm-stat__label"><?php esc_html_e( 'Default per page', 'cpt-filter-load-more' ); ?></span>
			<strong class="cptflm-stat__value"><?php echo esc_html( (string) ( $settings['frontend']['per_page'] ?? 6 ) ); ?></strong>
		</div>
	</div>
</section>

<section class="cptflm-panel">
	<h2><?php esc_html_e( 'Quick start', 'cpt-filter-load-more' ); ?></h2>
	<p class="cptflm-panel__lead"><?php esc_html_e( 'Four steps from empty install to a filtered listing on the front end.', 'cpt-filter-load-more' ); ?></p>
	<ol class="cptflm-steps">
		<li><?php esc_html_e( 'Add or edit Custom Post Types under Post Types.', 'cpt-filter-load-more' ); ?></li>
		<li><?php esc_html_e( 'Create taxonomies and attach them to those post types.', 'cpt-filter-load-more' ); ?></li>
		<li><?php esc_html_e( 'Add sample posts with featured images & categories.', 'cpt-filter-load-more' ); ?></li>
		<li><?php esc_html_e( 'Paste a shortcode from the Shortcodes tab into any page.', 'cpt-filter-load-more' ); ?></li>
	</ol>
	<pre class="cptflm-code">[cpt_filter_load_more post_type="project" taxonomy="project_category" per_page="6" columns="3"]</pre>
</section>
