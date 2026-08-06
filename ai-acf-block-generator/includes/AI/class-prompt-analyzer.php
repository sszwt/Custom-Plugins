<?php
/**
 * Orchestrates AI prompt analysis.
 *
 * @package AABG
 */

namespace AABG\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prompt_Analyzer
 */
class Prompt_Analyzer {

	/**
	 * Analyze a prompt and return block specification.
	 *
	 * @param string $prompt       User prompt.
	 * @param array  $block_config Block configuration.
	 * @return array|\WP_Error
	 */
	public function analyze( $prompt, $block_config ) {
		$api_key = AI_Provider_Factory::get_api_key();
		$image   = $block_config['design_image_path'] ?? '';
		$use_ai  = AI_Provider_Factory::is_ai_enabled() || ( ! empty( $image ) && ! empty( $api_key ) );

		if ( ! empty( $image ) && empty( $api_key ) ) {
			return new \WP_Error(
				'api_key_required',
				sprintf(
					/* translators: %s: provider name */
					__( '%s API key is required when uploading a design reference image. Add it in AI ACF Blocks → Settings.', 'ai-acf-block-generator' ),
					AI_Provider_Factory::get_provider_label()
				)
			);
		}

		if ( $use_ai && ! empty( $api_key ) ) {
			$provider = AI_Provider_Factory::create();

			if ( ! empty( $image ) && file_exists( $image ) ) {
				$result = $provider->analyze_with_image( $prompt, $block_config, $image );
			} else {
				$result = $provider->analyze( $prompt, $block_config );
			}

			if ( ! is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! empty( $image ) ) {
				$fallback = ( new Rule_Based_Analyzer() )->analyze( $prompt, $block_config );
				if ( is_wp_error( $fallback ) ) {
					return $result;
				}

				$fallback['source']  = 'rule-based-fallback';
				$fallback['warning'] = sprintf(
					/* translators: 1: provider label, 2: error message */
					__( '%1$s failed (%2$s). Block created from prompt only — layout may not match your image.', 'ai-acf-block-generator' ),
					AI_Provider_Factory::get_provider_label(),
					$result->get_error_message()
				);
				return $fallback;
			}

			$result->add_data( array( 'fallback' => true ) );
		}

		$analyzer = new Rule_Based_Analyzer();
		return $analyzer->analyze( $prompt, $block_config );
	}
}
