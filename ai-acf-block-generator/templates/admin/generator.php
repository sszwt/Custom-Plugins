<?php
/**
 * Generator page template.
 *
 * @package AABG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap aabg-wrap">
	<div class="aabg-header">
		<h1><?php esc_html_e( 'AI ACF Block Generator', 'ai-acf-block-generator' ); ?></h1>
		<p class="aabg-subtitle"><?php esc_html_e( 'Enter a title, describe your block, and generate a complete ACF Gutenberg block in your theme.', 'ai-acf-block-generator' ); ?></p>
	</div>

	<div class="aabg-layout">
		<div class="aabg-main">
			<form id="aabg-generator-form" class="aabg-card" enctype="multipart/form-data">
				<div class="aabg-form-grid">
					<div class="aabg-field">
						<label for="aabg-block-name"><?php esc_html_e( 'Title', 'ai-acf-block-generator' ); ?> <span class="required">*</span></label>
						<input type="text" id="aabg-block-name" name="block_name" required placeholder="<?php esc_attr_e( 'e.g. Hero Banner', 'ai-acf-block-generator' ); ?>" />
					</div>

					<div class="aabg-field">
						<label for="aabg-block-slug"><?php esc_html_e( 'Slug', 'ai-acf-block-generator' ); ?></label>
						<input type="text" id="aabg-block-slug" name="block_slug" placeholder="<?php esc_attr_e( 'auto-generated from title', 'ai-acf-block-generator' ); ?>" />
					</div>
				</div>

				<div class="aabg-field">
					<label><?php esc_html_e( 'Design Reference Image', 'ai-acf-block-generator' ); ?></label>
					<div class="aabg-upload-zone" id="aabg-design-dropzone" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Upload design reference image', 'ai-acf-block-generator' ); ?>">
						<input type="file" id="aabg-design-image" name="design_image" accept="image/png,image/jpeg,image/webp,image/gif" class="aabg-upload-input" />
						<div class="aabg-upload-placeholder" id="aabg-upload-placeholder">
							<span class="dashicons dashicons-format-image"></span>
							<p class="aabg-upload-title"><?php esc_html_e( 'Drag & drop design here', 'ai-acf-block-generator' ); ?></p>
							<p class="aabg-upload-hint"><?php esc_html_e( 'or click to browse · paste screenshot (Ctrl+V)', 'ai-acf-block-generator' ); ?></p>
						</div>
						<div id="aabg-design-preview" class="aabg-design-preview aabg-hidden">
							<img src="" alt="<?php esc_attr_e( 'Design preview', 'ai-acf-block-generator' ); ?>" />
							<button type="button" class="aabg-remove-image" id="aabg-remove-image" aria-label="<?php esc_attr_e( 'Remove image', 'ai-acf-block-generator' ); ?>">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'Upload a design mockup. OpenAI or Gemini API key required — Vision AI matches your theme row/cell layout, colors, and spacing.', 'ai-acf-block-generator' ); ?></p>
				</div>

				<div class="aabg-field">
					<label for="aabg-prompt"><?php esc_html_e( 'Prompt', 'ai-acf-block-generator' ); ?></label>
					<textarea id="aabg-prompt" name="prompt" rows="10" placeholder="<?php esc_attr_e( "Optional if you upload a design image.\n\nExample:\nCreate this section exactly as shown.\nTitle\nDescription\nButton\nIllustration image\nTwo-column layout", 'ai-acf-block-generator' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Describe fields and layout, or rely on the design image with Vision AI (Gemini/OpenAI key required).', 'ai-acf-block-generator' ); ?></p>
				</div>

				<div class="aabg-actions">
					<button type="submit" class="button button-primary button-hero" id="aabg-generate-btn">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'Generate Block', 'ai-acf-block-generator' ); ?>
					</button>
				</div>
			</form>

			<div id="aabg-result" class="aabg-card aabg-hidden">
				<h2><?php esc_html_e( 'Generation Result', 'ai-acf-block-generator' ); ?></h2>
				<div id="aabg-result-message"></div>
				<div id="aabg-fields-list" class="aabg-fields-list"></div>
				<div id="aabg-suggestions" class="aabg-suggestions"></div>
				<div id="aabg-files-list" class="aabg-files-list"></div>
			</div>
		</div>

		<div class="aabg-sidebar">
			<div class="aabg-card aabg-tips">
				<h3><?php esc_html_e( 'Prompt Tips', 'ai-acf-block-generator' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'List each field on a new line', 'ai-acf-block-generator' ); ?></li>
					<li><?php esc_html_e( 'Mention layout: two-column, slider, grid', 'ai-acf-block-generator' ); ?></li>
					<li><?php esc_html_e( 'Specify interactions: autoplay, accordion, tabs', 'ai-acf-block-generator' ); ?></li>
					<li><?php esc_html_e( 'Upload a design image for closer visual match', 'ai-acf-block-generator' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</div>
