<?php
/**
 * Display settings tab.
 *
 * @package CPTFLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$f = $settings['frontend'] ?? array();
?>
<section class="cptflm-panel">
	<h2><?php esc_html_e( 'Frontend listing defaults', 'cpt-filter-load-more' ); ?></h2>
	<p class="cptflm-panel__lead"><?php esc_html_e( 'These values apply when a shortcode attribute is omitted.', 'cpt-filter-load-more' ); ?></p>
	<div class="cptflm-form-grid" id="cptflm-display-form">
		<label>
			<span><?php esc_html_e( 'Posts per page', 'cpt-filter-load-more' ); ?></span>
			<input type="number" min="1" max="48" data-front="per_page" value="<?php echo esc_attr( $f['per_page'] ?? 6 ); ?>" />
		</label>
		<label>
			<span><?php esc_html_e( 'Columns', 'cpt-filter-load-more' ); ?></span>
			<select data-front="columns">
				<?php foreach ( array( 1, 2, 3, 4 ) as $col ) : ?>
					<option value="<?php echo esc_attr( $col ); ?>" <?php selected( (int) ( $f['columns'] ?? 3 ), $col ); ?>><?php echo esc_html( $col ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Load more button text', 'cpt-filter-load-more' ); ?></span>
			<input type="text" data-front="button_text" value="<?php echo esc_attr( $f['button_text'] ?? 'Load More' ); ?>" />
		</label>
		<label>
			<span><?php esc_html_e( '“All” filter label', 'cpt-filter-load-more' ); ?></span>
			<input type="text" data-front="all_label" value="<?php echo esc_attr( $f['all_label'] ?? 'All' ); ?>" />
		</label>
		<label>
			<span><?php esc_html_e( 'Order by', 'cpt-filter-load-more' ); ?></span>
			<select data-front="orderby">
				<?php
				$orders = array(
					'date'       => __( 'Date', 'cpt-filter-load-more' ),
					'title'      => __( 'Title', 'cpt-filter-load-more' ),
					'menu_order' => __( 'Menu order', 'cpt-filter-load-more' ),
					'rand'       => __( 'Random', 'cpt-filter-load-more' ),
				);
				foreach ( $orders as $val => $lab ) :
					?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f['orderby'] ?? 'date', $val ); ?>><?php echo esc_html( $lab ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Order', 'cpt-filter-load-more' ); ?></span>
			<select data-front="order">
				<option value="DESC" <?php selected( $f['order'] ?? 'DESC', 'DESC' ); ?>><?php esc_html_e( 'Descending', 'cpt-filter-load-more' ); ?></option>
				<option value="ASC" <?php selected( $f['order'] ?? 'DESC', 'ASC' ); ?>><?php esc_html_e( 'Ascending', 'cpt-filter-load-more' ); ?></option>
			</select>
		</label>
		<label class="cptflm-check">
			<input type="checkbox" data-front="show_image" value="1" <?php checked( ! empty( $f['show_image'] ) ); ?> />
			<span class="cptflm-switch" aria-hidden="true"></span>
			<span class="cptflm-check__label"><?php esc_html_e( 'Show featured image', 'cpt-filter-load-more' ); ?></span>
		</label>
		<label class="cptflm-check">
			<input type="checkbox" data-front="show_excerpt" value="1" <?php checked( ! empty( $f['show_excerpt'] ) ); ?> />
			<span class="cptflm-switch" aria-hidden="true"></span>
			<span class="cptflm-check__label"><?php esc_html_e( 'Show excerpt', 'cpt-filter-load-more' ); ?></span>
		</label>
	</div>
</section>
