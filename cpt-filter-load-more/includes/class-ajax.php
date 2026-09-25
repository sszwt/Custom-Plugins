<?php
/**
 * AJAX handlers.
 *
 * @package CPTFLM
 */

namespace CPTFLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ajax
 */
class Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cptflm_load_posts', array( $this, 'load_posts' ) );
		add_action( 'wp_ajax_nopriv_cptflm_load_posts', array( $this, 'load_posts' ) );
		add_action( 'wp_ajax_cptflm_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Frontend load / filter.
	 */
	public function load_posts() {
		check_ajax_referer( 'cptflm_front', 'nonce' );

		$post_type = sanitize_key( wp_unslash( $_POST['post_type'] ?? 'project' ) );

		if ( ! CPT_Registry::is_managed( $post_type ) || ! post_type_exists( $post_type ) ) {
			wp_send_json_success(
				array(
					'html'       => '',
					'found'      => 0,
					'max_pages'  => 0,
					'page'       => 1,
					'has_more'   => false,
				)
			);
		}

		$taxonomy = sanitize_key( wp_unslash( $_POST['taxonomy'] ?? '' ) );
		$allowed  = CPT_Registry::taxonomies_for( $post_type );
		if ( $taxonomy && ! isset( $allowed[ $taxonomy ] ) ) {
			$taxonomy = '';
		}

		$args = array(
			'post_type'    => $post_type,
			'taxonomy'     => $taxonomy,
			'term'         => sanitize_title( wp_unslash( $_POST['term'] ?? 'all' ) ),
			'page'         => absint( $_POST['page'] ?? 1 ),
			'per_page'     => absint( $_POST['per_page'] ?? 0 ),
			'orderby'      => sanitize_key( wp_unslash( $_POST['orderby'] ?? 'date' ) ),
			'order'        => sanitize_key( wp_unslash( $_POST['order'] ?? 'DESC' ) ),
			'show_image'   => ! empty( $_POST['show_image'] ),
			'show_excerpt' => ! empty( $_POST['show_excerpt'] ),
		);

		$result = Query::fetch( $args );
		wp_send_json_success( $result );
	}

	/**
	 * Save admin settings.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cpt-filter-load-more' ) ), 403 );
		}

		check_ajax_referer( 'cptflm_admin', 'nonce' );

		$raw = wp_unslash( $_POST['settings'] ?? '' );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
		} else {
			$decoded = $raw;
		}

		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings payload.', 'cpt-filter-load-more' ) ) );
		}

		$clean = $this->sanitize_settings( $decoded );
		Plugin::update_settings( $clean );
		flush_rewrite_rules( false );

		wp_send_json_success(
			array(
				'message'  => __( 'Settings saved. Permalinks refreshed.', 'cpt-filter-load-more' ),
				'settings' => $clean,
			)
		);
	}

	/**
	 * Sanitize full settings tree.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	private function sanitize_settings( $data ) {
		$defaults = Plugin::default_settings();
		$out      = $defaults;

		$out['post_types'] = array();
		foreach ( (array) ( $data['post_types'] ?? array() ) as $cpt ) {
			$slug = sanitize_key( $cpt['slug'] ?? '' );
			if ( ! $slug || in_array( $slug, array( 'post', 'page', 'attachment', 'revision' ), true ) ) {
				continue;
			}
			$supports = array_values(
				array_intersect(
					array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'comments', 'custom-fields' ),
					array_map( 'sanitize_key', (array) ( $cpt['supports'] ?? array( 'title', 'editor', 'thumbnail', 'excerpt' ) ) )
				)
			);
			if ( empty( $supports ) ) {
				$supports = array( 'title', 'editor', 'thumbnail', 'excerpt' );
			}
			$out['post_types'][] = array(
				'slug'         => $slug,
				'label'        => sanitize_text_field( $cpt['label'] ?? ucfirst( $slug ) ),
				'singular'     => sanitize_text_field( $cpt['singular'] ?? ucfirst( $slug ) ),
				'menu_icon'    => sanitize_text_field( $cpt['menu_icon'] ?? 'dashicons-admin-post' ),
				'public'       => empty( $cpt['public'] ) ? 0 : 1,
				'has_archive'  => empty( $cpt['has_archive'] ) ? 0 : 1,
				'show_in_rest' => empty( $cpt['show_in_rest'] ) ? 0 : 1,
				'supports'     => $supports,
				'enabled'      => empty( $cpt['enabled'] ) ? 0 : 1,
			);
		}

		$out['taxonomies'] = array();
		foreach ( (array) ( $data['taxonomies'] ?? array() ) as $tax ) {
			$slug = sanitize_key( $tax['slug'] ?? '' );
			if ( ! $slug ) {
				continue;
			}
			$pts = array_values( array_filter( array_map( 'sanitize_key', (array) ( $tax['post_types'] ?? array() ) ) ) );
			$out['taxonomies'][] = array(
				'slug'         => $slug,
				'label'        => sanitize_text_field( $tax['label'] ?? ucfirst( $slug ) ),
				'singular'     => sanitize_text_field( $tax['singular'] ?? ucfirst( $slug ) ),
				'post_types'   => $pts,
				'hierarchical' => empty( $tax['hierarchical'] ) ? 0 : 1,
				'show_in_rest' => empty( $tax['show_in_rest'] ) ? 0 : 1,
				'enabled'      => empty( $tax['enabled'] ) ? 0 : 1,
			);
		}

		$front = (array) ( $data['frontend'] ?? array() );
		$out['frontend'] = array(
			'per_page'     => max( 1, min( 48, (int) ( $front['per_page'] ?? 6 ) ) ),
			'columns'      => max( 1, min( 4, (int) ( $front['columns'] ?? 3 ) ) ),
			'button_text'  => sanitize_text_field( $front['button_text'] ?? 'Load More' ),
			'all_label'    => sanitize_text_field( $front['all_label'] ?? 'All' ),
			'show_excerpt' => empty( $front['show_excerpt'] ) ? 0 : 1,
			'show_image'   => empty( $front['show_image'] ) ? 0 : 1,
			'orderby'      => sanitize_key( $front['orderby'] ?? 'date' ),
			'order'        => in_array( strtoupper( $front['order'] ?? 'DESC' ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $front['order'] ) : 'DESC',
		);

		return $out;
	}
}
