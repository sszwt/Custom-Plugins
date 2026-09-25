<?php
/**
 * FAQ Set CPT.
 *
 * @package AIFAQ
 */

namespace AIFAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Post_Type
 */
class Post_Type {

	const SLUG = 'aifaq_set';

	/**
	 * Register CPT.
	 */
	public static function register() {
		register_post_type(
			self::SLUG,
			array(
				'labels'              => array(
					'name'          => __( 'FAQ Sets', 'ai-faq-generator-publisher' ),
					'singular_name' => __( 'FAQ Set', 'ai-faq-generator-publisher' ),
					'add_new_item'  => __( 'Add FAQ Set', 'ai-faq-generator-publisher' ),
					'edit_item'     => __( 'Edit FAQ Set', 'ai-faq-generator-publisher' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Get FAQ items for a set.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_items( $post_id ) {
		$items = get_post_meta( $post_id, '_aifaq_items', true );
		if ( ! is_array( $items ) ) {
			return array();
		}
		$clean = array();
		foreach ( $items as $item ) {
			$q = isset( $item['question'] ) ? trim( (string) $item['question'] ) : '';
			$a = isset( $item['answer'] ) ? trim( (string) $item['answer'] ) : '';
			if ( '' === $q && '' === $a ) {
				continue;
			}
			$clean[] = array(
				'question' => $q,
				'answer'   => $a,
			);
		}
		return $clean;
	}

	/**
	 * Save FAQ items.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $items   Items.
	 */
	public static function save_items( $post_id, $items ) {
		$clean = array();
		foreach ( (array) $items as $item ) {
			$q = sanitize_text_field( $item['question'] ?? '' );
			$a = wp_kses_post( $item['answer'] ?? '' );
			if ( '' === $q && '' === $a ) {
				continue;
			}
			$clean[] = array(
				'question' => $q,
				'answer'   => $a,
			);
		}
		update_post_meta( $post_id, '_aifaq_items', $clean );
	}

	/**
	 * List sets for library.
	 *
	 * @return array
	 */
	public static function list_sets() {
		$posts = get_posts(
			array(
				'post_type'      => self::SLUG,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			$items = self::get_items( $post->ID );
			$out[] = array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'status'     => $post->post_status,
				'count'      => count( $items ),
				'items'      => $items,
				'modified'   => get_the_modified_date( 'Y-m-d H:i', $post ),
				'shortcode'  => '[ai_faq id="' . $post->ID . '"]',
			);
		}
		return $out;
	}
}
