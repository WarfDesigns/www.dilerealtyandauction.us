<?php
/**
 * StringUtility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.

/**
 * StringUtility class.
 *
 * This class contains methods to remove characters from strings.
 *
 * @since ??
 */
class StringUtility {

	/**
	 * Compare two version strings with custom prefixes normalization.
	 *
	 * This method normalizes known prefixes to match expected semantic version order
	 * and then uses PHP's version_compare function for the actual comparison.
	 *
	 * @since ??
	 *
	 * @param string      $v1       First version string to compare.
	 * @param string      $v2       Second version string to compare.
	 * @param string|null $operator Optional comparison operator.
	 *
	 * @return bool|int Returns comparison result based on operator, or integer if no operator.
	 */
	public static function version_compare( $v1, $v2, $operator = null ) {
		// Normalize known prefixes to match expected semver order.
		$map = [
			'dev-alpha'    => 'alpha.1',
			'dev-beta'     => 'alpha.2',
			'public-alpha' => 'alpha.3',
			'public-beta'  => 'alpha.4',
		];

		$v1_norm = strtr( $v1, $map );
		$v2_norm = strtr( $v2, $map );

		return $operator
			? version_compare( $v1_norm, $v2_norm, $operator )
			: version_compare( $v1_norm, $v2_norm );
	}

	/**
	 * Remove provided characters from given string.
	 *
	 * @since ??
	 *
	 * @param string $string     The string to trim.
	 * @param array  $characters An array of single character to trim.
	 *
	 * @return string
	 */
	public static function trim_extended( $string, $characters ) {
		// Allow only single character.
		if ( $characters ) {
			$characters = array_filter(
				$characters,
				function ( $character ) {
					return is_string( $character ) && 1 === strlen( $character );
				}
			);
		}

		if ( ! $characters ) {
			return $string;
		}

		$first_char = substr( $string, 0, 1 );

		while ( '' !== $string && in_array( $first_char, $characters, true ) ) {
			// Remove the first character.
			$string = substr_replace( $string, '', 0, 1 );

			if ( '' === $string ) {
				break;
			}

			// Get the first character of the string for next iteration.
			$first_char = substr( $string, 0, 1 );
		}

		$last_char = substr( $string, -1 );

		while ( '' !== $string && in_array( $last_char, $characters, true ) ) {
			// Remove the last character.
			$string = substr_replace( $string, '', -1, 1 );

			if ( '' === $string ) {
				break;
			}

			// Get the last character of the string for next iteration.
			$last_char = substr( $string, -1 );
		}

		return $string;
	}

	/**
	 * Trim string if the first and last character of a string are the same and are in the list of
	 * characters to remove.
	 *
	 * @since ??
	 *
	 * @param string $string     The string to trim.
	 * @param array  $characters An array of single character to trim.
	 *
	 * @return string
	 */
	public static function trim_pair( $string, $characters ) {
		// Allow only single character and not a new line character.
		if ( $characters ) {
			$characters = array_filter(
				$characters,
				function ( $character ) {
					return is_string( $character ) && 1 === strlen( $character );
				}
			);
		}

		if ( ! $characters ) {
			return $string;
		}

		$first_char = substr( $string, 0, 1 );
		$last_char  = substr( $string, -1 );

		while ( '' !== $string && $first_char === $last_char && in_array( $first_char, $characters, true ) ) {
			// Remove the first character.
			$string = substr_replace( $string, '', 0, 1 );

			// Remove the last character.
			$string = substr_replace( $string, '', -1, 1 );

			if ( '' === $string ) {
				break;
			}

			// Get the first character of the string for next iteration.
			$first_char = substr( $string, 0, 1 );

			// Get the last character of the string for next iteration.
			$last_char = substr( $string, -1 );
		}

		return $string;
	}

	/**
	 * Checks if a string starts with a given substring
	 *
	 * Performs a case-sensitive check indicating if haystack begins with needle.
	 *
	 * @since ??
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The substring to search for in the haystack.
	 * @return bool Returns true if haystack begins with needle, false otherwise.
	 */
	public static function starts_with( string $haystack, string $needle ): bool {
		if ( ! function_exists( 'str_starts_with' ) ) {
			return str_starts_with( $haystack, $needle );
		}

		return strlen( $needle ) === 0 || strpos( $haystack, $needle ) === 0;
	}

	/**
	 * Checks if a string ends with a given substring
	 *
	 * Performs a case-sensitive check indicating if haystack ends with needle.
	 *
	 * @since ??
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The substring to search for in the haystack.
	 *
	 * @return bool Returns true if haystack ends with needle, false otherwise.
	 */
	public static function ends_with( string $haystack, string $needle ): bool {
		if ( ! function_exists( 'str_ends_with' ) ) {
			return str_ends_with( $haystack, $needle );
		}

		$needle_len = strlen( $needle );

		return ( 0 === $needle_len || 0 === substr_compare( $haystack, $needle, - $needle_len ) );
	}

	/**
	 * Preserve JSON Unicode escape sequences in content for WordPress meta operations.
	 *
	 * WordPress automatically unslashes content when retrieving from the database (via get_posts(),
	 * get_post(), etc.), which strips backslashes from JSON Unicode escape sequences (e.g., \u003c -> u003c).
	 * Additionally, update_post_meta() internally calls wp_unslash() which can corrupt Unicode escapes.
	 *
	 * This function:
	 * 1. Restores backslashes to malformed Unicode escape sequences (u003c -> \u003c)
	 * 2. Applies wp_slash() to preserve escapes through update_post_meta() operations
	 *
	 * @since ??
	 *
	 * @param string $content Content that may contain malformed Unicode escape sequences.
	 *
	 * @return string Content with preserved Unicode escape sequences, ready for WordPress meta operations.
	 *
	 * @example
	 * ```php
	 * // Content retrieved from database with corrupted Unicode escapes
	 * $content = '{"value":"u003cpu003eHellou003c/pu003e"}';
	 *
	 * // Preserve Unicode escapes for caching
	 * $preserved = StringUtility::preserve_unicode_escapes_for_meta( $content );
	 * // Result: '{"value":"\\u003cp\\u003eHello\\u003c/p\\u003e"}' (slashed)
	 *
	 * // Now safe to use with update_post_meta()
	 * update_post_meta( $post_id, 'cached_content', $preserved );
	 * ```
	 */
	public static function preserve_unicode_escapes_for_meta( string $content ): string {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		// Restore backslashes to JSON Unicode escape sequences that were stripped by wp_unslash.
		// Pattern: u followed by 4 hex digits (but not already preceded by backslash).
		$content = preg_replace( '/(?<!\\\\)u([0-9a-f]{4})/i', '\\\\u$1', $content );

		// update_post_meta() internally calls wp_unslash() which strips backslashes from Unicode escapes.
		// Apply wp_slash() to preserve the escapes through the update_post_meta() process.
		return wp_slash( $content );
	}
}
