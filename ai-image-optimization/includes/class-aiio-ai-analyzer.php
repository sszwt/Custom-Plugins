<?php
/**
 * AI analyzer for smart quality recommendations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIIO_AI_Analyzer {

	/**
	 * @var AIIO_Settings
	 */
	private $settings;

	/**
	 * @param AIIO_Settings $settings Plugin settings.
	 */
	public function __construct( AIIO_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Suggest JPEG/WebP quality based on image content.
	 *
	 * Returns quality 40–95, or null on failure.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int|null
	 */
	public function suggest_quality( $attachment_id ) {
		if ( ! $this->settings->is_ai_configured() ) {
			return null;
		}

		$image = $this->get_image_payload( $attachment_id );

		if ( is_wp_error( $image ) ) {
			return null;
		}

		$prompt = 'Analyze this image for web compression. Reply with ONLY a JSON object like {"quality":82,"type":"photo"} where quality is an integer 50-92 and type is one of: photo, screenshot, graphic, illustration, document. Use lower quality (55-70) for screenshots/simple graphics, medium (75-82) for photos, higher (85-92) for detailed photos or text-heavy images.';

		$provider = $this->settings->get( 'api_provider' );
		$text     = 'gemini' === $provider
			? $this->call_gemini( $image, $prompt )
			: $this->call_openai( $image, $prompt );

		if ( is_wp_error( $text ) || empty( $text ) ) {
			return null;
		}

		$quality = $this->parse_quality( $text );

		return null !== $quality ? $quality : null;
	}

	/**
	 * Test API connection.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		if ( ! $this->settings->is_ai_configured() ) {
			return new WP_Error( 'aiio_no_key', __( 'API key is missing.', 'ai-image-optimization' ) );
		}

		$provider = $this->settings->get( 'api_provider' );

		if ( 'gemini' === $provider ) {
			$model = sanitize_text_field( (string) $this->settings->get( 'gemini_model' ) );
			$key   = $this->settings->get_api_key();
			$url   = add_query_arg(
				'key',
				$key,
				'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent'
			);

			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 30,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'contents' => array(
								array(
									'parts' => array(
										array( 'text' => 'Reply with OK' ),
									),
								),
							),
						)
					),
				)
			);
		} else {
			$response = wp_remote_post(
				'https://api.openai.com/v1/chat/completions',
				array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->settings->get_api_key(),
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'    => $this->settings->get( 'model' ),
							'messages' => array(
								array(
									'role'    => 'user',
									'content' => 'Reply with OK',
								),
							),
							'max_tokens' => 5,
						)
					),
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg  = $body['error']['message'] ?? __( 'API request failed.', 'ai-image-optimization' );
			return new WP_Error( 'aiio_api_error', $msg );
		}

		return true;
	}

	/**
	 * Build resized base64 payload for vision APIs.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{mime:string,data:string}|WP_Error
	 */
	private function get_image_payload( $attachment_id ) {
		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			return new WP_Error( 'aiio_no_file', __( 'Image file not found.', 'ai-image-optimization' ) );
		}

		$mime = get_post_mime_type( $attachment_id );

		if ( ! $mime || 0 !== strpos( $mime, 'image/' ) ) {
			return new WP_Error( 'aiio_invalid_mime', __( 'Invalid image type.', 'ai-image-optimization' ) );
		}

		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return new WP_Error( 'aiio_read_failed', __( 'Could not read image.', 'ai-image-optimization' ) );
		}

		$size = @getimagesize( $path );

		if ( $size && max( $size[0], $size[1] ) > 768 && function_exists( 'wp_get_image_editor' ) ) {
			$editor = wp_get_image_editor( $path );

			if ( ! is_wp_error( $editor ) ) {
				$editor->resize( 768, 768, false );
				$saved = $editor->save();

				if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
					$resized = file_get_contents( $saved['path'] );

					if ( false !== $resized ) {
						$contents = $resized;
						$mime     = $saved['mime-type'] ?? $mime;
					}

					if ( $saved['path'] !== $path && file_exists( $saved['path'] ) ) {
						@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					}
				}
			}
		}

		return array(
			'mime' => $mime,
			'data' => base64_encode( $contents ),
		);
	}

	/**
	 * Call Gemini vision.
	 *
	 * @param array{mime:string,data:string} $image  Image payload.
	 * @param string                         $prompt Prompt text.
	 * @return string|WP_Error
	 */
	private function call_gemini( $image, $prompt ) {
		$model = sanitize_text_field( (string) $this->settings->get( 'gemini_model' ) );
		$url   = add_query_arg(
			'key',
			$this->settings->get_api_key(),
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent'
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'contents' => array(
							array(
								'parts' => array(
									array( 'text' => $prompt ),
									array(
										'inline_data' => array(
											'mime_type' => $image['mime'],
											'data'      => $image['data'],
										),
									),
								),
							),
						),
						'generationConfig' => array(
							'temperature'     => 0.2,
							'maxOutputTokens' => 100,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

		return is_string( $text ) ? $text : '';
	}

	/**
	 * Call OpenAI vision.
	 *
	 * @param array{mime:string,data:string} $image  Image payload.
	 * @param string                         $prompt Prompt text.
	 * @return string|WP_Error
	 */
	private function call_openai( $image, $prompt ) {
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->settings->get_api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'    => $this->settings->get( 'model' ),
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => array(
									array(
										'type' => 'text',
										'text' => $prompt,
									),
									array(
										'type'      => 'image_url',
										'image_url' => array(
											'url' => 'data:' . $image['mime'] . ';base64,' . $image['data'],
										),
									),
								),
							),
						),
						'max_tokens'  => 100,
						'temperature' => 0.2,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = $body['choices'][0]['message']['content'] ?? '';

		return is_string( $text ) ? $text : '';
	}

	/**
	 * Parse quality integer from model response.
	 *
	 * @param string $text Model text.
	 * @return int|null
	 */
	private function parse_quality( $text ) {
		if ( preg_match( '/\{[^}]*"quality"\s*:\s*(\d+)/', $text, $m ) ) {
			$q = (int) $m[1];
			return min( 95, max( 50, $q ) );
		}

		if ( preg_match( '/\b(\d{2})\b/', $text, $m ) ) {
			$q = (int) $m[1];
			if ( $q >= 50 && $q <= 95 ) {
				return $q;
			}
		}

		return null;
	}
}
