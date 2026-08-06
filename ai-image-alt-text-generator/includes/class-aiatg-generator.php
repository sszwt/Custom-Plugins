<?php
/**
 * ALT text generation logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIATG_Generator {

	/**
	 * @var AIATG_Settings
	 */
	private $settings;

	/**
	 * @param AIATG_Settings $settings Plugin settings.
	 */
	public function __construct( AIATG_Settings $settings ) {
		$this->settings = $settings;

		add_action( 'add_attachment', array( $this, 'maybe_generate_on_upload' ), 20 );
	}

	/**
	 * Generate ALT text when image is uploaded (if enabled).
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function maybe_generate_on_upload( $attachment_id ) {
		if ( ! $this->settings->get( 'auto_on_upload' ) ) {
			return;
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$this->generate_for_attachment( $attachment_id, false );
	}

	/**
	 * Generate and save ALT text for an attachment.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force         Overwrite existing ALT text.
	 * @return array{success:bool,alt_text?:string,message?:string,source?:string}
	 */
	public function generate_for_attachment( $attachment_id, $force = false ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Attachment is not an image.', 'ai-image-alt-text-generator' ),
			);
		}

		$existing = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		if ( ! $force && ! empty( $existing ) ) {
			return array(
				'success'  => true,
				'alt_text' => $existing,
				'message'  => __( 'ALT text already exists.', 'ai-image-alt-text-generator' ),
				'source'   => 'existing',
			);
		}

		$result = $this->generate_alt_text( $attachment_id );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $result['text'] );

		return array(
			'success'  => true,
			'alt_text' => $result['text'],
			'message'  => __( 'ALT text generated successfully.', 'ai-image-alt-text-generator' ),
			'source'   => $result['source'],
		);
	}

	/**
	 * Generate ALT text string for an image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{text:string,source:string}|WP_Error
	 */
	public function generate_alt_text( $attachment_id ) {
		if ( $this->settings->is_configured() ) {
			$provider = $this->settings->get( 'api_provider' );

			if ( 'gemini' === $provider ) {
				$ai_result = $this->generate_with_gemini( $attachment_id );
			} else {
				$ai_result = $this->generate_with_openai( $attachment_id );
			}

			if ( ! is_wp_error( $ai_result ) ) {
				return array(
					'text'   => $ai_result,
					'source' => $provider,
				);
			}
		}

		return array(
			'text'   => $this->generate_fallback( $attachment_id ),
			'source' => 'fallback',
		);
	}

	/**
	 * Build prompt with language hint.
	 *
	 * @return string
	 */
	private function build_prompt() {
		$prompt   = $this->settings->get( 'prompt' );
		$language = $this->settings->get( 'language' );

		if ( ! empty( $language ) && 'en' !== $language ) {
			$prompt .= ' Respond in language code: ' . $language . '.';
		}

		return $prompt;
	}

	/**
	 * Call OpenAI Vision API using local file (works on localhost).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|WP_Error
	 */
	private function generate_with_openai( $attachment_id ) {
		$image = AIATG_Image_Helper::get_attachment_image_data( $attachment_id );

		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$body = array(
			'model'    => $this->settings->get( 'model' ),
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $this->build_prompt(),
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
			'max_tokens' => 80,
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->settings->get_api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		return $this->parse_openai_response( $response );
	}

	/**
	 * Call Google Gemini Vision API.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|WP_Error
	 */
	private function generate_with_gemini( $attachment_id ) {
		$image = AIATG_Image_Helper::get_attachment_image_data( $attachment_id );

		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$api_key = $this->settings->get_api_key();
		$model   = $this->settings->get( 'gemini_model' );
		$url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $this->build_prompt() ),
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
				'maxOutputTokens' => 80,
				'temperature'     => 0.4,
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 45,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Gemini API request failed.', 'ai-image-alt-text-generator' );
			return new WP_Error( 'aiatg_api_error', $error );
		}

		$content = isset( $data['candidates'][0]['content']['parts'][0]['text'] )
			? trim( $data['candidates'][0]['content']['parts'][0]['text'] )
			: '';

		if ( empty( $content ) ) {
			return new WP_Error( 'aiatg_empty', __( 'AI returned empty ALT text.', 'ai-image-alt-text-generator' ) );
		}

		return $this->sanitize_alt_text( $content );
	}

	/**
	 * Parse OpenAI HTTP response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return string|WP_Error
	 */
	private function parse_openai_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'OpenAI API request failed.', 'ai-image-alt-text-generator' );
			return new WP_Error( 'aiatg_api_error', $error );
		}

		$content = isset( $data['choices'][0]['message']['content'] ) ? trim( $data['choices'][0]['message']['content'] ) : '';

		if ( empty( $content ) ) {
			return new WP_Error( 'aiatg_empty', __( 'AI returned empty ALT text.', 'ai-image-alt-text-generator' ) );
		}

		return $this->sanitize_alt_text( $content );
	}

	/**
	 * Test API connection with a minimal request.
	 *
	 * @return true|WP_Error
	 */
	public function test_api_connection() {
		if ( ! $this->settings->is_configured() ) {
			return new WP_Error( 'aiatg_no_key', __( 'API key is not configured.', 'ai-image-alt-text-generator' ) );
		}

		if ( 'gemini' === $this->settings->get( 'api_provider' ) ) {
			$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $this->settings->get( 'gemini_model' ) ) . ':generateContent?key=' . rawurlencode( $this->settings->get_api_key() );
			$body = array(
				'contents' => array(
					array( 'parts' => array( array( 'text' => 'Reply with OK only.' ) ) ),
				),
			);

			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 20,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( $body ),
				)
			);
		} else {
			$body = array(
				'model'      => $this->settings->get( 'model' ),
				'messages'   => array(
					array( 'role' => 'user', 'content' => 'Reply with OK only.' ),
				),
				'max_tokens' => 5,
			);

			$response = wp_remote_post(
				'https://api.openai.com/v1/chat/completions',
				array(
					'timeout' => 20,
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->settings->get_api_key(),
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'API test failed.', 'ai-image-alt-text-generator' );
			return new WP_Error( 'aiatg_test_failed', $error );
		}

		return true;
	}

	/**
	 * Fallback ALT text from filename/title when API is unavailable.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function generate_fallback( $attachment_id ) {
		$post = get_post( $attachment_id );

		if ( ! $post ) {
			return __( 'Image', 'ai-image-alt-text-generator' );
		}

		$title = trim( $post->post_title );

		if ( ! empty( $title ) && 'auto-draft' !== $title ) {
			return $this->sanitize_alt_text( $title );
		}

		$filename = basename( get_attached_file( $attachment_id ) );
		$filename = preg_replace( '/\.[^.]+$/', '', $filename );
		$filename = str_replace( array( '-', '_' ), ' ', $filename );
		$filename = ucwords( trim( $filename ) );

		if ( ! empty( $filename ) ) {
			return $this->sanitize_alt_text( $filename );
		}

		return __( 'Image', 'ai-image-alt-text-generator' );
	}

	/**
	 * Clean and limit ALT text.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function sanitize_alt_text( $text ) {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/^(image of|a photo of|picture of)\s+/i', '', $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );

		$max = (int) $this->settings->get( 'max_length' );

		if ( $max > 0 && strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max - 3 ) . '...';
		}

		return $text;
	}

	/**
	 * Count images missing ALT text.
	 *
	 * @return int
	 */
	public function count_missing_alt_text() {
		global $wpdb;

		$sql = "
			SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'
			AND (pm.meta_value IS NULL OR pm.meta_value = '')
		";

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get attachment IDs missing ALT text.
	 *
	 * @param int $limit Max results.
	 * @return int[]
	 */
	public function get_missing_attachment_ids( $limit = 10 ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_wp_attachment_image_alt',
						'value'   => '',
						'compare' => '=',
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}
}
