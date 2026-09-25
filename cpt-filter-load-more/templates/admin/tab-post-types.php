<?php
/**
 * Post types tab.
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
			<h2><?php esc_html_e( 'Custom Post Types', 'cpt-filter-load-more' ); ?></h2>
			<p class="cptflm-panel__lead" style="margin:0"><?php esc_html_e( 'Each card registers one CPT in WordPress. Toggle supports, then Save Changes.', 'cpt-filter-load-more' ); ?></p>
		</div>
		<button type="button" class="cptflm-btn cptflm-btn--soft" id="cptflm-add-cpt"><?php esc_html_e( 'Add Post Type', 'cpt-filter-load-more' ); ?></button>
	</div>
	<div id="cptflm-cpt-list" class="cptflm-list" data-kind="post_types"></div>
</section>
