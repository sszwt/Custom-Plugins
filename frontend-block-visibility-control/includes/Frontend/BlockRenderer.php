<?php
namespace FrontendBlockVisibility\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side block & ACF sub-field visibility.
 */
class BlockRenderer {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'render_block', array( $this, 'filter_render_block' ), 10, 2 );
		add_filter( 'acf/format_value', array( $this, 'filter_acf_subfield' ), 10, 3 );
	}

	/**
	 * Filter rendered block HTML.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block Block data array.
	 * @return string
	 */
	public function filter_render_block( $block_content, $block ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() ) {
			return $block_content;
		}

		$settings = get_option( 'fbv_control_settings', array() );

		$globally_hidden = isset( $settings['globally_hidden_blocks'] ) ? (array) $settings['globally_hidden_blocks'] : array();
		if ( ! empty( $block['blockName'] ) && in_array( $block['blockName'], $globally_hidden, true ) ) {
			return '';
		}

		if ( empty( $block['attrs'] ) || ! isset( $block['attrs']['blockVisibility'] ) ) {
			return $block_content;
		}

		$attrs = (array) $block['attrs']['blockVisibility'];

		if ( ! empty( $attrs['hidden'] ) ) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Suppress ACF field values marked hidden on the frontend.
	 *
	 * @param mixed $value Field value.
	 * @param int   $post_id Post ID.
	 * @param array $field ACF field array.
	 * @return mixed
	 */
	public function filter_acf_subfield( $value, $post_id, $field ) {
		if ( is_admin() ) {
			return $value;
		}

		if ( empty( $field['name'] ) ) {
			return $value;
		}

		global $post;
		if ( ! $post || empty( $post->post_content ) ) {
			return $value;
		}

		if ( false === strpos( $post->post_content, '"' . $field['name'] . '"' ) || false === strpos( $post->post_content, 'hiddenFields' ) ) {
			return $value;
		}

		$blocks = parse_blocks( $post->post_content );
		foreach ( $blocks as $b ) {
			if ( empty( $b['attrs']['blockVisibility']['hiddenFields'] ) ) {
				continue;
			}
			$hidden_list = (array) $b['attrs']['blockVisibility']['hiddenFields'];
			if ( in_array( $field['name'], $hidden_list, true ) ) {
				return null;
			}
		}

		return $value;
	}
}
