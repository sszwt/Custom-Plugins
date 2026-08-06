<?php
/**
 * Sanitization helpers.
 *
 * @package AABG
 */

namespace AABG\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sanitizer
 */
class Sanitizer {

	/**
	 * Sanitize block slug.
	 *
	 * CSS class names and folder names must not start with a digit.
	 *
	 * @param string $slug Raw slug.
	 * @return string
	 */
	public static function block_slug( $slug ) {
		$slug = sanitize_title( $slug );
		$slug = trim( (string) $slug, '-' );

		if ( '' === $slug ) {
			return 'custom-block';
		}

		// Leading digit breaks CSS selectors and PHP vars derived from the slug.
		if ( preg_match( '/^[0-9]/', $slug ) ) {
			$slug = 'block-' . $slug;
		}

		return $slug;
	}

	/**
	 * Sanitize block name input.
	 *
	 * @param string $name Block name.
	 * @return string
	 */
	public static function block_name( $name ) {
		return sanitize_text_field( wp_unslash( $name ) );
	}

	/**
	 * Sanitize prompt text.
	 *
	 * @param string $prompt Prompt.
	 * @return string
	 */
	public static function prompt( $prompt ) {
		return sanitize_textarea_field( wp_unslash( $prompt ) );
	}

	/**
	 * Sanitize field name for ACF / PHP identifiers.
	 *
	 * Never returns a name that starts with a digit (invalid as a PHP variable).
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	public static function field_name( $name ) {
		$name = strtolower( (string) $name );
		$name = preg_replace( '/[^a-z0-9_]/', '_', $name );
		$name = preg_replace( '/_+/', '_', $name );
		$name = trim( (string) $name, '_' );

		if ( '' === $name ) {
			$name = 'field';
		}

		if ( preg_match( '/^[0-9]/', $name ) ) {
			$name = 'b_' . $name;
		}

		return $name;
	}

	/**
	 * Convert a field name into a safe PHP variable name (no leading $).
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	public static function php_var( $name ) {
		return self::field_name( str_replace( '-', '_', (string) $name ) );
	}
}
