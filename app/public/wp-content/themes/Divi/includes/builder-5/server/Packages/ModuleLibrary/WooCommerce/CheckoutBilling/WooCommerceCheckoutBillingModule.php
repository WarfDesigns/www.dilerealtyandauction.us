<?php
/**
 * Module Library: WooCommerceCheckoutBilling Module
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutBilling;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\WooCommerce\WooCommerceHooks;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use Exception;
use WC_Checkout;
use WC_Shortcode_Checkout;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * WooCommerceCheckoutBillingModule class.
 *
 * This class implements the functionality of a WooCommerce checkout billing component
 * in a frontend application. It provides functions for rendering the checkout billing form,
 * managing styles and responsive behavior, and integrating with WooCommerce checkout process.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceCheckoutBillingModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceCheckoutBilling module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceCheckoutBillingEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceCheckoutBilling module.
	 *
	 * @example
	 * ```php
	 * $attrs = [
	 *   'attrName' => 'value',
	 *   //...
	 * ];
	 * $content = 'The block content.';
	 * $block = new WP_Block();
	 * $elements = new ModuleElements();
	 *
	 * WooCommerceCheckoutBillingModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Return empty string if this is the order pay page, we should not render the module on this page.
		// to avoid showing duplicates of the module.
		global $wp;
		if ( ! empty( $wp->query_vars['order-pay'] ) ) {
			return '';
		}

		/**
		 * Check for cart error state to return empty string.
		 *
		 * This is done because if an error exists, the WooCommerce template output will simply be
		 * "There are some issues with the items in your cart.
		 * Please go back to the cart page and resolve these issues before checking out."
		 * We do not want duplicates of this message on the checkout page, so we return empty string.
		*/
		if (
			WooCommerceUtils::is_woocommerce_cart_method_callable( 'check_cart_items' )
			&& ! is_et_pb_preview()
			&& ! Conditions::is_rest_api_request()
		) {
			WC()->cart->check_cart_items();

			if ( function_exists( 'wc_notice_count' ) && wc_notice_count( 'error' ) > 0 ) {
				return '';
			}
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return '';
		}

		// Get the checkout billing HTML markup.
		$checkout_html = self::get_checkout_billing( [] );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => $parent->attrs ?? [],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'children'            => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'tagEscaped'        => true,
							'attributes'        => [
								'class' => 'et_pb_module_inner',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $checkout_html,
						]
					),
				],
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceCheckoutBilling module.
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
	 *
	 * @example
	 * ```php
	 * $args = [
	 *     'classnamesInstance' => $classnamesInstance,
	 *     'attrs' => $attrs,
	 * ];
	 *
	 * WooCommerceCheckoutBillingModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Base WooCommerce classes (first, matching legacy order).
		$classnames_instance->add( 'woocommerce-checkout' );
		$classnames_instance->add( 'woocommerce' );

		// Text Options (includes text orientation classname, matching legacy order).
		$classnames_instance->add(
			TextClassnames::text_options_classnames(
				$attrs['module']['advanced']['text'] ?? [],
				[
					'color'       => false,
					'orientation' => true,
				]
			),
			true
		);

		// Fields layout classes.
		$fields_width = $attrs['layout']['advanced']['fieldsWidth'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( $fields_width ) {
			$classnames_instance->add( "et_pb_fields_layout_{$fields_width}" );
		}

		// Module element classnames (last, to allow proper CSS override cascade).
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
	 * WooCommerceCheckoutBilling module script data.
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
	 *
	 * @example:
	 * ```php
	 * // Generate the script data for a module with specific arguments.
	 * $args = array(
	 *     'id'             => 'my-module',
	 *     'name'           => 'My Module',
	 *     'selector'       => '.my-module',
	 *     'attrs'          => array(
	 *         'portfolio' => array(
	 *             'advanced' => array(
	 *                 'showTitle'       => false,
	 *                 'showCategories'  => true,
	 *                 'showPagination' => true,
	 *             )
	 *         )
	 *     ),
	 *     'elements'       => $elements,
	 *     'store_instance' => 123,
	 * );
	 *
	 * WooCommerceCheckoutBillingModule::module_script_data( $args );
	 * ```
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$store_instance = $args['storeInstance'] ?? null;
		$elements       = $args['elements'];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		// Add responsive class names for field layout settings.
		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setClassName'  => [
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_fields_layout_stacked' => $attrs['layout']['advanced']['fieldsWidth'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'stacked' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_fields_layout_inline' => $attrs['layout']['advanced']['fieldsWidth'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'inline' === $value ? 'add' : 'remove';
						},
					],
				],
			]
		);
	}

	/**
	 * WooCommerceCheckoutBilling Module's style components.
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

		// Extract the order class.
		$order_class = $args['orderClass'] ?? '';

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
								'disabledOn'     => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/text',
										'props'         => [
											'selector' => "{$order_class}",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $attrs['module']['decoration']['border'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												$overflow_attr = $attrs['module']['decoration']['overflow'] ?? [];
												return Declarations::overflow_for_border_radius_style_declaration( $params, $overflow_attr );
											},
										],
									],
								],
							],
						]
					),

					// Title.
					$elements->style(
						[
							'attrName' => 'title',
						]
					),

					// Field.
					$elements->style(
						[
							'attrName'   => 'field',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $attrs['field']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),

					// Field Labels.
					$elements->style(
						[
							'attrName'   => 'fieldLabels',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr'      => $attrs['fieldLabels']['advanced']['requiredFieldIndicatorColor'] ?? [],
											'declarationFunction' => [ self::class, 'required_field_indicator_color_style_declaration' ],
											'selector'  => "{$order_class} form .form-row .required",
											'selectors' => [
												'desktop' => [
													'value' => "{$order_class} form .form-row .required",
													'hover' => "{$order_class} form .form-row:hover .required",
												],
											],
										],
									],
								],
							],
						]
					),

					// Form Notice.
					$elements->style(
						[
							'attrName'   => 'formNotice',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr'     => $attrs['formNotice']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
											'selector' => "{$order_class} .woocommerce-error",
										],
									],
								],
							],
						]
					),

					// Form Field - Enhanced field styling with focus states.
					FormFieldStyle::style(
						[
							'selector'          => implode(
								', ',
								[
									".woocommerce {$order_class} .select2-container--default .select2-selection--single",
									".woocommerce {$order_class} form .form-row .input-text",
								]
							),
							'attr'              => $attrs['field'] ?? [],
							'orderClass'        => $order_class,
							'important'         => [
								'font'    => [
									'font' => [
										'desktop' => [
											'value' => [
												'line-height' => true,
												'font-size'   => true,
												'font-family' => true,
											],
										],
									],
								],
								'spacing' => true, // Required to override WooCommerce default spacing.
								'focus'   => [
									'background' => true,
									'font'       => [
										'font' => [
											'desktop' => [
												'value' => [
													'line-height' => true,
													'font-size'   => true,
													'font-family' => true,
												],
											],
										],
									],
								],
							],
							'propertySelectors' => [
								'spacing'    => [
									'desktop' => [
										'value' => [
											'margin'  => implode(
												', ',
												[
													"{$order_class} form .form-row input.input-text",
													"{$order_class} .select2-container--default .select2-selection--single",
												]
											),
											'padding' => implode(
												', ',
												[
													"{$order_class} form .form-row input.input-text",
													"{$order_class} .select2-container--default .select2-selection--single",
												]
											),
										],
									],
								],
								'background' => [
									'desktop' => [
										'hover' => [
											'background-color' => implode(
												', ',
												[
													"{$order_class} .select2-container--default .select2-selection--single:hover",
													".woocommerce {$order_class} form .form-row .input-text:hover",
												]
											),
										],
									],
								],
								'focus'      => [
									'background' => [
										'desktop' => [
											'value' => [
												'background-color' => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--open .select2-selection",
														".woocommerce {$order_class} form .input-text",
													]
												),
											],
											'hover' => [
												'background-color' => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--open:hover .select2-selection",
														".woocommerce {$order_class} form .input-text:hover",
													]
												),
											],
										],
									],
									'font'       => [
										'font' => [
											'desktop' => [
												'value' => [
													'color' => implode(
														', ',
														[
															".woocommerce {$order_class} .select2-container--open .select2-selection__rendered",
															".woocommerce {$order_class} form .form-row input.input-text",
														]
													),
												],
												'hover' => [
													'color' => implode(
														', ',
														[
															".woocommerce {$order_class} .select2-container--open:hover .select2-selection__rendered",
															".woocommerce {$order_class} form .form-row input.input-text:hover",
														]
													),
												],
											],
										],
									],
									'border'     => [
										'desktop' => [
											'value' => [
												'border-radius' => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--default.select2-container--open .select2-selection--single",
														".woocommerce {$order_class} form .form-row input.input-text",
													]
												),
												'border-style'  => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--default.select2-container--open .select2-selection--single",
														".woocommerce {$order_class} form .form-row .input-text",
													]
												),
											],
											'hover' => [
												'border-radius' => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--default.select2-container--open:hover .select2-selection--single",
														".woocommerce {$order_class} form .form-row input.input-text:hover",
													]
												),
												'border-style'  => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--default.select2-container--open:hover .select2-selection--single",
														".woocommerce {$order_class} form .form-row .input-text:hover",
													]
												),
											],
										],
									],
								],
								'font'       => [
									'font' => [
										'desktop' => [
											'hover' => [
												'color' => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container .select2-selection--single:hover .select2-selection__rendered",
														".woocommerce {$order_class} form .form-row .input-text:hover",
													]
												),
											],
										],
									],
								],
							],
						]
					),

					// Field Overflow - Border radius clipping for form fields.
					CommonStyle::style(
						[
							'selector'            => implode(
								', ',
								[
									".woocommerce {$order_class} .select2-container--default .select2-selection--single",
									".woocommerce {$order_class} form .form-row .input-text",
								]
							),
							'attr'                => $attrs['field']['decoration']['border'] ?? [],
							'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
							'orderClass'          => $order_class,
						]
					),

					// Placeholder styles - single ElementStyle component for all pseudo-element selectors.
					ElementStyle::style(
						[
							'selector'   => implode(
								', ',
								[
									".woocommerce {$order_class} form .form-row input.input-text",
									".woocommerce {$order_class} form .form-row textarea",
								]
							),
							'attrs'      => [
								'font' => $attrs['field']['advanced']['placeholder']['font'] ?? [],
							],
							'orderClass' => $order_class,
							'font'       => [
								'selectorFunction' => function ( $params ) {
									$maybe_multiple_selectors = $params['selector'] ?? '';
									$base_selectors           = array_map( 'trim', explode( ',', $maybe_multiple_selectors ) );
									$placeholder_selectors    = [];

									// Generate all pseudo-element combinations for each base selector.
									foreach ( $base_selectors as $selector ) {
										$placeholder_selectors[] = "{$selector}::placeholder";
										$placeholder_selectors[] = "{$selector}::-webkit-input-placeholder";
										$placeholder_selectors[] = "{$selector}::-moz-placeholder";
										$placeholder_selectors[] = "{$selector}:-ms-input-placeholder";
									}

									return implode( ', ', $placeholder_selectors );
								},
								'important'        => true,
							],
						]
					),

					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'],
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}


	/**
	 * Required field indicator color style declaration.
	 *
	 * This function handles the color styling for required field indicators in forms.
	 * It creates CSS declarations for the color property of required field asterisks.
	 *
	 * This function is the PHP equivalent of the TypeScript function
	 * `requiredFieldIndicatorColorStyleDeclaration` located in the visual-builder style declarations.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type string $attrValue Optional. The color value for required field indicators. Default empty string.
	 * }
	 *
	 * @return string The CSS declaration string for the required field indicator color.
	 *
	 * @example
	 * ```php
	 * $params = [
	 *     'attrValue' => '#ff0000',
	 * ];
	 *
	 * WooCommerceCheckoutBillingModule::required_field_indicator_color_style_declaration( $params );
	 * ```
	 */
	public static function required_field_indicator_color_style_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? '';

		if ( empty( $attr_value ) ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		$style_declarations->add( 'color', $attr_value );

		return $style_declarations->value();
	}

	/**
	 * Get the custom CSS fields for the Divi WooCommerceCheckoutBilling module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceCheckoutBilling module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceCheckoutBilling module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceCheckoutBilling module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-checkout-billing' );

		if ( ! $registered_block ) {
			return [];
		}

		$custom_css = $registered_block->customCssFields;

		if ( ! is_array( $custom_css ) ) {
			return [];
		}

		return $custom_css;
	}

	/**
	 * Loads `WooCommerceCheckoutBillingModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 * @throws Exception If the module registration fails.
	 */
	public function load(): void {
		/*
		 * Bail if the WooCommerce plugin is not active.
		 */
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/checkout-billing/';

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
	 * Gets the Checkout Billing markup.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags List of conditional tags.
	 *
	 * @return string The rendered HTML markup.
	 */
	public static function get_checkout_billing( array $conditional_tags = [] ): string {
		$is_tb              = $conditional_tags['is_tb'] ?? false;
		$is_use_placeholder = $is_tb || is_et_pb_preview();
		$is_visual_builder  = Conditions::is_rest_api_request() || Conditions::is_vb_app_window() || is_et_pb_preview();

		if ( $is_visual_builder || $is_use_placeholder ) {
			// Ensure WooCommerce objects are properly initialized for VB/TB and preview contexts.
			WooCommerceUtils::ensure_woocommerce_objects_initialized( $conditional_tags );
		}

		self::_maybe_handle_hooks( $conditional_tags );

		$is_cart_empty = WooCommerceUtils::is_woocommerce_cart_available() && WC()->cart->is_empty();

		// Set fake cart contents when no product is in the cart.
		// This is needed when the cart is empty on VB/TB and preview contexts.
		if ( ( $is_cart_empty && $is_visual_builder ) || $is_use_placeholder ) {
			add_filter(
				'woocommerce_get_cart_contents',
				[ WooCommerceUtils::class, 'set_dummy_cart_contents' ],
				10,
				1
			);
		}

		ob_start();

		WC_Shortcode_Checkout::output( [] );

		$markup = ob_get_clean();

		if ( ( $is_cart_empty && $is_visual_builder ) || $is_use_placeholder ) {
			remove_filter(
				'woocommerce_get_cart_contents',
				[ WooCommerceUtils::class, 'set_dummy_cart_contents' ]
			);
		}

		self::_maybe_reset_hooks( $conditional_tags );

		// Return an empty string if the markup is not a string.
		if ( ! is_string( $markup ) ) {
			$markup = '';
		}

		return $markup;
	}

	/**
	 * Swaps Checkout template.
	 *
	 * Coupon Remove Link must be shown in VB. Hence, we swap the template.
	 *
	 * @since ??
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default path.
	 *
	 * @return string
	 */
	public static function swap_template( string $template, string $template_name, array $args, string $template_path, string $default_path ): string {
		$is_template_override = 'checkout/form-checkout.php' === $template_name;

		if ( $is_template_override ) {
			return trailingslashit( ET_BUILDER_5_DIR ) . 'server/Packages/WooCommerce/Templates/' . $template_name;
		}

		return $template;
	}

	/**
	 * Handle hooks for checkout billing rendering.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags List of conditional tags.
	 */
	private static function _maybe_handle_hooks( array $conditional_tags = [] ): void {
		$is_tb = $conditional_tags['is_tb'] ?? false;

		WooCommerceHooks::detach_wc_checkout_coupon_form();
		WooCommerceHooks::detach_wc_checkout_login_form();
		WooCommerceHooks::detach_wc_checkout_order_review();
		WooCommerceHooks::detach_wc_checkout_payment();

		if ( ! Conditions::is_rest_api_request() && ! $is_tb ) {
			add_filter(
				'wc_get_template',
				[ self::class, 'swap_template' ],
				10,
				5
			);
		}

		remove_action(
			'woocommerce_checkout_shipping',
			[ WC_Checkout::instance(), 'checkout_form_shipping' ]
		);
	}

	/**
	 * Reset hooks after checkout billing rendering.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags List of conditional tags.
	 */
	private static function _maybe_reset_hooks( array $conditional_tags = [] ): void {
		$is_tb = $conditional_tags['is_tb'] ?? false;

		WooCommerceHooks::attach_wc_checkout_coupon_form();
		WooCommerceHooks::attach_wc_checkout_login_form();
		WooCommerceHooks::attach_wc_checkout_order_review();
		WooCommerceHooks::attach_wc_checkout_payment();

		if ( ! Conditions::is_rest_api_request() && ! $is_tb ) {
			remove_filter(
				'wc_get_template',
				[ self::class, 'swap_template' ],
				10,
				5
			);
		}

		add_action(
			'woocommerce_checkout_shipping',
			[ WC_Checkout::instance(), 'checkout_form_shipping' ]
		);
	}
}
