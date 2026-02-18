<?php
/**
 * Module Library: BarCounters Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\BarCounters;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class BarCountersPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\BarCounters
 */
class BarCountersPresetAttrsMap {
	/**
	 * Get the preset attributes map for the BarCounters module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/counters' !== $module_name ) {
			return $map;
		}

		return array_merge(
			$map,
			[
				'barProgress.advanced.usePercentages'      => [
					'attrName' => 'barProgress.advanced.usePercentages',
					'preset'   => [ 'html' ],
				],
				'children.barProgress.decoration.background__color' => [
					'attrName' => 'children.barProgress.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'module.decoration.scroll__gridMotion.enable' => [
					'attrName' => 'module.decoration.scroll',
					'preset'   => [ 'script' ],
					'subName'  => 'gridMotion.enable',
				],
				'module.decoration.layout__display'        => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'display',
				],
				'module.decoration.layout__flexDirection'  => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexDirection',
				],
				'module.decoration.layout__flexWrap'       => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexWrap',
				],
				'module.decoration.layout__gridAutoColumns' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAutoColumns',
				],
				'module.decoration.layout__gridAutoFlow'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAutoFlow',
				],
				'module.decoration.layout__gridAutoRows'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAutoRows',
				],
				'module.decoration.layout__gridColumnCount' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnCount',
				],
				'module.decoration.layout__gridColumnMinWidth' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnMinWidth',
				],
				'module.decoration.layout__gridColumnWidth' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnWidth',
				],
				'module.decoration.layout__gridColumnWidths' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnWidths',
				],
				'module.decoration.layout__gridDensity'    => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridDensity',
				],
				'module.decoration.layout__gridJustifyItems' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridJustifyItems',
				],
				'module.decoration.layout__gridOffsetRules' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridOffsetRules',
				],
				'module.decoration.layout__gridRowCount'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowCount',
				],
				'module.decoration.layout__gridRowHeight'  => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowHeight',
				],
				'module.decoration.layout__gridRowHeights' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowHeights',
				],
				'module.decoration.layout__gridRowMinHeight' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowMinHeight',
				],
				'module.decoration.layout__gridTemplateColumns' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridTemplateColumns',
				],
				'module.decoration.layout__gridTemplateRows' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridTemplateRows',
				],
				'module.decoration.layout__justifyContent' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'justifyContent',
				],
				'module.decoration.layout__alignItems'     => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignItems',
				],
				'module.decoration.layout__collapseEmptyColumns' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'collapseEmptyColumns',
				],
				'module.decoration.layout__alignContent'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignContent',
				],
				'module.decoration.layout__columnGap'      => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'module.decoration.layout__rowGap'         => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'rowGap',
				],
				'module.advanced.html__elementType'        => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'elementType',
				],
				'module.advanced.html__htmlAfter'          => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'htmlAfter',
				],
				'module.advanced.html__htmlBefore'         => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'htmlBefore',
				],
			]
		);
	}
}
