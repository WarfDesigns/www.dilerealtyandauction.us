<?php
/**
 * Module Library: WooCommerce Product Gallery Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductGallery;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * WooCommerce Product Gallery REST Controller class.
 *
 * @since ??
 */
class WooCommerceProductGalleryController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Product Gallery module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object containing the rendered HTML.
	 *                                  If the request is invalid, a `WP_Error` object is returned.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$product_id = $request->get_param( 'productId' ) ?? 'current';

		// Validate product exists for numeric product IDs.
		$product = WooCommerceUtils::get_product( $product_id );

		if ( ! $product ) {
			return self::response_error(
				'product_not_found',
				__( 'Product not found.', 'divi' ),
				[ 'status' => 404 ],
				404
			);
		}

		// Prepare arguments for the unified gallery method.
		$args = [
			'product'                => $product_id,
			'gallery_layout'         => $request->get_param( 'galleryLayout' ) ?? 'grid',
			'thumbnail_orientation'  => $request->get_param( 'thumbnailOrientation' ) ?? 'landscape',
			'show_pagination'        => $request->get_param( 'showPagination' ) ?? 'on',
			'show_title_and_caption' => $request->get_param( 'showTitleAndCaption' ) ?? 'on',
			'hover_icon'             => $request->get_param( 'hoverIcon' ) ?? '',
			'hover_icon_tablet'      => $request->get_param( 'hoverIconTablet' ) ?? '',
			'hover_icon_phone'       => $request->get_param( 'hoverIconPhone' ) ?? '',
			'heading_level'          => $request->get_param( 'headingLevel' ) ?? 'h3',
			'posts_number'           => 4,
		];

		// Use the unified gallery method to generate HTML.
		$gallery_html = WooCommerceProductGalleryModule::get_gallery( $args );

		$response = [
			'html' => $gallery_html,
		];

		return self::response_success( $response );
	}

	/**
	 * Get the arguments for the index action.
	 *
	 * This function returns an array that defines the arguments for the index action,
	 * which is used in the `register_rest_route()` function.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the index action.
	 */
	public static function index_args(): array {
		return [
			'productId'            => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'current',
				'sanitize_callback' => function ( $param ) {
					$param = sanitize_text_field( $param );

					// Handle empty strings by defaulting to 'current'.
					if ( empty( $param ) ) {
						return 'current';
					}
					return ( 'current' !== $param && 'latest' !== $param ) ? absint( $param ) : $param;
				},
				'validate_callback' => function ( $param, $request ) {
					return WooCommerceUtils::validate_product_id( $param, $request );
				},
			],
			'galleryLayout'        => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'grid',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'grid', 'slider' ], true );
				},
			],
			'thumbnailOrientation' => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'landscape',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'landscape', 'portrait' ], true );
				},
			],
			'showPagination'       => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'on',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'on', 'off' ], true );
				},
			],
			'showTitleAndCaption'  => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'off',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'on', 'off' ], true );
				},
			],
			'hoverIcon'            => [
				'type'     => [ 'object', 'string' ],
				'required' => false,
				'default'  => '',
			],
			'hoverIconTablet'      => [
				'type'     => [ 'object', 'string' ],
				'required' => false,
				'default'  => '',
			],
			'hoverIconPhone'       => [
				'type'     => [ 'object', 'string' ],
				'required' => false,
				'default'  => '',
			],
			'headingLevel'         => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'h3',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Provides the permission status for the index action.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the rest endpoint, otherwise `false`.
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
