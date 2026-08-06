<?php
/**
 * SEO + Accessibility image standardization.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIIO_Standardizer {

	/**
	 * @var AIIO_Settings
	 */
	private $settings;

	/**
	 * @var AIIO_AI_Analyzer
	 */
	private $analyzer;

	/**
	 * Track first content image to skip lazy-load (LCP).
	 *
	 * @var bool
	 */
	private $first_content_image_done = false;

	/**
	 * @param AIIO_Settings     $settings Settings.
	 * @param AIIO_AI_Analyzer  $analyzer AI analyzer (for optional ALT).
	 */
	public function __construct( AIIO_Settings $settings, AIIO_AI_Analyzer $analyzer ) {
		$this->settings = $settings;
		$this->analyzer = $analyzer;

		if ( $this->settings->get( 'seo_auto_alt_on_upload' ) ) {
			add_action( 'add_attachment', array( $this, 'maybe_fill_alt_on_upload' ), 25 );
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( $this->any_frontend_feature_enabled() ) {
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_image_attributes' ), 20, 3 );
			add_filter( 'the_content', array( $this, 'filter_content_img_attrs' ), 25 );
			add_filter( 'post_thumbnail_html', array( $this, 'filter_thumbnail_html' ), 25 );
			add_filter( 'widget_text', array( $this, 'filter_content_img_attrs' ), 25 );
			add_filter( 'widget_block_content', array( $this, 'filter_content_img_attrs' ), 25 );
		}
	}

	/**
	 * Whether any frontend SEO/a11y feature is on.
	 */
	private function any_frontend_feature_enabled() {
		return $this->settings->get( 'a11y_lazy_load' )
			|| $this->settings->get( 'a11y_async_decode' )
			|| $this->settings->get( 'a11y_force_dimensions' )
			|| $this->settings->get( 'seo_add_title_attr' )
			|| $this->settings->get( 'seo_ensure_alt_frontend' );
	}

	/**
	 * Fill missing ALT when image is uploaded.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function maybe_fill_alt_on_upload( $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$this->ensure_alt_text( $attachment_id, false );
	}

	/**
	 * Ensure attachment has ALT text.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force         Overwrite existing.
	 * @return array{success:bool,alt_text?:string,message?:string,source?:string}
	 */
	public function ensure_alt_text( $attachment_id, $force = false ) {
		$existing = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		if ( ! $force && '' !== (string) $existing && null !== $existing ) {
			// Allow intentionally empty alt for decorative — treat non-empty only.
			if ( strlen( trim( (string) $existing ) ) > 0 ) {
				return array(
					'success'  => true,
					'alt_text' => $existing,
					'source'   => 'existing',
					'message'  => __( 'ALT text already set.', 'ai-image-optimization' ),
				);
			}
		}

		$alt    = '';
		$source = 'fallback';

		if ( $this->settings->get( 'seo_use_ai_alt' ) && $this->settings->is_ai_configured() ) {
			$ai_alt = $this->generate_ai_alt( $attachment_id );
			if ( $ai_alt ) {
				$alt    = $ai_alt;
				$source = 'ai';
			}
		}

		if ( '' === $alt ) {
			$alt = $this->generate_fallback_alt( $attachment_id );
		}

		$alt = $this->normalize_alt( $alt );

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );

		return array(
			'success'  => true,
			'alt_text' => $alt,
			'source'   => $source,
			'message'  => __( 'ALT text standardized.', 'ai-image-optimization' ),
		);
	}

	/**
	 * Normalize ALT for SEO/a11y.
	 *
	 * @param string $alt Raw alt.
	 * @return string
	 */
	public function normalize_alt( $alt ) {
		$alt = wp_strip_all_tags( (string) $alt );
		$alt = preg_replace( '/\s+/', ' ', $alt );
		$alt = trim( $alt );

		// Remove common useless prefixes.
		$alt = preg_replace( '/^(image of|a photo of|a picture of|photo of)\s+/i', '', $alt );

		$max = (int) $this->settings->get( 'seo_alt_max_length' );
		if ( $max > 0 && mb_strlen( $alt ) > $max ) {
			$alt = rtrim( mb_substr( $alt, 0, $max - 1 ) ) . '…';
		}

		return $alt;
	}

	/**
	 * Fallback ALT from title / filename / caption.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public function generate_fallback_alt( $attachment_id ) {
		$post = get_post( $attachment_id );

		if ( $post && ! empty( $post->post_excerpt ) ) {
			return $this->normalize_alt( $post->post_excerpt );
		}

		if ( $post && ! empty( $post->post_title ) && ! $this->looks_like_filename( $post->post_title ) ) {
			return $this->normalize_alt( $post->post_title );
		}

		$file = get_attached_file( $attachment_id );
		$name = $file ? pathinfo( $file, PATHINFO_FILENAME ) : '';
		$name = urldecode( $name );
		$name = preg_replace( '/[-_]+/', ' ', $name );
		$name = preg_replace( '/\d{2,}/', ' ', $name );
		$name = preg_replace( '/\s+/', ' ', $name );
		$name = trim( $name );

		if ( '' === $name && $post ) {
			$name = $post->post_title;
		}

		return $this->normalize_alt( ucwords( $name ) );
	}

	/**
	 * Whether string looks like a raw filename.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	private function looks_like_filename( $text ) {
		return (bool) preg_match( '/\.(jpe?g|png|gif|webp)$/i', $text )
			|| (bool) preg_match( '/^IMG[-_]?\d+/i', $text )
			|| (bool) preg_match( '/^DSC[-_]?\d+/i', $text );
	}

	/**
	 * Generate ALT via AI vision (Gemini/OpenAI).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function generate_ai_alt( $attachment_id ) {
		// Soft reuse: ask analyzer path via temporary prompt through public suggest — better dedicated call.
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return '';
		}

		$max     = (int) $this->settings->get( 'seo_alt_max_length' );
		$lang    = sanitize_text_field( (string) $this->settings->get( 'seo_alt_language' ) );
		$lang    = $lang ? $lang : 'en';
		$prompt  = sprintf(
			'Write concise HTML image alt text for accessibility and SEO in language "%1$s". Max %2$d characters. Describe the main subject. Do not start with "Image of" or "A photo of". Reply with alt text only, no quotes.',
			$lang,
			$max > 0 ? $max : 125
		);

		$provider = $this->settings->get( 'api_provider' );
		$text     = $this->call_vision_for_alt( $attachment_id, $prompt, $provider );

		return is_string( $text ) ? $this->normalize_alt( $text ) : '';
	}

	/**
	 * Vision API call for ALT.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $prompt        Prompt.
	 * @param string $provider      Provider.
	 * @return string
	 */
	private function call_vision_for_alt( $attachment_id, $prompt, $provider ) {
		$path = get_attached_file( $attachment_id );
		$mime = get_post_mime_type( $attachment_id );
		$data = @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( false === $data || ! $mime ) {
			return '';
		}

		// Shrink for API cost.
		if ( function_exists( 'wp_get_image_editor' ) ) {
			$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( $size && max( $size[0], $size[1] ) > 768 ) {
				$editor = wp_get_image_editor( $path );
				if ( ! is_wp_error( $editor ) ) {
					$editor->resize( 768, 768, false );
					$saved = $editor->save();
					if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
						$resized = file_get_contents( $saved['path'] );
						if ( false !== $resized ) {
							$data = $resized;
							$mime = $saved['mime-type'] ?? $mime;
						}
						if ( $saved['path'] !== $path && file_exists( $saved['path'] ) ) {
							@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
						}
					}
				}
			}
		}

		$b64 = base64_encode( $data );

		if ( 'gemini' === $provider ) {
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
												'mime_type' => $mime,
												'data'      => $b64,
											),
										),
									),
								),
							),
							'generationConfig' => array(
								'temperature'     => 0.2,
								'maxOutputTokens' => 80,
							),
						)
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return '';
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return trim( (string) ( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' ) );
		}

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
						'model'      => $this->settings->get( 'model' ),
						'messages'   => array(
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
											'url' => 'data:' . $mime . ';base64,' . $b64,
										),
									),
								),
							),
						),
						'max_tokens' => 80,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return trim( (string) ( $body['choices'][0]['message']['content'] ?? '' ) );
	}

	/**
	 * Filter attributes for wp_get_attachment_image().
	 *
	 * @param array<string,string> $attr       Attributes.
	 * @param WP_Post              $attachment Attachment.
	 * @param string|int[]         $size       Size.
	 * @return array<string,string>
	 */
	public function filter_image_attributes( $attr, $attachment, $size ) {
		$id = (int) $attachment->ID;

		if ( $this->settings->get( 'seo_ensure_alt_frontend' ) ) {
			$alt = isset( $attr['alt'] ) ? trim( (string) $attr['alt'] ) : '';
			if ( '' === $alt ) {
				$stored = get_post_meta( $id, '_wp_attachment_image_alt', true );
				if ( '' === trim( (string) $stored ) && $this->settings->get( 'seo_auto_alt' ) ) {
					$result = $this->ensure_alt_text( $id, false );
					$stored = $result['alt_text'] ?? '';
				}
				$attr['alt'] = (string) $stored;
			} else {
				$attr['alt'] = $this->normalize_alt( $alt );
			}
		}

		if ( $this->settings->get( 'seo_add_title_attr' ) && empty( $attr['title'] ) ) {
			$attr['title'] = ! empty( $attr['alt'] ) ? $attr['alt'] : get_the_title( $id );
		}

		$skip_lazy = $this->should_skip_lazy_for_attachment( $id );

		if ( $this->settings->get( 'a11y_lazy_load' ) && ! $skip_lazy ) {
			$attr['loading'] = 'lazy';
		} elseif ( $skip_lazy ) {
			$attr['loading'] = 'eager';
			$attr['fetchpriority'] = 'high';
		}

		if ( $this->settings->get( 'a11y_async_decode' ) ) {
			$attr['decoding'] = 'async';
		}

		if ( $this->settings->get( 'a11y_force_dimensions' ) ) {
			if ( empty( $attr['width'] ) || empty( $attr['height'] ) ) {
				$src = wp_get_attachment_image_src( $id, $size );
				if ( $src ) {
					$attr['width']  = (string) $src[1];
					$attr['height'] = (string) $src[2];
				}
			}
		}

		return $attr;
	}

	/**
	 * Featured / first image: skip lazy for LCP.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function should_skip_lazy_for_attachment( $attachment_id ) {
		if ( ! $this->settings->get( 'a11y_skip_lazy_lcp' ) ) {
			return false;
		}

		if ( is_singular() && (int) get_post_thumbnail_id() === (int) $attachment_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Filter post thumbnail HTML (LCP eager).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public function filter_thumbnail_html( $html ) {
		$html = $this->filter_content_img_attrs( $html, true );
		return $html;
	}

	/**
	 * Add missing SEO/a11y attrs inside HTML img tags.
	 *
	 * @param string $content   HTML.
	 * @param bool   $is_lcp    Treat as LCP candidate.
	 * @return string
	 */
	public function filter_content_img_attrs( $content, $is_lcp = false ) {
		if ( false === strpos( $content, '<img' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $m ) use ( $is_lcp ) {
				return $this->enhance_img_tag( $m[0], $is_lcp );
			},
			$content
		);
	}

	/**
	 * Enhance a single img tag string.
	 *
	 * @param string $tag    Img tag.
	 * @param bool   $is_lcp LCP context.
	 * @return string
	 */
	private function enhance_img_tag( $tag, $is_lcp = false ) {
		$skip_lazy = $is_lcp;
		if ( ! $skip_lazy && $this->settings->get( 'a11y_skip_lazy_lcp' ) && ! $this->first_content_image_done ) {
			$skip_lazy = true;
			$this->first_content_image_done = true;
		}

		// ALT.
		if ( $this->settings->get( 'seo_ensure_alt_frontend' ) ) {
			if ( ! preg_match( '/\balt=/i', $tag ) ) {
				$alt = '';
				if ( preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $src_m ) ) {
					$id = $this->url_to_id( $src_m[1] );
					if ( $id ) {
						$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
						if ( '' === trim( (string) $alt ) && $this->settings->get( 'seo_auto_alt' ) ) {
							$res = $this->ensure_alt_text( $id, false );
							$alt = $res['alt_text'] ?? '';
						}
					}
				}
				$tag = preg_replace( '/<img\b/i', '<img alt="' . esc_attr( (string) $alt ) . '"', $tag, 1 );
			}
		}

		// Title.
		if ( $this->settings->get( 'seo_add_title_attr' ) && ! preg_match( '/\btitle=/i', $tag ) ) {
			$title = '';
			if ( preg_match( '/\balt=["\']([^"\']*)["\']/i', $tag, $alt_m ) ) {
				$title = $alt_m[1];
			}
			if ( $title ) {
				$tag = preg_replace( '/<img\b/i', '<img title="' . esc_attr( $title ) . '"', $tag, 1 );
			}
		}

		// Lazy / eager.
		if ( $this->settings->get( 'a11y_lazy_load' ) ) {
			if ( $skip_lazy ) {
				$tag = $this->set_attr( $tag, 'loading', 'eager' );
				$tag = $this->set_attr( $tag, 'fetchpriority', 'high' );
			} else {
				$tag = $this->set_attr( $tag, 'loading', 'lazy' );
			}
		}

		if ( $this->settings->get( 'a11y_async_decode' ) ) {
			$tag = $this->set_attr( $tag, 'decoding', 'async' );
		}

		// Dimensions from attachment if missing.
		if ( $this->settings->get( 'a11y_force_dimensions' ) ) {
			$has_w = preg_match( '/\bwidth=/i', $tag );
			$has_h = preg_match( '/\bheight=/i', $tag );
			if ( ( ! $has_w || ! $has_h ) && preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $src_m ) ) {
				$id = $this->url_to_id( $src_m[1] );
				if ( $id ) {
					$src = wp_get_attachment_image_src( $id, 'full' );
					if ( $src ) {
						if ( ! $has_w ) {
							$tag = $this->set_attr( $tag, 'width', (string) $src[1] );
						}
						if ( ! $has_h ) {
							$tag = $this->set_attr( $tag, 'height', (string) $src[2] );
						}
					}
				}
			}
		}

		return $tag;
	}

	/**
	 * Set or replace an HTML attribute on img tag.
	 *
	 * @param string $tag   Tag.
	 * @param string $name  Attr name.
	 * @param string $value Value.
	 * @return string
	 */
	private function set_attr( $tag, $name, $value ) {
		$pattern = '/\s' . preg_quote( $name, '/' ) . '=(["\'])[^"\']*\1/i';
		if ( preg_match( $pattern, $tag ) ) {
			return preg_replace( $pattern, ' ' . $name . '="' . esc_attr( $value ) . '"', $tag, 1 );
		}
		return preg_replace( '/<img\b/i', '<img ' . $name . '="' . esc_attr( $value ) . '"', $tag, 1 );
	}

	/**
	 * URL to attachment ID.
	 *
	 * @param string $url URL.
	 * @return int
	 */
	private function url_to_id( $url ) {
		$url = strtok( $url, '?' );
		$id  = attachment_url_to_postid( $url );
		if ( $id ) {
			return (int) $id;
		}
		$stripped = preg_replace( '/-\d+x\d+(?=\.[a-zA-Z0-9]+$)/', '', $url );
		if ( $stripped && $stripped !== $url ) {
			return (int) attachment_url_to_postid( $stripped );
		}
		// Delivered cache URLs: .../aiio-delivered/webp/123-full.webp
		if ( preg_match( '/aiio-delivered\/(?:webp|jpeg|png)\/(\d+)-/i', $url, $m ) ) {
			return (int) $m[1];
		}
		return 0;
	}

	/**
	 * Count images missing ALT.
	 *
	 * @return int
	 */
	public function count_missing_alt() {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 1,
				'fields'         => 'ids',
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

		return (int) $query->found_posts;
	}

	/**
	 * Get IDs missing ALT.
	 *
	 * @param int $limit Batch.
	 * @return int[]
	 */
	public function get_missing_alt_ids( $limit = 5 ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
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
