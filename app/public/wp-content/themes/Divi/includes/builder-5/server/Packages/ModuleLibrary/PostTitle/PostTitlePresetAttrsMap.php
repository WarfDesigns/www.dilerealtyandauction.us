<?php
/**
 * Module Library: PostTitle Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostTitle;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class PostTitlePresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\PostTitle
 */
class PostTitlePresetAttrsMap {
	/**
	 * Get the preset attributes map for the PostTitle module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/post-title' !== $module_name ) {
			return $map;
		}

		return array_merge(
			$map,
			[
				'title.decoration.font.font__headingLevel' => [
					'attrName' => 'title.decoration.font.font',
					'preset'   => [ 'html' ],
					'subName'  => 'headingLevel',
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
