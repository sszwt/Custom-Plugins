<?php
/**
 * Google Gemini API provider for prompt analysis.
 *
 * @package AABG
 */

namespace AABG\AI;

use AABG\Utils\Image_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gemini_Provider
 */
class Gemini_Provider {

	use AI_Spec_Helper;

	/**
	 * Analyze a prompt via Gemini.
	 *
	 * @param string $prompt       User prompt.
	 * @param array  $block_config Block configuration.
	 * @return array|\WP_Error
	 */
	public function analyze( $prompt, $block_config ) {
		$system = $this->get_system_prompt();
		$user   = $this->build_user_message( $prompt, $block_config );

		return $this->request( $system, $user, $block_config, array(), $prompt );
	}

	/**
	 * Analyze prompt with design image (vision).
	 *
	 * @param string $prompt       User prompt.
	 * @param array  $block_config Block configuration.
	 * @param string $image_path   Image path.
	 * @return array|\WP_Error
	 */
	public function analyze_with_image( $prompt, $block_config, $image_path ) {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'ai-acf-block-generator' ) );
		}

		$prepared = Image_Helper::prepare_for_vision( $image_path );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$contents = file_get_contents( $prepared['path'] );
		if ( false === $contents ) {
			return new \WP_Error( 'image_read_failed', __( 'Could not read design image.', 'ai-acf-block-generator' ) );
		}

		if ( ! empty( $prepared['temp'] ) && ! empty( $prepared['path'] ) ) {
			register_shutdown_function(
				static function () use ( $prepared ) {
					if ( file_exists( $prepared['path'] ) ) {
						@unlink( $prepared['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					}
				}
			);
		}

		$system = $this->get_system_prompt( true );
		$text   = $this->get_vision_user_preamble() . $this->build_user_message( $prompt, $block_config );

		$parts = array(
			array( 'text' => $text ),
			array(
				'inlineData' => array(
					'mimeType' => $prepared['mime'],
					'data'     => base64_encode( $contents ),
				),
			),
		);

		return $this->request( $system, '', $block_config, $parts, $prompt );
	}

	/**
	 * Send Gemini generateContent request.
	 *
	 * @param string $system       System instruction.
	 * @param string $user_message User text (when no image parts).
	 * @param array  $block_config Block config.
	 * @param array  $parts        Optional content parts (vision).
	 * @param string $prompt       Original user prompt.
	 * @return array|\WP_Error
	 */
	private function request( $system, $user_message, $block_config, $parts = array(), $prompt = '' ) {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'ai-acf-block-generator' ) );
		}

		$model    = $this->get_model();
		$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		if ( empty( $parts ) ) {
			$parts = array(
				array( 'text' => $user_message ),
			);
		}

		$body = array(
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => $parts,
				),
			),
			'systemInstruction' => array(
				'parts' => array(
					array( 'text' => $system ),
				),
			),
			'generationConfig' => array(
				'temperature'     => 0.2,
				'maxOutputTokens' => 8192,
				'responseMimeType'=> 'application/json',
			),
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 180,
				'sslverify' => apply_filters( 'https_local_ssl_verify', true ),
				'headers'   => array(
					'Content-Type' => 'application/json',
				),
				'body'      => wp_json_encode( $body ),
			)
		);

		$parsed = $this->parse_response( $response );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		return $this->normalize_response( $parsed, $block_config, 'gemini', $prompt );
	}

	/**
	 * Parse Gemini HTTP response.
	 *
	 * @param array|\WP_Error $response Response.
	 * @return array|\WP_Error
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			if ( false !== stripos( $message, 'curl' ) || false !== stripos( $message, 'ssl' ) ) {
				return new \WP_Error(
					'gemini_connection',
					__( 'Could not connect to Google Gemini. Check your internet connection.', 'ai-acf-block-generator' )
				);
			}
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );

		if ( 200 !== $code ) {
			$message = $body['error']['message'] ?? __( 'Gemini API request failed.', 'ai-acf-block-generator' );
			return new \WP_Error( 'gemini_error', $message, array( 'status' => $code ) );
		}

		$content = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
		$parsed  = json_decode( $content, true );

		if ( ! is_array( $parsed ) ) {
			return new \WP_Error( 'invalid_response', __( 'Invalid AI response format from Gemini.', 'ai-acf-block-generator' ) );
		}

		return $parsed;
	}

	/**
	 * Get API key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		return trim( (string) get_option( 'aabg_gemini_api_key', '' ) );
	}

	/**
	 * Get Gemini model.
	 *
	 * @return string
	 */
	private function get_model() {
		$model = get_option( 'aabg_gemini_model', 'gemini-2.5-flash' );
		$allowed = array(
			'gemini-2.5-flash',
			'gemini-2.0-flash',
			'gemini-2.0-flash-lite',
			'gemini-1.5-flash',
			'gemini-1.5-pro',
		);

		return in_array( $model, $allowed, true ) ? $model : 'gemini-2.5-flash';
	}

	/**
	 * Test API connection.
	 *
	 * @return true|\WP_Error
	 */
	public function test_connection() {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'No Gemini API key saved.', 'ai-acf-block-generator' ) );
		}

		$model    = $this->get_model();
		$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'contents' => array(
							array(
								'parts' => array(
									array( 'text' => 'Reply with only: OK' ),
								),
							),
						),
						'generationConfig' => array(
							'maxOutputTokens' => 5,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = $body['error']['message'] ?? __( 'Gemini API request failed.', 'ai-acf-block-generator' );
			return new \WP_Error( 'gemini_error', $message );
		}

		return true;
	}
}
