<?php
/**
 * Module Library: Gallery Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Gallery;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class GalleryPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Gallery
 */
class GalleryPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Gallery module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/gallery' !== $module_name ) {
			return $map;
		}

		$keys_to_remove = [
			'pagination.decoration.font.font__textAlign',
		];

		foreach ( $keys_to_remove as $key ) {
			unset( $map[ $key ] );
		}

		return array_merge(
			$map,
			[
				'module.decoration.scroll__gridMotion.enable' => [
					'attrName' => 'module.decoration.scroll',
					'preset'   => [
						'script',
					],
					'subName'  => 'gridMotion.enable',
				],
				'galleryGrid.decoration.layout__alignContent' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignContent',
				],
				'galleryGrid.decoration.layout__alignItems' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignItems',
				],
				'galleryGrid.decoration.layout__columnGap' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'galleryGrid.decoration.layout__display'   => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'display',
				],
				'galleryGrid.decoration.layout__flexDirection' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexDirection',
				],
				'galleryGrid.decoration.layout__flexWrap'  => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexWrap',
				],
				'galleryGrid.decoration.layout__justifyContent' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'justifyContent',
				],
				'galleryGrid.decoration.layout__rowGap'    => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'rowGap',
				],
				'pagination.decoration.font__textAlign'    => [
					'attrName' => 'pagination.decoration.font',
					'preset'   => [
						'style',
					],
					'subName'  => 'textAlign',
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
