<?php
/**
 * Admin dashboard.
 *
 * @package AIFAQ
 *
 * @var array  $settings
 * @var array  $sets
 * @var string $view
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$acc         = $settings['accordion'] ?? array();
$has_openai  = ! empty( $settings['openai_key'] );
$has_gemini  = ! empty( $settings['gemini_key'] );
$provider    = $settings['provider'] ?? 'openai';
$set_count   = count( $sets );
$pub_count   = 0;
foreach ( $sets as $s ) {
	if ( 'publish' === ( $s['status'] ?? '' ) ) {
		++$pub_count;
	}
}
$key_ready = ( 'gemini' === $provider && $has_gemini ) || ( 'openai' === $provider && $has_openai );
?>
<div class="aifaq-wrap wrap">
	<div class="aifaq-shell" data-aifaq-app data-view="<?php echo esc_attr( $view ); ?>">

		<aside class="aifaq-rail" aria-label="<?php esc_attr_e( 'AI FAQ navigation', 'ai-faq-generator-publisher' ); ?>">
			<div class="aifaq-brand">
				<span class="aifaq-brand__mark" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none">
						<path d="M12 3c-2.2 3.4-4 5.6-4 8.2A4 4 0 0 0 12 15.5a4 4 0 0 0 4-4.3C16 8.6 14.2 6.4 12 3Z" stroke="currentColor" stroke-width="1.8"/>
						<path d="M9.5 17.5h5M10.5 20h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
					</svg>
				</span>
				<div class="aifaq-brand__text">
					<strong>AI FAQ</strong>
					<span>Generator &amp; Publisher</span>
				</div>
			</div>

			<p class="aifaq-rail__label"><?php esc_html_e( 'Workspace', 'ai-faq-generator-publisher' ); ?></p>

			<nav class="aifaq-nav">
				<button type="button" class="aifaq-nav__btn is-active" data-nav="generate">
					<span class="aifaq-nav__ico" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.7"/></svg>
					</span>
					<span class="aifaq-nav__txt">
						<em><?php esc_html_e( 'Generate', 'ai-faq-generator-publisher' ); ?></em>
						<small><?php esc_html_e( 'Create with AI', 'ai-faq-generator-publisher' ); ?></small>
					</span>
				</button>
				<button type="button" class="aifaq-nav__btn" data-nav="library">
					<span class="aifaq-nav__ico" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14.5A2.5 2.5 0 0 1 17.5 21H6.5A2.5 2.5 0 0 1 4 18.5v-12Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
					</span>
					<span class="aifaq-nav__txt">
						<em><?php esc_html_e( 'Library', 'ai-faq-generator-publisher' ); ?></em>
						<small><?php esc_html_e( 'Sets & shortcodes', 'ai-faq-generator-publisher' ); ?></small>
					</span>
					<span class="aifaq-nav__badge" data-lib-count><?php echo esc_html( (string) $set_count ); ?></span>
				</button>
				<button type="button" class="aifaq-nav__btn" data-nav="settings">
					<span class="aifaq-nav__ico" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.6.9 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" stroke="currentColor" stroke-width="1.2"/></svg>
					</span>
					<span class="aifaq-nav__txt">
						<em><?php esc_html_e( 'Settings', 'ai-faq-generator-publisher' ); ?></em>
						<small><?php esc_html_e( 'API & accordion', 'ai-faq-generator-publisher' ); ?></small>
					</span>
				</button>
			</nav>

			<div class="aifaq-rail__card">
				<span class="aifaq-rail__card-label"><?php esc_html_e( 'Active provider', 'ai-faq-generator-publisher' ); ?></span>
				<strong><?php echo esc_html( strtoupper( (string) $provider ) ); ?></strong>
				<span class="aifaq-rail__status <?php echo $key_ready ? 'is-ready' : 'is-warn'; ?>">
					<?php echo $key_ready ? esc_html__( 'API key ready', 'ai-faq-generator-publisher' ) : esc_html__( 'Add API key', 'ai-faq-generator-publisher' ); ?>
				</span>
			</div>

			<div class="aifaq-rail__foot">
				<span class="aifaq-pill">Custom Plugin</span>
				<span class="aifaq-muted">v<?php echo esc_html( AIFAQ_VERSION ); ?></span>
			</div>
		</aside>

		<main class="aifaq-main">
			<div class="aifaq-toast aifaq-toast--float" data-toast-float hidden role="status" aria-live="polite"></div>
			<header class="aifaq-top">
				<div class="aifaq-top__copy">
					<p class="aifaq-eyebrow"><?php esc_html_e( 'Custom Plugin', 'ai-faq-generator-publisher' ); ?></p>
					<h1 data-page-title><?php esc_html_e( 'Generate FAQs', 'ai-faq-generator-publisher' ); ?></h1>
					<p class="aifaq-lead" data-page-lead><?php esc_html_e( 'Describe a topic, generate Q&As with AI, edit, then publish with a slide accordion shortcode.', 'ai-faq-generator-publisher' ); ?></p>
				</div>
				<div class="aifaq-top__aside">
					<div class="aifaq-stats" aria-hidden="true">
						<div class="aifaq-stat">
							<span><?php echo esc_html( (string) $set_count ); ?></span>
							<small><?php esc_html_e( 'Sets', 'ai-faq-generator-publisher' ); ?></small>
						</div>
						<div class="aifaq-stat">
							<span><?php echo esc_html( (string) $pub_count ); ?></span>
							<small><?php esc_html_e( 'Live', 'ai-faq-generator-publisher' ); ?></small>
						</div>
					</div>
				</div>
			</header>

			<div class="aifaq-steps" data-steps>
				<div class="aifaq-step is-on"><span>1</span><?php esc_html_e( 'Prompt', 'ai-faq-generator-publisher' ); ?></div>
				<div class="aifaq-step"><span>2</span><?php esc_html_e( 'Edit', 'ai-faq-generator-publisher' ); ?></div>
				<div class="aifaq-step"><span>3</span><?php esc_html_e( 'Publish', 'ai-faq-generator-publisher' ); ?></div>
			</div>

			<!-- GENERATE -->
			<section class="aifaq-panel is-active" data-panel="generate">
				<div class="aifaq-grid aifaq-grid--split">
					<div class="aifaq-card aifaq-card--prompt">
						<div class="aifaq-card__head">
							<div>
								<span class="aifaq-step-tag">01</span>
								<h2><?php esc_html_e( 'Prompt', 'ai-faq-generator-publisher' ); ?></h2>
							</div>
							<span class="aifaq-chip"><?php esc_html_e( 'AI', 'ai-faq-generator-publisher' ); ?></span>
						</div>
						<p class="aifaq-card__hint"><?php esc_html_e( 'Give clear context — page topic, audience, and what visitors usually ask.', 'ai-faq-generator-publisher' ); ?></p>

						<label class="aifaq-field">
							<span><?php esc_html_e( 'Topic / page context', 'ai-faq-generator-publisher' ); ?></span>
							<textarea data-field="topic" rows="5" placeholder="<?php esc_attr_e( 'e.g. Growth Dimensions service — digital marketing for clinics in India…', 'ai-faq-generator-publisher' ); ?>"></textarea>
						</label>

						<div class="aifaq-row">
							<label class="aifaq-field">
								<span><?php esc_html_e( 'Number of FAQs', 'ai-faq-generator-publisher' ); ?></span>
								<input type="number" min="3" max="20" data-field="count" value="<?php echo esc_attr( (string) ( $settings['default_count'] ?? 8 ) ); ?>" />
							</label>
							<label class="aifaq-field">
								<span><?php esc_html_e( 'Tone', 'ai-faq-generator-publisher' ); ?></span>
								<input type="text" data-field="tone" value="<?php echo esc_attr( $settings['default_tone'] ?? 'clear and helpful' ); ?>" />
							</label>
						</div>

						<label class="aifaq-field">
							<span><?php esc_html_e( 'Extra instructions (optional)', 'ai-faq-generator-publisher' ); ?></span>
							<textarea data-field="extra" rows="2" placeholder="<?php esc_attr_e( 'Avoid medical claims. Mention booking a consult.', 'ai-faq-generator-publisher' ); ?>"></textarea>
						</label>

						<div class="aifaq-actions">
							<button type="button" class="aifaq-btn aifaq-btn--primary aifaq-btn--lg" data-action="generate">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l1.2 4.8L18 9l-4.8 1.2L12 15l-1.2-4.8L6 9l4.8-1.2L12 3zM18.5 15l.6 2.4L21.5 18l-2.4.6-.6 2.4-.6-2.4L15.5 18l2.4-.6.6-2.4z" fill="currentColor"/></svg>
								<span class="aifaq-btn__label"><?php esc_html_e( 'Generate with AI', 'ai-faq-generator-publisher' ); ?></span>
								<span class="aifaq-spinner" aria-hidden="true"></span>
							</button>
						</div>
					</div>

					<div class="aifaq-card aifaq-card--editor">
						<div class="aifaq-card__head">
							<div>
								<span class="aifaq-step-tag">02</span>
								<h2><?php esc_html_e( 'Editor', 'ai-faq-generator-publisher' ); ?></h2>
							</div>
							<span class="aifaq-chip aifaq-chip--ghost"><span data-item-count>0</span> <?php esc_html_e( 'items', 'ai-faq-generator-publisher' ); ?></span>
						</div>

						<label class="aifaq-field">
							<span><?php esc_html_e( 'FAQ set title', 'ai-faq-generator-publisher' ); ?></span>
							<input type="text" data-field="title" placeholder="<?php esc_attr_e( 'Frequently Asked Questions', 'ai-faq-generator-publisher' ); ?>" />
						</label>

						<input type="hidden" data-field="set-id" value="0" />

						<div class="aifaq-editor" data-editor>
							<div class="aifaq-empty" data-editor-empty>
								<div class="aifaq-empty__icon" aria-hidden="true">
									<svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><rect x="4" y="4" width="16" height="16" rx="3" stroke="currentColor" stroke-width="1.7"/></svg>
								</div>
								<p><?php esc_html_e( 'Generated questions appear here. You can edit each answer, then publish.', 'ai-faq-generator-publisher' ); ?></p>
							</div>
						</div>

						<div class="aifaq-actions aifaq-actions--spread">
							<button type="button" class="aifaq-btn aifaq-btn--ghost" data-action="add-item"><?php esc_html_e( '+ Add question', 'ai-faq-generator-publisher' ); ?></button>
							<div class="aifaq-actions__right">
								<button type="button" class="aifaq-btn aifaq-btn--ghost" data-action="save-draft"><?php esc_html_e( 'Save draft', 'ai-faq-generator-publisher' ); ?></button>
								<button type="button" class="aifaq-btn aifaq-btn--primary" data-action="publish"><?php esc_html_e( 'Publish', 'ai-faq-generator-publisher' ); ?></button>
							</div>
						</div>

						<div class="aifaq-toast aifaq-toast--local" data-toast hidden role="status" aria-live="polite"></div>

						<div class="aifaq-shortcode-box" data-shortcode-box hidden>
							<label><?php esc_html_e( 'Shortcode ready', 'ai-faq-generator-publisher' ); ?></label>
							<div class="aifaq-shortcode-row">
								<code data-shortcode></code>
								<button type="button" class="aifaq-btn aifaq-btn--sm aifaq-btn--dark" data-action="copy-shortcode"><?php esc_html_e( 'Copy', 'ai-faq-generator-publisher' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			</section>

			<!-- LIBRARY -->
			<section class="aifaq-panel" data-panel="library">
				<div class="aifaq-card aifaq-card--flush">
					<div class="aifaq-card__head aifaq-card__head--pad">
						<div>
							<span class="aifaq-step-tag"><?php esc_html_e( 'Lib', 'ai-faq-generator-publisher' ); ?></span>
							<h2><?php esc_html_e( 'Published & drafts', 'ai-faq-generator-publisher' ); ?></h2>
						</div>
					</div>
					<div class="aifaq-library" data-library>
						<?php if ( empty( $sets ) ) : ?>
							<div class="aifaq-empty aifaq-empty--pad">
								<div class="aifaq-empty__icon" aria-hidden="true">
									<svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14.5A2.5 2.5 0 0 1 17.5 21H6.5A2.5 2.5 0 0 1 4 18.5v-12Z" stroke="currentColor" stroke-width="1.7"/></svg>
								</div>
								<p><?php esc_html_e( 'No FAQ sets yet. Generate your first one.', 'ai-faq-generator-publisher' ); ?></p>
							</div>
						<?php else : ?>
							<?php foreach ( $sets as $set ) : ?>
								<article class="aifaq-lib-card" data-set-id="<?php echo esc_attr( (string) $set['id'] ); ?>">
									<div class="aifaq-lib-card__main">
										<div class="aifaq-lib-card__title-row">
											<h3><?php echo esc_html( $set['title'] ); ?></h3>
											<span class="aifaq-status aifaq-status--<?php echo esc_attr( $set['status'] ); ?>"><?php echo esc_html( $set['status'] ); ?></span>
										</div>
										<p>
											<span><?php echo esc_html( sprintf( /* translators: %d count */ _n( '%d question', '%d questions', $set['count'], 'ai-faq-generator-publisher' ), $set['count'] ) ); ?></span>
											<span class="aifaq-dot" aria-hidden="true"></span>
											<span class="aifaq-muted"><?php echo esc_html( $set['modified'] ); ?></span>
										</p>
										<code><?php echo esc_html( $set['shortcode'] ); ?></code>
									</div>
									<div class="aifaq-lib-card__actions">
										<button type="button" class="aifaq-btn aifaq-btn--sm" data-action="edit-set" data-id="<?php echo esc_attr( (string) $set['id'] ); ?>"><?php esc_html_e( 'Edit', 'ai-faq-generator-publisher' ); ?></button>
										<button type="button" class="aifaq-btn aifaq-btn--sm aifaq-btn--dark" data-action="copy-lib" data-code="<?php echo esc_attr( $set['shortcode'] ); ?>"><?php esc_html_e( 'Copy', 'ai-faq-generator-publisher' ); ?></button>
										<button type="button" class="aifaq-btn aifaq-btn--sm aifaq-btn--danger" data-action="delete-set" data-id="<?php echo esc_attr( (string) $set['id'] ); ?>"><?php esc_html_e( 'Delete', 'ai-faq-generator-publisher' ); ?></button>
									</div>
								</article>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<!-- SETTINGS -->
			<section class="aifaq-panel" data-panel="settings">
				<div class="aifaq-grid aifaq-grid--settings">
					<div class="aifaq-card">
						<div class="aifaq-card__head">
							<div>
								<span class="aifaq-step-tag"><?php esc_html_e( 'API', 'ai-faq-generator-publisher' ); ?></span>
								<h2><?php esc_html_e( 'AI provider', 'ai-faq-generator-publisher' ); ?></h2>
							</div>
						</div>
						<p class="aifaq-card__hint"><?php esc_html_e( 'Keys stay on your site. Leave blank to keep an existing saved key.', 'ai-faq-generator-publisher' ); ?></p>

						<div class="aifaq-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Provider', 'ai-faq-generator-publisher' ); ?>" data-provider-tabs>
							<label class="aifaq-seg__opt">
								<input type="radio" name="aifaq_provider" value="openai" data-field="provider" <?php checked( $provider, 'openai' ); ?> />
								<span>
									<strong>OpenAI</strong>
									<small>GPT models</small>
								</span>
							</label>
							<label class="aifaq-seg__opt">
								<input type="radio" name="aifaq_provider" value="gemini" data-field="provider" <?php checked( $provider, 'gemini' ); ?> />
								<span>
									<strong>Gemini</strong>
									<small>Google AI</small>
								</span>
							</label>
						</div>

						<div class="aifaq-provider-panel<?php echo 'openai' === $provider ? ' is-active' : ''; ?>" data-provider-panel="openai" <?php echo 'openai' === $provider ? '' : 'hidden'; ?>>
							<label class="aifaq-field">
								<span><?php esc_html_e( 'OpenAI API key', 'ai-faq-generator-publisher' ); ?><?php echo $has_openai ? ' <em class="aifaq-ok">saved</em>' : ''; ?></span>
								<input type="password" data-field="openai_key" autocomplete="off" placeholder="<?php echo $has_openai ? esc_attr__( '•••••••• (leave blank to keep)', 'ai-faq-generator-publisher' ) : 'sk-…'; ?>" />
							</label>
							<label class="aifaq-field">
								<span><?php esc_html_e( 'OpenAI model', 'ai-faq-generator-publisher' ); ?></span>
								<input type="text" data-field="openai_model" value="<?php echo esc_attr( $settings['openai_model'] ?? 'gpt-4o-mini' ); ?>" />
							</label>
						</div>

						<div class="aifaq-provider-panel<?php echo 'gemini' === $provider ? ' is-active' : ''; ?>" data-provider-panel="gemini" <?php echo 'gemini' === $provider ? '' : 'hidden'; ?>>
							<label class="aifaq-field">
								<span><?php esc_html_e( 'Gemini API key', 'ai-faq-generator-publisher' ); ?><?php echo $has_gemini ? ' <em class="aifaq-ok">saved</em>' : ''; ?></span>
								<input type="password" data-field="gemini_key" autocomplete="off" placeholder="<?php echo $has_gemini ? esc_attr__( '•••••••• (leave blank to keep)', 'ai-faq-generator-publisher' ) : 'AIza…'; ?>" />
							</label>
							<label class="aifaq-field">
								<span><?php esc_html_e( 'Gemini model', 'ai-faq-generator-publisher' ); ?></span>
								<input type="text" data-field="gemini_model" value="<?php echo esc_attr( $settings['gemini_model'] ?? 'gemini-2.5-flash' ); ?>" />
							</label>
						</div>
					</div>

					<div class="aifaq-card">
						<div class="aifaq-card__head">
							<div>
								<span class="aifaq-step-tag"><?php esc_html_e( 'UI', 'ai-faq-generator-publisher' ); ?></span>
								<h2><?php esc_html_e( 'Accordion defaults', 'ai-faq-generator-publisher' ); ?></h2>
							</div>
						</div>
						<p class="aifaq-card__hint"><?php esc_html_e( 'These apply to every shortcode unless you override attributes.', 'ai-faq-generator-publisher' ); ?></p>

						<label class="aifaq-field">
							<span><?php esc_html_e( 'Default FAQ count', 'ai-faq-generator-publisher' ); ?></span>
							<input type="number" min="3" max="20" data-field="default_count" value="<?php echo esc_attr( (string) ( $settings['default_count'] ?? 8 ) ); ?>" />
						</label>
						<label class="aifaq-field">
							<span><?php esc_html_e( 'Default tone', 'ai-faq-generator-publisher' ); ?></span>
							<input type="text" data-field="default_tone" value="<?php echo esc_attr( $settings['default_tone'] ?? 'clear and helpful' ); ?>" />
						</label>

						<div class="aifaq-toggle-list">
							<label class="aifaq-toggle">
								<input type="checkbox" data-field="open_first" <?php checked( ! empty( $acc['open_first'] ) ); ?> />
								<span class="aifaq-toggle__ui" aria-hidden="true"></span>
								<span class="aifaq-toggle__label">
									<strong><?php esc_html_e( 'Open first item', 'ai-faq-generator-publisher' ); ?></strong>
									<small><?php esc_html_e( 'First FAQ expands on page load', 'ai-faq-generator-publisher' ); ?></small>
								</span>
							</label>
							<label class="aifaq-toggle">
								<input type="checkbox" data-field="allow_multiple" <?php checked( ! empty( $acc['allow_multiple'] ) ); ?> />
								<span class="aifaq-toggle__ui" aria-hidden="true"></span>
								<span class="aifaq-toggle__label">
									<strong><?php esc_html_e( 'Allow multiple open', 'ai-faq-generator-publisher' ); ?></strong>
									<small><?php esc_html_e( 'Visitors can expand several at once', 'ai-faq-generator-publisher' ); ?></small>
								</span>
							</label>
							<label class="aifaq-toggle">
								<input type="checkbox" data-field="show_schema" <?php checked( ! empty( $acc['show_schema'] ) ); ?> />
								<span class="aifaq-toggle__ui" aria-hidden="true"></span>
								<span class="aifaq-toggle__label">
									<strong><?php esc_html_e( 'FAQPage schema', 'ai-faq-generator-publisher' ); ?></strong>
									<small><?php esc_html_e( 'JSON-LD for search engines', 'ai-faq-generator-publisher' ); ?></small>
								</span>
							</label>
						</div>

						<div class="aifaq-actions">
							<button type="button" class="aifaq-btn aifaq-btn--primary aifaq-btn--lg" data-action="save-settings"><?php esc_html_e( 'Save settings', 'ai-faq-generator-publisher' ); ?></button>
						</div>
					</div>
				</div>
			</section>
		</main>
	</div>
</div>
