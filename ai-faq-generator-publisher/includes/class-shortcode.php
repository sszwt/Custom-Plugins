<?php
/**
 * Shortcode + render.
 *
 * @package AIFAQ
 */

namespace AIFAQ;

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
		add_shortcode( 'ai_faq', array( $this, 'render' ) );
		add_shortcode( 'aifaq', array( $this, 'render' ) );
	}

	/**
	 * Render accordion.
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'             => 0,
				'title'          => '',
				'allow_multiple' => '',
				'open_first'     => '',
				'schema'         => '',
			),
			$atts,
			'ai_faq'
		);

		$id = absint( $atts['id'] );
		if ( $id <= 0 ) {
			return '';
		}

		$post = get_post( $id );
		if ( ! $post || Post_Type::SLUG !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}

		$items = Post_Type::get_items( $id );
		if ( empty( $items ) ) {
			return '';
		}

		$settings = Plugin::get_settings();
		$acc      = $settings['accordion'] ?? array();

		$allow_multiple = '' !== $atts['allow_multiple']
			? (int) $atts['allow_multiple']
			: (int) ( $acc['allow_multiple'] ?? 0 );
		$open_first = '' !== $atts['open_first']
			? (int) $atts['open_first']
			: (int) ( $acc['open_first'] ?? 1 );
		$show_schema = '' !== $atts['schema']
			? (int) $atts['schema']
			: (int) ( $acc['show_schema'] ?? 1 );

		$heading = '' !== $atts['title'] ? $atts['title'] : $post->post_title;

		wp_enqueue_style( 'aifaq-front' );
		wp_enqueue_script( 'aifaq-front' );

		$uid = 'aifaq-' . $id . '-' . wp_unique_id();

		ob_start();
		?>
		<section
			class="aifaq"
			id="<?php echo esc_attr( $uid ); ?>"
			data-aifaq
			data-allow-multiple="<?php echo esc_attr( (string) $allow_multiple ); ?>"
			data-open-first="<?php echo esc_attr( (string) $open_first ); ?>"
			aria-label="<?php echo esc_attr( $heading ); ?>"
		>
			<?php if ( $heading ) : ?>
				<h2 class="aifaq__heading"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>

			<div class="aifaq__list">
				<?php foreach ( $items as $index => $item ) : ?>
					<?php
					$panel_id = $uid . '-panel-' . $index;
					$btn_id   = $uid . '-btn-' . $index;
					$is_open  = $open_first && 0 === $index;
					?>
					<div class="aifaq__item<?php echo $is_open ? ' is-open' : ''; ?>" data-aifaq-item>
						<button
							type="button"
							class="aifaq__trigger"
							id="<?php echo esc_attr( $btn_id ); ?>"
							aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
							aria-controls="<?php echo esc_attr( $panel_id ); ?>"
							data-aifaq-trigger
						>
							<span class="aifaq__q"><?php echo esc_html( $item['question'] ); ?></span>
							<span class="aifaq__icon" aria-hidden="true">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</span>
						</button>
						<div
							class="aifaq__panel"
							id="<?php echo esc_attr( $panel_id ); ?>"
							role="region"
							aria-labelledby="<?php echo esc_attr( $btn_id ); ?>"
							data-aifaq-panel
							<?php echo $is_open ? '' : 'hidden'; ?>
						>
							<div class="aifaq__panel-inner">
								<div class="aifaq__a"><?php echo wp_kses_post( wpautop( $item['answer'] ) ); ?></div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php

		if ( $show_schema ) {
			$entities = array();
			foreach ( $items as $item ) {
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => $item['question'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags( $item['answer'] ),
					),
				);
			}
			$schema = array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $entities,
			);
			echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
		}

		return (string) ob_get_clean();
	}
}
