<?php
/**
 * Factory for AI providers.
 *
 * @package AABG
 */

namespace AABG\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Provider_Factory
 */
class AI_Provider_Factory {

	/**
	 * Get active provider slug.
	 *
	 * @return string openai|gemini
	 */
	public static function get_provider_slug() {
		$provider = get_option( 'aabg_ai_provider', 'openai' );
		return in_array( $provider, array( 'openai', 'gemini' ), true ) ? $provider : 'openai';
	}

	/**
	 * Get API key for active provider.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		if ( 'gemini' === self::get_provider_slug() ) {
			return trim( (string) get_option( 'aabg_gemini_api_key', '' ) );
		}
		return trim( (string) get_option( 'aabg_openai_api_key', '' ) );
	}

	/**
	 * Whether AI mode is enabled.
	 *
	 * @return bool
	 */
	public static function is_ai_enabled() {
		return get_option( 'aabg_use_openai', '0' ) === '1';
	}

	/**
	 * Whether a usable API key exists for the active provider.
	 *
	 * @return bool
	 */
	public static function has_api_key() {
		return ! empty( self::get_api_key() );
	}

	/**
	 * Create provider instance.
	 *
	 * @return OpenAI_Provider|Gemini_Provider
	 */
	public static function create() {
		if ( 'gemini' === self::get_provider_slug() ) {
			return new Gemini_Provider();
		}
		return new OpenAI_Provider();
	}

	/**
	 * Human-readable provider label.
	 *
	 * @return string
	 */
	public static function get_provider_label() {
		return 'gemini' === self::get_provider_slug()
			? 'Google Gemini'
			: 'OpenAI';
	}
}
