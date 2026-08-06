<?php
/**
 * Settings / dashboard template.
 *
 * @var array<string, mixed> $settings
 * @var int                  $pending
 * @var int                  $total_saved
 * @var bool                 $configured
 * @var int                  $cached_files
 * @var array<string,string> $format_choices
 * @var int                  $missing_alt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$provider        = $settings['api_provider'] ?? 'gemini';
$frontend_format = $settings['frontend_format'] ?? 'webp';
$opt             = AIIO_Settings::OPTION_KEY;
?>
<div class="wrap aiio-wrap">
	<div class="aiio-header">
		<h1><?php esc_html_e( 'AI Image Optimization', 'ai-image-optimization' ); ?></h1>
		<p class="aiio-subtitle"><?php esc_html_e( 'Standardize images for faster loading, better SEO, and accessibility. Media Library originals stay untouched.', 'ai-image-optimization' ); ?></p>
	</div>

	<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'ai-image-optimization' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="aiio-dashboard">
		<form method="post" action="options.php">
			<?php settings_fields( 'aiio_settings_group' ); ?>

			<!-- 1. Frontend format -->
			<div class="aiio-card aiio-card--hero">
				<h2><?php esc_html_e( '1) Frontend Image Format', 'ai-image-optimization' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Controls which image format your website visitors see. Media Library files are not modified.', 'ai-image-optimization' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aiio_frontend_format"><?php esc_html_e( 'Show images as', 'ai-image-optimization' ); ?></label></th>
						<td>
							<select id="aiio_frontend_format" name="<?php echo esc_attr( $opt ); ?>[frontend_format]" class="regular-text aiio-format-select">
								<?php foreach ( $format_choices as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $frontend_format, $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><code>wp-content/uploads/aiio-delivered/</code></p>
						</td>
					</tr>
				</table>
				<p class="aiio-notice aiio-notice--info">
					<?php esc_html_e( 'Tip: WebP is best for speed. Use JPG for broad compatibility. Use PNG if you need transparency.', 'ai-image-optimization' ); ?>
				</p>
			</div>

			<div class="aiio-stats-row">
				<div class="aiio-card aiio-card--stats">
					<h2><?php esc_html_e( 'Delivery Cache', 'ai-image-optimization' ); ?></h2>
					<p class="aiio-stat"><strong><?php echo esc_html( number_format_i18n( $cached_files ) ); ?></strong><?php esc_html_e( 'converted copies', 'ai-image-optimization' ); ?></p>
					<button type="button" class="button" id="aiio-clear-cache"><?php esc_html_e( 'Clear Delivery Cache', 'ai-image-optimization' ); ?></button>
					<p class="description" id="aiio-cache-status"></p>
				</div>
				<div class="aiio-card aiio-card--stats">
					<h2><?php esc_html_e( 'Missing ALT', 'ai-image-optimization' ); ?></h2>
					<p class="aiio-stat"><strong><?php echo esc_html( number_format_i18n( $missing_alt ) ); ?></strong><?php esc_html_e( 'images without ALT', 'ai-image-optimization' ); ?></p>
					<?php if ( $missing_alt > 0 ) : ?>
						<button type="button" class="button button-primary" id="aiio-bulk-alt"><?php esc_html_e( 'Fix Missing ALT Texts', 'ai-image-optimization' ); ?></button>
						<p class="description" id="aiio-alt-status"></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'All images have ALT text.', 'ai-image-optimization' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="aiio-card aiio-card--stats">
					<h2><?php esc_html_e( 'Compress Pending', 'ai-image-optimization' ); ?></h2>
					<p class="aiio-stat"><strong><?php echo esc_html( number_format_i18n( $pending ) ); ?></strong><?php esc_html_e( 'not compressed', 'ai-image-optimization' ); ?></p>
					<?php if ( $pending > 0 ) : ?>
						<button type="button" class="button" id="aiio-bulk-optimize"><?php esc_html_e( 'Compress Pending', 'ai-image-optimization' ); ?></button>
						<p class="description" id="aiio-bulk-status"></p>
					<?php endif; ?>
					<p class="description"><?php echo esc_html( size_format( $total_saved ) ); ?> <?php esc_html_e( 'saved total', 'ai-image-optimization' ); ?></p>
				</div>
			</div>

			<!-- 2. SEO + Accessibility -->
			<div class="aiio-card">
				<h2><?php esc_html_e( '2) SEO & Accessibility (Standardize)', 'ai-image-optimization' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Designed for both search engines and screen readers. These options standardize ALT text and improve image performance.', 'ai-image-optimization' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto ALT text', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[seo_auto_alt]" value="1" <?php checked( ! empty( $settings['seo_auto_alt'] ) ); ?> />
							<?php esc_html_e( 'Fill missing ALT using the image title/filename (SEO + accessibility).', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'ALT on upload', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[seo_auto_alt_on_upload]" value="1" <?php checked( ! empty( $settings['seo_auto_alt_on_upload'] ) ); ?> />
							<?php esc_html_e( 'Automatically set ALT text when a new image is uploaded.', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ensure ALT on frontend', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[seo_ensure_alt_frontend]" value="1" <?php checked( ! empty( $settings['seo_ensure_alt_frontend'] ) ); ?> />
							<?php esc_html_e( 'Fix missing alt attributes in the rendered frontend HTML (where needed).', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'AI ALT text', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[seo_use_ai_alt]" value="1" <?php checked( ! empty( $settings['seo_use_ai_alt'] ) ); ?> />
							<?php esc_html_e( 'Generate ALT using AI vision for missing ALT (requires an API key).', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiio_seo_alt_language"><?php esc_html_e( 'ALT language', 'ai-image-optimization' ); ?></label></th>
						<td>
							<input type="text" id="aiio_seo_alt_language" name="<?php echo esc_attr( $opt ); ?>[seo_alt_language]" value="<?php echo esc_attr( (string) $settings['seo_alt_language'] ); ?>" class="small-text" placeholder="en" />
							<p class="description"><?php esc_html_e( 'Use an ISO language code (e.g., en, hi, es).', 'ai-image-optimization' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiio_seo_alt_max_length"><?php esc_html_e( 'Max ALT length', 'ai-image-optimization' ); ?></label></th>
						<td>
							<input type="number" id="aiio_seo_alt_max_length" name="<?php echo esc_attr( $opt ); ?>[seo_alt_max_length]" value="<?php echo esc_attr( (string) $settings['seo_alt_max_length'] ); ?>" min="40" max="250" class="small-text" />
							<p class="description"><?php esc_html_e( 'Recommended: 100–125 characters.', 'ai-image-optimization' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Title attribute', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[seo_add_title_attr]" value="1" <?php checked( ! empty( $settings['seo_add_title_attr'] ) ); ?> />
							<?php esc_html_e( 'Optionally add an image title attribute (copies the ALT value).', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Lazy load', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[a11y_lazy_load]" value="1" <?php checked( ! empty( $settings['a11y_lazy_load'] ) ); ?> />
							<?php esc_html_e( 'Add loading="lazy" to non-critical images to improve speed and Core Web Vitals.', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Skip lazy for LCP', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[a11y_skip_lazy_lcp]" value="1" <?php checked( ! empty( $settings['a11y_skip_lazy_lcp'] ) ); ?> />
							<?php esc_html_e( 'Ensure the featured/LCP image loads eagerly and set fetchpriority="high".', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Async decode', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[a11y_async_decode]" value="1" <?php checked( ! empty( $settings['a11y_async_decode'] ) ); ?> />
							<?php esc_html_e( 'Add decoding="async" to reduce render blocking.', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Width & Height', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[a11y_force_dimensions]" value="1" <?php checked( ! empty( $settings['a11y_force_dimensions'] ) ); ?> />
							<?php esc_html_e( 'Add width/height attributes to reduce layout shift (CLS).', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
				</table>
			</div>

			<!-- 3. Compression -->
			<div class="aiio-card">
				<h2><?php esc_html_e( '3) Compression Settings', 'ai-image-optimization' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aiio_jpeg_quality"><?php esc_html_e( 'JPEG Quality', 'ai-image-optimization' ); ?></label></th>
						<td><input type="number" id="aiio_jpeg_quality" name="<?php echo esc_attr( $opt ); ?>[jpeg_quality]" value="<?php echo esc_attr( (string) $settings['jpeg_quality'] ); ?>" min="40" max="100" class="small-text" />
							<p class="description"><?php esc_html_e( '75–85 is a good balance between quality and size.', 'ai-image-optimization' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="aiio_png_quality"><?php esc_html_e( 'PNG Quality', 'ai-image-optimization' ); ?></label></th>
						<td><input type="number" id="aiio_png_quality" name="<?php echo esc_attr( $opt ); ?>[png_quality]" value="<?php echo esc_attr( (string) $settings['png_quality'] ); ?>" min="40" max="100" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aiio_webp_quality"><?php esc_html_e( 'WebP Quality', 'ai-image-optimization' ); ?></label></th>
						<td><input type="number" id="aiio_webp_quality" name="<?php echo esc_attr( $opt ); ?>[webp_quality]" value="<?php echo esc_attr( (string) $settings['webp_quality'] ); ?>" min="40" max="100" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aiio_max_width"><?php esc_html_e( 'Max Width (px)', 'ai-image-optimization' ); ?></label></th>
						<td><input type="number" id="aiio_max_width" name="<?php echo esc_attr( $opt ); ?>[max_width]" value="<?php echo esc_attr( (string) $settings['max_width'] ); ?>" min="0" max="10000" class="small-text" />
							<p class="description"><?php esc_html_e( '0 = no limit.', 'ai-image-optimization' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="aiio_max_height"><?php esc_html_e( 'Max Height (px)', 'ai-image-optimization' ); ?></label></th>
						<td><input type="number" id="aiio_max_height" name="<?php echo esc_attr( $opt ); ?>[max_height]" value="<?php echo esc_attr( (string) $settings['max_height'] ); ?>" min="0" max="10000" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aiio_skip_small_kb"><?php esc_html_e( 'Skip small files (KB)', 'ai-image-optimization' ); ?></label></th>
						<td><input type="number" id="aiio_skip_small_kb" name="<?php echo esc_attr( $opt ); ?>[skip_small_kb]" value="<?php echo esc_attr( (string) $settings['skip_small_kb'] ); ?>" min="0" max="5000" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Thumbnails', 'ai-image-optimization' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[optimize_thumbnails]" value="1" <?php checked( ! empty( $settings['optimize_thumbnails'] ) ); ?> />
							<?php esc_html_e( 'Also optimize thumbnail sizes.', 'ai-image-optimization' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto on upload', 'ai-image-optimization' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[auto_on_upload]" value="1" <?php checked( ! empty( $settings['auto_on_upload'] ) ); ?> />
							<?php esc_html_e( 'Automatically optimize images when uploaded.', 'ai-image-optimization' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Change Media files (advanced)', 'ai-image-optimization' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[convert_webp]" value="1" <?php checked( ! empty( $settings['convert_webp'] ) ); ?> />
							<?php esc_html_e( 'Replace files in the Media Library with WebP versions (original files will be changed). Usually keep OFF.', 'ai-image-optimization' ); ?></label><br /><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[replace_original]" value="1" <?php checked( ! empty( $settings['replace_original'] ) ); ?> />
							<?php esc_html_e( 'Delete originals after conversion (use with caution).', 'ai-image-optimization' ); ?></label>
						</td>
					</tr>
				</table>
			</div>

			<!-- 4. AI -->
			<div class="aiio-card">
				<h2><?php esc_html_e( '4) AI Settings (optional)', 'ai-image-optimization' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'AI Smart Quality', 'ai-image-optimization' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[ai_smart_quality]" value="1" <?php checked( ! empty( $settings['ai_smart_quality'] ) ); ?> />
							<?php esc_html_e( 'AI evaluates image content and selects the best compression settings.', 'ai-image-optimization' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="aiio_api_provider"><?php esc_html_e( 'AI Provider', 'ai-image-optimization' ); ?></label></th>
						<td>
							<select id="aiio_api_provider" name="<?php echo esc_attr( $opt ); ?>[api_provider]">
								<option value="gemini" <?php selected( $provider, 'gemini' ); ?>><?php esc_html_e( 'Google Gemini', 'ai-image-optimization' ); ?></option>
								<option value="openai" <?php selected( $provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'ai-image-optimization' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<div id="aiio-openai-panel" class="aiio-provider-panel" <?php echo 'openai' !== $provider ? 'style="display:none"' : ''; ?>>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="aiio_api_key"><?php esc_html_e( 'OpenAI API Key', 'ai-image-optimization' ); ?></label></th>
							<td><input type="password" id="aiio_api_key" name="<?php echo esc_attr( $opt ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text" autocomplete="off" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="aiio_model"><?php esc_html_e( 'Model', 'ai-image-optimization' ); ?></label></th>
							<td>
								<select id="aiio_model" name="<?php echo esc_attr( $opt ); ?>[model]">
									<option value="gpt-4o-mini" <?php selected( $settings['model'], 'gpt-4o-mini' ); ?>>GPT-4o Mini</option>
									<option value="gpt-4o" <?php selected( $settings['model'], 'gpt-4o' ); ?>>GPT-4o</option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div id="aiio-gemini-panel" class="aiio-provider-panel" <?php echo 'gemini' !== $provider ? 'style="display:none"' : ''; ?>>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="aiio_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'ai-image-optimization' ); ?></label></th>
							<td>
								<input type="password" id="aiio_gemini_api_key" name="<?php echo esc_attr( $opt ); ?>[gemini_api_key]" value="<?php echo esc_attr( $settings['gemini_api_key'] ); ?>" class="regular-text" autocomplete="off" />
								<p class="description"><?php esc_html_e( 'aistudio.google.com/apikey', 'ai-image-optimization' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aiio_gemini_model"><?php esc_html_e( 'Model', 'ai-image-optimization' ); ?></label></th>
							<td>
								<select id="aiio_gemini_model" name="<?php echo esc_attr( $opt ); ?>[gemini_model]">
									<option value="gemini-2.5-flash" <?php selected( $settings['gemini_model'], 'gemini-2.5-flash' ); ?>>gemini-2.5-flash</option>
									<option value="gemini-2.0-flash" <?php selected( $settings['gemini_model'], 'gemini-2.0-flash' ); ?>>gemini-2.0-flash</option>
									<option value="gemini-1.5-flash" <?php selected( $settings['gemini_model'], 'gemini-1.5-flash' ); ?>>gemini-1.5-flash</option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<p>
					<button type="button" class="button" id="aiio-test-api"><?php esc_html_e( 'Test API Key', 'ai-image-optimization' ); ?></button>
					<span id="aiio-test-result"></span>
				</p>
				<?php if ( ( ! empty( $settings['seo_use_ai_alt'] ) || ! empty( $settings['ai_smart_quality'] ) ) && ! $configured ) : ?>
					<p class="aiio-notice aiio-notice--warning"><?php esc_html_e( 'AI features are enabled, but the required API key is missing. Add a key in Settings or disable AI features.', 'ai-image-optimization' ); ?></p>
				<?php endif; ?>
			</div>

			<?php submit_button( __( 'Save All Settings', 'ai-image-optimization' ) ); ?>
		</form>
	</div>

	<div class="aiio-card aiio-card--help">
		<h2><?php esc_html_e( 'Complete Guide — what each option means', 'ai-image-optimization' ); ?></h2>

		<h3><?php esc_html_e( 'A) Frontend Format', 'ai-image-optimization' ); ?></h3>
		<ul>
			<li><strong>WebP</strong> — <?php esc_html_e( 'Smallest file size for modern browsers. Best for speed + SEO (Core Web Vitals).', 'ai-image-optimization' ); ?></li>
			<li><strong>JPG</strong> — <?php esc_html_e( 'Safe for older browsers and email clients.', 'ai-image-optimization' ); ?></li>
			<li><strong>PNG</strong> — <?php esc_html_e( 'Best for logos and images with transparency.', 'ai-image-optimization' ); ?></li>
			<li><strong>Original</strong> — <?php esc_html_e( 'No format changes. Your frontend uses the same format as in the Media Library.', 'ai-image-optimization' ); ?></li>
			<li><?php esc_html_e( 'Converted copies are stored separately in uploads/aiio-delivered/. Media Library originals remain untouched.', 'ai-image-optimization' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'B) SEO & Accessibility', 'ai-image-optimization' ); ?></h3>
		<ul>
			<li><strong>ALT text</strong> — <?php esc_html_e( 'Essential for screen readers and image SEO. Missing ALT can weaken accessibility.', 'ai-image-optimization' ); ?></li>
			<li><strong>Auto ALT</strong> — <?php esc_html_e( 'Creates readable ALT from the filename/title (e.g., IMG_1234 → clean text).', 'ai-image-optimization' ); ?></li>
			<li><strong>AI ALT</strong> — <?php esc_html_e( 'Uses AI vision to write a meaningful description (requires API key).', 'ai-image-optimization' ); ?></li>
			<li><strong>Lazy load</strong> — <?php esc_html_e( 'Loads images when the user scrolls near them, improving initial page load time.', 'ai-image-optimization' ); ?></li>
			<li><strong>Skip lazy for LCP</strong> — <?php esc_html_e( 'Ensures the hero/featured image loads quickly for better PageSpeed LCP results.', 'ai-image-optimization' ); ?></li>
			<li><strong>Width/Height</strong> — <?php esc_html_e( 'Prevents layout jumps (CLS), improving UX and SEO.', 'ai-image-optimization' ); ?></li>
			<li><strong>decoding=async</strong> — <?php esc_html_e( 'Lets the browser render text first and decode images afterward.', 'ai-image-optimization' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'C) Compression', 'ai-image-optimization' ); ?></h3>
		<ul>
			<li><strong>Quality</strong> — <?php esc_html_e( 'Controls output quality and file size. Higher quality means larger files.', 'ai-image-optimization' ); ?></li>
			<li><strong>Max width/height</strong> — <?php esc_html_e( 'Limits resizing so extremely large images do not remain unnecessarily heavy.', 'ai-image-optimization' ); ?></li>
			<li><strong>Auto on upload</strong> — <?php esc_html_e( 'Automatically compress new uploads.', 'ai-image-optimization' ); ?></li>
			<li><strong>Change Media (advanced)</strong> — <?php esc_html_e( 'Keep OFF unless you explicitly want to replace Media Library originals with optimized versions.', 'ai-image-optimization' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'D) Recommended setup (most sites)', 'ai-image-optimization' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Frontend format = WebP', 'ai-image-optimization' ); ?></li>
			<li><?php esc_html_e( 'Auto ALT + ALT on upload + Ensure ALT = ON', 'ai-image-optimization' ); ?></li>
			<li><?php esc_html_e( 'Lazy load + Skip LCP + Async decode + Width/Height = ON', 'ai-image-optimization' ); ?></li>
			<li><?php esc_html_e( 'JPEG/WebP quality ≈ 80–85', 'ai-image-optimization' ); ?></li>
			<li><?php esc_html_e( 'Change Media files = OFF', 'ai-image-optimization' ); ?></li>
			<li><?php esc_html_e( '"Fix Missing ALT Texts" + "Compress Pending" (run once after setup)', 'ai-image-optimization' ); ?></li>
		</ol>
	</div>
</div>
