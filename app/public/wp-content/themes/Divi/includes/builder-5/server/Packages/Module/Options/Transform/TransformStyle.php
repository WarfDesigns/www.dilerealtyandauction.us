<?php
/**
 * Module: TransformStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Transform;

use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\StyleLibrary\Declarations\Transform\Transform;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * TransformStyle class.
 *
 * @since ??
 */
class TransformStyle {

	/**
	 * Get transform style component.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js-beta/divi-module/functions/TransformStyle TransformStyle} in
	 * `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string        $selector                 The CSS selector.
	 *     @type array         $selectors                Optional. An array of selectors for each breakpoint and state. Default `[]`.
	 *     @type callable      $selectorFunction         Optional. The function to be called to generate CSS selector. Default `null`.
	 *     @type array         $propertySelectors        Optional. The property selectors that you want to unpack. Default `[]`.
	 *     @type array         $attr                     An array of module attribute data.
	 *     @type array         $defaultPrintedStyleAttr  Optional. An array of default printed style attribute data. Default `[]`.
	 *     @type array|bool    $important                Optional. Whether to apply "!important" flag to the style declarations.
	 *                                                   Default `false`.
	 *     @type bool          $asStyle                  Optional. Whether to wrap the style declaration with style tag or not.
	 *                                                   Default `true`.
	 *     @type string|null   $orderClass               Optional. The selector class name.
	 *     @type bool          $isInsideStickyModule     Optional. Whether the module is inside a sticky module or not. Default `false`.
	 *     @type string        $returnType               Optional. This is the type of value that the function will return.
	 *                                                   Can be either `string` or `array`. Default `array`.
	 *     @type string        $atRules                  Optional. CSS at-rules to wrap the style declarations in. Default `''`.
	 *     @type array         $positionAttr             Optional. Position attributes to extract translates from. Default `[]`.
	 * }
	 *
	 * @return string|array The transform style component.
	 *
	 * @example:
	 * ```php
	 * // Apply style using default arguments.
	 * $args = [];
	 * $style = self::style( $args );
	 *
	 * // Apply style with specific selectors and properties.
	 * $args = [
	 *     'selectors' => [
	 *         '.element1',
	 *         '.element2',
	 *     ],
	 *     'propertySelectors' => [
	 *         '.element1 .property1',
	 *         '.element2 .property2',
	 *     ]
	 * ];
	 * $style = self::style( $args );
	 * ```
	 */
	public static function style( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'selectors'         => [],
				'propertySelectors' => [],
				'selectorFunction'  => null,
				'important'         => false,
				'asStyle'           => true,
				'orderClass'        => null,
				'returnType'        => 'array',
				'atRules'           => '',
				'positionAttr'      => [],
			]
		);

		$selector           = $args['selector'];
		$selectors          = $args['selectors'];
		$selector_function  = $args['selectorFunction'];
		$property_selectors = $args['propertySelectors'];
		$attr               = $args['attr'];
		$important          = $args['important'];
		$as_style           = $args['asStyle'];
		$order_class        = $args['orderClass'];
		$position_attr      = $args['positionAttr'];

		$is_inside_sticky_module = $args['isInsideStickyModule'] ?? false;

		// If no explicit transform attributes but position attributes exist, create minimal attr structure.
		// This ensures Utils::style_statements iterates and generates position-based transform CSS.
		// Build structure that includes ALL states from position attributes (including hover).
		if ( empty( $attr ) && ! empty( $position_attr ) ) {
			$attr = [];
			foreach ( $position_attr as $breakpoint => $breakpoint_data ) {
				if ( ! empty( $breakpoint_data ) ) {
					$attr[ $breakpoint ] = [];
					foreach ( $breakpoint_data as $state => $state_data ) {
						$attr[ $breakpoint ][ $state ] = [];
					}
				}
			}
		}

		// If both attr and positionAttr exist, ensure attr includes all position states for iteration.
		if ( ! empty( $attr ) && ! empty( $position_attr ) ) {
			foreach ( $position_attr as $breakpoint => $breakpoint_data ) {
				if ( empty( $breakpoint_data ) ) {
					continue;
				}

				if ( ! isset( $attr[ $breakpoint ] ) ) {
					$attr[ $breakpoint ] = [];
				}

				foreach ( $breakpoint_data as $state => $state_data ) {
					if ( ! isset( $attr[ $breakpoint ][ $state ] ) ) {
						$attr[ $breakpoint ][ $state ] = [];
					}
				}
			}
		}

		// Bail, if nothing is there to process.
		if ( empty( $attr ) ) {
			return 'array' === $args['returnType'] ? [] : '';
		}

		$children = Utils::style_statements(
			[
				'selectors'               => ! empty( $selectors ) ? $selectors : [ 'desktop' => [ 'value' => $selector ] ],
				'selectorFunction'        => $selector_function,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr,
				'defaultPrintedStyleAttr' => $args['defaultPrintedStyleAttr'] ?? [],
				'important'               => $important,
				'declarationFunction'     => function ( $params ) use ( $position_attr ) {
					$params['additional'] = [ 'positionAttrs' => $position_attr ];
					return Transform::style_declaration( $params );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'returnType'              => $args['returnType'],
				'atRules'                 => $args['atRules'],
			]
		);

		/**
		 * Here resetting the `:hover` style declaration is removed because that is a VB-Specific code.
		 * Related Slack thread: https://elegantthemes.slack.com/archives/C02NEJ9GFPV/p1658147385625339
		 */

		return Utils::style_wrapper(
			[
				'attr'     => $attr,
				'asStyle'  => $as_style,
				'children' => $children,
			]
		);
	}
}
