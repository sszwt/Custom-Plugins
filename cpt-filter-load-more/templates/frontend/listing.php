<?php
/**
 * Frontend listing wrapper.
 *
 * @var string $uid
 * @var array  $atts
 * @var array  $terms
 * @var array  $result
 * @var string $post_type
 * @var string $taxonomy
 * @var int    $per_page
 * @var int    $columns
 *
 * @package CPTFLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	id="<?php echo esc_attr( $uid ); ?>"
	class="cptflm"
	data-cptflm
	data-post-type="<?php echo esc_attr( $post_type ); ?>"
	data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
	data-per-page="<?php echo esc_attr( $per_page ); ?>"
	data-columns="<?php echo esc_attr( $columns ); ?>"
	data-orderby="<?php echo esc_attr( $atts['orderby'] ); ?>"
	data-order="<?php echo esc_attr( $atts['order'] ); ?>"
	data-show-image="<?php echo esc_attr( $atts['show_image'] ); ?>"
	data-show-excerpt="<?php echo esc_attr( $atts['show_excerpt'] ); ?>"
	data-page="1"
	data-max="<?php echo esc_attr( (string) $result['max_pages'] ); ?>"
>
	<?php if ( ! empty( $terms ) ) : ?>
		<div class="cptflm__filters" role="tablist" aria-label="<?php esc_attr_e( 'Filters', 'cpt-filter-load-more' ); ?>">
			<button type="button" class="cptflm__chip is-active" data-term="all"><?php echo esc_html( $atts['all_label'] ); ?></button>
			<?php foreach ( $terms as $term ) : ?>
				<button type="button" class="cptflm__chip" data-term="<?php echo esc_attr( $term->slug ); ?>">
					<?php echo esc_html( $term->name ); ?>
					<span class="cptflm__count"><?php echo esc_html( (string) $term->count ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="cptflm__grid cptflm__grid--cols-<?php echo esc_attr( $columns ); ?>" data-cptflm-grid>
		<?php echo $result['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped template ?>
	</div>

	<div class="cptflm__empty" data-cptflm-empty <?php echo empty( $result['items'] ) ? '' : 'hidden'; ?>>
		<?php esc_html_e( 'No items found.', 'cpt-filter-load-more' ); ?>
	</div>

	<div class="cptflm__footer">
		<button
			type="button"
			class="cptflm__more"
			data-cptflm-more
			<?php echo ! empty( $result['has_more'] ) ? '' : 'hidden'; ?>
		>
			<?php echo esc_html( $atts['button_text'] ); ?>
		</button>
	</div>
</div>
