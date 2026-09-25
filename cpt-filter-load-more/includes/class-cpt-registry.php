<?php
/**
 * Register CPTs and taxonomies from settings.
 *
 * @package CPTFLM
 */

namespace CPTFLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CPT_Registry
 */
class CPT_Registry {

	/**
	 * Register all enabled types.
	 */
	public static function register_all() {
		$settings = Plugin::get_settings();

		foreach ( (array) ( $settings['post_types'] ?? array() ) as $cpt ) {
			if ( empty( $cpt['enabled'] ) || empty( $cpt['slug'] ) ) {
				continue;
			}
			self::register_post_type( $cpt );
		}

		foreach ( (array) ( $settings['taxonomies'] ?? array() ) as $tax ) {
			if ( empty( $tax['enabled'] ) || empty( $tax['slug'] ) ) {
				continue;
			}
			self::register_taxonomy( $tax );
		}
	}

	/**
	 * Register one CPT.
	 *
	 * @param array $cpt Config.
	 */
	public static function register_post_type( $cpt ) {
		$slug     = sanitize_key( $cpt['slug'] );
		$label    = sanitize_text_field( $cpt['label'] ?? ucfirst( $slug ) );
		$singular = sanitize_text_field( $cpt['singular'] ?? $label );
		$supports = ! empty( $cpt['supports'] ) && is_array( $cpt['supports'] )
			? array_map( 'sanitize_key', $cpt['supports'] )
			: array( 'title', 'editor', 'thumbnail', 'excerpt' );

		$labels = array(
			'name'          => $label,
			'singular_name' => $singular,
			'add_new_item'  => sprintf( /* translators: %s: singular */ __( 'Add New %s', 'cpt-filter-load-more' ), $singular ),
			'edit_item'     => sprintf( /* translators: %s: singular */ __( 'Edit %s', 'cpt-filter-load-more' ), $singular ),
			'view_item'     => sprintf( /* translators: %s: singular */ __( 'View %s', 'cpt-filter-load-more' ), $singular ),
			'search_items'  => sprintf( /* translators: %s: label */ __( 'Search %s', 'cpt-filter-load-more' ), $label ),
			'not_found'     => __( 'Nothing found.', 'cpt-filter-load-more' ),
			'menu_name'     => $label,
		);

		register_post_type(
			$slug,
			array(
				'labels'       => $labels,
				'public'       => ! empty( $cpt['public'] ),
				'has_archive'  => ! empty( $cpt['has_archive'] ),
				'show_in_rest' => ! empty( $cpt['show_in_rest'] ),
				'menu_icon'    => sanitize_text_field( $cpt['menu_icon'] ?? 'dashicons-admin-post' ),
				'supports'     => $supports,
				'rewrite'      => array( 'slug' => $slug ),
				'show_ui'      => true,
				'show_in_menu' => true,
			)
		);
	}

	/**
	 * Register one taxonomy.
	 *
	 * @param array $tax Config.
	 */
	public static function register_taxonomy( $tax ) {
		$slug       = sanitize_key( $tax['slug'] );
		$label      = sanitize_text_field( $tax['label'] ?? ucfirst( $slug ) );
		$singular   = sanitize_text_field( $tax['singular'] ?? $label );
		$post_types = array_map( 'sanitize_key', (array) ( $tax['post_types'] ?? array() ) );

		if ( empty( $post_types ) ) {
			return;
		}

		$labels = array(
			'name'          => $label,
			'singular_name' => $singular,
			'search_items'  => sprintf( /* translators: %s: label */ __( 'Search %s', 'cpt-filter-load-more' ), $label ),
			'all_items'     => sprintf( /* translators: %s: label */ __( 'All %s', 'cpt-filter-load-more' ), $label ),
			'edit_item'     => sprintf( /* translators: %s: singular */ __( 'Edit %s', 'cpt-filter-load-more' ), $singular ),
			'update_item'   => sprintf( /* translators: %s: singular */ __( 'Update %s', 'cpt-filter-load-more' ), $singular ),
			'add_new_item'  => sprintf( /* translators: %s: singular */ __( 'Add New %s', 'cpt-filter-load-more' ), $singular ),
			'menu_name'     => $label,
		);

		register_taxonomy(
			$slug,
			$post_types,
			array(
				'labels'            => $labels,
				'hierarchical'      => ! empty( $tax['hierarchical'] ),
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => ! empty( $tax['show_in_rest'] ),
				'rewrite'           => array( 'slug' => $slug ),
			)
		);
	}

	/**
	 * Enabled CPT slugs for selects.
	 *
	 * @return array slug => label
	 */
	public static function enabled_post_types() {
		$out = array();
		foreach ( (array) ( Plugin::get_settings()['post_types'] ?? array() ) as $cpt ) {
			if ( ! empty( $cpt['enabled'] ) && ! empty( $cpt['slug'] ) ) {
				$out[ $cpt['slug'] ] = $cpt['label'] ?? $cpt['slug'];
			}
		}
		return $out;
	}

	/**
	 * Whether slug is an enabled CPT managed by this plugin.
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function is_managed( $slug ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return false;
		}
		$enabled = self::enabled_post_types();
		return isset( $enabled[ $slug ] );
	}

	/**
	 * Enabled taxonomies for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function taxonomies_for( $post_type ) {
		$out = array();
		foreach ( (array) ( Plugin::get_settings()['taxonomies'] ?? array() ) as $tax ) {
			if ( empty( $tax['enabled'] ) || empty( $tax['slug'] ) ) {
				continue;
			}
			$pts = (array) ( $tax['post_types'] ?? array() );
			if ( in_array( $post_type, $pts, true ) ) {
				$out[ $tax['slug'] ] = $tax['label'] ?? $tax['slug'];
			}
		}
		return $out;
	}
}
