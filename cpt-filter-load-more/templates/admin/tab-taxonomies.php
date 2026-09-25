<?php
/**
 * Taxonomies tab.
 *
 * @package CPTFLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="cptflm-panel">
	<div class="cptflm-panel__head">
		<div>
			<h2><?php esc_html_e( 'Taxonomies (Filters)', 'cpt-filter-load-more' ); ?></h2>
			<p class="cptflm-panel__lead" style="margin:0"><?php esc_html_e( 'These become the filter chips on the frontend listing.', 'cpt-filter-load-more' ); ?></p>
		</div>
		<button type="button" class="cptflm-btn cptflm-btn--soft" id="cptflm-add-tax"><?php esc_html_e( 'Add Taxonomy', 'cpt-filter-load-more' ); ?></button>
	</div>
	<div id="cptflm-tax-list" class="cptflm-list" data-kind="taxonomies"></div>
</section>
