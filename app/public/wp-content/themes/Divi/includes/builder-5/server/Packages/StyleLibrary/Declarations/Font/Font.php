<?php
/**
 * Font class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Font;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * Font class.
 *
 * @since ??
 */
class Font {

	/**
	 * Get websafe font fallback stack based on font type.
	 *
	 * This function matches D4 behavior from et_builder_get_websafe_font_stack().
	 *
	 * @since ??
	 *
	 * @param string      $type      The font type (sans-serif, serif, cursive, etc.).
	 * @param string|null $font_name The primary font name to exclude from the stack. Default null.
	 *
	 * @return string The appropriate fallback font stack.
	 */
	private static function _get_websafe_font_stack( $type = 'sans-serif', $font_name = null ) {
		switch ( $type ) {
			case 'sans-serif':
				$stack = 'Helvetica, Arial, Lucida, sans-serif';
				break;
			case 'serif':
				$stack = 'Georgia, "Times New Roman", serif';
				break;
			case 'cursive':
				$stack = 'cursive';
				break;
			case 'display':
				// Google Fonts uses 'display' category, map to valid CSS generic 'fantasy'.
				$stack = 'fantasy';
				break;
			case 'handwriting':
				// Google Fonts uses 'handwriting' category, map to valid CSS generic 'cursive'.
				$stack = 'cursive';
				break;
			case 'monospace':
				$stack = 'monospace';
				break;
			default:
				// Fallback to sans-serif for any unknown types.
				$stack = 'sans-serif';
				break;
		}

		// Remove duplicate fonts from the stack to avoid redundancy.
		if ( $font_name ) {
			// Parse font_name into individual fonts.
			$font_name_list = array_map( 'trim', explode( ',', $font_name ) );

			// Check if font_name already ends with a generic keyword.
			// Generic keywords: sans-serif, serif, monospace, cursive, fantasy.
			$generic_keywords = [ 'sans-serif', 'serif', 'monospace', 'cursive', 'fantasy' ];
			$last_font        = end( $font_name_list );
			$last_font_clean  = strtolower( trim( $last_font, '\'"' ) );

			if ( in_array( $last_font_clean, $generic_keywords, true ) ) {
				// Font name already has a generic fallback, don't add more.
				return '';
			}

			// Split the stack into individual fonts.
			$stack_fonts = array_map( 'trim', explode( ',', $stack ) );

			// Build a set of font names for comparison (cleaned, lowercase).
			$font_name_set = [];
			foreach ( $font_name_list as $font ) {
				$font_clean                   = strtolower( trim( $font, '\'"' ) );
				$font_name_set[ $font_clean ] = true;
			}

			// Filter out any fonts from stack that are already in font_name.
			$filtered_fonts = array_filter(
				$stack_fonts,
				function ( $font ) use ( $font_name_set ) {
					$font_clean = strtolower( trim( $font, '\'"' ) );
					return ! isset( $font_name_set[ $font_clean ] );
				}
			);

			// Rebuild the stack.
			$stack = implode( ', ', $filtered_fonts );
		}

		return $stack;
	}

	/**
	 * Get Font's CSS declaration based on given attrValue.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/font-style-declaration fontStyleDeclaration} in:
	 * `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  Optional. Whether to add `!important` tag. Default `false`.
	 *     @type string     $returnType Optional. This is the type of value that the function will return.
	 *                                  Can be either `string` or `key_value_pair`. Default `string`.
	 *     @type array|null $fonts      Optional. Websafe fonts data for MS version handling. Default `null`.
	 * }
	 *
	 * @return array|string
	 */
	public static function style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
				'breakpoint' => 'desktop',
				'state'      => 'value',
				'fonts'      => null,
			]
		);

		$attr        = $args['attr'];
		$attr_value  = $args['attrValue'];
		$important   = $args['important'];
		$return_type = $args['returnType'];
		$breakpoint  = $args['breakpoint'];
		$state       = $args['state'];
		$fonts       = $args['fonts'];

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$inherited_font_style = []; // Inherited font style from upper breakpoint.
		if ( 'tablet' === $breakpoint ) {
			$inherited_font_style = $attr['desktop'][ $state ]['style'] ?? [];
		} elseif ( 'phone' === $breakpoint ) {
			$inherited_font_style = $attr['tablet'][ $state ]['style'] ?? $attr['desktop'][ $state ]['style'] ?? [];
		}

		// Ensure $inherited_font_style is always an array to prevent type errors.
		if ( ! is_array( $inherited_font_style ) ) {
			$inherited_font_style = [];
		}

		if ( isset( $attr_value['family'] ) && strtolower( $attr_value['family'] ) !== 'default' ) {
			/**
			 * Check if font family is a CSS variable.
			 * Test regex https://regex101.com/r/4cTjiQ/1.
			 */
			$regex           = '/var\(\s*(-{2,})([a-zA-Z0-9-_]+)\)/i';
			$is_css_variable = preg_match( $regex, $attr_value['family'] ) === 1;

			// The check has been done to avoid adding single quotes to CSS variable.
			if ( $is_css_variable ) {
				// Normalize CSS variable format to ensure consistent processing for both VB and FE.
				$font_family = preg_replace_callback(
					$regex,
					function ( $matches ) {
						// Always use exactly two dashes for CSS variables.
						return 'var(--' . $matches[2] . ')';
					},
					$attr_value['family']
				);
			} else {
				// Handle MS version for websafe fonts (Issue #45473).
				$font_data = null;
				if ( $fonts ) {
					$font_data = $fonts[ $attr_value['family'] ] ?? null;
				}

				// Check if this font needs MS version (websafe fonts like Trebuchet).
				if ( $font_data && isset( $font_data['add_ms_version'] ) && $font_data['add_ms_version'] ) {
					$font_family = "'" . $attr_value['family'] . " MS', '" . $attr_value['family'] . "'";
				} else {
					$font_family = "'" . $attr_value['family'] . "'";
				}

				// Get font type and append websafe fallback stack (Issue #46031).
				// Add fallback for all registered fonts (Google fonts, websafe fonts).
				// Default to 'sans-serif' if type is missing or empty.
				$font_type      = ! empty( $font_data['type'] ) ? $font_data['type'] : 'sans-serif';
				$fallback_stack = self::_get_websafe_font_stack( $font_type, $attr_value['family'] );
				// Only append fallback stack if it's not empty.
				if ( ! empty( $fallback_stack ) ) {
					$font_family = $font_family . ', ' . $fallback_stack;
				}
			}

			$style_declarations->add( 'font-family', $font_family );
		}

		if ( isset( $attr_value['weight'] ) ) {
			$style_declarations->add( 'font-weight', $attr_value['weight'] );
		}

		$font_style = isset( $attr_value['style'] ) ? $attr_value['style'] : null;

		// Normalize font style to always be an array for consistent processing.
		if ( ! is_array( $font_style ) && null !== $font_style ) {
			// Handle legacy string values by converting to array.
			$font_style = [ $font_style ];
		}

		if ( is_array( $font_style ) ) {
			// Empty font style array indicates explicit reset to override inherited or preset styles.
			$is_empty_font_style = empty( $font_style );

			if ( in_array( 'italic', $font_style, true ) ) {
				$style_declarations->add( 'font-style', 'italic' );
			} elseif ( in_array( 'italic', $inherited_font_style, true ) || $is_empty_font_style ) {
				$style_declarations->add( 'font-style', 'normal' );
			}

			if ( in_array( 'uppercase', $font_style, true ) ) {
				$style_declarations->add( 'text-transform', 'uppercase' );
			} elseif ( in_array( 'uppercase', $inherited_font_style, true ) || $is_empty_font_style ) {
				$style_declarations->add( 'text-transform', 'none' );
			}

			if ( in_array( 'capitalize', $font_style, true ) ) {
				$style_declarations->add( 'font-variant', 'small-caps' );
			} elseif ( in_array( 'capitalize', $inherited_font_style, true ) || $is_empty_font_style ) {
				$style_declarations->add( 'font-variant', 'normal' );
			}

			if ( in_array( 'underline', $font_style, true ) ) {
				$style_declarations->add( 'text-decoration-line', 'underline' );
			} elseif ( in_array( 'strikethrough', $font_style, true ) ) {
				$style_declarations->add( 'text-decoration-line', 'line-through' );
			} elseif ( in_array( 'underline', $inherited_font_style, true ) || in_array( 'strikethrough', $inherited_font_style, true ) || $is_empty_font_style ) {
				$style_declarations->add( 'text-decoration-line', 'none' );
			}
		}

		if ( isset( $attr_value['lineColor'] ) ) {
			$style_declarations->add( 'text-decoration-color', $attr_value['lineColor'] );
		}

		$line_style = isset( $attr_value['lineStyle'] ) ? $attr_value['lineStyle'] : 'solid';

		if ( is_array( $font_style ) && ( in_array( 'strikethrough', $font_style, true ) || in_array( 'underline', $font_style, true ) ) ) {
			$style_declarations->add( 'text-decoration-style', $line_style );
		}

		if ( isset( $attr_value['color'] ) ) {
			$style_declarations->add( 'color', $attr_value['color'] );
		}

		if ( isset( $attr_value['size'] ) ) {
			// Normalize font-size to ensure it has a unit.
			// Add 'px' as default unit for unitless numeric values.
			// This handles migrated D4 layouts that may have unitless font-size values.
			$font_size = $attr_value['size'];

			// Check if value is numeric without unit.
			if ( is_numeric( $font_size ) ) {
				// Value is purely numeric - add 'px' unit.
				$font_size = $font_size . 'px';
			}

			$style_declarations->add( 'font-size', $font_size );
		}

		if ( isset( $attr_value['letterSpacing'] ) ) {
			$style_declarations->add( 'letter-spacing', $attr_value['letterSpacing'] );
		}

		if ( isset( $attr_value['lineHeight'] ) ) {
			$style_declarations->add( 'line-height', $attr_value['lineHeight'] );
		}

		if ( isset( $attr_value['textAlign'] ) ) {
			$style_declarations->add( 'text-align', $attr_value['textAlign'] );
		}

		return $style_declarations->value();
	}
}
