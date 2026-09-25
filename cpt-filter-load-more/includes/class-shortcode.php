<?php
/**
 * Shortcode renderer.
 *
 * @package CPTFLM
 */

namespace CPTFLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcode
 */
class Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'cpt_filter_load_more', array( $this, 'render' ) );
		add_shortcode( 'cptflm', array( $this, 'render' ) );
	}

	/**
	 * Render listing.
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public function render( $atts ) {
		$settings = Plugin::get_settings();
		$front    = $settings['frontend'] ?? array();

		$atts = shortcode_atts(
			array(
				'post_type'    => 'project',
				'taxonomy'     => 'project_category',
				'per_page'     => (int) ( $front['per_page'] ?? 6 ),
				'columns'      => (int) ( $front['columns'] ?? 3 ),
				'button_text'  => $front['button_text'] ?? 'Load More',
				'all_label'    => $front['all_label'] ?? 'All',
				'show_image'   => ! empty( $front['show_image'] ) ? '1' : '0',
				'show_excerpt' => ! empty( $front['show_excerpt'] ) ? '1' : '0',
				'orderby'      => $front['orderby'] ?? 'date',
				'order'        => $front['order'] ?? 'DESC',
			),
			$atts,
			'cpt_filter_load_more'
		);

		$post_type = sanitize_key( $atts['post_type'] );

		// If CPT was removed/disabled in the plugin, do not render leftover shortcode output.
		if ( ! CPT_Registry::is_managed( $post_type ) || ! post_type_exists( $post_type ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="cptflm-missing" style="padding:12px 14px;border:1px dashed #cfd3da;border-radius:10px;color:#6b7385;font-size:13px;">'
					. esc_html__( 'CPT Filter: this post type is no longer registered in the plugin. Remove or update the shortcode.', 'cpt-filter-load-more' )
					. '</div>';
			}
			return '';
		}

		Assets::enqueue_front();

		$taxonomy  = sanitize_key( $atts['taxonomy'] );
		$per_page  = max( 1, (int) $atts['per_page'] );
		$columns   = max( 1, min( 4, (int) $atts['columns'] ) );

		// Taxonomy must still be attached to this managed CPT (or empty = no filters).
		$allowed_tax = CPT_Registry::taxonomies_for( $post_type );
		if ( $taxonomy && ! isset( $allowed_tax[ $taxonomy ] ) ) {
			$taxonomy = '';
		}

		$terms = array();
		if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);
			if ( is_wp_error( $terms ) ) {
				$terms = array();
			}
		}

		$result = Query::fetch(
			array(
				'post_type'    => $post_type,
				'taxonomy'     => $taxonomy,
				'term'         => 'all',
				'page'         => 1,
				'per_page'     => $per_page,
				'orderby'      => $atts['orderby'],
				'order'        => $atts['order'],
				'show_image'   => $atts['show_image'],
				'show_excerpt' => $atts['show_excerpt'],
			)
		);

		$uid = 'cptflm-' . wp_unique_id();

		ob_start();
		include CPTFLM_PLUGIN_DIR . 'templates/frontend/listing.php';
		return (string) ob_get_clean();
	}
}
