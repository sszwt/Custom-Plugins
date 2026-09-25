<?php
/**
 * Query helper for listings.
 *
 * @package CPTFLM
 */

namespace CPTFLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Query
 */
class Query {

	/**
	 * Build WP_Query args from request.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public static function build_args( $args ) {
		$settings = Plugin::get_settings();
		$front    = $settings['frontend'] ?? array();

		$post_type = sanitize_key( $args['post_type'] ?? 'project' );
		$taxonomy  = sanitize_key( $args['taxonomy'] ?? '' );
		$term      = sanitize_title( $args['term'] ?? '' );
		$page      = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page  = max( 1, min( 48, (int) ( $args['per_page'] ?? ( $front['per_page'] ?? 6 ) ) ) );
		$orderby   = sanitize_key( $args['orderby'] ?? ( $front['orderby'] ?? 'date' ) );
		$order     = strtoupper( sanitize_key( $args['order'] ?? ( $front['order'] ?? 'DESC' ) ) );
		$order     = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

		$query = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
			'no_found_rows'  => false,
		);

		if ( $taxonomy && $term && 'all' !== $term ) {
			$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $term,
				),
			);
		}

		return $query;
	}

	/**
	 * Run query and return payload.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public static function fetch( $args ) {
		$query_args = self::build_args( $args );
		$q          = new \WP_Query( $query_args );
		$cards      = array();

		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$cards[] = self::card_data( get_the_ID() );
			}
			wp_reset_postdata();
		}

		$page     = (int) $query_args['paged'];
		$max      = (int) $q->max_num_pages;
		$per_page = (int) $query_args['posts_per_page'];

		return array(
			'items'      => $cards,
			'html'       => self::render_cards_html( $cards, $args ),
			'page'       => $page,
			'max_pages'  => $max,
			'found'      => (int) $q->found_posts,
			'has_more'   => $page < $max,
			'per_page'   => $per_page,
		);
	}

	/**
	 * Card data array.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function card_data( $post_id ) {
		$thumb = get_the_post_thumbnail_url( $post_id, 'medium_large' );
		return array(
			'id'        => $post_id,
			'title'     => get_the_title( $post_id ),
			'permalink' => get_permalink( $post_id ),
			'excerpt'   => wp_trim_words( get_the_excerpt( $post_id ), 22 ),
			'image'     => $thumb ? $thumb : '',
			'date'      => get_the_date( '', $post_id ),
		);
	}

	/**
	 * Render cards HTML.
	 *
	 * @param array $cards Cards.
	 * @param array $args  Args.
	 * @return string
	 */
	public static function render_cards_html( $cards, $args = array() ) {
		$settings = Plugin::get_settings();
		$front    = $settings['frontend'] ?? array();
		$show_img = ! isset( $args['show_image'] ) ? ! empty( $front['show_image'] ) : ! empty( $args['show_image'] );
		$show_ex  = ! isset( $args['show_excerpt'] ) ? ! empty( $front['show_excerpt'] ) : ! empty( $args['show_excerpt'] );

		ob_start();
		foreach ( $cards as $card ) {
			include CPTFLM_PLUGIN_DIR . 'templates/frontend/card.php';
		}
		return (string) ob_get_clean();
	}
}
