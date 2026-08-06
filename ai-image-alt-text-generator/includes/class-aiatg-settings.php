<?php
/**
 * Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIATG_Settings {

	const OPTION_KEY = 'aiatg_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults() {
		return array(
			'api_key'         => '',
			'gemini_api_key'  => '',
			'api_provider'    => 'gemini',
			'model'           => 'gpt-4o-mini',
			'gemini_model'    => 'gemini-2.5-flash',
			'language'        => 'en',
			'max_length'      => 125,
			'auto_on_upload'  => false,
			'prompt'          => 'Describe this image in one concise sentence suitable for HTML alt text. Focus on the main subject and important context. Do not start with "Image of" or "A photo of". Maximum 125 characters.',
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
			'aiatg_settings_group',
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

		$sanitized['api_key'] = isset( $input['api_key'] )
			? sanitize_text_field( $input['api_key'] )
			: $defaults['api_key'];

		$sanitized['gemini_api_key'] = isset( $input['gemini_api_key'] )
			? sanitize_text_field( $input['gemini_api_key'] )
			: $defaults['gemini_api_key'];

		$providers = array( 'openai', 'gemini' );
		$sanitized['api_provider'] = isset( $input['api_provider'] ) && in_array( $input['api_provider'], $providers, true )
			? $input['api_provider']
			: $defaults['api_provider'];

		$sanitized['model'] = isset( $input['model'] )
			? sanitize_text_field( $input['model'] )
			: $defaults['model'];

		$sanitized['gemini_model'] = isset( $input['gemini_model'] )
			? sanitize_text_field( $input['gemini_model'] )
			: $defaults['gemini_model'];

		$sanitized['language'] = isset( $input['language'] )
			? sanitize_text_field( $input['language'] )
			: $defaults['language'];

		$sanitized['max_length'] = isset( $input['max_length'] )
			? min( 250, max( 50, absint( $input['max_length'] ) ) )
			: $defaults['max_length'];

		$sanitized['auto_on_upload'] = ! empty( $input['auto_on_upload'] );

		$sanitized['prompt'] = isset( $input['prompt'] )
			? sanitize_textarea_field( $input['prompt'] )
			: $defaults['prompt'];

		return wp_parse_args( $sanitized, $defaults );
	}

	/**
	 * Whether API is configured for active provider.
	 */
	public function is_configured() {
		return ! empty( $this->get_api_key() );
	}

	/**
	 * Get provider label for UI.
	 *
	 * @return string
	 */
	public function get_provider_label() {
		return 'gemini' === $this->get( 'api_provider' )
			? __( 'Google Gemini', 'ai-image-alt-text-generator' )
			: __( 'OpenAI', 'ai-image-alt-text-generator' );
	}
}
