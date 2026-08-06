<?php
/**
 * Validates generated PHP source before it is written to disk.
 *
 * @package AABG
 */

namespace AABG\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PHP_Validator
 *
 * Uses the tokenizer in TOKEN_PARSE mode to detect syntax/parse errors in
 * generated code WITHOUT executing it. This prevents broken templates (for
 * example, invalid PHP produced by an AI response) from ever being written
 * into the active theme, where they would cause fatal errors.
 */
class PHP_Validator {

	/**
	 * Check whether a string of PHP code is syntactically valid.
	 *
	 * @param string $code  PHP source code (may or may not start with <?php).
	 * @param string $label Optional identifier used for logging.
	 * @return true|\WP_Error True when valid, WP_Error describing the problem otherwise.
	 */
	public static function validate( $code, $label = 'generated.php' ) {
		if ( '' === trim( (string) $code ) ) {
			return new \WP_Error(
				'empty_code',
				__( 'Generated file is empty.', 'ai-acf-block-generator' )
			);
		}

		// token_get_all requires an opening tag to parse in TOKEN_PARSE mode.
		$to_check = self::has_open_tag( $code ) ? $code : "<?php\n" . $code;

		try {
			// TOKEN_PARSE forces a full parse and throws ParseError on invalid syntax.
			token_get_all( $to_check, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			$message = $e->getMessage();
			Logger::log_invalid_code( $label, $code, $message );

			return new \WP_Error(
				'php_parse_error',
				sprintf(
					/* translators: %s: parser error message */
					__( 'Generated PHP is invalid and was not saved: %s', 'ai-acf-block-generator' ),
					$message
				),
				array( 'code' => $code )
			);
		} catch ( \Throwable $e ) {
			$message = $e->getMessage();
			Logger::log_invalid_code( $label, $code, $message );

			return new \WP_Error(
				'php_validation_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Generated PHP could not be validated: %s', 'ai-acf-block-generator' ),
					$message
				),
				array( 'code' => $code )
			);
		}

		return true;
	}

	/**
	 * Detect a leading PHP open tag.
	 *
	 * @param string $code Source code.
	 * @return bool
	 */
	private static function has_open_tag( $code ) {
		return false !== strpos( $code, '<?php' ) || false !== strpos( $code, '<?=' );
	}
}
