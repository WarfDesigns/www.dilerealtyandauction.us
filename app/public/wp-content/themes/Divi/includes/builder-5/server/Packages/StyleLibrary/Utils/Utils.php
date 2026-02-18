<?php
/**
 * StyleLibrary\Utils class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;

/**
 * Utils class is a helper class with helper methods to work with the style library.
 *
 * @since ??
 */
class Utils {
	/**
	 * Join array of declarations into `;` separated string, suffixed by `;`.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/join-declarations joinDeclarations} in:
	 * `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $declarations Array of declarations.
	 *
	 * @return string
	 */
	public static function join_declarations( array $declarations ): string {
		$joined = implode( '; ', $declarations );

		if ( 0 < count( $declarations ) ) {
			$joined = $joined . ';';
		}

		return $joined;
	}

	/**
	 * Recursively resolve any `$variable(...)$` strings within an array or string.
	 *
	 * @since ??
	 *
	 * @param mixed $value The raw input, string or array.
	 *
	 * @return mixed The resolved value with all dynamic variables normalized.
	 */
	public static function resolve_dynamic_variables_recursive( $value ) {
		if ( ! is_array( $value ) ) {
			return self::resolve_dynamic_variable( $value );
		}

		foreach ( $value as $key => $subvalue ) {
			$value[ $key ] = self::resolve_dynamic_variables_recursive( $subvalue );
		}

		return $value;
	}

	/**
	 * Resolves a `$variable(...)$` encoded dynamic content string into a CSS variable.
	 *
	 * Example:
	 * Input:  $variable({"type":"content","value":{"name":"gvid-abc123"}})$
	 * Output: var(--gvid-abc123)
	 *
	 * @since ??
	 *
	 * @param string $value The raw string to be resolved.
	 *
	 * @return string The resolved CSS variable or original value if not matched.
	 */
	public static function resolve_dynamic_variable( $value ) {
		if ( is_string( $value ) && preg_match( '/^\$variable\((.+)\)\$$/', $value, $matches ) ) {
			$decoded = json_decode( $matches[1], true );
			$type    = $decoded['type'] ?? '';
			$name    = $decoded['value']['name'] ?? null;

			if ( $name ) {
				// Check if this is an ACF color field.
				$is_acf_color_field = self::_is_acf_color_field( $name, $type, $decoded['value']['settings'] ?? [] );

				if ( $is_acf_color_field ) {
					// For ACF color fields, return the resolved ACF value directly without HSL processing.
					return self::_resolve_acf_color_field_value( $name, $decoded['value']['settings'] ?? [] );
				}

				$css_variable = "var(--{$name})";

				switch ( $type ) {
					case 'color':
						return GlobalData::transform_state_into_global_color_value( $css_variable, $decoded['value']['settings'] ?? [] );
					default:
						return $css_variable;
				}
			}
		}

		return $value;
	}

	/**
	 * Check if a dynamic variable represents an ACF color picker field.
	 *
	 * @since ??
	 *
	 * @param string $name     The dynamic variable name.
	 * @param string $type     The dynamic variable type.
	 * @param array  $settings The dynamic variable settings.
	 *
	 * @return bool True if this is an ACF color picker field.
	 */
	private static function _is_acf_color_field( $name, $type, $settings = [] ) {
		// Only check color type variables.
		if ( 'color' !== $type ) {
			return false;
		}

		// Simplified detection: if it's a custom_meta field with color type, treat it as ACF color field.
		// This is more reliable than trying to check ACF field types at runtime since ACF may not be loaded during server-side rendering.
		if ( 0 === strpos( $name, 'custom_meta_' ) ) {
			return true;
		}

		// Check for legacy format: name is 'post_meta_key' with ACF field in settings.
		if ( 'post_meta_key' === $name ) {
			$selected_meta_key = $settings['select_meta_key'] ?? '';

			if ( 0 === strpos( $selected_meta_key, 'custom_meta_' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve ACF color field value directly without HSL processing.
	 *
	 * ACF color picker fields return hex color values directly and should not be processed
	 * as global color variables with HSL adjustments.
	 *
	 * @since ??
	 *
	 * @param string $field_name The ACF field name (e.g., 'custom_meta_color_field').
	 * @param array  $settings   Dynamic content settings (may include before/after text).
	 *
	 * @return string The resolved ACF color field value.
	 */
	private static function _resolve_acf_color_field_value( $field_name, $settings = [] ) {
		// Extract the actual meta key based on the field name format.
		$meta_key = '';

		if ( 0 === strpos( $field_name, 'custom_meta_' ) ) {
			// New format: remove 'custom_meta_' prefix.
			$meta_key = str_replace( 'custom_meta_', '', $field_name );
		} elseif ( 'post_meta_key' === $field_name ) {
			// Legacy format: extract from settings.
			$selected_meta_key = $settings['select_meta_key'] ?? '';

			if ( 0 === strpos( $selected_meta_key, 'custom_meta_' ) ) {
				$meta_key = str_replace( 'custom_meta_', '', $selected_meta_key );
			} elseif ( ! empty( $settings['meta_key'] ) ) {
				$meta_key = $settings['meta_key'];
			}
		}

		if ( empty( $meta_key ) ) {
			return '';
		}

		// Get the post ID from global context (set by DynamicData processing) or current post.
		global $et_dynamic_data_post_id;
		$post_id = $et_dynamic_data_post_id ?? get_the_ID();

		if ( false === $post_id || 0 === $post_id || null === $post_id ) {
			// No valid post context in REST API or other contexts.
			// Return empty value since we can't resolve ACF values without a post ID.
			return '';
		}

		// Use ACF utils to get the meta value.
		$value = DynamicContentACFUtils::get_meta_value_by_type( 'post', $post_id, $meta_key );

		// Ensure we have a valid color value.
		if ( ! is_string( $value ) || empty( $value ) ) {
			$value = '';
		}

		// Add before/after text if specified.
		$before = isset( $settings['before'] ) ? $settings['before'] : '';
		$after  = isset( $settings['after'] ) ? $settings['after'] : '';

		return $before . $value . $after;
	}

	/**
	 * Helper function to resolve nested global colors and global variables to actual color values.
	 * This ensures SVG elements get concrete color values instead of CSS variables or variable syntax.
	 *
	 * Handles all global color formats including:
	 * - CSS variables: var(--gcid-xxx)
	 * - Variable syntax: $variable({"type":"color","value":{"name":"gcid-xxx","settings":{...}}})$
	 * - HSL with variables: hsl(from var(--gcid-xxx) calc(h + 0) calc(s + 0) calc(l + 0) / 0.2)
	 * - Nested global colors: Global colors that reference other global colors
	 * - Multiple levels of nesting with recursive resolution
	 *
	 * @param string $color The input color value (could be global color ID, $variable syntax, CSS variable, or nested reference).
	 * @param int    $depth Current recursion depth to prevent infinite loops.
	 * @return string The resolved concrete color value or original color if not a global color.
	 */
	public static function resolve_global_color_to_value( $color, $depth = 0 ) {
		// Input validation.
		if ( ! is_string( $color ) || empty( $color ) ) {
			return $color;
		}

		// Maximum recursion depth to prevent infinite loops.
		$max_depth = 10;

		if ( $depth >= $max_depth ) {
			return $color;
		}

		// Check if it's a global color (either CSS variable or $variable syntax).
		$global_color_id    = GlobalData::get_global_color_id_from_value( $color );
		$is_variable_syntax = 0 === strpos( $color, '$variable(' ) && '$' === substr( $color, -1 );

		if ( ! $global_color_id && ! $is_variable_syntax ) {
			return $color; // Not a global color, return as-is.
		}

		// Resolve the global color variable (handles nested references).
		$resolved_color = GlobalData::resolve_global_color_variable( $color );

		// Safety check: ensure resolved color is valid.
		if ( ! is_string( $resolved_color ) || empty( $resolved_color ) ) {
			return $color;
		}

		// If still contains CSS variable, get the raw color value.
		if ( false !== strpos( $resolved_color, 'var(--' ) && $global_color_id ) {
			$color_data = GlobalData::get_global_color_by_id( $global_color_id );
			if ( is_array( $color_data ) && isset( $color_data['color'] ) && ! empty( $color_data['color'] ) ) {
				$resolved_color = $color_data['color'];
			}
		}

		// If still contains nested $variable syntax, resolve recursively.
		if ( false !== strpos( $resolved_color, '$variable(' ) ) {
			$resolved_color = self::resolve_global_color_to_value( $resolved_color, $depth + 1 );
		}

		// Return original color if resolution failed.
		return ( is_string( $resolved_color ) && ! empty( $resolved_color ) ) ? $resolved_color : $color;
	}
}
