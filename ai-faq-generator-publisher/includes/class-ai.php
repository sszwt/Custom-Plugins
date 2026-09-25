<?php
/**
 * AI provider.
 *
 * @package AIFAQ
 */

namespace AIFAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI
 */
class AI {

	/**
	 * Generate FAQ items.
	 *
	 * @param string $topic   Topic / context.
	 * @param int    $count   Number of FAQs.
	 * @param string $tone    Tone.
	 * @param string $extra   Extra instructions.
	 * @return array|\WP_Error
	 */
	public static function generate( $topic, $count = 8, $tone = 'clear and helpful', $extra = '' ) {
		$settings = Plugin::get_settings();
		$provider = $settings['provider'] ?? 'openai';
		$count    = max( 3, min( 20, (int) $count ) );
		$topic    = trim( (string) $topic );
		$tone     = trim( (string) $tone );
		$extra    = trim( (string) $extra );

		if ( '' === $topic ) {
			return new \WP_Error( 'aifaq_empty_topic', __( 'Please enter a topic or page context.', 'ai-faq-generator-publisher' ) );
		}

		$prompt = self::build_prompt( $topic, $count, $tone, $extra );

		if ( 'gemini' === $provider ) {
			$key = $settings['gemini_key'] ?? '';
			if ( '' === $key ) {
				return new \WP_Error( 'aifaq_no_key', __( 'Add a Gemini API key in Settings.', 'ai-faq-generator-publisher' ) );
			}
			$model = $settings['gemini_model'] ?? 'gemini-2.5-flash';
			return self::call_gemini( $key, $model, $prompt );
		}

		$key = $settings['openai_key'] ?? '';
		if ( '' === $key ) {
			return new \WP_Error( 'aifaq_no_key', __( 'Add an OpenAI API key in Settings.', 'ai-faq-generator-publisher' ) );
		}
		$model = $settings['openai_model'] ?? 'gpt-4o-mini';
		return self::call_openai( $key, $model, $prompt );
	}

	/**
	 * Build prompt.
	 *
	 * @param string $topic Topic.
	 * @param int    $count Count.
	 * @param string $tone  Tone.
	 * @param string $extra Extra.
	 * @return string
	 */
	private static function build_prompt( $topic, $count, $tone, $extra ) {
		$extra_line = $extra ? "\nExtra instructions: {$extra}" : '';

		return <<<PROMPT
You are an expert content writer creating FAQ sections for websites.

Topic / page context:
{$topic}

Tone: {$tone}
{$extra_line}

Generate exactly {$count} frequently asked questions with clear, accurate answers.

Rules:
- Questions should be natural (what real visitors ask).
- Answers: 2–5 sentences, helpful, no fluff, no inventing fake credentials.
- Use plain language. No markdown headings.
- Return ONLY valid JSON in this exact shape:
{"faqs":[{"question":"...","answer":"..."}]}
PROMPT;
	}

	/**
	 * OpenAI call.
	 *
	 * @param string $key    Key.
	 * @param string $model  Model.
	 * @param string $prompt Prompt.
	 * @return array|\WP_Error
	 */
	private static function call_openai( $key, $model, $prompt ) {
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'           => $model,
						'temperature'     => 0.6,
						'response_format' => array( 'type' => 'json_object' ),
						'messages'        => array(
							array(
								'role'    => 'system',
								'content' => 'You generate FAQ JSON only.',
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'OpenAI request failed.', 'ai-faq-generator-publisher' );
			return new \WP_Error( 'aifaq_openai', $msg );
		}

		$content = $body['choices'][0]['message']['content'] ?? '';
		return self::parse_faqs( $content );
	}

	/**
	 * Gemini call.
	 *
	 * @param string $key    Key.
	 * @param string $model  Model.
	 * @param string $prompt Prompt.
	 * @return array|\WP_Error
	 */
	private static function call_gemini( $key, $model, $prompt ) {
		$url = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $model ),
			rawurlencode( $key )
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 90,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'contents'         => array(
							array(
								'parts' => array( array( 'text' => $prompt ) ),
							),
						),
						'generationConfig' => array(
							'temperature'      => 0.6,
							'responseMimeType' => 'application/json',
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Gemini request failed.', 'ai-faq-generator-publisher' );
			return new \WP_Error( 'aifaq_gemini', $msg );
		}

		$content = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
		return self::parse_faqs( $content );
	}

	/**
	 * Parse FAQ JSON.
	 *
	 * @param string $content Raw.
	 * @return array|\WP_Error
	 */
	private static function parse_faqs( $content ) {
		$content = trim( (string) $content );
		if ( preg_match( '/\{.*\}/s', $content, $m ) ) {
			$content = $m[0];
		}

		$data = json_decode( $content, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'aifaq_parse', __( 'Could not parse AI response.', 'ai-faq-generator-publisher' ) );
		}

		$list = array();
		if ( isset( $data['faqs'] ) && is_array( $data['faqs'] ) ) {
			$list = $data['faqs'];
		} elseif ( array_keys( $data ) === range( 0, count( $data ) - 1 ) ) {
			$list = $data;
		}

		$out = array();
		foreach ( $list as $row ) {
			$q = isset( $row['question'] ) ? trim( (string) $row['question'] ) : '';
			$a = isset( $row['answer'] ) ? trim( (string) $row['answer'] ) : '';
			if ( '' === $q || '' === $a ) {
				continue;
			}
			$out[] = array(
				'question' => sanitize_text_field( $q ),
				'answer'   => wp_kses_post( $a ),
			);
		}

		if ( empty( $out ) ) {
			return new \WP_Error( 'aifaq_empty', __( 'AI returned no usable FAQs.', 'ai-faq-generator-publisher' ) );
		}

		return $out;
	}
}
