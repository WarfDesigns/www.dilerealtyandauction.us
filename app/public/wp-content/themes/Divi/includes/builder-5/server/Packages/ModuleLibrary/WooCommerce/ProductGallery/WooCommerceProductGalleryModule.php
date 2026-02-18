<?php
/**
 * Module Library: WooCommerceProductGallery Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductGallery;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses snakeCase in \WP_Block_Parser_Block
// phpcs:disable ElegantThemes.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowClassnames;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\ModuleUtils\ImageUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * WooCommerceProductGalleryModule class.
 *
 * Independent implementation following "copy and adapt" pattern from D4 source:
 * - Gallery logic copied from includes/builder/module/Gallery.php (D4)
 * - WooCommerce logic copied from includes/builder/module/woocommerce/Gallery.php (D4)
 * - Adapted for D5 POST requests and independent architecture
 *
 * No inheritance from GalleryModule - clean separation as requested.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductGalleryModule implements DependencyInterface {

	/**
	 * Gets Placeholder ID as Gallery IDs when in TB mode or Unsupported REST API request.
	 *
	 * Based on D4 ET_Builder_Module_Woocommerce_Gallery::get_gallery_ids()
	 *
	 * @see includes/builder/module/woocommerce/Gallery.php:193-214 (D4)
	 *
	 * Key D4 logic copied:
	 * - Line 198-202: TB mode and REST API request detection
	 * - Line 206-207: WooCommerce placeholder image source retrieval
	 * - Line 208-213: Placeholder ID extraction and validation
	 *
	 * @since ??
	 *
	 * @param bool $is_tb Whether we're in Theme Builder mode.
	 *
	 * @return array Array containing placeholder Id when in TB mode. Empty array otherwise.
	 */
	public static function get_gallery_ids_placeholder( bool $is_tb ): array {
		if (
			( ! $is_tb && ! et_builder_is_rest_api_request( '/module-data/shortcode-module' ) )
			|| ! function_exists( 'wc_placeholder_img_src' ) ) {
			return [];
		}

		$placeholder_src = wc_placeholder_img_src( 'full' );
		$placeholder_id  = attachment_url_to_postid( $placeholder_src );

		if ( 0 === absint( $placeholder_id ) ) {
			return [];
		}

		return [ $placeholder_id ];
	}

	/**
	 * Generate gallery HTML output.
	 *
	 * Based on D4 ET_Builder_Module_Gallery::render()
	 *
	 * @see includes/builder/module/Gallery.php:612-713+ (D4)
	 *
	 * Key D4 patterns followed:
	 * - Line 612-623: Gallery wrapper with proper classes and data attributes
	 * - Line 635-642: Overlay output generation for grid layout
	 * - Line 664-742: Gallery item structure and image output
	 * - Line 747-773: Pagination output for grid layout (not fullwidth)
	 * - Line 694-700: Gallery item classes and positioning
	 *
	 * Adapted for D5 POST requests and WooCommerce context with HTMLUtility pattern
	 *
	 * @since ??
	 *
	 * @param array               $attachments      Gallery attachments with metadata.
	 * @param array               $args            Gallery rendering arguments.
	 * @param array               $attrs           Module attributes.
	 * @param array               $icon_data       Icon data for enhanced overlay rendering.
	 * @param ModuleElements|null $elements        Optional. Module elements for style component rendering.
	 * @param array               $attrs_for_overlay Optional. Image attributes for box shadow overlay detection.
	 *
	 * @return string The gallery HTML output.
	 */
	public static function generate_gallery_html( array $attachments, array $args, array $attrs, array $icon_data = [], ?ModuleElements $elements = null, array $attrs_for_overlay = [] ): string {
		// Extract parameters (from D4 Gallery render() method).
		$posts_number           = $args['posts_number'] ?? 4;
		$fullwidth              = $args['fullwidth'] ?? 'off';
		$show_title_and_caption = $args['show_title_and_caption'] ?? 'off';
		$show_pagination        = $args['show_pagination'] ?? 'on';
		$orientation            = $args['orientation'] ?? 'landscape';
		$heading_level          = $args['heading_level'] ?? 'h3';

		// Validate posts_number: ensure it's a positive integer.
		$posts_number = absint( $posts_number );
		if ( 0 === $posts_number ) {
			$posts_number = 4; // Default to 4 if invalid.
		}

		// D5 Enhancement: Extract icon data for enhanced overlay.
		$icon        = $icon_data['icon'] ?? '';
		$icon_tablet = $icon_data['icon_tablet'] ?? '';
		$icon_phone  = $icon_data['icon_phone'] ?? '';

		// D5 Enhancement: Generate enhanced overlay using HTMLUtility
		// @see includes/builder-5/server/Packages/ModuleLibrary/Gallery/GalleryModule.php:772-790.
		$overlay_classes = [
			'et_overlay'               => true,
			'et_pb_inline_icon'        => ! empty( $icon ),
			'et_pb_inline_icon_tablet' => ! empty( $icon_tablet ),
			'et_pb_inline_icon_phone'  => ! empty( $icon_phone ),
		];

		$overlay_output = HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class'            => HTMLUtility::classnames( $overlay_classes ),
					'data-icon'        => $icon,
					'data-icon-tablet' => $icon_tablet,
					'data-icon-phone'  => $icon_phone,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		// Generate CSS classes for D5 HTMLUtility pattern.
		$gallery_classes = [
			'et_pb_gallery'    => true,
			'et_pb_wc_gallery' => true,
		];

		if ( 'on' === $fullwidth ) {
			$gallery_classes['et_pb_slider']            = true;
			$gallery_classes['et_pb_gallery_fullwidth'] = true;
		} else {
			$gallery_classes['et_pb_gallery_grid'] = true;
		}

		// Generate gallery items wrapper with D5 HTMLUtility pattern.
		$gallery_items = [];

		// Gallery items container attributes.
		$gallery_items_classes = [ 'et_pb_gallery_items', 'et_post_gallery', 'clearfix' ];

		// Use grid system with et_grid_module for CSS Grid layout that takes the column type into account.
		// Follows D5 Gallery module pattern - only add et_grid_module, not et_pb_grid_items.
		if ( 'on' !== $fullwidth ) {
			$gallery_items_classes[] = 'et_grid_module';
		}

		// For pagination OFF: set data-per_page to total attachments to show all items.
		// For pagination ON: use posts_number to limit items per page.
		$per_page_value = ( 'on' !== $show_pagination ) ? count( $attachments ) : $posts_number;

		$gallery_items_attrs = [
			'class'         => implode( ' ', $gallery_items_classes ),
			'data-per_page' => $per_page_value,
		];

		// Generate gallery items (based on D4 Gallery render() lines 664-713+).
		$images_count = 0;

		// Pagination Logic: Include all images but let JavaScript handle pagination (Gallery module pattern).
		// The Gallery script calculates pages based on total DOM items vs data-per_page attribute.
		// Don't limit attachments here - JavaScript will hide/show items for pagination.

		foreach ( $attachments as $attachment ) {
			// Prefer WooCommerce's canonical image markup to maximize compatibility with extensions.
			// Treat the first image as main image when in slider (fullwidth) mode.
			$is_main_image = ( 'on' === $fullwidth && 0 === $images_count );

			// Fallback to WP_Post->ID if custom object.
			$attachment_id = 0;
			if ( isset( $attachment->ID ) ) {
				$attachment_id = (int) $attachment->ID;
			} elseif ( isset( $attachment->id ) ) {
				$attachment_id = (int) $attachment->id; // Defensive.
			}

			// Generate orientation-specific thumbnails (Gallery module pattern).
			$width  = 400;
			$height = ( 'landscape' === $orientation ) ? 284 : 516;

			// Apply Divi filters for image sizing.
			$width  = (int) apply_filters( 'et_pb_gallery_image_width', $width );
			$height = (int) apply_filters( 'et_pb_gallery_image_height', $height );

			$wc_image_html = '';
			if ( $attachment_id > 0 ) {
				// Get properly sized thumbnail for orientation.
				$image_src_full  = wp_get_attachment_image_src( $attachment_id, 'full' );
				$image_src_thumb = wp_get_attachment_image_src( $attachment_id, [ $width, $height ] );

				if ( $image_src_full && $image_src_thumb ) {
					// Generate WooCommerce-compatible image HTML with orientation-specific sizing.
					$image_alt   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
					$image_title = get_the_title( $attachment_id );

					$wc_image_html = sprintf(
						'<div data-thumb="%1$s" data-thumb-alt="%2$s" data-thumb-srcset="" data-thumb-sizes="" class="woocommerce-product-gallery__image">
							<a href="%3$s">
								<img width="%4$d" height="%5$d" src="%1$s" class="" alt="%2$s" data-caption="%6$s" data-src="%3$s" data-large_image="%3$s" data-large_image_width="%7$d" data-large_image_height="%8$d" decoding="async" loading="lazy" />
							</a>
						</div>',
						esc_url( $image_src_thumb[0] ),
						esc_attr( $image_alt ),
						esc_url( $image_src_full[0] ),
						(int) $image_src_thumb[1],
						(int) $image_src_thumb[2],
						esc_attr( $image_title ),
						(int) $image_src_full[1],
						(int) $image_src_full[2]
					);
				}
			}

			// If WooCommerce did not return markup for any reason, fall back to minimal anchor+img.
			if ( empty( $wc_image_html ) ) {
				$full_src  = $attachment->image_src_full[0] ?? '';
				$thumb_src = $attachment->image_src_thumb[0] ?? '';
				$alt       = $attachment->image_alt_text ?? '';

				$wc_image_html = sprintf(
					'<a href="%1$s" title="%2$s">
                        <img src="%3$s" alt="%4$s">
                    </a>',
					esc_url( $full_src ),
					esc_attr( $attachment->post_title ?? '' ),
					esc_url( $thumb_src ),
					esc_attr( $alt )
				);
			}

			// Append Divi overlay on top of WooCommerce image markup, preserving our structure/classes.
			$image_output = $wc_image_html . $overlay_output;

			// Generate gallery item classes following D4 pattern.
			// @see includes/builder/module/Gallery.php:694-700 (D4).
			$item_classes = [
				'et_pb_gallery_item' => true,
			];

			// Add grid item classes for grid layout (D4 + D5 pattern).
			if ( 'on' !== $fullwidth ) {
				$item_classes['et_pb_grid_item'] = true;
			}

			// D4 Pattern: Add gallery order and count classes
			// @see includes/builder/module/Gallery.php:691-692 (D4).
			// Note: Gallery order would typically come from module rendering context in D4
			// For D5 REST API context, we use a simplified approach.
			$gallery_order       = 'wc_gallery'; // Simplified for REST API context.
			$item_class_specific = "et_pb_gallery_item_{$gallery_order}_{$images_count}";

			// Note: first_in_row, last_in_row, and on_last_row classes are added dynamically by JavaScript
			// via et_pb_set_responsive_grid() function, not in PHP. This matches D4 behavior.

			// Use user-selected orientation setting.
			// Respect user preference - don't override with automatic detection.
			$actual_orientation = $orientation;

			// Build gallery image container with D5 HTMLUtility pattern.
			// Add box shadow overlay classname when elements are available (matches Gallery module pattern).
			$image_container_classes = [
				'et_pb_gallery_image' => true,
				'landscape'           => 'portrait' !== $actual_orientation,
				'portrait'            => 'portrait' === $actual_orientation,
			];

			// Build children array with image output.
			$image_container_children = [ $image_output ];

			// Add box shadow overlay component when elements are available (matches Gallery module pattern).
			if ( null !== $elements ) {
				$image_container_children[] = $elements->style_components(
					[
						'attrName' => 'image',
					]
				);
			}

			$image_container = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class' => HTMLUtility::classnames(
							$image_container_classes,
							( null !== $elements && ! empty( $attrs_for_overlay ) )
								? BoxShadowClassnames::has_overlay( $attrs_for_overlay['decoration']['boxShadow'] ?? [] )
								: []
						),
					],
					'children'          => $image_container_children,
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);

			// Build title and caption elements if enabled.
			$item_children = [ $image_container ];

			if ( 'on' !== $fullwidth && 'on' === $show_title_and_caption ) {
				if ( ! empty( $attachment->post_title ) ) {
					$item_children[] = HTMLUtility::render(
						[
							'tag'        => $heading_level,
							'attributes' => [
								'class' => 'et_pb_gallery_title',
							],
							'children'   => wptexturize( $attachment->post_title ),
						]
					);
				}

				if ( ! empty( $attachment->post_excerpt ) ) {
					$item_children[] = HTMLUtility::render(
						[
							'tag'        => 'p',
							'attributes' => [
								'class' => 'et_pb_gallery_caption',
							],
							'children'   => wptexturize( $attachment->post_excerpt ),
						]
					);
				}
			}

			// Build complete gallery item with D5 HTMLUtility pattern.
			$gallery_items[] = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class' => HTMLUtility::classnames( $item_classes ) . ' ' . $item_class_specific,
					],
					'children'          => implode( '', $item_children ),
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);

			++$images_count;
		}

		// Build gallery items container with D5 HTMLUtility pattern.
		$gallery_items_container = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => $gallery_items_attrs,
				'children'          => implode( '', $gallery_items ),
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		// D4 Pattern: Add pagination for grid layout (not fullwidth/slider)
		// @see includes/builder/module/Gallery.php:747-773 (D4).
		$pagination_output = '';
		$show_pagination   = $args['show_pagination'] ?? 'on';

		if ( 'on' !== $fullwidth && 'on' === $show_pagination ) {
			$pagination_output = HTMLUtility::render(
				[
					'tag'        => 'div',
					'attributes' => [
						'class' => 'et_pb_gallery_pagination',
					],
					'children'   => '', // Pagination content would be generated by JavaScript.
				]
			);
		}

		// Build complete gallery wrapper with D5 HTMLUtility pattern.
		$gallery_children = $gallery_items_container . $pagination_output;

		$output = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => HTMLUtility::classnames( $gallery_classes ),
				],
				'children'          => $gallery_children,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		return $output;
	}

	/**
	 * Get gallery HTML for WooCommerce product gallery.
	 *
	 * Unified method that generates gallery HTML for both default settings (settings store)
	 * and custom settings (REST API). This follows the pattern used by other WooCommerce modules.
	 *
	 * @since ??
	 *
	 * @param array               $args     Optional. Arguments including 'product' and gallery settings.
	 * @param ModuleElements|null $elements Optional. Module elements for style component rendering.
	 * @param array               $attrs    Optional. Image attributes for box shadow overlay detection.
	 *
	 * @return string The rendered gallery HTML.
	 */
	public static function get_gallery( array $args = [], ?ModuleElements $elements = null, array $attrs = [] ): string {
		$args = wp_parse_args(
			$args,
			[
				'product'                => 'current',
				'fullwidth'              => 'off',
				'orientation'            => 'landscape',
				'show_pagination'        => 'on',
				'show_title_and_caption' => 'on',
				'posts_number'           => 4,
				'gallery_layout'         => 'grid',
				'thumbnail_orientation'  => 'landscape',
				'hover_icon'             => '',
				'hover_icon_tablet'      => '',
				'hover_icon_phone'       => '',
			]
		);

		// Get WooCommerce gallery attachments.
		$attachments = self::get_wc_gallery( $args );

		if ( empty( $attachments ) ) {
			return '<div class="et_pb_module_placeholder">' . esc_html__( 'No gallery images found for this product.', 'divi' ) . '</div>';
		}

		// Process icon data for enhanced overlay rendering.
		$icon        = ! empty( $args['hover_icon'] ) ? Utils::process_font_icon( $args['hover_icon'] ) : '';
		$icon_tablet = ! empty( $args['hover_icon_tablet'] ) ? Utils::process_font_icon( $args['hover_icon_tablet'] ) : '';
		$icon_phone  = ! empty( $args['hover_icon_phone'] ) ? Utils::process_font_icon( $args['hover_icon_phone'] ) : '';

		$icon_data = [
			'icon'        => $icon,
			'icon_tablet' => $icon_tablet,
			'icon_phone'  => $icon_phone,
		];

		// Prepare rendering arguments.
		$render_args = [
			'posts_number'           => $args['posts_number'],
			'fullwidth'              => 'slider' === $args['gallery_layout'] ? 'on' : 'off',
			'show_title_and_caption' => $args['show_title_and_caption'],
			'show_pagination'        => $args['show_pagination'] ?? 'on',
			'orientation'            => $args['thumbnail_orientation'] ?? $args['orientation'],
			'heading_level'          => $args['heading_level'] ?? 'h3',
		];

		// Use the same HTML generation method to ensure consistency.
		return self::generate_gallery_html( $attachments, $render_args, [], $icon_data, $elements, $attrs );
	}

	/**
	 * Loads `WooCommerceProductGalleryModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		/*
		 * Bail if  WooCommerce plugin is not active.
		 */
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		// Add a filter for processing dynamic attribute defaults.
		add_filter(
			'divi_module_library_module_default_attributes_divi/woocommerce-product-gallery',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-gallery/';

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
	 * Get the WooCommerce product gallery items for REST API endpoints.
	 *
	 * Independent implementation following "copy and adapt" pattern from D4 source.
	 *
	 * Based on D4 ET_Builder_Module_Gallery::get_gallery() and
	 * ET_Builder_Module_Woocommerce_Gallery::get_wc_gallery()
	 *
	 * @see includes/builder/module/Gallery.php:418-472 (D4)
	 * @see includes/builder/module/woocommerce/Gallery.php:229-261 (D4)
	 *
	 * @since ??
	 *
	 * @param array $args Gallery rendering arguments.
	 *
	 * @return array The processed gallery attachments (independent implementation).
	 */
	public static function get_gallery_items( array $args = [] ): array {
		// Get WooCommerce gallery data (which extracts product gallery IDs), then gallery attachments.
		$attachments = self::get_wc_gallery( $args );

		if ( empty( $attachments ) ) {
			return [];
		}

		// Convert to format expected by REST API.
		return array_map(
			function ( $attachment ) {
				return [
					'id'        => $attachment->ID,
					'url'       => $attachment->image_src_full[0] ?? '',
					'thumbnail' => $attachment->image_src_thumb[0] ?? '',
					'alt'       => $attachment->image_alt_text ?? '',
					'title'     => $attachment->post_title ?? '',
					'caption'   => $attachment->post_excerpt ?? '',
				];
			},
			$attachments
		);
	}

	/**
	 * Get WooCommerce product gallery items using parent Gallery logic.
	 *
	 * Based on D4 ET_Builder_Module_Woocommerce_Gallery::get_wc_gallery()
	 *
	 * @see includes/builder/module/woocommerce/Gallery.php:229-261 (D4)
	 *
	 * Follows D4 pattern: prepare WooCommerce data, delegate to parent.
	 * Key D4 logic copied:
	 * - Line 230-237: Theme Builder global object setup
	 * - Line 241-244: Product gallery ID extraction
	 * - Line 247-249: Placeholder image handling for TB mode
	 * - Line 260: Delegation to parent ET_Builder_Module_Gallery::get_gallery()
	 *
	 * @since ??
	 *
	 * @param array $args Gallery rendering arguments.
	 *
	 * @return array The gallery items.
	 */
	public static function get_wc_gallery( array $args = [] ): array {
		$defaults = [
			'product'                => 'current',
			'gallery_layout'         => 'grid',
			'thumbnail_orientation'  => 'landscape',
			'show_pagination'        => 'off',
			'show_title_and_caption' => 'off',
		];

		$args = wp_parse_args( $args, $defaults );

		// 1. WooCommerce-specific product handling following D4 pattern.
		// Based on D4 ET_Builder_Module_Woocommerce_Gallery::get_wc_gallery() lines 230-237.
		global $product, $post;
		$original_product = $product; // Store for restoration.
		$original_post    = $post;    // Store for restoration.

		$overwrite_global   = WooCommerceUtils::need_overwrite_global( $args['product'] );
		$is_tb              = et_builder_tb_enabled();
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		// D4 Pattern: Theme Builder global object setup
		// @see includes/builder/module/woocommerce/Gallery.php:230-237 (D4).
		if ( 'current' === $args['product'] && $is_use_placeholder ) {
			// Use D4's et_theme_builder_wc_set_global_objects() equivalent.
			WooCommerceUtils::set_global_objects_for_theme_builder();
		} elseif ( $overwrite_global ) {
			$product_id = WooCommerceUtils::get_product_id_from_attributes( $args );
			$product    = wc_get_product( $product_id );
			$post       = get_post( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally override global $post, will be restored later.
		}

		// 2. Extract WooCommerce gallery image IDs.
		$current_product = $product;
		if ( ! $current_product || ! is_a( $current_product, 'WC_Product' ) ) {
			$current_product = WooCommerceUtils::get_product( $args['product'] );
		}

		if ( ! $current_product ) {
			return [];
		}

		// 3. Extract WooCommerce gallery image IDs following D4 pattern.
		// @see includes/builder/module/woocommerce/Gallery.php:241-244 (D4).
		$featured_image_id = intval( $current_product->get_image_id() );
		$attachment_ids    = $current_product->get_gallery_image_ids();

		// D4 Pattern: Load placeholder image when in TB mode
		// @see includes/builder/module/woocommerce/Gallery.php:247-249 (D4).
		if ( is_array( $attachment_ids ) && empty( $attachment_ids ) ) {
			$attachment_ids = self::get_gallery_ids_placeholder( $is_tb );
		}

		// Include featured image if gallery is empty (D5 enhancement).
		if ( empty( $attachment_ids ) && $featured_image_id ) {
			$attachment_ids = [ $featured_image_id ];
		}

		if ( empty( $attachment_ids ) ) {
			return [];
		}

		// Delegate to parent with prepared data.
		$gallery_args = [
			'gallery_ids'     => $attachment_ids, // D5 expects array, not string.
			'gallery_orderby' => $args['gallery_orderby'] ?? '',
			'fullwidth'       => $args['fullwidth'] ?? 'off',
			'orientation'     => $args['thumbnail_orientation'],
		];

		// Restore globals if overwritten (D4 pattern).
		if ( $overwrite_global ) {
			$product = $original_product;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring global $post to original value.
			$post = $original_post;
		}

		// Use our independent gallery attachment logic (will be implemented next).
		return self::get_gallery_attachments( $gallery_args, $args );
	}

	/**
	 * Get gallery attachments (independent implementation)
	 *
	 * Based on D4 ET_Builder_Module_Gallery::get_gallery()
	 *
	 * @see includes/builder/module/Gallery.php:418-472 (D4)
	 *
	 * Key D4 logic copied:
	 * - Line 432-438: Attachment query arguments setup
	 * - Line 447-449: Random orderby handling
	 * - Line 451-457: Image sizing logic (fullwidth vs grid)
	 * - Line 459-460: Filter application for dimensions
	 * - Line 462: get_posts() attachment query
	 * - Line 464-469: Attachment metadata extraction
	 *
	 * Adapted for D5 POST requests and WooCommerce context
	 *
	 * @since ??
	 *
	 * @param array $args Gallery rendering arguments.
	 * @param array $attrs Module attributes for responsive image sizing.
	 *
	 * @return array The gallery attachments with metadata.
	 */
	public static function get_gallery_attachments( array $args, array $attrs = [] ): array {
		$defaults = [
			'gallery_ids'      => [],
			'gallery_orderby'  => '',
			'gallery_captions' => [],
			'fullwidth'        => 'off',
			'orientation'      => 'landscape',
		];

		$args = wp_parse_args( $args, $defaults );

		// Return early if no gallery IDs provided.
		if ( empty( $args['gallery_ids'] ) ) {
			return [];
		}

		// Copy D4 attachment query logic from Gallery.php:432-438.
		$attachments_args = [
			'include'        => $args['gallery_ids'],
			'post_status'    => 'inherit',
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'order'          => 'ASC',
			'orderby'        => 'post__in',
		];

		// Copy D4 orderby logic from Gallery.php:447-449.
		if ( 'rand' === $args['gallery_orderby'] ) {
			$attachments_args['orderby'] = 'rand';
		}

		// Select optimal image size based on layout and flexbox column configuration.
		$layout              = 'on' === $args['fullwidth'] ? 'fullwidth' : 'grid';
		$selected_image_size = ImageUtils::select_optimal_image_size( $attrs, $layout, 'galleryGrid' );

		// Use the same image sizing logic as Portfolio modules.
		if ( 'et-pb-post-main-image-fullwidth' === $selected_image_size ) {
			// Large grid images for big columns (1/1, 2/3, 1/2 on desktop/tablet).
			$width  = 1080;
			$height = 675;
		} elseif ( 'et-pb-portfolio-image-single' === $selected_image_size ) {
			// Fullwidth layout uses original aspect ratio.
			$width  = 1080;
			$height = 9999;
		} else {
			// Small grid images for small columns (1/4, 1/3) - default et-pb-portfolio-image.
			$width  = 400;
			$height = ( 'landscape' === $args['orientation'] ) ? 284 : 516;
		}

		// Apply legacy filters for backward compatibility.
		$width  = (int) apply_filters( 'et_pb_gallery_image_width', $width );
		$height = (int) apply_filters( 'et_pb_gallery_image_height', $height );

		$_attachments = get_posts( $attachments_args );
		$attachments  = [];

		foreach ( $_attachments as $key => $val ) {
			$attachments[ $key ]                  = $_attachments[ $key ];
			$attachments[ $key ]->image_alt_text  = get_post_meta( $val->ID, '_wp_attachment_image_alt', true );
			$attachments[ $key ]->image_src_full  = wp_get_attachment_image_src( $val->ID, 'full' );
			$attachments[ $key ]->image_src_thumb = wp_get_attachment_image_src( $val->ID, [ $width, $height ] );
		}

		return $attachments;
	}

	/**
	 * D4-compatible get_attachments method for backwards compatibility.
	 *
	 * Based on D4 ET_Builder_Module_Woocommerce_Gallery::get_attachments()
	 *
	 * @see includes/builder/module/woocommerce/Gallery.php:291-295 (D4)
	 *
	 * Key D4 logic copied:
	 * - Line 292: Extract product from module props
	 * - Line 294: Delegate to get_wc_gallery() method
	 *
	 * @since ??
	 *
	 * @param array $args Additional arguments (D4 compatibility).
	 *
	 * @return array Gallery attachments.
	 */
	public static function get_attachments( array $args = [] ): array {
		// D4 Pattern: Extract product from args and delegate to get_wc_gallery()
		// @see includes/builder/module/woocommerce/Gallery.php:291-295 (D4).
		if ( ! isset( $args['product'] ) ) {
			$args['product'] = 'current';
		}

		return self::get_wc_gallery( $args );
	}

	/**
	 * Add WooCommerce-specific CSS classes to gallery.
	 *
	 * Based on D4 ET_Builder_Module_Woocommerce_Gallery::add_wc_gallery_classname()
	 *
	 * @see includes/builder/module/woocommerce/Gallery.php:273-278 (D4)
	 *
	 * Key D4 logic copied:
	 * - Line 275: Add base 'et_pb_gallery' class for proper rendering
	 * - Filter application pattern for module wrapper classes
	 *
	 * @since ??
	 *
	 * @param array $classes The classes to add to the gallery.
	 * @param array $attrs   Module attributes (optional, for filter compatibility).
	 *
	 * @return array The classes to add to the gallery.
	 */
	public static function module_classnames_add_wc_gallery( array $classes, array $attrs = [] ): array {
		// Ensure base gallery class is preserved.
		// @see includes/builder/module/woocommerce/Gallery.php:275 (D4).
		if ( ! in_array( 'et_pb_gallery', $classes, true ) ) {
			$classes[] = 'et_pb_gallery';
		}

		// Add WooCommerce-specific class.
		$classes[] = 'et_pb_wc_gallery';

		return $classes;
	}

	/**
	 * Filters the module.decoration attributes.
	 *
	 * This function is equivalent of JS function filterModuleDecorationAttrs located in
	 * visual-builder/packages/module-library/src/components/gallery/attrs-filter/filter-module-decoration-attrs/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $decoration_attrs The original decoration attributes.
	 * @param array $attrs The attributes of the Gallery module.
	 *
	 * @return array The filtered decoration attributes.
	 */
	public static function filter_module_decoration_attrs( array $decoration_attrs, array $attrs ): array {
		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Get fullwidth attribute using proper utility pattern.
		$is_slider = $attrs['layout']['advanced']['fullwidth'][ $default_breakpoint ][ $default_state ] ?? null;

		// If the module layout is Grid, it returns the decoration attributes with empty `boxShadow`.
		if ( 'on' !== $is_slider ) {
			$decoration_attrs = array_merge(
				$decoration_attrs,
				[
					'boxShadow' => [],
				]
			);
		}

		return $decoration_attrs;
	}

	/**
	 * Filters the image.decoration attributes.
	 *
	 * This function is equivalent of JS function filterImageDecorationAttrs located in
	 * visual-builder/packages/module-library/src/components/gallery/attrs-filter/filter-image-decoration-attrs/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $decoration_attrs The decoration attributes to be filtered.
	 * @param array $attrs           The whole module attributes.
	 *
	 * @return array The filtered decoration attributes.
	 */
	public static function filter_image_decoration_attrs( array $decoration_attrs, array $attrs ): array {
		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Get fullwidth attribute using proper utility pattern.
		$is_slider = $attrs['layout']['advanced']['fullwidth'][ $default_breakpoint ][ $default_state ] ?? null;

		// If the module layout is Slider, it returns the image decoration attributes with empty `border` and `boxShadow`.
		if ( 'on' === $is_slider ) {
			$decoration_attrs = array_merge(
				$decoration_attrs,
				[
					'border'    => [],
					'boxShadow' => [],
				]
			);
		}

		return $decoration_attrs;
	}

	/**
	 * Gallery Grid Item's CSS declaration for horizontal gap.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of parameters.
	 *
	 *     @type string $selector    Selector.
	 *     @type array  $attr        Attribute.
	 *     @type bool   $important   Important.
	 *     @type string $returnType  Return type.
	 * }
	 *
	 * @return string
	 */
	public static function gallery_grid_item_style_declaration( array $params ): string {
		$declarations = new StyleDeclarations( $params );
		$attr         = $params['attr'] ?? [];

		return $declarations->value();
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceProductGallery module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js/module-library/module-classnames moduleClassnames}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *    An array of arguments.
	 *
	 *    @type object $classnamesInstance Module classnames instance.
	 *    @type array  $attrs              Block attributes data for rendering the module.
	 * }
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * $args = [
	 *    'classnamesInstance' => $classnamesInstance,
	 *    'attrs'              => $attrs,
	 * ];
	 *
	 * WooCommerceProductGalleryModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Add WooCommerce Gallery specific classes following D4 pattern.
		// Based on D4 ET_Builder_Module_Woocommerce_Gallery::add_wc_gallery_classname()
		// @see includes/builder/module/woocommerce/Gallery.php:273-278 (D4)
		//
		// Key D4 classes:
		// - et_pb_gallery: Base gallery class (line 275)
		// - et_pb_wc_gallery: WooCommerce gallery identifier
		// - et_pb_wc_gallery_module: D5 enhancement for module-specific styling.

		$classnames_instance->add( 'et_pb_gallery' );
		$classnames_instance->add( 'et_pb_wc_gallery' );
		$classnames_instance->add( 'et_pb_wc_gallery_module' );

		// Add layout classes following Gallery module pattern.
		// @see includes/builder-5/server/Packages/ModuleLibrary/Gallery/GalleryModule.php:149-171.
		$fullwidth = $attrs['layout']['advanced']['fullwidth']['desktop']['value'] ?? 'off';

		if ( 'on' === $fullwidth ) {
			$classnames_instance->add( 'et_pb_slider' );
			$classnames_instance->add( 'et_pb_gallery_fullwidth' );
		} else {
			$classnames_instance->add( 'et_pb_gallery_grid' );
		}
	}

	/**
	 * WooCommerceProductGallery module script data.
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
	 *       Optional. An array of arguments for setting the module script data.
	 *
	 *       @type string                $id                        The module ID.
	 *       @type string                $name                  The module name.
	 *       @type string                $selector          The module selector.
	 *       @type array                    $attrs               The module attributes.
	 *       @type int                      $storeInstance The ID of the instance where this block is stored in the `BlockParserStore` class.
	 *       @type ModuleElements $elements         The `ModuleElements` instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_script_data( $args ) {
		// Independent implementation for WooCommerce Product Gallery script data.

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

		// Get layout setting - check if it's fullwidth (slider) or grid using proper utility pattern.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		$is_fullwidth = 'on' === ( $attrs['layout']['advanced']['fullwidth'][ $default_breakpoint ][ $default_state ] ?? 'off' );

		// Set up multiview script data for show/hide functionality.
		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setVisibility' => [
					// Only add caption visibility for grid layout (not fullwidth/slider).
					$is_fullwidth ? [] : [
						'selector'      => $selector . ' .et_pb_gallery_title, ' . $selector . ' .et_pb_gallery_caption',
						'data'          => $attrs['content']['advanced']['showTitleAndCaption'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'on' === $value ? 'visible' : 'hidden';
						},
					],
					// Only add pagination visibility for grid layout (not fullwidth/slider).
					$is_fullwidth ? [] : [
						'selector'      => $selector . ' .et_pb_gallery_pagination',
						'data'          => $attrs['content']['advanced']['showPagination'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'on' === ( $value ?? 'on' ) ? 'visible' : 'hidden';
						},
					],
				],
			]
		);

		// Add frontend JavaScript for box shadow overlay injection.
		// This runs after gallery initialization to apply inset box shadow overlays.
		// The selector matches the Visual Builder pattern: {{selector}}.et_pb_gallery .et_pb_gallery_image.
		$box_shadow_selector = '.et_pb_gallery .et_pb_gallery_image';
		$inline_script       = sprintf(
			'
			(function() {
				var moduleSelector = "%s";
				var applyOverlay = function() {
					if (typeof window.et_pb_box_shadow_apply_overlay === "function") {
						var $moduleContainer = jQuery(moduleSelector);
						if ($moduleContainer.length) {
							// Find gallery image elements within the module container.
							// This matches the Visual Builder pattern: find elements using selector without {{selector}} prefix.
							var $galleryItems = $moduleContainer.find("%s");
							if ($galleryItems.length > 0) {
								window.et_pb_box_shadow_apply_overlay($galleryItems);
							}
						}
					}
				};
				// Run on DOM ready.
				if (document.readyState === "loading") {
					document.addEventListener("DOMContentLoaded", applyOverlay);
				} else {
					applyOverlay();
				}
				// Also run after gallery modules are initialized.
				jQuery(window).on("et_pb_init_modules", applyOverlay);
			})();
			',
			esc_js( $selector ),
			esc_js( $box_shadow_selector )
		);

		// Add inline script to footer.
		wp_add_inline_script( 'divi-script-library', $inline_script );
	}

	/**
	 * WooCommerceProductGallery Module's style components.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js/module-library/module-styles moduleStyles}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *       An array of arguments.
	 *
	 *          @type string $id                                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *          @type string $name                          Module name.
	 *          @type string $attrs                      Module attributes.
	 *          @type string $parentAttrs            Parent attrs.
	 *          @type string $orderClass                Selector class name.
	 *          @type string $parentOrderClass  Parent selector class name.
	 *          @type string $wrapperOrderClass Wrapper selector class name.
	 *          @type string $settings                  Custom settings.
	 *          @type string $state                      Attributes state.
	 *          @type string $mode                          Style mode.
	 *          @type ModuleElements $elements  ModuleElements instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		// Independent implementation for WooCommerce Product Gallery styles.

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
											'selector' => "{$order_class}.et_pb_gallery .et_pb_gallery_title, {$order_class}.et_pb_gallery .mfp-title, {$order_class}.et_pb_gallery .et_pb_gallery_caption, {$order_class}.et_pb_gallery .et_pb_gallery_pagination a",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class}.et_pb_gallery.et_pb_gallery_grid",
														],
													],
												],
											],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_gallery .et_pb_gallery_item",
											'attr'     => $attrs['module']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
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
					// Image.
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'attrsFilter'    => function ( $decoration_attrs ) use ( $attrs ) {
									return self::filter_image_decoration_attrs( $decoration_attrs, $attrs );
								},
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_gallery .et_pb_gallery_image",
											'attr'     => $attrs['image']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Caption.
					$elements->style(
						[
							'attrName' => 'caption',
						]
					),
					// Overlay.
					$elements->style(
						[
							'attrName' => 'overlay',
						]
					),
					// Overlay Icon.
					$elements->style(
						[
							'attrName' => 'overlayIcon',
						]
					),
					// Pagination.
					$elements->style(
						[
							'attrName' => 'pagination',
						]
					),
					// Thumbnail Orientation.
					$elements->style(
						[
							'attrName'   => 'layout',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_gallery .et_pb_gallery_image img",
											'attr'     => $attrs['layout']['advanced']['orientation'] ?? [],
											'declarationFunction' => [ self::class, 'thumbnail_image_style_declaration' ],
										],
									],
								],
							],
						]
					),

					// Gallery Grid - Layout settings for gallery wrapper.
					$elements->style(
						[
							'attrName'   => 'galleryGrid',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/layout',
										'props'         => [
											'attr' => $attrs['galleryGrid']['decoration']['layout'] ?? [],
											'selectorFunction' => function ( $params ) {
												return $params['selector'];
											},
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
	 * Thumbnail Image Style Declaration.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function thumbnail_image_style_declaration(): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Use object-fit to ensure images display properly in containers.
		$style_declarations->add( 'object-fit', 'cover' );
		$style_declarations->add( 'width', '100%' );
		$style_declarations->add( 'height', '100%' );

		return $style_declarations->value();
	}


	/**
	 * Get the custom CSS fields for the Divi WooCommerceProductGallery module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceProductGallery module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js-beta/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductGallery module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductGallery module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-gallery' )->customCssFields;
	}



	/**
	 * Render callback for the WooCommerceProductGallery module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductGalleryEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 * @param array          $default_printed_style_attrs The default printed style attributes.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductGallery module.
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
	 * WooCommerceProductGalleryModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// D5 Enhancement: Responsive icon handling (from D5 Gallery lines 711-717)
		// @see includes/builder-5/server/Packages/ModuleLibrary/Gallery/GalleryModule.php:711-717.
		$hover_icon        = $attrs['overlay']['decoration']['icon'][ $default_breakpoint ][ $default_state ] ?? '';
		$hover_icon_tablet = $attrs['overlay']['decoration']['icon']['tablet'][ $default_state ] ?? '';
		$hover_icon_phone  = $attrs['overlay']['decoration']['icon']['phone'][ $default_state ] ?? '';

		$icon        = ! empty( $hover_icon ) ? Utils::process_font_icon( $hover_icon ) : '';
		$icon_tablet = ! empty( $hover_icon_tablet ) ? Utils::process_font_icon( $hover_icon_tablet ) : '';
		$icon_phone  = ! empty( $hover_icon_phone ) ? Utils::process_font_icon( $hover_icon_phone ) : '';

		// Extract WooCommerce-specific attributes.
		$product                = $attrs['content']['advanced']['product'][ $default_breakpoint ][ $default_state ] ?? 'current';
		$fullwidth              = $attrs['layout']['advanced']['fullwidth'][ $default_breakpoint ][ $default_state ] ?? 'off';
		$orientation            = $attrs['layout']['advanced']['orientation'][ $default_breakpoint ][ $default_state ] ?? 'landscape';
		$show_pagination        = $attrs['content']['advanced']['showPagination'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_title_and_caption = $attrs['content']['advanced']['showTitleAndCaption'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$posts_number           = $attrs['content']['advanced']['postsNumber'][ $default_breakpoint ][ $default_state ] ?? 4;
		$heading_level          = $attrs['title']['decoration']['font']['font'][ $default_breakpoint ][ $default_state ]['headingLevel'] ?? 'h3';

		// Convert posts_number to int (D4 Gallery render() line 575).
		$posts_number = 0 === intval( $posts_number ) ? 4 : intval( $posts_number );

		// Validate orientation (D4 Gallery render() line 531-532).
		$orientation = 'portrait' === $orientation ? 'portrait' : 'landscape';

		// Use the unified gallery method for consistency with settings store and REST API.
		$args = [
			'product'                => $product,
			'fullwidth'              => $fullwidth,
			'orientation'            => $orientation,
			'show_title_and_caption' => $show_title_and_caption,
			'show_pagination'        => $show_pagination,
			'posts_number'           => $posts_number,
			'gallery_layout'         => 'on' === $fullwidth ? 'slider' : 'grid',
			'thumbnail_orientation'  => $orientation,
			'hover_icon'             => $hover_icon,
			'hover_icon_tablet'      => $hover_icon_tablet,
			'hover_icon_phone'       => $hover_icon_phone,
			'heading_level'          => $heading_level,
		];

		// Generate gallery HTML using the unified method.
		// Pass elements and image attrs for box shadow overlay rendering (matches Gallery module pattern).
		$image_attrs  = $attrs['image'] ?? [];
		$gallery_html = self::get_gallery( $args, $elements, $image_attrs );

		// Render empty string if no output is generated to avoid unwanted vertical space.
		if ( empty( $gallery_html ) ) {
			return '';
		}

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
							'children'          => $gallery_html,
						]
					),
				],
			]
		);
	}
}
