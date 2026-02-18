<?php
/**
 * Module Library: Icon List Module Text Style
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
 * TextStyle class.
 *
 * This class provides style declaration functionality for Icon List text orientation alignment.
 * Since .et_pb_icon_list_text uses display: flex, we need to convert
 * text orientation values to justify-content for proper alignment.
 * This handles module.advanced.text.text.desktop.value.orientation attributes.
 *
 * @since ??
 */
class TextStyle {

	/**
	 * Generate CSS for Icon List text orientation alignment.
	 *
	 * Converts text orientation values to justify-content for flexbox compatibility.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Parameters for the text orientation CSS declaration.
	 *
	 *     @type array $attrValue The text attribute value containing orientation.
	 * }
	 *
	 * @return string The CSS for text orientation alignment.
	 */
	public static function text_orientation_declaration( array $params ): string {
		$text_attr = $params['attrValue'];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Extract orientation value from text attribute.
		$orientation = isset( $text_attr['orientation'] ) ? $text_attr['orientation'] : null;

		if ( $orientation ) {
			// Convert text orientation values to justify-content for flexbox.
			switch ( $orientation ) {
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
