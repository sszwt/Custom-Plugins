<?php
/**
 * Slug and naming helpers.
 *
 * @package AABG
 */

namespace AABG\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Slug_Helper
 */
class Slug_Helper {

	/**
	 * Generate a unique field key.
	 *
	 * @param string $prefix Prefix.
	 * @return string
	 */
	public static function field_key( $prefix = 'field' ) {
		return $prefix . '_' . wp_generate_password( 12, false, false );
	}

	/**
	 * Generate a unique group key.
	 *
	 * @return string
	 */
	public static function group_key() {
		return 'group_' . wp_generate_password( 12, false, false );
	}

	/**
	 * Create field prefix from block slug.
	 *
	 * @param string $slug Block slug.
	 * @return string
	 */
	public static function field_prefix( $slug ) {
		$slug   = Sanitizer::block_slug( $slug );
		$parts  = explode( '-', $slug );
		$prefix = '';

		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			// Skip purely numeric segments so "2-column-..." does not become "2cws".
			if ( ctype_digit( $part ) ) {
				continue;
			}
			$prefix .= substr( $part, 0, 1 );
		}

		if ( strlen( $prefix ) < 2 ) {
			$prefix = substr( str_replace( '-', '', $slug ), 0, 4 );
		}

		return Sanitizer::field_name( $prefix );
	}

	/**
	 * Generate unique block slug.
	 *
	 * @param string $name Block name.
	 * @return string
	 */
	public static function from_name( $name ) {
		return Sanitizer::block_slug( $name );
	}

	/**
	 * Generate duplicate slug.
	 *
	 * @param string $slug Original slug.
	 * @return string
	 */
	public static function duplicate_slug( $slug ) {
		$base   = $slug;
		$suffix = 2;

		while ( file_exists( File_Manager::get_block_path( $base . '-copy-' . $suffix ) ) ) {
			++$suffix;
		}

		return $base . '-copy-' . $suffix;
	}
}
