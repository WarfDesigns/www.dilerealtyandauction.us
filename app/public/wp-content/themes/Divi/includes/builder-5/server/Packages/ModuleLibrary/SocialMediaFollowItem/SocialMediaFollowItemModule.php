<?php
/**
 * Module: Social Media Follow Network class.
 *
 * @package ET\Builder\Packages\ModuleLibrary\SocialMediaFollowItem
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\SocialMediaFollowItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleLibrary\SocialMediaFollow\SocialMediaFollowModule;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Attributes\AttributeUtils;
use WP_Block;
use ET\Builder\Packages\GlobalData\GlobalPreset;

/**
 * `SocialMediaFollowItem` is consisted of functions used for Social Media Follow Network such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class SocialMediaFollowItemModule implements DependencyInterface {

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS const cssFields located in
	 * visual-builder/packages/module-library/src/components/social-media-follow-network/custom-css.ts.
	 *
	 * A minor difference with the JS const cssFields, this function did not have `label` property on each array item.
	 *
	 * @since ??
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/social-media-follow-network' )->customCssFields;
	}

	/**
	 * Module classnames function for Social Media Follow Network module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/social-media-follow-item/module-classnames.ts.
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 * @type object $classnamesInstance Instance of ET\Builder\Packages\Module\Layout\Components\Classnames.
	 * @type array $attrs Block attributes data that being rendered.
	 * }
	 * @since ??
	 */
	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$social_network_fa_icons = self::font_awesome_icons();

		$network_name = $attrs['socialNetwork']['innerContent']['desktop']['value']['title'] ?? 'facebook';

		$classnames_instance->add( 'et_pb_social_icon' );
		$classnames_instance->add( 'et_pb_social_network_link' );
		$classnames_instance->add( 'et-pb-social-fa-icon', in_array( $network_name, $social_network_fa_icons, true ) );
		$classnames_instance->add( "et-social-{$network_name}" );
	}

	/**
	 * Social Media Follow Network render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function SocialMediaFollowItemEdit located in
	 * visual-builder/packages/module-library/src/components/social-media-follow-item/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by VB.
	 * @param string         $child_modules_content       The child modules content.
	 * @param WP_Block       $block                       Parsed block object that being rendered.
	 * @param ModuleElements $elements                    ModuleElements instance.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Social Media Follow Network module.
	 */
	public static function render_callback( $attrs, $child_modules_content, $block, $elements, $default_printed_style_attrs ) {
		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		$default_parent_attrs = ModuleRegistration::get_default_attrs( 'divi/social-media-follow' );
		$parent_attrs         = array_replace_recursive( $default_parent_attrs, $parent->attrs ?? [] );

		$item_url            = $attrs['socialNetwork']['innerContent']['desktop']['value']['link'] ?? '#';
		$network_label       = $attrs['socialNetwork']['innerContent']['desktop']['value']['label'] ?? 'facebook';
		$follow_button_label = __( 'Follow', 'et_builder_5' );
		$link_target_attrs   = $parent_attrs['button']['innerContent']['desktop']['value']['linkTarget'] ?? '';
		$target              = HTMLUtility::link_target( $link_target_attrs );

		$formatted_network_name = ucwords( $network_label );
		$formatted_title        = sprintf( __( 'Follow on %s', 'et_builder_5' ), $formatted_network_name );

		// Check for custom target attribute that overrides parent's linkTarget.
		$custom_attributes_data = $attrs['module']['decoration']['attributes'] ?? [];
		if ( ! empty( $custom_attributes_data ) ) {
			$separated_attributes = AttributeUtils::separate_attributes_by_target_element( $custom_attributes_data );
			$main_attributes      = $separated_attributes['main'] ?? [];

			// If a custom target attribute exists, use it to override the parent's linkTarget.
			if ( isset( $main_attributes['target'] ) ) {
				$target = esc_attr( $main_attributes['target'] );
			}
		}

		$is_follow_button_enabled = ModuleUtils::has_value(
			$parent_attrs['socialNetwork']['advanced']['followButton'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'on' === ( $value ?? 'off' );
				},
			]
		);

		$follow_button = $is_follow_button_enabled ?
			HTMLUtility::render(
				[
					'tag'        => 'a',
					'attributes' => [
						'class'  => 'follow_button',
						'href'   => $item_url,
						'target' => $target,
						'title'  => $formatted_title,
						'rel'    => 'noopener',
					],
					'children'   => $follow_button_label,
				]
			) : null;

		$follow_button_label_container = HTMLUtility::render(
			[
				'tag'        => 'span',
				'attributes' => [
					'class' => 'et_pb_social_media_follow_network_name',
				],
				'children'   => $follow_button_label,
			]
		);

		$children = HTMLUtility::render(
			[
				'tag'               => 'a',
				'attributes'        => [
					'href'   => $item_url,
					'class'  => 'icon',
					'target' => $target,
					'title'  => $formatted_title,
					'rel'    => 'noopener',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $follow_button_label_container,
			]
		);

		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'moduleCategory'           => $block->block_type->category,
				'attrs'                    => $attrs,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'elements'                 => $elements,
				'tag'                      => 'li',
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'parentAttrs'              => $parent_attrs,
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
				'childrenIds'              => $children_ids,
				'children'                 => $children . $follow_button . $child_modules_content,
			]
		);
	}

	/**
	 * Set script data of used module options.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   Array of arguments.
	 *
	 *   @type string         $id            Module id.
	 *   @type string         $name          Module name.
	 *   @type string         $selector      Module selector.
	 *   @type array          $attrs         Module attributes.
	 *   @type int            $storeInstance The ID of instance where this block stored in BlockParserStore class.
	 *   @type ModuleElements $elements      ModuleElements instance.
	 * }
	 */
	public static function module_script_data( $args ) {
		// Assign variables.
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$parent_attrs   = $args['parentAttrs'] ?? [];
		$elements       = $args['elements'];
		$store_instance = $args['storeInstance'] ?? null;

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => '{{parentSelector}}',
				'setVisibility' => [
					[
						'selector'      => "{$selector} .follow_button",
						'data'          => $parent_attrs['socialNetwork']['advanced']['followButton'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'on' === ( $value ?? 'off' ) ? 'visible' : 'hidden';
						},
					],
				],
			]
		);
	}

	/**
	 * Get social networks.
	 *
	 * @since ??
	 *
	 * @return array Social networks.
	 */
	public static function get_social_networks(): array {
		return [
			''              => [
				'background' => '',
			],
			'amazon'        => [
				'background' => '#ff9900',
			],
			'bandcamp'      => [
				'background' => '#629aa9',
			],
			'behance'       => [
				'background' => '#0057ff',
			],
			'bitbucket'     => [
				'background' => '#205081',
			],
			'buffer'        => [
				'background' => '#000000',
			],
			'codepen'       => [
				'background' => '#000000',
			],
			'deviantart'    => [
				'background' => '#05cc47',
			],
			'dribbble'      => [
				'background' => '#ea4c8d',
			],
			'facebook'      => [
				'background' => '#3b5998',
			],
			'flikr'         => [
				'background' => '#ff0084',
			],
			'flipboard'     => [
				'background' => '#e12828',
			],
			'foursquare'    => [
				'background' => '#f94877',
			],
			'github'        => [
				'background' => '#333333',
			],
			'goodreads'     => [
				'background' => '#553b08',
			],
			'google'        => [
				'background' => '#4285f4',
			],
			'houzz'         => [
				'background' => '#7ac142',
			],
			'instagram'     => [
				'background' => '#ea2c59',
			],
			'itunes'        => [
				'background' => '#fe7333',
			],
			'last_fm'       => [
				'background' => '#b90000',
			],
			'line'          => [
				'background' => '#00c300',
			],
			'linkedin'      => [
				'background' => '#007bb6',
			],
			'medium'        => [
				'background' => '#00ab6c',
			],
			'meetup'        => [
				'background' => '#e0393e',
			],
			'myspace'       => [
				'background' => '#3b5998',
			],
			'odnoklassniki' => [
				'background' => '#ed812b',
			],
			'patreon'       => [
				'background' => '#f96854',
			],
			'periscope'     => [
				'background' => '#3aa4c6',
			],
			'pinterest'     => [
				'background' => '#cb2027',
			],
			'quora'         => [
				'background' => '#a82400',
			],
			'reddit'        => [
				'background' => '#ff4500',
			],
			'researchgate'  => [
				'background' => '#40ba9b',
			],
			'rss'           => [
				'background' => '#ff8a3c',
			],
			'skype'         => [
				'background' => '#12A5F4',
			],
			'snapchat'      => [
				'background' => '#fffc00',
			],
			'soundcloud'    => [
				'background' => '#ff8800',
			],
			'spotify'       => [
				'background' => '#1db954',
			],
			'steam'         => [
				'background' => '#00adee',
			],
			'telegram'      => [
				'background' => '#179cde',
			],
			'tiktok'        => [
				'background' => '#fe2c55',
			],
			'tripadvisor'   => [
				'background' => '#00af87',
			],
			'tumblr'        => [
				'background' => '#32506d',
			],
			'twitch'        => [
				'background' => '#6441a5',
			],
			'twitter'       => [
				'background' => '#000000',
			],
			'vimeo'         => [
				'background' => '#45bbff',
			],
			'vk'            => [
				'background' => '#45668e',
			],
			'weibo'         => [
				'background' => '#eb7350',
			],
			'whatsapp'      => [
				'background' => '#25D366',
			],
			'xing'          => [
				'background' => '#026466',
			],
			'yelp'          => [
				'background' => '#af0606',
			],
			'youtube'       => [
				'background' => '#a82400',
			],
		];
	}

	/**
	 * Get social network item.
	 *
	 * @since ??
	 *
	 * @param string $slug Social network slug.
	 *
	 * @return array Social network item object.
	 */
	public static function get_social_network( string $slug ): array {
		$social_networks = self::get_social_networks();

		return $social_networks[ $slug ] ?? [];
	}

	/**
	 * Omit the background color attribute from the module attributes if it matches the default value.
	 *
	 * @since ??
	 *
	 * @param array  $module_decoration_background_attr The module attributes.
	 * @param array  $preset_decoration_background_attr The preset attributes.
	 * @param string $default_background_color The default value for the background color.
	 *
	 * @returns array The module attributes with the background color omitted if it matches the default value.
	 */
	public static function maybeOmitBackgroundColorAttr(
		array $module_decoration_background_attr,
		array $preset_decoration_background_attr,
		string $default_background_color
	): array {
		$decoration_background_attr = $module_decoration_background_attr;

		foreach ( $module_decoration_background_attr as $breakpoint => $states ) {
			foreach ( $states as $state => $state_value ) {
				$background_color = $state_value['color'] ?? null;

				if ( $background_color === $default_background_color && isset( $preset_decoration_background_attr[ $breakpoint ][ $state ]['color'] ) ) {
					unset( $decoration_background_attr[ $breakpoint ][ $state ]['color'] );
				}

				if ( empty( $decoration_background_attr[ $breakpoint ][ $state ] ) ) {
					unset( $decoration_background_attr[ $breakpoint ][ $state ] );
				}
			}

			if ( empty( $decoration_background_attr[ $breakpoint ] ) ) {
				unset( $decoration_background_attr[ $breakpoint ] );
			}
		}

		return $decoration_background_attr;
	}

	/**
	 * SocialMediaFollowItem Module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/social-media-follow-item/styles.tsx.
	 *
	 * @param array $args {
	 *      An array of arguments.
	 *
	 *      @type string $id Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *      @type string $name Module name.
	 *      @type string $attrs Module attributes.
	 *      @type string $parentAttrs Parent attrs.
	 *      @type string $orderClass Selector class name.
	 *      @type string $parentOrderClass Parent selector class name.
	 *      @type string $wrapperOrderClass Wrapper selector class name.
	 *      @type string $settings Custom settings.
	 *      @type string $state Attributes state.
	 *      @type string $mode Style mode.
	 *      @type ModuleElements $elements ModuleElements instance.
	 * }
	 * @since ??
	 */
	public static function module_styles( $args ) {
		$attrs       = $args['attrs'] ?? [];
		$elements    = $args['elements'];
		$settings    = $args['settings'] ?? [];
		$style_group = $args['styleGroup'] ?? 'module';

		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];
		$parent_attrs                = $args['parentAttrs'] ?? [];

		$base_order_class = $args['baseOrderClass'] ?? '';
		$selector_prefix  = $args['selectorPrefix'] ?? '';

		// Icon size inheritance: merge parent icon size attributes with child attributes.
		// This properly inherits parent icon size attributes and merges them with child attributes.
		$icon_size_attr        = ArrayUtility::get_value( $attrs, 'icon.advanced.size', [] );
		$parent_icon_size_attr = ArrayUtility::get_value( $parent_attrs, 'icon.advanced.size', [] );
		$merged_icon_size_attr = array_replace_recursive( [], $parent_icon_size_attr, $icon_size_attr );

		// Check if we have icon size values (either from child or parent) across any breakpoint/state.
		// We need to check for actual string values AND that useSize is not explicitly 'off'.
		// When useSize is 'off', it means "don't use custom size, use defaults".
		$has_icon_size = ModuleUtils::has_value(
			$merged_icon_size_attr,
			[
				'valueResolver' => function ( $value ) {
					// $value is the full size attribute object for the current breakpoint/state.
					$size     = $value['size'] ?? '';
					$use_size = $value['useSize'] ?? '';

					return isset( $size ) && is_string( $size ) && '' !== $size && 'off' !== $use_size;
				},
			]
		);

		$icon_advanced_styles = [
			[
				'componentName' => 'divi/common',
				'props'         => [
					'selector' => "{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:before",
					'attr'     => $attrs['icon']['advanced']['color'] ?? [],
					'property' => 'color',
				],
			],
		];

		// Include size/dimension styles when we have icon size values (from child or parent).
		if ( $has_icon_size ) {
			$icon_advanced_styles = array_merge(
				$icon_advanced_styles,
				[
					[
						'componentName' => 'divi/common',
						'props'         => [
							'selectors'           => [
								'desktop' => [
									'value' => "{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:before",
									'hover' => implode(
										', ',
										[
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon:hover a.icon:before",
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:hover:before",
											"{$selector_prefix}li{$base_order_class}.et_vb_hover.et_pb_social_icon a.icon:before",
										]
									),
								],
								'tablet'  => [
									'value' => "{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:before",
									'hover' => implode(
										', ',
										[
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon:hover a.icon:before",
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:hover:before",
											"{$selector_prefix}li{$base_order_class}.et_vb_hover.et_pb_social_icon a.icon:before",
										]
									),
								],
								'phone'   => [
									'value' => "{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:before",
									'hover' => implode(
										', ',
										[
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon:hover a.icon:before",
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:hover:before",
											"{$selector_prefix}li{$base_order_class}.et_vb_hover.et_pb_social_icon a.icon:before",
										]
									),
								],
							],
							'attr'                => $merged_icon_size_attr,
							'declarationFunction' => [
								SocialMediaFollowModule::class,
								'icon_size_style_declaration',
							],
						],
					],
					[
						'componentName' => 'divi/common',
						'props'         => [
							'selectors'           => [
								'desktop' => [
									'value' => "{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon",
									'hover' => implode(
										', ',
										[
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon:hover a.icon",
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:hover",
											"{$selector_prefix}li{$base_order_class}.et_vb_hover.et_pb_social_icon a.icon",
										]
									),
								],
								'tablet'  => [
									'value' => "{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon",
									'hover' => implode(
										', ',
										[
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon:hover a.icon",
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:hover",
											"{$selector_prefix}li{$base_order_class}.et_vb_hover.et_pb_social_icon a.icon",
										]
									),
								],
								'phone'   => [
									'value' => "{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon",
									'hover' => implode(
										', ',
										[
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon:hover a.icon",
											"{$selector_prefix}li{$base_order_class}.et_pb_social_icon a.icon:hover",
											"{$selector_prefix}li{$base_order_class}.et_vb_hover.et_pb_social_icon a.icon",
										]
									),
								],
							],
							'attr'                => $merged_icon_size_attr,
							'declarationFunction' => [
								SocialMediaFollowModule::class,
								'icon_dimension_style_declaration',
							],
						],
					],
				]
			);
		}

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
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? [],
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'attrsFilter'              => function ( array $decoration_attrs ) use ( $style_group, $elements, $attrs, $args, $default_printed_style_attrs ) {
									if ( 'module' === $style_group ) {
										$preset_attrs           = GlobalPreset::get_selected_preset(
											[
												'moduleName' => $args['name'],
												'moduleAttrs' => $attrs ?? [],
											]
										)->get_data_attrs();
										$preset_background_attr = $preset_attrs['module']['decoration']['background'] ?? [];
										$module_background_attr = $decoration_attrs['background'] ?? [];

										if ( ! empty( $preset_background_attr ) && ! empty( $module_background_attr ) ) {
											$selected_network         = $attrs['socialNetwork']['innerContent']['desktop']['value']['title'] ?? 'facebook';
											$social_network           = self::get_social_network( $selected_network );
											$default_background_color = $social_network['background'] ?? '';
											$background_attr_omitted  = self::maybeOmitBackgroundColorAttr(
												$module_background_attr,
												$preset_background_attr,
												$default_background_color
											);

											unset( $decoration_attrs['background'] );

											if ( ! empty( $background_attr_omitted ) ) {
												$decoration_attrs['background'] = $background_attr_omitted;
											}
										}
									}

									return $decoration_attrs;
								},
							],
						]
					),

					// Icon.
					$elements->style(
						[
							'attrName'   => 'icon',
							'styleProps' => [
								'advancedStyles' => $icon_advanced_styles,
							],
						]
					),

					// Button.
					$elements->style(
						[
							'attrName' => 'button',
						]
					),

					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => '.et_pb_social_media_follow li' . $args['orderClass'],
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Loads `SocialMediaFollowItem` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/social-media-follow-item/';

		add_filter( 'divi_conversion_presets_attrs_map', [ SocialMediaFollowItemPresetAttrsMap::class, 'get_map' ], 10, 2 );

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}

	/**
	 * Social Media Networks that use Font Awesome icons.
	 *
	 * The Social Media module uses Font Awesome icons for some networks.
	 * If a network is not in this list, then it uses the Divi icon font.
	 *
	 * @since ??
	 *
	 * @return array The list of social media networks that use Font Awesome icons.
	 */
	public static function font_awesome_icons() {
		return [
			'amazon',
			'bandcamp',
			'behance',
			'bitbucket',
			'buffer',
			'codepen',
			'deviantart',
			'flipboard',
			'foursquare',
			'github',
			'goodreads',
			'google',
			'houzz',
			'itunes',
			'last_fm',
			'line',
			'medium',
			'meetup',
			'odnoklassniki',
			'patreon',
			'periscope',
			'quora',
			'reddit',
			'researchgate',
			'snapchat',
			'soundcloud',
			'spotify',
			'steam',
			'telegram',
			'tiktok',
			'tripadvisor',
			'twitch',
			'vk',
			'weibo',
			'whatsapp',
			'xing',
			'yelp',
		];
	}
}
