<?php
/**
 * Separate storage + conversion for frontend delivery formats.
 * Media Library originals are never modified.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIIO_Format_Cache {

	const CACHE_DIR = 'aiio-delivered';

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
	 * Allowed frontend formats.
	 *
	 * @return array<string, string>
	 */
	public static function get_format_choices() {
		return array(
			'original' => __( 'Original (no change)', 'ai-image-optimization' ),
			'webp'     => __( 'WebP', 'ai-image-optimization' ),
			'jpeg'     => __( 'JPG / JPEG', 'ai-image-optimization' ),
			'png'      => __( 'PNG', 'ai-image-optimization' ),
		);
	}

	/**
	 * Map format key to mime + extension.
	 *
	 * @param string $format Format key.
	 * @return array{mime:string,ext:string}|null
	 */
	public function get_format_meta( $format ) {
		$map = array(
			'webp' => array(
				'mime' => 'image/webp',
				'ext'  => 'webp',
			),
			'jpeg' => array(
				'mime' => 'image/jpeg',
				'ext'  => 'jpg',
			),
			'png'  => array(
				'mime' => 'image/png',
				'ext'  => 'png',
			),
		);

		return isset( $map[ $format ] ) ? $map[ $format ] : null;
	}

	/**
	 * Get quality for a format.
	 *
	 * @param string $format Format key.
	 * @return int
	 */
	public function get_quality( $format ) {
		if ( 'png' === $format ) {
			return (int) $this->settings->get( 'png_quality' );
		}
		if ( 'webp' === $format ) {
			return (int) $this->settings->get( 'webp_quality' );
		}
		return (int) $this->settings->get( 'jpeg_quality' );
	}

	/**
	 * Base cache directory (absolute).
	 *
	 * @return string
	 */
	public function get_cache_basedir() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['basedir'] ) . self::CACHE_DIR;
	}

	/**
	 * Base cache URL.
	 *
	 * @return string
	 */
	public function get_cache_baseurl() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['baseurl'] ) . self::CACHE_DIR;
	}

	/**
	 * Ensure cache folder exists.
	 *
	 * @param string $format Format key.
	 * @return bool
	 */
	public function ensure_dirs( $format ) {
		$base = $this->get_cache_basedir();
		$dir  = trailingslashit( $base ) . sanitize_key( $format );

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$index = trailingslashit( $base ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		return true;
	}

	/**
	 * Build relative cache path for an attachment size.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Size name or 'full'.
	 * @param string $format        Format key.
	 * @return string Relative path under cache dir.
	 */
	public function get_relative_path( $attachment_id, $size, $format ) {
		$meta = $this->get_format_meta( $format );
		$ext  = $meta ? $meta['ext'] : 'jpg';
		$size = sanitize_key( (string) $size );
		if ( '' === $size ) {
			$size = 'full';
		}

		return sanitize_key( $format ) . '/' . (int) $attachment_id . '-' . $size . '.' . $ext;
	}

	/**
	 * Get source file path for attachment size (original media — read only).
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string|array $size          Size.
	 * @return array{path:string,width:int,height:int}|WP_Error
	 */
	public function get_source_file( $attachment_id, $size = 'full' ) {
		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			return new WP_Error( 'aiio_no_source', __( 'Original image not found.', 'ai-image-optimization' ) );
		}

		$width  = 0;
		$height = 0;

		if ( 'full' === $size || empty( $size ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			$width  = (int) ( $meta['width'] ?? 0 );
			$height = (int) ( $meta['height'] ?? 0 );

			return array(
				'path'   => $path,
				'width'  => $width,
				'height' => $height,
			);
		}

		$image = image_get_intermediate_size( $attachment_id, $size );

		if ( $image && ! empty( $image['path'] ) ) {
			$upload    = wp_upload_dir();
			$full_path = path_join( $upload['basedir'], $image['path'] );

			if ( ! file_exists( $full_path ) ) {
				$full_path = path_join( dirname( $path ), basename( $image['file'] ?? $image['path'] ) );
			}

			if ( file_exists( $full_path ) ) {
				return array(
					'path'   => $full_path,
					'width'  => (int) ( $image['width'] ?? 0 ),
					'height' => (int) ( $image['height'] ?? 0 ),
				);
			}
		}

		if ( $image && ! empty( $image['file'] ) ) {
			$upload = wp_upload_dir();
			$maybe  = path_join( dirname( $path ), $image['file'] );

			if ( ! file_exists( $maybe ) ) {
				$maybe = trailingslashit( $upload['basedir'] ) . ltrim( $image['file'], '/' );
			}

			if ( file_exists( $maybe ) ) {
				return array(
					'path'   => $maybe,
					'width'  => (int) ( $image['width'] ?? 0 ),
					'height' => (int) ( $image['height'] ?? 0 ),
				);
			}
		}

		// Last resort: full original.
		$meta = wp_get_attachment_metadata( $attachment_id );
		return array(
			'path'   => $path,
			'width'  => (int) ( $meta['width'] ?? 0 ),
			'height' => (int) ( $meta['height'] ?? 0 ),
		);
	}

	/**
	 * Get or create delivered URL for attachment size.
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string|array $size          Size name.
	 * @return array{url:string,width:int,height:int,mime:string}|false
	 */
	public function get_delivered( $attachment_id, $size = 'full' ) {
		$format = (string) $this->settings->get( 'frontend_format' );

		if ( 'original' === $format || '' === $format ) {
			return false;
		}

		$format_meta = $this->get_format_meta( $format );
		if ( ! $format_meta ) {
			return false;
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		$size_key = is_array( $size ) ? 'custom-' . implode( 'x', array_map( 'absint', $size ) ) : (string) $size;
		if ( '' === $size_key ) {
			$size_key = 'full';
		}

		$relative = $this->get_relative_path( $attachment_id, $size_key, $format );
		$abs      = trailingslashit( $this->get_cache_basedir() ) . $relative;
		$url      = trailingslashit( $this->get_cache_baseurl() ) . str_replace( '\\', '/', $relative );

		$source = $this->get_source_file( $attachment_id, is_array( $size ) ? 'full' : $size );

		if ( is_wp_error( $source ) ) {
			return false;
		}

		// If source is already the target mime and format is same type, still create cache copy for consistency? Prefer convert always to cache.
		if ( file_exists( $abs ) && filesize( $abs ) > 0 ) {
			return array(
				'url'    => $url,
				'width'  => $source['width'],
				'height' => $source['height'],
				'mime'   => $format_meta['mime'],
			);
		}

		$created = $this->create_converted_file( $source['path'], $abs, $format );

		if ( is_wp_error( $created ) || ! $created ) {
			return false;
		}

		return array(
			'url'    => $url,
			'width'  => $source['width'],
			'height' => $source['height'],
			'mime'   => $format_meta['mime'],
		);
	}

	/**
	 * Convert source file into cache path. Never touches the source.
	 *
	 * @param string $source_path Source absolute path.
	 * @param string $dest_path   Destination absolute path.
	 * @param string $format      Format key.
	 * @return true|WP_Error
	 */
	public function create_converted_file( $source_path, $dest_path, $format ) {
		$format_meta = $this->get_format_meta( $format );

		if ( ! $format_meta ) {
			return new WP_Error( 'aiio_bad_format', __( 'Invalid format.', 'ai-image-optimization' ) );
		}

		if ( ! $this->ensure_dirs( $format ) ) {
			return new WP_Error( 'aiio_mkdir', __( 'Could not create cache folder.', 'ai-image-optimization' ) );
		}

		$dest_dir = dirname( $dest_path );
		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return new WP_Error( 'aiio_mkdir', __( 'Could not create cache folder.', 'ai-image-optimization' ) );
		}

		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			return new WP_Error( 'aiio_no_editor', __( 'Image editor not available.', 'ai-image-optimization' ) );
		}

		// Copy source to a temp working file if converting PNG→JPEG (transparency).
		$editor = wp_get_image_editor( $source_path );

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$editor->set_quality( $this->get_quality( $format ) );

		// For JPEG from transparent PNG, flatten onto white.
		if ( 'jpeg' === $format && method_exists( $editor, 'get_image' ) ) {
			// Imagick/GD specifics handled by save mime; try GD flatten via filter if needed.
		}

		$saved = $editor->save( $dest_path, $format_meta['mime'] );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		if ( empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
			return new WP_Error( 'aiio_save_failed', __( 'Could not save converted image.', 'ai-image-optimization' ) );
		}

		// If editor saved to a slightly different path, move into expected dest.
		if ( wp_normalize_path( $saved['path'] ) !== wp_normalize_path( $dest_path ) ) {
			if ( ! @rename( $saved['path'], $dest_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				@copy( $saved['path'], $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}

		return true;
	}

	/**
	 * Count files in cache.
	 *
	 * @return int
	 */
	public function count_cached_files() {
		$base = $this->get_cache_basedir();

		if ( ! is_dir( $base ) ) {
			return 0;
		}

		$count = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' !== strtolower( $file->getExtension() ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Clear entire delivery cache.
	 *
	 * @return int Number of deleted files.
	 */
	public function clear_cache() {
		$base = $this->get_cache_basedir();

		if ( ! is_dir( $base ) ) {
			return 0;
		}

		$deleted  = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			$path = $file->getPathname();
			if ( $file->isFile() ) {
				if ( @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
					++$deleted;
				}
			} elseif ( $file->isDir() ) {
				@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}

		return $deleted;
	}
}
