<?php
/**
 * Deprecated Attribute Mapping for Preset Conversion.
 *
 * This class handles attribute mappings for deprecated settings that have been
 * removed from module UIs but still need to be included in preset conversion
 * for backwards compatibility.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Conversion;

/**
 * Class DeprecatedAttributeMapping
 *
 * Manages deprecated attribute mappings for modules that need them for preset conversion.
 */
class DeprecatedAttributeMapping {

	/**
	 * Get deprecated attribute mappings for a specific module.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name (e.g., 'divi/sidebar').
	 *
	 * @return array Array of deprecated attribute mappings for the module.
	 */
	public static function get_deprecated_attrs_for_module( $module_name ) {
		$module_attribute_types = self::_get_module_attribute_types();
		$attribute_definitions  = self::_get_attribute_definitions();

		$deprecated_attrs = [];

		// Get the deprecated attribute types for this module.
		$attribute_types = $module_attribute_types[ $module_name ] ?? [];

		// Build the deprecated attributes array from the attribute type definitions.
		foreach ( $attribute_types as $attribute_type ) {
			if ( isset( $attribute_definitions[ $attribute_type ] ) ) {
				$deprecated_attrs = array_merge( $deprecated_attrs, $attribute_definitions[ $attribute_type ] );
			}
		}

		return $deprecated_attrs;
	}

	/**
	 * Check if a module has deprecated attributes.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name (e.g., 'divi/sidebar').
	 *
	 * @return bool True if the module has deprecated attributes, false otherwise.
	 */
	public static function has_deprecated_attrs( $module_name ) {
		$module_attribute_types = self::_get_module_attribute_types();

		return isset( $module_attribute_types[ $module_name ] );
	}

	/**
	 * Get mapping of modules to their deprecated attribute types.
	 *
	 * @since ??
	 *
	 * @return array Array mapping module names to their deprecated attribute types.
	 */
	private static function _get_module_attribute_types() {
		return [
			'divi/accordion'                           => [ 'htmlAttributes' ],
			'divi/audio'                               => [ 'htmlAttributes' ],
			'divi/blog'                                => [ 'htmlAttributes', 'blogLayout', 'blogGridFlexType' ],
			'divi/blurb'                               => [ 'htmlAttributes', 'imageIconAlt' ],
			'divi/button'                              => [ 'htmlAttributes', 'buttonRel' ],
			'divi/circle-counter'                      => [ 'htmlAttributes' ],
			'divi/code'                                => [ 'htmlAttributes' ],
			'divi/column'                              => [ 'htmlAttributes' ],
			'divi/column-inner'                        => [ 'htmlAttributes' ],
			'divi/comments'                            => [ 'htmlAttributes' ],
			'divi/contact-field'                       => [ 'htmlAttributes', 'contactFieldFullwidth' ],
			'divi/contact-form'                        => [ 'htmlAttributes' ],
			'divi/countdown-timer'                     => [ 'htmlAttributes' ],
			'divi/counters'                            => [ 'htmlAttributes' ],
			'divi/cta'                                 => [ 'htmlAttributes', 'buttonRel' ],
			'divi/divider'                             => [ 'htmlAttributes' ],
			'divi/filterable-portfolio'                => [ 'htmlAttributes', 'portfolioLayout', 'portfolioGridFlexType' ],
			'divi/fullwidth-code'                      => [ 'htmlAttributes' ],
			'divi/fullwidth-header'                    => [ 'htmlAttributes', 'logoAlt', 'imageAlt', 'buttonOneRel', 'buttonTwoRel', 'imageTitle', 'logoTitle' ],
			'divi/fullwidth-image'                     => [ 'htmlAttributes', 'imageAlt', 'imageTitleText' ],
			'divi/fullwidth-map'                       => [ 'htmlAttributes' ],
			'divi/fullwidth-menu'                      => [ 'htmlAttributes', 'logoAlt' ],
			'divi/fullwidth-portfolio'                 => [ 'htmlAttributes', 'portfolioGridFlexType' ],
			'divi/fullwidth-post-content'              => [ 'htmlAttributes' ],
			'divi/fullwidth-post-slider'               => [ 'htmlAttributes', 'buttonRel' ],
			'divi/fullwidth-post-title'                => [ 'htmlAttributes' ],
			'divi/fullwidth-slider'                    => [ 'htmlAttributes', 'childrenButtonRel' ],
			'divi/gallery'                             => [ 'htmlAttributes', 'galleryGridFlexType' ],
			'divi/group'                               => [ 'htmlAttributes' ],
			'divi/group-carousel'                      => [ 'htmlAttributes' ],
			'divi/heading'                             => [ 'htmlAttributes' ],
			'divi/icon'                                => [ 'htmlAttributes', 'iconTitle' ],
			'divi/icon-list'                           => [ 'htmlAttributes' ],
			'divi/icon-list-item'                      => [ 'htmlAttributes', 'iconTitle' ],
			'divi/image'                               => [ 'htmlAttributes', 'imageAlt', 'imageTitleText', 'imageRel' ],
			'divi/login'                               => [ 'htmlAttributes' ],
			'divi/lottie'                              => [ 'htmlAttributes' ],
			'divi/map'                                 => [ 'htmlAttributes' ],
			'divi/menu'                                => [ 'htmlAttributes', 'logoAlt' ],
			'divi/number-counter'                      => [ 'htmlAttributes' ],
			'divi/portfolio'                           => [ 'htmlAttributes', 'portfolioLayout', 'portfolioGridFlexType' ],
			'divi/post-content'                        => [ 'htmlAttributes' ],
			'divi/post-nav'                            => [ 'htmlAttributes' ],
			'divi/post-slider'                         => [ 'htmlAttributes', 'buttonRel' ],
			'divi/post-title'                          => [ 'htmlAttributes' ],
			'divi/pricing-table'                       => [ 'buttonRel' ],
			'divi/pricing-tables'                      => [ 'htmlAttributes', 'childrenButtonRel' ],
			'divi/pricing-tables-item'                 => [ 'htmlAttributes' ],
			'divi/row'                                 => [ 'htmlAttributes' ],
			'divi/row-inner'                           => [ 'htmlAttributes' ],
			'divi/search'                              => [ 'htmlAttributes' ],
			'divi/section'                             => [ 'htmlAttributes' ],
			'divi/shop'                                => [ 'htmlAttributes' ],
			'divi/sidebar'                             => [ 'htmlAttributes' ],
			'divi/signup'                              => [ 'htmlAttributes', 'buttonRel', 'formLayout' ],
			'divi/slide'                               => [ 'imageAlt', 'buttonRel' ],
			'divi/slider'                              => [ 'htmlAttributes', 'childrenButtonRel' ],
			'divi/social-media-follow'                 => [ 'htmlAttributes' ],
			'divi/tabs'                                => [ 'htmlAttributes' ],
			'divi/team-member'                         => [ 'htmlAttributes' ],
			'divi/testimonial'                         => [ 'htmlAttributes' ],
			'divi/text'                                => [ 'htmlAttributes' ],
			'divi/toggle'                              => [ 'htmlAttributes' ],
			'divi/video'                               => [ 'htmlAttributes' ],
			'divi/video-slider'                        => [ 'htmlAttributes' ],
			'divi/woocommerce-breadcrumb'              => [ 'htmlAttributes' ],
			'divi/woocommerce-cart-notice'             => [ 'htmlAttributes' ],
			'divi/woocommerce-product-add-to-cart'     => [ 'htmlAttributes' ],
			'divi/woocommerce-product-additional-info' => [ 'htmlAttributes' ],
			'divi/woocommerce-product-description'     => [ 'htmlAttributes' ],
			'divi/woocommerce-product-gallery'         => [ 'htmlAttributes' ],
			'divi/woocommerce-product-images'          => [ 'htmlAttributes' ],
			'divi/woocommerce-product-meta'            => [ 'htmlAttributes' ],
			'divi/woocommerce-product-price'           => [ 'htmlAttributes' ],
			'divi/woocommerce-product-rating'          => [ 'htmlAttributes' ],
			'divi/woocommerce-product-reviews'         => [ 'htmlAttributes' ],
			'divi/woocommerce-product-stock'           => [ 'htmlAttributes' ],
			'divi/woocommerce-product-tabs'            => [ 'htmlAttributes' ],
			'divi/woocommerce-product-title'           => [ 'htmlAttributes' ],
			'divi/woocommerce-product-upsell'          => [ 'htmlAttributes' ],
			'divi/woocommerce-related-products'        => [ 'htmlAttributes' ],
		];
	}

	/**
	 * Get attribute definitions organized by attribute type.
	 *
	 * @since ??
	 *
	 * @return array Array of attribute definitions organized by attribute type.
	 */
	private static function _get_attribute_definitions() {
		return [
			'htmlAttributes'        => self::_get_html_attributes_definition(),
			'imageIconAlt'          => self::_get_image_icon_alt_definition(),
			'imageAlt'              => self::_get_image_alt_definition(),
			'imageTitleText'        => self::_get_image_title_text_definition(),
			'logoAlt'               => self::_get_logo_alt_definition(),
			'buttonRel'             => self::_get_button_rel_definition(),
			'iconTitle'             => self::_get_icon_title_definition(),
			'buttonOneRel'          => self::_get_button_one_rel_definition(),
			'buttonTwoRel'          => self::_get_button_two_rel_definition(),
			'imageTitle'            => self::_get_image_title_definition(),
			'logoTitle'             => self::_get_logo_title_definition(),
			'childrenButtonRel'     => self::_get_children_button_rel_definition(),
			'imageRel'              => self::_get_image_rel_definition(),
			'formLayout'            => self::_get_form_layout_definition(),
			'portfolioLayout'       => self::_get_portfolio_layout_definition(),
			'blogLayout'            => self::_get_blog_layout_definition(),
			'portfolioGridFlexType' => self::_get_portfolio_grid_flex_type_definition(),
			'blogGridFlexType'      => self::_get_blog_grid_flex_type_definition(),
			'galleryGridFlexType'   => self::_get_gallery_grid_flex_type_definition(),
			'contactFieldFullwidth' => self::_get_contact_field_fullwidth_definition(),
		];
	}

	/**
	 * Get htmlAttributes deprecated attribute definition.
	 *
	 * Many modules have deprecated htmlAttributes from the UI but still
	 * need them for preset conversion.
	 *
	 * @since ??
	 *
	 * @return array Array of htmlAttributes deprecated attribute mappings.
	 */
	private static function _get_html_attributes_definition() {
		return [
			'module.advanced.htmlAttributes__id'    => [
				'attrName' => 'module.advanced.htmlAttributes',
				'preset'   => 'content',
				'subName'  => 'id',
			],
			'module.advanced.htmlAttributes__class' => [
				'attrName' => 'module.advanced.htmlAttributes',
				'preset'   => [ 'html' ],
				'subName'  => 'class',
			],
		];
	}

	/**
	 * Get imageIconAlt deprecated attribute definition.
	 *
	 * Used by Blurb module for imageIcon.innerContent__alt attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageIconAlt deprecated attribute mappings.
	 */
	private static function _get_image_icon_alt_definition() {
		return [
			'imageIcon.innerContent__alt' => [
				'attrName' => 'imageIcon.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'alt',
			],
		];
	}

	/**
	 * Get imageAlt deprecated attribute definition.
	 *
	 * Used by modules with image elements for image.innerContent__alt attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageAlt deprecated attribute mappings.
	 */
	private static function _get_image_alt_definition() {
		return [
			'image.innerContent__alt' => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'alt',
			],
		];
	}

	/**
	 * Get imageTitleText deprecated attribute definition.
	 *
	 * Used by modules with image elements for image.innerContent__titleText attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageTitleText deprecated attribute mappings.
	 */
	private static function _get_image_title_text_definition() {
		return [
			'image.innerContent__titleText' => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'titleText',
			],
		];
	}

	/**
	 * Get logoAlt deprecated attribute definition.
	 *
	 * Used by modules with logo elements for logo.innerContent__alt attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of logoAlt deprecated attribute mappings.
	 */
	private static function _get_logo_alt_definition() {
		return [
			'logo.innerContent__alt' => [
				'attrName' => 'logo.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'alt',
			],
		];
	}

	/**
	 * Get formLayout deprecated attribute definition.
	 *
	 * Used by Signup module for module.advanced.layout attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of formLayout deprecated attribute mappings.
	 */
	private static function _get_form_layout_definition() {
		return [
			'module.advanced.layout' => [
				'attrName' => 'module.advanced.layout',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get portfolioLayout deprecated attribute definition.
	 *
	 * Used by Portfolio and Filterable Portfolio modules for portfolio.advanced.layout attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of portfolioLayout deprecated attribute mappings.
	 */
	private static function _get_portfolio_layout_definition() {
		return [
			'portfolio.advanced.layout' => [
				'attrName' => 'portfolio.advanced.layout',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get blogLayout deprecated attribute definition.
	 *
	 * Used by Blog module for fullwidth.advanced.enable attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of blogLayout deprecated attribute mappings.
	 */
	private static function _get_blog_layout_definition() {
		return [
			'fullwidth.advanced.enable' => [
				'attrName' => 'fullwidth.advanced.enable',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get portfolioGridFlexType deprecated attribute definition.
	 *
	 * Used by Portfolio and Filterable Portfolio modules for portfolioGrid.advanced.flexType attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of portfolioGridFlexType deprecated attribute mappings.
	 */
	private static function _get_portfolio_grid_flex_type_definition() {
		return [
			'portfolioGrid.advanced.flexType' => [
				'attrName' => 'portfolioGrid.advanced.flexType',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get blogGridFlexType deprecated attribute definition.
	 *
	 * Used by Blog module for blogGrid.advanced.flexType attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of blogGridFlexType deprecated attribute mappings.
	 */
	private static function _get_blog_grid_flex_type_definition() {
		return [
			'blogGrid.advanced.flexType' => [
				'attrName' => 'blogGrid.advanced.flexType',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get galleryGridFlexType deprecated attribute definition.
	 *
	 * Used by Gallery module for galleryGrid.advanced.flexType attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of galleryGridFlexType deprecated attribute mappings.
	 */
	private static function _get_gallery_grid_flex_type_definition() {
		return [
			'galleryGrid.advanced.flexType' => [
				'attrName' => 'galleryGrid.advanced.flexType',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get contactFieldFullwidth deprecated attribute definition.
	 *
	 * Used by Contact Field module for fieldItem.advanced.fullwidth attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of contactFieldFullwidth deprecated attribute mappings.
	 */
	private static function _get_contact_field_fullwidth_definition() {
		return [
			'fieldItem.advanced.fullwidth' => [
				'attrName' => 'fieldItem.advanced.fullwidth',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get buttonRel deprecated attribute definition.
	 *
	 * Used by Button, CTA, and other modules for button.innerContent__rel attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonRel deprecated attribute mappings.
	 */
	private static function _get_button_rel_definition() {
		return [
			'button.innerContent__rel' => [
				'attrName' => 'button.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}

	/**
	 * Get iconTitle deprecated attribute definition.
	 *
	 * Used by Icon module for icon.innerContent__title attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of iconTitle deprecated attribute mappings.
	 */
	private static function _get_icon_title_definition() {
		return [
			'icon.innerContent__title' => [
				'attrName' => 'icon.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'title',
			],
		];
	}

	/**
	 * Get buttonOneRel deprecated attribute definition.
	 *
	 * Used by Fullwidth Header module for buttonOne.innerContent__rel attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonOneRel deprecated attribute mappings.
	 */
	private static function _get_button_one_rel_definition() {
		return [
			'buttonOne.innerContent__rel' => [
				'attrName' => 'buttonOne.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}

	/**
	 * Get buttonTwoRel deprecated attribute definition.
	 *
	 * Used by Fullwidth Header module for buttonTwo.innerContent__rel attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonTwoRel deprecated attribute mappings.
	 */
	private static function _get_button_two_rel_definition() {
		return [
			'buttonTwo.innerContent__rel' => [
				'attrName' => 'buttonTwo.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}

	/**
	 * Get imageTitle deprecated attribute definition.
	 *
	 * Used by Fullwidth Header module for image.innerContent__title attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageTitle deprecated attribute mappings.
	 */
	private static function _get_image_title_definition() {
		return [
			'image.innerContent__title' => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'title',
			],
		];
	}

	/**
	 * Get logoTitle deprecated attribute definition.
	 *
	 * Used by Fullwidth Header module for logo.innerContent__title attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of logoTitle deprecated attribute mappings.
	 */
	private static function _get_logo_title_definition() {
		return [
			'logo.innerContent__title' => [
				'attrName' => 'logo.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'title',
			],
		];
	}

	/**
	 * Get childrenButtonRel deprecated attribute definition.
	 *
	 * Used by Slider and Pricing Tables modules for children button rel attributes.
	 *
	 * @since ??
	 *
	 * @return array Array of childrenButtonRel deprecated attribute mappings.
	 */
	private static function _get_children_button_rel_definition() {
		return [
			'children.button.innerContent__rel' => [
				'attrName' => 'children.button.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}

	/**
	 * Get imageRel deprecated attribute definition.
	 *
	 * Used by Image module for image.innerContent__rel attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageRel deprecated attribute mappings.
	 */
	private static function _get_image_rel_definition() {
		return [
			'image.innerContent__rel' => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}
}
