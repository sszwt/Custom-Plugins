<?php
/**
 * Frontend image URL rewriting — serves selected format from separate cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIIO_Frontend {

	/**
	 * @var AIIO_Settings
	 */
	private $settings;

	/**
	 * @var AIIO_Format_Cache
	 */
	private $cache;

	/**
	 * @param AIIO_Settings     $settings Settings.
	 * @param AIIO_Format_Cache $cache    Format cache.
	 */
	public function __construct( AIIO_Settings $settings, AIIO_Format_Cache $cache ) {
		$this->settings = $settings;
		$this->cache    = $cache;

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$format = (string) $this->settings->get( 'frontend_format' );
		if ( 'original' === $format || '' === $format ) {
			return;
		}

		add_filter( 'wp_get_attachment_image_src', array( $this, 'filter_image_src' ), 20, 4 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'filter_srcset' ), 20, 5 );
		add_filter( 'the_content', array( $this, 'filter_content_images' ), 20 );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_content_images' ), 20 );
		add_filter( 'widget_text', array( $this, 'filter_content_images' ), 20 );
		add_filter( 'widget_block_content', array( $this, 'filter_content_images' ), 20 );
	}

	/**
	 * Replace attachment image src.
	 *
	 * @param array|false  $image         Image data.
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Size.
	 * @param bool         $icon          Whether icon.
	 * @return array|false
	 */
	public function filter_image_src( $image, $attachment_id, $size, $icon ) {
		if ( $icon || ! $image || ! is_array( $image ) ) {
			return $image;
		}

		if ( $this->should_skip() ) {
			return $image;
		}

		$size_key  = is_array( $size ) ? 'full' : $size;
		$delivered = $this->cache->get_delivered( (int) $attachment_id, $size_key );

		if ( ! $delivered ) {
			return $image;
		}

		$image[0] = $delivered['url'];

		if ( ! empty( $delivered['width'] ) ) {
			$image[1] = $delivered['width'];
		}
		if ( ! empty( $delivered['height'] ) ) {
			$image[2] = $delivered['height'];
		}

		return $image;
	}

	/**
	 * Replace srcset candidates.
	 *
	 * @param array<string, array<string, mixed>> $sources       Sources.
	 * @param array<int, int>                     $size_array    Width/height.
	 * @param string                              $image_src     Image src.
	 * @param array<string, mixed>                $image_meta    Meta.
	 * @param int                                 $attachment_id Attachment ID.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( $this->should_skip() || empty( $sources ) || ! is_array( $sources ) ) {
			return $sources;
		}

		foreach ( $sources as $width => $source ) {
			$size_name = $this->find_size_name_by_width( $image_meta, (int) $width );
			$delivered = $this->cache->get_delivered( (int) $attachment_id, $size_name ? $size_name : 'full' );

			if ( $delivered ) {
				$sources[ $width ]['url'] = $delivered['url'];
			}
		}

		return $sources;
	}

	/**
	 * Rewrite <img src> URLs inside HTML that point to uploads.
	 *
	 * @param string $content HTML.
	 * @return string
	 */
	public function filter_content_images( $content ) {
		if ( $this->should_skip() || false === strpos( $content, '<img' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<img\b[^>]*>/i',
			array( $this, 'replace_img_tag' ),
			$content
		);
	}

	/**
	 * Callback: rewrite one img tag.
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string
	 */
	public function replace_img_tag( $matches ) {
		$tag = $matches[0];

		if ( ! preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $src_m ) ) {
			return $tag;
		}

		$src           = $src_m[1];
		$attachment_id = $this->url_to_attachment_id( $src );

		if ( ! $attachment_id ) {
			return $tag;
		}

		$size      = $this->guess_size_from_url( $src, $attachment_id );
		$delivered = $this->cache->get_delivered( $attachment_id, $size );

		if ( ! $delivered ) {
			return $tag;
		}

		$tag = str_replace( $src, $delivered['url'], $tag );

		// Also rewrite srcset if present.
		if ( preg_match( '/\bsrcset=["\']([^"\']+)["\']/i', $tag, $srcset_m ) ) {
			$new_srcset = $this->rewrite_srcset_string( $srcset_m[1], $attachment_id );
			if ( $new_srcset ) {
				$tag = str_replace( $srcset_m[1], $new_srcset, $tag );
			}
		}

		return $tag;
	}

	/**
	 * Rewrite a srcset attribute value.
	 *
	 * @param string $srcset         Srcset string.
	 * @param int    $attachment_id  Attachment ID.
	 * @return string|false
	 */
	private function rewrite_srcset_string( $srcset, $attachment_id ) {
		$parts  = array_map( 'trim', explode( ',', $srcset ) );
		$out    = array();
		$changed = false;

		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^(\S+)\s+(.+)$/', $part, $m ) ) {
				$out[] = $part;
				continue;
			}

			$url  = $m[1];
			$desc = $m[2];
			$size = $this->guess_size_from_url( $url, $attachment_id );
			$delivered = $this->cache->get_delivered( $attachment_id, $size );

			if ( $delivered ) {
				$out[]   = $delivered['url'] . ' ' . $desc;
				$changed = true;
			} else {
				$out[] = $part;
			}
		}

		return $changed ? implode( ', ', $out ) : false;
	}

	/**
	 * Resolve attachment ID from uploads URL.
	 *
	 * @param string $url Image URL.
	 * @return int
	 */
	private function url_to_attachment_id( $url ) {
		$url = strtok( $url, '?' );

		// Strip size suffix: image-300x200.jpg → try attachment_url_to_postid on full.
		$id = attachment_url_to_postid( $url );

		if ( $id ) {
			return (int) $id;
		}

		$stripped = preg_replace( '/-\d+x\d+(?=\.[a-zA-Z0-9]+$)/', '', $url );
		if ( $stripped && $stripped !== $url ) {
			$id = attachment_url_to_postid( $stripped );
			if ( $id ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/**
	 * Guess WP size name from URL filename.
	 *
	 * @param string $url            URL.
	 * @param int    $attachment_id  Attachment ID.
	 * @return string
	 */
	private function guess_size_from_url( $url, $attachment_id ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$file = $path ? basename( $path ) : '';

		if ( ! $file ) {
			return 'full';
		}

		$meta = wp_get_attachment_metadata( $attachment_id );

		if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
			return 'full';
		}

		foreach ( $meta['sizes'] as $name => $data ) {
			if ( ! empty( $data['file'] ) && $data['file'] === $file ) {
				return $name;
			}
		}

		if ( ! empty( $meta['file'] ) && basename( $meta['file'] ) === $file ) {
			return 'full';
		}

		return 'full';
	}

	/**
	 * Find size name by width from metadata.
	 *
	 * @param array<string, mixed> $image_meta Meta.
	 * @param int                  $width      Width.
	 * @return string|null
	 */
	private function find_size_name_by_width( $image_meta, $width ) {
		if ( empty( $image_meta['sizes'] ) || ! is_array( $image_meta['sizes'] ) ) {
			return null;
		}

		foreach ( $image_meta['sizes'] as $name => $data ) {
			if ( isset( $data['width'] ) && (int) $data['width'] === $width ) {
				return $name;
			}
		}

		return null;
	}

	/**
	 * Whether rewriting should be skipped.
	 */
	private function should_skip() {
		if ( is_feed() || is_preview() ) {
			return true;
		}

		// REST / admin-ajax for media library editing should keep originals.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && is_user_logged_in() && current_user_can( 'upload_files' ) ) {
			// Still rewrite public REST? Safer to rewrite only classic front.
			if ( ! empty( $_SERVER['HTTP_REFERER'] ) && false !== strpos( (string) $_SERVER['HTTP_REFERER'], '/wp-admin/' ) ) {
				return true;
			}
		}

		return false;
	}
}
