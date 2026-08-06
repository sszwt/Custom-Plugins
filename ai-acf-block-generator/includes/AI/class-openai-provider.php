<?php
/**
 * OpenAI API provider for prompt analysis.
 *
 * @package AABG
 */

namespace AABG\AI;

use AABG\Utils\Image_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenAI_Provider
 */
class OpenAI_Provider {

	use AI_Spec_Helper;

	/**
	 * API endpoint.
	 *
	 * @var string
	 */
	private $endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Analyze a prompt via OpenAI.
	 *
	 * @param string $prompt       User prompt.
	 * @param array  $block_config Block configuration.
	 * @return array|\WP_Error
	 */
	public function analyze( $prompt, $block_config ) {
		$system_prompt = $this->get_system_prompt();
		$user_message  = $this->build_user_message( $prompt, $block_config );

		return $this->request( $system_prompt, $user_message, $block_config, $prompt );
	}

	/**
	 * Analyze prompt with a design reference image (vision).
	 *
	 * @param string $prompt       User prompt.
	 * @param array  $block_config Block configuration.
	 * @param string $image_path   Absolute image path.
	 * @return array|\WP_Error
	 */
	public function analyze_with_image( $prompt, $block_config, $image_path ) {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'OpenAI API key is not configured.', 'ai-acf-block-generator' ) );
		}

		$prepared = Image_Helper::prepare_for_vision( $image_path );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$model = $this->get_vision_model();

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

		$base64 = base64_encode( $contents );
		$system = $this->get_system_prompt( true );
		$text   = $this->get_vision_user_preamble() . $this->build_user_message( $prompt, $block_config );

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout'   => 180,
				'sslverify' => apply_filters( 'https_local_ssl_verify', true ),
				'headers'   => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode(
					array(
						'model'           => $model,
						'messages'        => array(
							array(
								'role'    => 'system',
								'content' => $system,
							),
							array(
								'role'    => 'user',
								'content' => array(
									array(
										'type' => 'text',
										'text' => $text,
									),
									array(
										'type'      => 'image_url',
										'image_url' => array(
											'url'    => 'data:' . $prepared['mime'] . ';base64,' . $base64,
											'detail' => 'high',
										),
									),
								),
							),
						),
						'temperature'     => 0.2,
						'max_tokens'      => 8192,
						'response_format' => array( 'type' => 'json_object' ),
					)
				),
			)
		);

		$parsed = $this->parse_response( $response );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		return $this->normalize_response( $parsed, $block_config, 'openai', $prompt );
	}

	/**
	 * Send chat completion request.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_message  User message.
	 * @param array  $block_config  Block config.
	 * @param string $prompt        Original user prompt.
	 * @return array|\WP_Error
	 */
	private function request( $system_prompt, $user_message, $block_config, $prompt = '' ) {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'OpenAI API key is not configured.', 'ai-acf-block-generator' ) );
		}

		$model = get_option( 'aabg_openai_model', 'gpt-4o-mini' );

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout'   => 120,
				'sslverify' => apply_filters( 'https_local_ssl_verify', true ),
				'headers'   => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode(
					array(
						'model'           => $model,
						'messages'        => array(
							array(
								'role'    => 'system',
								'content' => $system_prompt,
							),
							array(
								'role'    => 'user',
								'content' => $user_message,
							),
						),
						'temperature'     => 0.3,
						'max_tokens'      => 4096,
						'response_format' => array( 'type' => 'json_object' ),
					)
				),
			)
		);

		$parsed = $this->parse_response( $response );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		return $this->normalize_response( $parsed, $block_config, 'openai', $prompt );
	}

	/**
	 * Parse OpenAI HTTP response.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return array|\WP_Error
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			if ( false !== stripos( $message, 'curl' ) || false !== stripos( $message, 'ssl' ) ) {
				return new \WP_Error(
					'openai_connection',
					__( 'Could not connect to OpenAI. Check your internet connection or Local WP external HTTP settings.', 'ai-acf-block-generator' )
				);
			}
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );

		if ( 200 !== (int) $code ) {
			$message = $body['error']['message'] ?? __( 'OpenAI API request failed.', 'ai-acf-block-generator' );
			return new \WP_Error( 'openai_error', $message, array( 'status' => $code ) );
		}

		$content = $body['choices'][0]['message']['content'] ?? '';
		$parsed  = json_decode( $content, true );

		if ( ! is_array( $parsed ) ) {
			return new \WP_Error( 'invalid_response', __( 'Invalid AI response format.', 'ai-acf-block-generator' ) );
		}

		return $parsed;
	}

	/**
	 * Get trimmed API key from settings.
	 *
	 * @return string
	 */
	private function get_api_key() {
		return trim( (string) get_option( 'aabg_openai_api_key', '' ) );
	}

	/**
	 * Model that supports vision.
	 *
	 * @return string
	 */
	private function get_vision_model() {
		$model = get_option( 'aabg_openai_model', 'gpt-4o-mini' );
		$vision_models = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo' );

		if ( in_array( $model, $vision_models, true ) ) {
			return $model;
		}

		return 'gpt-4o-mini';
	}

	/**
	 * Test API connection.
	 *
	 * @return true|\WP_Error
	 */
	public function test_connection() {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'No OpenAI API key saved.', 'ai-acf-block-generator' ) );
		}

		$model = get_option( 'aabg_openai_model', 'gpt-4o-mini' );

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'Reply with only: OK',
							),
						),
						'max_tokens' => 5,
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
			$message = $body['error']['message'] ?? __( 'OpenAI API request failed.', 'ai-acf-block-generator' );
			return new \WP_Error( 'openai_error', $message );
		}

		return true;
	}
}
