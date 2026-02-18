<?php
/**
 * Module Library: Sidebar Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Sidebar;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class SidebarPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Sidebar
 */
class SidebarPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Sidebar module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/sidebar' !== $module_name ) {
			return $map;
		}

		return [
			'sidebar.innerContent__area'                   => [
				'attrName' => 'sidebar.innerContent',
				'preset'   => 'content',
				'subName'  => 'area',
			],
			'sidebar.advanced.layout__layoutStyle'         => [
				'attrName' => 'sidebar.advanced.layout',
				'preset'   => [ 'html' ],
				'subName'  => 'layoutStyle',
			],
			'sidebar.advanced.layout__alignment'           => [
				'attrName' => 'sidebar.advanced.layout',
				'preset'   => [ 'html' ],
				'subName'  => 'alignment',
			],
			'sidebar.advanced.layout__showBorder'          => [
				'attrName' => 'sidebar.advanced.layout',
				'preset'   => [ 'html' ],
				'subName'  => 'showBorder',
			],
			'sidebarWidgets.advanced.flexType'             => [
				'attrName' => 'sidebarWidgets.advanced.flexType',
				'preset'   => [ 'html' ],
			],
			'sidebar.decoration.layout__alignContent'      => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'alignContent',
			],
			'sidebar.decoration.layout__alignItems'        => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'alignItems',
			],
			'sidebar.decoration.layout__columnGap'         => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'sidebar.decoration.layout__display'           => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'display',
			],
			'sidebar.decoration.layout__flexDirection'     => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexDirection',
			],
			'sidebar.decoration.layout__flexWrap'          => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexWrap',
			],
			'sidebar.decoration.layout__justifyContent'    => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'justifyContent',
			],
			'sidebar.decoration.layout__rowGap'            => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'rowGap',
			],
			'module.decoration.layout__display'            => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'display',
			],
			'module.decoration.layout__flexDirection'      => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexDirection',
			],
			'module.decoration.layout__flexWrap'           => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexWrap',
			],
			'module.decoration.layout__gridAutoColumns'    => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridAutoColumns',
			],
			'module.decoration.layout__gridAutoFlow'       => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridAutoFlow',
			],
			'module.decoration.layout__gridAutoRows'       => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridAutoRows',
			],
			'module.decoration.layout__gridColumnCount'    => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnCount',
			],
			'module.decoration.layout__gridColumnMinWidth' => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnMinWidth',
			],
			'module.decoration.layout__gridColumnWidth'    => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnWidth',
			],
			'module.decoration.layout__gridColumnWidths'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridColumnWidths',
			],
			'module.decoration.layout__gridDensity'        => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridDensity',
			],
			'module.decoration.layout__gridJustifyItems'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridJustifyItems',
			],
			'module.decoration.layout__gridRowCount'       => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowCount',
			],
			'module.decoration.layout__gridRowHeight'      => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowHeight',
			],
			'module.decoration.layout__gridRowHeights'     => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridRowHeights',
			],
			'module.decoration.layout__gridRowMinHeight'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowMinHeight',
			],
			'module.decoration.layout__gridTemplateColumns' => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridTemplateColumns',
			],
			'module.decoration.layout__gridTemplateRows'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridTemplateRows',
			],
			'module.decoration.layout__justifyContent'     => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'justifyContent',
			],
			'module.decoration.layout__rowGap'             => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'rowGap',
			],
			'module.advanced.html__elementType'            => [
				'attrName' => 'module.advanced.html',
				'preset'   => [ 'html' ],
				'subName'  => 'elementType',
			],
			'module.advanced.html__htmlAfter'              => [
				'attrName' => 'module.advanced.html',
				'preset'   => [ 'html' ],
				'subName'  => 'htmlAfter',
			],
			'module.advanced.html__htmlBefore'             => [
				'attrName' => 'module.advanced.html',
				'preset'   => [ 'html' ],
				'subName'  => 'htmlBefore',
			],
		];
	}
}
