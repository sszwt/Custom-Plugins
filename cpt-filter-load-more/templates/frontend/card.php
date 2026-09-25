<?php
/**
 * Single card.
 *
 * @var array $card
 * @var bool  $show_img
 * @var bool  $show_ex
 *
 * @package CPTFLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="cptflm-card">
	<a class="cptflm-card__link" href="<?php echo esc_url( $card['permalink'] ); ?>">
		<?php if ( $show_img ) : ?>
			<figure class="cptflm-card__media">
				<?php if ( ! empty( $card['image'] ) ) : ?>
					<img src="<?php echo esc_url( $card['image'] ); ?>" alt="<?php echo esc_attr( $card['title'] ); ?>" loading="lazy" decoding="async" />
				<?php else : ?>
					<span class="cptflm-card__placeholder" aria-hidden="true"></span>
				<?php endif; ?>
			</figure>
		<?php endif; ?>
		<div class="cptflm-card__body">
			<h3 class="cptflm-card__title"><?php echo esc_html( $card['title'] ); ?></h3>
			<?php if ( $show_ex && ! empty( $card['excerpt'] ) ) : ?>
				<p class="cptflm-card__excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p>
			<?php endif; ?>
			<span class="cptflm-card__cta"><?php esc_html_e( 'View', 'cpt-filter-load-more' ); ?></span>
		</div>
	</a>
</article>
