<?php
/**
 * Module Library: WooCommerceCheckoutShipping Module
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutShipping;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\WooCommerce\WooCommerceHooks;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;
use WC_Checkout;
use WC_Shortcode_Checkout;

/**
 * WooCommerceCheckoutShippingModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceCheckoutShipping module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceCheckoutShippingModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceCheckoutShipping module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceCheckoutShippingEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceCheckoutShipping module.
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
	 * WooCommerceCheckoutShippingModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return '';
		}

		// Return empty string if shipping to billing address only.
		if ( function_exists( 'wc_ship_to_billing_address_only' ) && wc_ship_to_billing_address_only() ) {
			return '';
		}

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

		$checkout_shipping_html = self::get_checkout_shipping();

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
							'children'          => $checkout_shipping_html,
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
	 * arguments. It is used in the `render_callback` function of the WooCommerceCheckoutShipping module.
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
	 * WooCommerceCheckoutShippingModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		$fields_width = $attrs['layout']['advanced']['fieldsWidth'][ $default_breakpoint ][ $default_state ] ?? null;

		if ( $fields_width ) {
			$classnames_instance->add( "et_pb_fields_layout_{$fields_width}" );
		}

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
	 * WooCommerceCheckoutShipping module script data.
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
	 * WooCommerceCheckoutShippingModule::module_script_data( $args );
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

		// Add responsive class names for show/hide settings.
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
							'fields_width_value' => $attrs['layout']['advanced']['fieldsWidth'] ?? [],
						],
						'valueResolver' => function ( $fields_width_value ) {
							return "et_pb_fields_layout_{$fields_width_value}";
						},
					],
				],
			]
		);
	}

	/**
	 * WooCommerceCheckoutShipping Module's style components.
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
					FormFieldStyle::style(
						[
							'selector'          => implode(
								', ',
								[
									".woocommerce {$order_class} .select2-container--default .select2-selection--single",
									".woocommerce {$order_class} form .form-row .input-text",
								]
							),
							'attr'              => array_merge(
								$attrs['field'] ?? [],
								[
									'advanced' => array_merge(
										$attrs['field']['advanced'] ?? [],
										[
											'focusUseBorder' => [
												'desktop' => [ 'value' => 'on' ],
											],
										]
									),
								]
							),
							'orderClass'        => $order_class,
							'important'         => [
								'font' => [
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
							'propertySelectors' => [
								'background'  => [
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
								'focus'       => [
									'background' => [
										'desktop' => [
											'value' => [
												'background-color' => implode(
													', ',
													[
														"{$order_class} .select2-container--default .select2-selection--single:focus",
														".woocommerce {$order_class} form .form-row .input-text:focus",
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
															".woocommerce {$order_class} form .form-row input.input-text:focus",
														]
													),
												],
												'hover' => [
													'color' => implode(
														', ',
														[
															".woocommerce {$order_class} .select2-container--open:hover .select2-selection__rendered",
															".woocommerce {$order_class} form .form-row input.input-text:focus:hover",
														]
													),
												],
											],
										],
									],
									'border'     => [
										'desktop' => [
											'value' => [
												'border' => "{$order_class} form .form-row input.input-text",
											],
										],
									],
								],
								'font'        => [
									'font' => [
										'desktop' => [
											'value' => [
												'color' => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container .select2-selection--single .select2-selection__rendered",
														".woocommerce {$order_class} form .form-row .input-text",
													]
												),
											],
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
								'placeholder' => [
									'font' => [
										'font' => [
											'desktop' => [
												'value' => [
													'color' => "{$order_class} form .form-row input.input-text",
												],
											],
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
									// Required Field Indicator Color.
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr'      => $attrs['fieldLabels']['advanced']['requiredFieldIndicatorColor'] ?? [],
											'selector'  => "{$order_class} form .form-row .required",
											'declarationFunction' => [ self::class, 'required_field_indicator_color_style_declaration' ],
											'important' => true,
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
	 * Get the custom CSS fields for the Divi WooCommerceCheckoutShipping module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceCheckoutShipping module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceCheckoutShipping module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceCheckoutShipping module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-checkout-shipping' );

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
	 * Required field indicator color style declaration for WooCommerce Checkout Shipping module.
	 *
	 * This function is the equivalent of the `requiredFieldIndicatorColorStyleDeclaration` JS function located in
	 * the module library style declarations.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of parameters for the style declaration.
	 *
	 *     @type array $attrValue The attribute value containing required field indicator color.
	 * }.
	 *
	 * @return string The required field indicator color style declarations.
	 */
	public static function required_field_indicator_color_style_declaration( array $params ): string {
		$required_field_indicator_color = $params['attrValue'] ?? null;

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		if ( $required_field_indicator_color ) {
			$style_declarations->add( 'color', $required_field_indicator_color );
		}

		return $style_declarations->value();
	}

	/**
	 * Loads `WooCommerceCheckoutShippingModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		/*
		 * Bail if the WooCommerce plugin is not active.
		 */
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/checkout-shipping/';

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
	 * Swaps form checkout template.
	 *
	 * This function is used to swap the template for the form checkout.
	 * In VB, the `Coupon Remove Link` must be shown, which is why we swap the template.
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
	 * Handle hooks.
	 *
	 * This function is used to handle the hooks for the checkout shipping.
	 *
	 * @param array $conditional_tags {
	 *     An array of conditional tags.
	 *
	 *     @type bool $is_tb Whether the current request is a Theme Builder request.
	 * }
	 *
	 * @return void
	 */
	public static function maybe_handle_hooks( array $conditional_tags ): void {
		$is_tb = $conditional_tags['is_tb'] ?? false;

		WooCommerceHooks::detach_wc_checkout_coupon_form();
		WooCommerceHooks::detach_wc_checkout_login_form();
		WooCommerceHooks::detach_wc_checkout_order_review();
		WooCommerceHooks::detach_wc_checkout_payment();

		if ( ! Conditions::is_rest_api_request() && ! $is_tb ) {
			add_filter(
				'wc_get_template',
				[
					self::class,
					'swap_template',
				],
				10,
				5
			);
		}

		remove_action(
			'woocommerce_checkout_billing',
			[
				WC_Checkout::instance(),
				'checkout_form_billing',
			]
		);
	}

	/**
	 * Reset hooks.
	 *
	 * This function is used to reset the hooks for the checkout shipping.
	 *
	 * @param array $conditional_tags {
	 *     An array of conditional tags.
	 *
	 *     @type bool $is_tb Whether the current request is a Theme Builder request.
	 * }
	 *
	 * @return void
	 */
	public static function maybe_reset_hooks( array $conditional_tags ): void {
		$is_tb = $conditional_tags['is_tb'] ?? false;

		WooCommerceHooks::attach_wc_checkout_coupon_form();
		WooCommerceHooks::attach_wc_checkout_login_form();
		WooCommerceHooks::attach_wc_checkout_order_review();
		WooCommerceHooks::attach_wc_checkout_payment();

		if ( ! Conditions::is_rest_api_request() && ! $is_tb ) {
			remove_filter(
				'wc_get_template',
				[
					self::class,
					'swap_template',
				],
				10,
				5
			);
		}

		add_action(
			'woocommerce_checkout_billing',
			[
				WC_Checkout::instance(),
				'checkout_form_billing',
			]
		);
	}

	/**
	 * Gets the checkout shipping HTML markup.
	 *
	 * This function is used to get the checkout shipping HTML markup.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags {
	 *     An array of conditional tags.
	 *
	 *     @type bool $is_tb Whether the current request is a Theme Builder request.
	 * }
	 *
	 * @return string
	 */
	public static function get_checkout_shipping( array $conditional_tags = [] ): string {
		$is_tb              = $conditional_tags['is_tb'] ?? false;
		$is_use_placeholder = $is_tb || is_et_pb_preview();
		$is_visual_builder  = Conditions::is_rest_api_request() || Conditions::is_vb_app_window() || is_et_pb_preview();

		if ( $is_visual_builder || $is_use_placeholder ) {
			// Ensure WooCommerce objects are properly initialized for VB/TB and preview contexts.
			WooCommerceUtils::ensure_woocommerce_objects_initialized( $conditional_tags );
		}

		self::maybe_handle_hooks( $conditional_tags );

		$is_cart_empty = function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty();

		// Set fake cart contents to output checkout shipping markup when no product is in cart.
		if ( ( $is_cart_empty && $is_visual_builder ) || is_et_pb_preview() ) {
			add_filter(
				'woocommerce_get_cart_contents',
				[
					WooCommerceUtils::class,
					'set_dummy_cart_contents',
				]
			);
		}

		if ( $is_visual_builder ) {
			add_filter( 'woocommerce_cart_needs_shipping_address', '__return_true' );
		}

		ob_start();

		WC_Shortcode_Checkout::output( [] );

		$markup = ob_get_clean();

		if ( $is_visual_builder ) {
			remove_filter( 'woocommerce_cart_needs_shipping_address', '__return_true' );
		}

		if ( ( $is_cart_empty && $is_visual_builder ) || is_et_pb_preview() ) {
			remove_filter(
				'woocommerce_get_cart_contents',
				[
					WooCommerceUtils::class,
					'set_dummy_cart_contents',
				]
			);
		}

		self::maybe_reset_hooks( $conditional_tags );

		// Return empty string if the markup is not a string.
		if ( ! is_string( $markup ) ) {
			$markup = '';
		}

		return $markup;
	}
}
