<?php
/**
 * Filters class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Framework\Breakpoint\Breakpoint;

/**
 * Filters class.
 *
 * @since ??
 */
class Filters {

	/**
	 * Get filter's CSS property value based on given attrValue.
	 *
	 * @since ??
	 *
	 * @param array $attr_value The value (breakpoint > state > value) of module attribute.
	 *
	 * @return string
	 */
	public static function value( array $attr_value ): string {
		$filter_functions = [
			'hueRotate',
			'saturate',
			'brightness',
			'contrast',
			'invert',
			'sepia',
			'opacity',
			'blur',
		];

		$filter_value = [];

		foreach ( $filter_functions as $filter_function ) {
			if ( ! isset( $attr_value[ $filter_function ] ) ) {
				continue;
			}

			$function_value = $attr_value[ $filter_function ];

			if ( ! $function_value ) {
				continue;
			}

			switch ( $filter_function ) {
				case 'hueRotate':
					$filter_value[] = 'hue-rotate(' . $function_value . ')';
					break;

				default:
					$filter_value[] = $filter_function . '(' . $function_value . ')';
					break;
			}
		}

		return implode( ' ', $filter_value );
	}

	/**
	 * Get Filter's CSS declaration based on given attrValue.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/filters-style-declaration filtersStyleDeclaration} in:
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
	 *                                  Can be either string or key_value_pair. Default `string`.
	 *     @type array      $attr       Optional. The full attribute object for inheritance processing. Default `[]`.
	 *     @type string     $breakpoint Optional. Current breakpoint for inheritance processing. Default `desktop`.
	 *     @type string     $state      Optional. Current state for inheritance processing. Default `value`.
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
			]
		);

		$attr_value  = $args['attrValue'];
		$important   = $args['important'];
		$return_type = $args['returnType'];
		$attr        = $args['attr'] ?? [];
		$breakpoint  = $args['breakpoint'];
		$state       = $args['state'];

		// Use ModuleUtils::use_attr_value() for proper state inheritance.
		// This follows the established codebase pattern and handles all edge cases internally.
		// The utility will return defaultValue when inheritance is not applicable or available.
		$final_attr_value = ModuleUtils::use_attr_value(
			[
				'attr'         => $attr,
				'breakpoint'   => $breakpoint,
				'state'        => $state,
				'mode'         => 'getAndInheritAll',
				'defaultValue' => $attr_value,
			]
		);

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$filter_declaration = self::value( $final_attr_value );

		if ( $filter_declaration ) {
			$style_declarations->add( 'filter', $filter_declaration );
		}

		if ( isset( $final_attr_value['blendMode'] ) ) {
			$style_declarations->add( 'mix-blend-mode', $final_attr_value['blendMode'] );
		}

		return $style_declarations->value();
	}
}
