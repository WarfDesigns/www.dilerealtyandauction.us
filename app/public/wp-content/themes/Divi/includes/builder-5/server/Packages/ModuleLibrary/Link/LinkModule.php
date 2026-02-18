<?php
/**
 * Module Library: Link Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Link;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use WP_Block;

/**
 * LinkModule class.
 *
 * This class implements the functionality of a link component in a frontend
 * application. It provides functions for rendering the link, managing REST API
 * endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class LinkModule implements DependencyInterface {

	/**
	 * Link module script data.
	 *
	 * This function assigns variables and sets script data options for the module.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs ModuleScriptData}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for setting the module script data.
	 *
	 *     @type string         $id            The module ID.
	 *     @type string         $name          The module name.
	 *     @type string         $selector      The module selector.
	 *     @type array          $attrs         The module attributes.
	 *     @type int            $storeInstance The ID of the instance where this block is stored in the `BlockParserStore` class.
	 *     @type ModuleElements $elements      The `ModuleElements` instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$elements       = $args['elements'];
		$store_instance = $args['storeInstance'] ?? null;

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		// Multi-view Script Data for responsive content.
		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'selector'      => $selector,
				'setContent'    => [
					[
						'data'          => $attrs['content']['innerContent'] ?? [],
						'valueResolver' => function ( $value ) {
							$text = $value['text'] ?? '';

							if ( $text ) {
								$text = ModuleUtils::extract_link_title( $text );
							}

							return '' === $text && ! empty( $value['linkUrl'] ) ? esc_url( $value['linkUrl'] ) : esc_html( $text );
						},
					],
				],
				'setAttrs'      => [
					[
						'selector'   => $selector,
						'data'       => [
							'href' => $attrs['content']['innerContent'] ?? [],
						],
						'subName'    => 'linkUrl',
						'sanitizers' => [
							'href' => 'esc_url',
						],
						'tag'        => 'a',
					],
					[
						'selector'      => $selector,
						'data'          => [
							'target' => $attrs['content']['innerContent'] ?? [],
						],
						'subName'       => 'linkTarget',
						'valueResolver' => function ( $value ) {
							return 'on' === $value ? '_blank' : '';
						},
						'sanitizers'    => [
							'target' => 'esc_attr',
						],
						'tag'           => 'a',
					],
					[
						'selector'      => $selector,
						'data'          => [
							'rel' => $attrs['content']['innerContent'] ?? [],
						],
						'subName'       => 'linkTarget',
						'valueResolver' => function ( $value ) {
							return 'on' === $value ? 'noopener noreferrer' : '';
						},
						'sanitizers'    => [
							'rel' => 'esc_attr',
						],
						'tag'           => 'a',
					],
				],
				'setVisibility' => [
					[
						'data'          => $attrs['content']['innerContent'] ?? [],
						'valueResolver' => function ( $value ) {
							return empty( $value['text'] ) && empty( $value['linkUrl'] ) ? 'hidden' : 'visible';
						},
					],
				],
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Link module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js/module-library/module-classnames moduleClassnames}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Module classnames instance.
	 *     @type array  $attrs              Block attributes data for rendering the module.
	 * }
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['module']['decoration'] ?? [],
						[
							'link' => $attrs['module']['advanced']['link'] ?? [],
						]
					),
				]
			)
		);
	}

	/**
	 * Wrapper classnames function for the Link module.
	 *
	 * This function generates classnames for the module wrapper based on the provided arguments.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs wrapperClassnames}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Instance of ET\Builder\Packages\Module\Layout\Components\Classnames.
	 *     @type array  $attrs              Block attributes data that being rendered.
	 * }
	 *
	 * @return void
	 */
	public static function wrapper_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];

		// Add et_pb_module class to wrapper.
		$classnames_instance->add( 'et_pb_module' );
	}

	/**
	 * Link Module's style components.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js/module-library/module-styles moduleStyles}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type string $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *      @type string $name              Module name.
	 *      @type string $attrs             Module attributes.
	 *      @type string $parentAttrs       Parent attrs.
	 *      @type string $orderClass        Selector class name.
	 *      @type string $parentOrderClass  Parent selector class name.
	 *      @type string $wrapperOrderClass Wrapper selector class name.
	 *      @type string $settings          Custom settings.
	 *      @type string $state             Attributes state.
	 *      @type string $mode              Style mode.
	 *      @type ModuleElements $elements  ModuleElements instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Module.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
					// Content (anchor tag with font styles).
					$elements->style(
						[
							'attrName' => 'content',
						]
					),
					// Icon.
					$elements->style(
						[
							'attrName'   => 'icon',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et-pb-icon',
											'attr'     => $attrs['icon']['innerContent'] ?? null,
											'declarationFunction' => [ self::class, 'icon_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et-pb-icon',
											'attr'     => $attrs['icon']['advanced']['color'] ?? null,
											'property' => 'color',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et-pb-icon',
											'attr'     => $attrs['content']['decoration']['font']['font'] ?? null,
											'declarationFunction' => [ self::class, 'font_size_from_content_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et-pb-icon',
											'attr'     => $attrs['icon']['advanced']['size'] ?? null,
											'property' => 'font-size',
										],
									],
								],
							],
						]
					),
					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'],
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/link' )->customCssFields ?? [],
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback for the Link module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ LinkEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                Block attributes that were saved by Divi Builder.
	 * @param string         $child_modules_content The block's content (child modules content).
	 * @param WP_Block       $block                Parsed block object that is being rendered.
	 * @param ModuleElements $elements             An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the Link module.
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		// Get text, link URL, and link target from content element's innerContent.
		// For into-multiple-groups with subName, structure is: content.innerContent.desktop.value.fieldName.
		$link_url    = $attrs['content']['innerContent']['desktop']['value']['linkUrl'] ?? '';
		$link_target = $attrs['content']['innerContent']['desktop']['value']['linkTarget'] ?? 'off';
		$link_text   = $attrs['content']['innerContent']['desktop']['value']['text'] ?? '';

		// Ensure text is a string, not an array.
		if ( is_array( $link_text ) ) {
			$link_text = '';
		}

		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Get module config to check enableWhenChildren setting.
		$module_config        = \WP_Block_Type_Registry::get_instance()->get_registered( $block->block_type->name );
		$enable_when_children = $module_config->wrapper['enableWhenChildren'] ?? false;
		$has_children         = ! empty( $children_ids ) && is_array( $children_ids ) && count( $children_ids ) > 0;

		// When enableWhenChildren is true and there are children, child modules should be rendered
		// outside the anchor tag (as wrapper children) to maintain valid HTML structure.
		$should_separate_children = $enable_when_children && $has_children;

		// Prepare HTML attributes for the anchor tag.
		$html_attrs = [];

		// Add href attribute if URL is provided.
		if ( ! empty( $link_url ) ) {
			$html_attrs['href'] = esc_url( $link_url );
		}

		// Add target and rel attributes if link target is set to open in new tab.
		if ( 'on' === $link_target ) {
			$html_attrs['target'] = '_blank';
			$html_attrs['rel']    = 'noopener noreferrer';
		}

		// Build content (just the text, no inner anchor tag).
		$content = esc_html( $link_text );

		// Get and render icon if present.
		$icon_attr  = $attrs['icon']['innerContent']['desktop']['value'] ?? [];
		$icon_value = Utils::process_font_icon( $icon_attr );
		$icon       = '';
		if ( ! empty( $icon_value ) ) {
			$icon = $elements->render(
				[
					'attrName'      => 'icon',
					'tagName'       => 'span',
					'attributes'    => [ 'class' => 'et-pb-icon' ],
					'applyWpautop'  => false,
					'valueResolver' => function ( $value ) {
						$process_font_icon = Utils::process_font_icon( $value );

						return is_string( $process_font_icon ) ? $process_font_icon : '';
					},
				]
			);
		}

		// Build module children (style components + icon + text content).
		// When enableWhenChildren is true, child modules content goes to wrapperChildren instead.
		$module_children = $elements->style_components(
			[
				'attrName' => 'module',
			]
		) . $elements->style_components(
			[
				'attrName' => 'content',
			]
		) . $icon . $content;

		// If we should separate children, don't include child_modules_content in module children.
		if ( ! $should_separate_children ) {
			$module_children .= $child_modules_content;
		}

		return Module::render(
			[
				// FE only.
				'orderIndex'                => $block->parsed_block['orderIndex'],
				'storeInstance'             => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                     => $attrs,
				'elements'                  => $elements,
				'id'                        => $block->parsed_block['id'],
				'name'                      => $block->block_type->name,
				'tag'                       => 'a',
				'htmlAttrs'                 => $html_attrs,
				'classnamesFunction'        => [ self::class, 'module_classnames' ],
				'wrapperClassnamesFunction' => [ self::class, 'wrapper_classnames' ],
				'moduleCategory'            => $block->block_type->category,
				'stylesComponent'           => [ self::class, 'module_styles' ],
				'scriptDataComponent'       => [ self::class, 'module_script_data' ],
				'parentAttrs'               => $parent->attrs ?? [],
				'parentId'                  => $parent->id ?? '',
				'parentName'                => $parent->blockName ?? '',
				'childrenIds'               => $children_ids,
				'children'                  => $module_children,
				'wrapperChildren'           => $should_separate_children ? $child_modules_content : '',
			]
		);
	}

	/**
	 * Icon style declaration for the Link module.
	 *
	 * This function declares icon style (font-family, font-weight) for the icon element.
	 *
	 * @since ??
	 *
	 * @param array $params An array of arguments containing attrValue.
	 *
	 * @return string The CSS for icon style.
	 */
	/**
	 * Font size from content style declaration for the Link module.
	 *
	 * Outputs the content font's size to the icon so the icon matches the link text size.
	 *
	 * @since ??
	 *
	 * @param array $params An array of arguments containing attrValue.
	 *
	 * @return string The CSS for font-size.
	 */
	public static function font_size_from_content_style_declaration( array $params ): string {
		$font_attr = $params['attrValue'] ?? [];

		// Resolve size from breakpoint structure if needed (e.g. desktop.value.size).
		$size = $font_attr['size'] ?? null;
		if ( empty( $size ) && isset( $font_attr['value']['size'] ) ) {
			$size = $font_attr['value']['size'];
		}

		if ( empty( $size ) ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
			]
		);

		$style_declarations->add( 'font-size', $size );

		return $style_declarations->value();
	}

	/**
	 * Icon style declaration for the Link module.
	 *
	 * This function declares icon style (font-family, font-weight) for the icon element.
	 *
	 * @since ??
	 *
	 * @param array $params An array of arguments containing attrValue.
	 *
	 * @return string The CSS for icon style.
	 */
	public static function icon_style_declaration( array $params ): string {
		$icon_attr = $params['attrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'font-family' => true,
					'font-weight' => true,
				],
			]
		);

		if ( isset( $icon_attr['type'] ) ) {
			$font_family = 'fa' === $icon_attr['type'] ? 'FontAwesome' : 'ETmodules';
			$style_declarations->add( 'font-family', $font_family );
		}

		if ( ! empty( $icon_attr['weight'] ) ) {
			$style_declarations->add( 'font-weight', $icon_attr['weight'] );
		}

		return $style_declarations->value();
	}

	/**
	 * Loads `LinkModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/link/';

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
