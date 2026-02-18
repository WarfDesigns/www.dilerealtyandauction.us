<?php
/**
 * Module Library: Icon List Module Font Style
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\IconList\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * FontStyle class.
 *
 * This class provides style declaration functionality for Icon List text alignment.
 * Since .et_pb_icon_list_text uses display: flex, we need to convert
 * text-align values to justify-content for proper alignment.
 *
 * @since ??
 */
class FontStyle {

	/**
	 * Generate CSS for Icon List text alignment.
	 *
	 * Converts text-align values to justify-content for flexbox compatibility.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Parameters for the text alignment CSS declaration.
	 *
	 *     @type array $attrValue The font attribute value containing textAlign.
	 * }
	 *
	 * @return string The CSS for text alignment.
	 */
	public static function text_alignment_declaration( array $params ): string {
		$font_attr = $params['attrValue'];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Extract textAlign value from font attribute.
		$text_align = isset( $font_attr['textAlign'] ) ? $font_attr['textAlign'] : null;

		if ( $text_align ) {
			// Convert text-align values to justify-content for flexbox.
			switch ( $text_align ) {
				case 'left':
					$style_declarations->add( 'justify-content', 'flex-start' );
					break;
				case 'center':
					$style_declarations->add( 'justify-content', 'center' );
					break;
				case 'right':
					$style_declarations->add( 'justify-content', 'flex-end' );
					break;
				case 'justify':
					$style_declarations->add( 'justify-content', 'space-between' );
					break;
				default:
					// For any other values, fall back to flex-start.
					$style_declarations->add( 'justify-content', 'flex-start' );
					break;
			}
		}

		return $style_declarations->value();
	}
}
