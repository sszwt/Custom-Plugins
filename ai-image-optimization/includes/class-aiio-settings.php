<?php
/**
 * Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIIO_Settings {

	const OPTION_KEY = 'aiio_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults() {
		return array(
			// Frontend delivery format (Media untouched).
			'frontend_format'        => 'webp',

			// Compression.
			'jpeg_quality'           => 82,
			'png_quality'            => 82,
			'webp_quality'           => 80,
			'max_width'              => 2560,
			'max_height'             => 2560,
			'convert_webp'           => false,
			'replace_original'       => false,
			'strip_metadata'         => true,
			'optimize_thumbnails'    => true,
			'auto_on_upload'         => true,
			'skip_small_kb'          => 50,

			// SEO + Accessibility.
			'seo_auto_alt'           => true,
			'seo_auto_alt_on_upload' => true,
			'seo_ensure_alt_frontend'=> true,
			'seo_use_ai_alt'         => false,
			'seo_alt_max_length'     => 125,
			'seo_alt_language'       => 'en',
			'seo_add_title_attr'     => false,
			'a11y_lazy_load'         => true,
			'a11y_async_decode'      => true,
			'a11y_force_dimensions'  => true,
			'a11y_skip_lazy_lcp'     => true,

			// AI provider.
			'ai_smart_quality'       => false,
			'api_provider'           => 'gemini',
			'api_key'                => '',
			'gemini_api_key'         => '',
			'model'                  => 'gpt-4o-mini',
			'gemini_model'           => 'gemini-2.5-flash',
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $this->get_defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get( $key ) {
		$settings = $this->get_all();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Get active provider API key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		if ( 'gemini' === $this->get( 'api_provider' ) ) {
			return trim( (string) $this->get( 'gemini_api_key' ) );
		}

		return trim( (string) $this->get( 'api_key' ) );
	}

	/**
	 * Register settings.
	 */
	public function register() {
		register_setting(
			'aiio_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->get_defaults(),
			)
		);
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$defaults  = $this->get_defaults();
		$sanitized = array();

		$formats = array( 'original', 'webp', 'jpeg', 'png' );
		$sanitized['frontend_format'] = isset( $input['frontend_format'] ) && in_array( $input['frontend_format'], $formats, true )
			? $input['frontend_format']
			: $defaults['frontend_format'];

		$sanitized['jpeg_quality'] = isset( $input['jpeg_quality'] )
			? min( 100, max( 40, absint( $input['jpeg_quality'] ) ) )
			: $defaults['jpeg_quality'];

		$sanitized['png_quality'] = isset( $input['png_quality'] )
			? min( 100, max( 40, absint( $input['png_quality'] ) ) )
			: $defaults['png_quality'];

		$sanitized['webp_quality'] = isset( $input['webp_quality'] )
			? min( 100, max( 40, absint( $input['webp_quality'] ) ) )
			: $defaults['webp_quality'];

		$sanitized['max_width'] = isset( $input['max_width'] )
			? min( 10000, max( 0, absint( $input['max_width'] ) ) )
			: $defaults['max_width'];

		$sanitized['max_height'] = isset( $input['max_height'] )
			? min( 10000, max( 0, absint( $input['max_height'] ) ) )
			: $defaults['max_height'];

		$bool_keys = array(
			'convert_webp',
			'replace_original',
			'strip_metadata',
			'optimize_thumbnails',
			'auto_on_upload',
			'ai_smart_quality',
			'seo_auto_alt',
			'seo_auto_alt_on_upload',
			'seo_ensure_alt_frontend',
			'seo_use_ai_alt',
			'seo_add_title_attr',
			'a11y_lazy_load',
			'a11y_async_decode',
			'a11y_force_dimensions',
			'a11y_skip_lazy_lcp',
		);

		foreach ( $bool_keys as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		$sanitized['skip_small_kb'] = isset( $input['skip_small_kb'] )
			? min( 5000, max( 0, absint( $input['skip_small_kb'] ) ) )
			: $defaults['skip_small_kb'];

		$sanitized['seo_alt_max_length'] = isset( $input['seo_alt_max_length'] )
			? min( 250, max( 40, absint( $input['seo_alt_max_length'] ) ) )
			: $defaults['seo_alt_max_length'];

		$sanitized['seo_alt_language'] = isset( $input['seo_alt_language'] )
			? sanitize_text_field( $input['seo_alt_language'] )
			: $defaults['seo_alt_language'];

		$providers = array( 'openai', 'gemini' );
		$sanitized['api_provider'] = isset( $input['api_provider'] ) && in_array( $input['api_provider'], $providers, true )
			? $input['api_provider']
			: $defaults['api_provider'];

		$sanitized['api_key'] = isset( $input['api_key'] )
			? sanitize_text_field( $input['api_key'] )
			: $defaults['api_key'];

		$sanitized['gemini_api_key'] = isset( $input['gemini_api_key'] )
			? sanitize_text_field( $input['gemini_api_key'] )
			: $defaults['gemini_api_key'];

		$sanitized['model'] = isset( $input['model'] )
			? sanitize_text_field( $input['model'] )
			: $defaults['model'];

		$sanitized['gemini_model'] = isset( $input['gemini_model'] )
			? sanitize_text_field( $input['gemini_model'] )
			: $defaults['gemini_model'];

		return wp_parse_args( $sanitized, $defaults );
	}

	/**
	 * Whether AI is configured.
	 */
	public function is_ai_configured() {
		return ! empty( $this->get_api_key() );
	}

	/**
	 * Get provider label for UI.
	 *
	 * @return string
	 */
	public function get_provider_label() {
		return 'gemini' === $this->get( 'api_provider' )
			? __( 'Google Gemini', 'ai-image-optimization' )
			: __( 'OpenAI', 'ai-image-optimization' );
	}
}
