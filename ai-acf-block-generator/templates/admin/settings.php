<?php
/**
 * Settings page template.
 *
 * @package AABG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

settings_errors( 'aabg_settings' );
?>
<div class="wrap aabg-wrap">
	<div class="aabg-header">
		<h1><?php esc_html_e( 'Settings', 'ai-acf-block-generator' ); ?></h1>
		<p class="aabg-subtitle"><?php esc_html_e( 'Configure AI provider and plugin options.', 'ai-acf-block-generator' ); ?></p>
	</div>

	<div class="aabg-card aabg-diagnostics">
		<h2><?php esc_html_e( 'System Status', 'ai-acf-block-generator' ); ?></h2>
		<ul class="aabg-diag-list">
			<li>
				<?php if ( ! empty( $diagnostics['acf_active'] ) ) : ?>
					<span class="aabg-status aabg-status--ok"><?php esc_html_e( 'ACF Pro', 'ai-acf-block-generator' ); ?></span>
				<?php else : ?>
					<span class="aabg-status aabg-status--error"><?php esc_html_e( 'ACF Pro missing', 'ai-acf-block-generator' ); ?></span>
				<?php endif; ?>
			</li>
			<li>
				<?php if ( ! empty( $diagnostics['theme_exists'] ) ) : ?>
					<span class="aabg-status aabg-status--ok"><?php esc_html_e( 'Theme path OK', 'ai-acf-block-generator' ); ?></span>
				<?php else : ?>
					<span class="aabg-status aabg-status--error"><?php esc_html_e( 'Theme path invalid', 'ai-acf-block-generator' ); ?></span>
				<?php endif; ?>
			</li>
			<li>
				<span class="aabg-status aabg-status--ok"><?php echo esc_html( $diagnostics['ai_provider_label'] ?? 'AI' ); ?></span>
			</li>
			<li>
				<?php if ( ! empty( $diagnostics['block_css_count'] ) ) : ?>
					<span class="aabg-status aabg-status--ok"><?php echo esc_html( sprintf( __( 'Block CSS: %d files', 'ai-acf-block-generator' ), (int) $diagnostics['block_css_count'] ) ); ?></span>
				<?php else : ?>
					<span class="aabg-status aabg-status--error"><?php esc_html_e( 'Block CSS not generated', 'ai-acf-block-generator' ); ?></span>
				<?php endif; ?>
			</li>
			<li>
				<?php if ( ! empty( $diagnostics['api_key_set'] ) ) : ?>
					<span class="aabg-status aabg-status--ok"><?php esc_html_e( 'API key saved', 'ai-acf-block-generator' ); ?></span>
				<?php else : ?>
					<span class="aabg-status aabg-status--error"><?php esc_html_e( 'API key not saved', 'ai-acf-block-generator' ); ?></span>
				<?php endif; ?>
			</li>
		</ul>
		<?php if ( is_wp_error( $diagnostics['health'] ) ) : ?>
			<div class="aabg-notice aabg-notice--error"><?php echo esc_html( $diagnostics['health']->get_error_message() ); ?></div>
		<?php endif; ?>
	</div>

	<form method="post" class="aabg-card aabg-settings-form">
		<?php wp_nonce_field( 'aabg_save_settings', 'aabg_settings_nonce' ); ?>

		<h2><?php esc_html_e( 'Theme Output', 'ai-acf-block-generator' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active Theme', 'ai-acf-block-generator' ); ?></th>
				<td><code><?php echo esc_html( get_stylesheet_directory() ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><label for="aabg_theme_path"><?php esc_html_e( 'Theme Path (optional)', 'ai-acf-block-generator' ); ?></label></th>
				<td>
					<input type="text" id="aabg_theme_path" name="aabg_theme_path" value="<?php echo esc_attr( $theme_path ); ?>" class="large-text" placeholder="<?php echo esc_attr( get_stylesheet_directory() ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave empty to use active theme.', 'ai-acf-block-generator' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'AI Provider', 'ai-acf-block-generator' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable AI', 'ai-acf-block-generator' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="aabg_use_openai" value="1" <?php checked( $use_openai, '1' ); ?> />
						<?php esc_html_e( 'Enable AI for advanced prompt analysis', 'ai-acf-block-generator' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Required for design image matching. Without AI, rule-based mode works from prompt only.', 'ai-acf-block-generator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aabg_ai_provider"><?php esc_html_e( 'AI Provider', 'ai-acf-block-generator' ); ?></label></th>
				<td>
					<select id="aabg_ai_provider" name="aabg_ai_provider">
						<option value="openai" <?php selected( $ai_provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI (GPT-4o)', 'ai-acf-block-generator' ); ?></option>
						<option value="gemini" <?php selected( $ai_provider, 'gemini' ); ?>><?php esc_html_e( 'Google Gemini', 'ai-acf-block-generator' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<div id="aabg-openai-settings" class="aabg-provider-panel" <?php echo 'openai' !== $ai_provider ? 'style="display:none"' : ''; ?>>
			<h3><?php esc_html_e( 'OpenAI Settings', 'ai-acf-block-generator' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="aabg_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'ai-acf-block-generator' ); ?></label></th>
					<td>
						<input type="password" id="aabg_openai_api_key" name="aabg_openai_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off" placeholder="sk-proj-..." />
						<p class="description"><?php esc_html_e( 'Get key from platform.openai.com/api-keys', 'ai-acf-block-generator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aabg_openai_model"><?php esc_html_e( 'OpenAI Model', 'ai-acf-block-generator' ); ?></label></th>
					<td>
						<select id="aabg_openai_model" name="aabg_openai_model">
							<option value="gpt-4o-mini" <?php selected( $model, 'gpt-4o-mini' ); ?>>gpt-4o-mini</option>
							<option value="gpt-4o" <?php selected( $model, 'gpt-4o' ); ?>>gpt-4o</option>
							<option value="gpt-4-turbo" <?php selected( $model, 'gpt-4-turbo' ); ?>>gpt-4-turbo</option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<div id="aabg-gemini-settings" class="aabg-provider-panel" <?php echo 'gemini' !== $ai_provider ? 'style="display:none"' : ''; ?>>
			<h3><?php esc_html_e( 'Google Gemini Settings', 'ai-acf-block-generator' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="aabg_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'ai-acf-block-generator' ); ?></label></th>
					<td>
						<input type="password" id="aabg_gemini_api_key" name="aabg_gemini_api_key" value="<?php echo esc_attr( $gemini_key ); ?>" class="regular-text" autocomplete="off" placeholder="AIza..." />
						<p class="description"><?php esc_html_e( 'Get free key from aistudio.google.com/apikey', 'ai-acf-block-generator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aabg_gemini_model"><?php esc_html_e( 'Gemini Model', 'ai-acf-block-generator' ); ?></label></th>
					<td>
						<select id="aabg_gemini_model" name="aabg_gemini_model">
							<option value="gemini-2.5-flash" <?php selected( $gemini_model, 'gemini-2.5-flash' ); ?>>gemini-2.5-flash (recommended)</option>
							<option value="gemini-2.0-flash" <?php selected( $gemini_model, 'gemini-2.0-flash' ); ?>>gemini-2.0-flash</option>
							<option value="gemini-2.0-flash-lite" <?php selected( $gemini_model, 'gemini-2.0-flash-lite' ); ?>>gemini-2.0-flash-lite</option>
							<option value="gemini-1.5-flash" <?php selected( $gemini_model, 'gemini-1.5-flash' ); ?>>gemini-1.5-flash</option>
							<option value="gemini-1.5-pro" <?php selected( $gemini_model, 'gemini-1.5-pro' ); ?>>gemini-1.5-pro</option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<p>
			<button type="button" class="button" id="aabg-sync-css-btn"><?php esc_html_e( 'Sync Block CSS', 'ai-acf-block-generator' ); ?></button>
			<span id="aabg-sync-css-result"></span>
		</p>

		<p>
			<button type="button" class="button" id="aabg-test-api-btn"><?php esc_html_e( 'Test API Key', 'ai-acf-block-generator' ); ?></button>
			<span id="aabg-test-api-result"></span>
		</p>

		<?php submit_button( __( 'Save Settings', 'ai-acf-block-generator' ) ); ?>
	</form>
</div>
