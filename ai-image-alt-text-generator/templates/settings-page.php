<?php
/**
 * Settings page template.
 *
 * @var array<string, mixed> $settings
 * @var int                  $missing
 * @var bool                 $configured
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$provider = $settings['api_provider'] ?? 'gemini';
?>
<div class="wrap aiatg-wrap">
	<div class="aiatg-header">
		<h1><?php esc_html_e( 'AI Image ALT Text Generator', 'ai-image-alt-text-generator' ); ?></h1>
		<p class="aiatg-subtitle"><?php esc_html_e( 'Improve accessibility and SEO with AI-generated ALT text for your Media Library images.', 'ai-image-alt-text-generator' ); ?></p>
	</div>

	<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'ai-image-alt-text-generator' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="aiatg-dashboard">
		<div class="aiatg-card aiatg-card--stats">
			<h2><?php esc_html_e( 'Overview', 'ai-image-alt-text-generator' ); ?></h2>
			<p class="aiatg-stat">
				<strong><?php echo esc_html( number_format_i18n( $missing ) ); ?></strong>
				<?php esc_html_e( 'images missing ALT text', 'ai-image-alt-text-generator' ); ?>
			</p>
			<?php if ( $missing > 0 ) : ?>
				<button type="button" class="button button-primary" id="aiatg-bulk-generate">
					<?php esc_html_e( 'Generate ALT for All Missing Images', 'ai-image-alt-text-generator' ); ?>
				</button>
				<p class="description" id="aiatg-bulk-status"></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'All images have ALT text. Great job!', 'ai-image-alt-text-generator' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="aiatg-card">
			<h2><?php esc_html_e( 'Settings', 'ai-image-alt-text-generator' ); ?></h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'aiatg_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="aiatg_api_provider"><?php esc_html_e( 'AI Provider', 'ai-image-alt-text-generator' ); ?></label>
						</th>
						<td>
							<select id="aiatg_api_provider" name="<?php echo esc_attr( AIATG_Settings::OPTION_KEY ); ?>[api_provider]">
								<option value="gemini" <?php selected( $provider, 'gemini' ); ?>><?php esc_html_e( 'Google Gemini (recommended)', 'ai-image-alt-text-generator' ); ?></option>
								<option value="openai" <?php selected( $provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'ai-image-alt-text-generator' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<div id="aiatg-openai-panel" class="aiatg-provider-panel" <?php echo 'openai' !== $provider ? 'style="display:none"' : ''; ?>>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="aiatg_api_key"><?php esc_html_e( 'OpenAI API Key', 'ai-image-alt-text-generator' ); ?></label>
							</th>
							<td>
								<input type="password" id="aiatg_api_key" name="<?php echo esc_attr( AIATG_Settings::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text" autocomplete="off" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="aiatg_model"><?php esc_html_e( 'OpenAI Model', 'ai-image-alt-text-generator' ); ?></label>
							</th>
							<td>
								<select id="aiatg_model" name="<?php echo esc_attr( AIATG_Settings::OPTION_KEY ); ?>[model]">
									<option value="gpt-4o-mini" <?php selected( $settings['model'], 'gpt-4o-mini' ); ?>>GPT-4o Mini</option>
									<option value="gpt-4o" <?php selected( $settings['model'], 'gpt-4o' ); ?>>GPT-4o</option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div id="aiatg-gemini-panel" class="aiatg-provider-panel" <?php echo 'gemini' !== $provider ? 'style="display:none"' : ''; ?>>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="aiatg_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'ai-image-alt-text-generator' ); ?></label>
							</th>
							<td>
								<input type="password" id="aiatg_gemini_api_key" name="<?php echo esc_attr( AIATG_Settings::OPTION_KEY ); ?>[gemini_api_key]" value="<?php echo esc_attr( $settings['gemini_api_key'] ); ?>" class="regular-text" autocomplete="off" />
								<p class="description"><?php esc_html_e( 'Get key from aistudio.google.com/apikey', 'ai-image-alt-text-generator' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="aiatg_gemini_model"><?php esc_html_e( 'Gemini Model', 'ai-image-alt-text-generator' ); ?></label>
							</th>
							<td>
								<select id="aiatg_gemini_model" name="<?php echo esc_attr( AIATG_Settings::OPTION_KEY ); ?>[gemini_model]">
									<option value="gemini-2.5-flash" <?php selected( $settings['gemini_model'], 'gemini-2.5-flash' ); ?>>gemini-2.5-flash</option>
									<option value="gemini-2.0-flash" <?php selected( $settings['gemini_model'], 'gemini-2.0-flash' ); ?>>gemini-2.0-flash</option>
									<option value="gemini-1.5-flash" <?php selected( $settings['gemini_model'], 'gemini-1.5-flash' ); ?>>gemini-1.5-flash</option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="aiatg_language"><?php esc_html_e( 'Language', 'ai-image-alt-text-generator' ); ?></label>
						</th>
						<td>
							<input type="text" id="aiatg_language" name="<?php echo esc_attr( AIATG_Settings::OPTION_KEY ); ?>[language]" value="<?php echo esc_attr( $settings['language'] ); ?>" class="regular-text" placeholder="en" />
							<p class="description"><?php esc_html_e( 'ISO code: en, hi, es, etc.', 'ai-image-alt-text-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="aiatg_max_length"><?php esc_html_e( 'Max ALT length', 'ai-image-alt-text-generator' ); ?></label>
						</th>
						<td>
							<input type="number" id="aiatg_max_length" name="<?php echo esc_attr( AIATG_Settings::OPTION_KEY ); ?>[max_length]" value="<?php echo esc_attr( (string) $settings['max_length'] ); ?>" min="50" max="250" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto on upload', 'ai-image-alt-text-generator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AIATG_Settings::OPTION_KEY ); ?>[auto_on_upload]" value="1" <?php checked( ! empty( $settings['auto_on_upload'] ) ); ?> />
								<?php esc_html_e( 'Automatically generate ALT text when a new image is uploaded', 'ai-image-alt-text-generator' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="aiatg_prompt"><?php esc_html_e( 'AI Prompt', 'ai-image-alt-text-generator' ); ?></label>
						</th>
						<td>
							<textarea id="aiatg_prompt" name="<?php echo esc_attr( AIATG_Settings::OPTION_KEY ); ?>[prompt]" rows="4" class="large-text"><?php echo esc_textarea( $settings['prompt'] ); ?></textarea>
						</td>
					</tr>
				</table>

				<p>
					<button type="button" class="button" id="aiatg-test-api"><?php esc_html_e( 'Test API Key', 'ai-image-alt-text-generator' ); ?></button>
					<span id="aiatg-test-result"></span>
				</p>

				<?php if ( ! $configured ) : ?>
					<p class="aiatg-notice aiatg-notice--warning">
						<?php esc_html_e( 'No API key — fallback mode uses filename/title only.', 'ai-image-alt-text-generator' ); ?>
					</p>
				<?php endif; ?>

				<?php submit_button(); ?>
			</form>
		</div>
	</div>

	<div class="aiatg-card aiatg-card--help">
		<h2><?php esc_html_e( 'How to use', 'ai-image-alt-text-generator' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Save your Gemini or OpenAI API key above.', 'ai-image-alt-text-generator' ); ?></li>
			<li><?php esc_html_e( 'Click "Generate ALT for All Missing Images" or use Media Library bulk action.', 'ai-image-alt-text-generator' ); ?></li>
			<li><?php esc_html_e( 'In Media Library list view, use the ALT Text column → Generate button per image.', 'ai-image-alt-text-generator' ); ?></li>
			<li><?php esc_html_e( 'Open any image attachment and click "Generate with AI".', 'ai-image-alt-text-generator' ); ?></li>
		</ol>
	</div>
</div>
