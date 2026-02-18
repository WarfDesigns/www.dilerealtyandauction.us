<?php
/**
 * Module Library: Heading Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Heading;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class HeadingPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Heading
 */
class HeadingPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Heading module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/heading' !== $module_name ) {
			return $map;
		}

		unset( $map['module.advanced.text.text__orientation'] );
		unset( $map['module.advanced.text.text__color'] );

		return array_merge(
			$map,
			[
				'title.decoration.font.font__headingLevel' => [
					'attrName' => 'title.decoration.font.font',
					'preset'   => [ 'html' ],
					'subName'  => 'headingLevel',
				],
				'module.advanced.link__url'                => [
					'attrName' => 'module.advanced.link',
					'preset'   => 'content',
					'subName'  => 'url',
				],
				'module.advanced.link__target'             => [
					'attrName' => 'module.advanced.link',
					'preset'   => 'content',
					'subName'  => 'target',
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
