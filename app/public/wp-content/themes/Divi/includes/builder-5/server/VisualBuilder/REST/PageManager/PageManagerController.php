<?php
/**
 * REST: PageManagerController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\PageManager;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Page Manager REST Controller class.
 *
 * @since ??
 */
class PageManagerController extends RESTController {

	/**
	 * Retrieves an array of all posts with Divi builder enabled that the user can edit.
	 *
	 * This function retrieves all posts that have the Divi builder enabled
	 * (checked via `_et_pb_use_builder` postmeta) and groups them by post type.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response object containing the posts grouped by post type.
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$query = [
			'post_type'      => 'any',
			'posts_per_page' => -1,
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_query'     => [
				[
					'key'   => '_et_pb_use_builder',
					'value' => 'on',
				],
			],
		];

		$posts = new \WP_Query( $query );

		$results_by_type = [];

		foreach ( $posts->posts as $post ) {
			// Check if current user has permission to edit this post.
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$post_type = get_post_type( $post->ID );
			$post_url  = get_permalink( $post->ID );

			if ( ! isset( $results_by_type[ $post_type ] ) ) {
				$results_by_type[ $post_type ] = [];
			}

			$results_by_type[ $post_type ][] = [
				'id'    => $post->ID,
				'title' => wp_strip_all_tags( $post->post_title ),
				'url'   => $post_url,
			];
		}

		// Sort posts by saved order if available.
		foreach ( $results_by_type as $post_type => &$posts_array ) {
			// Get saved order for this post type.
			$order_meta_key = '_et_pb_page_manager_order_' . $post_type;
			$saved_order    = get_option( $order_meta_key, [] );

			if ( ! empty( $saved_order ) && is_array( $saved_order ) ) {
				// Create a map of post ID to order.
				$order_map = [];
				foreach ( $saved_order as $index => $post_id ) {
					$order_map[ $post_id ] = $index;
				}

				// Sort posts array by saved order.
				usort(
					$posts_array,
					function ( $a, $b ) use ( $order_map ) {
						$a_order = $order_map[ $a['id'] ] ?? PHP_INT_MAX;
						$b_order = $order_map[ $b['id'] ] ?? PHP_INT_MAX;

						return $a_order <=> $b_order;
					}
				);
			}
		}

		$data = [
			'results' => $results_by_type,
		];

		return self::response_success( $data );
	}

	/**
	 * Duplicates a post.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response object containing the duplicated post information.
	 */
	public static function duplicate( WP_REST_Request $request ): WP_REST_Response {
		$post_id = $request->get_param( 'id' );

		if ( ! $post_id ) {
			return self::response_error( 'missing_post_id', esc_html__( 'Post ID is required.', 'et_builder_5' ) );
		}

		$original_post = get_post( $post_id );

		if ( ! $original_post ) {
			return self::response_error( 'post_not_found', esc_html__( 'Post not found.', 'et_builder_5' ) );
		}

		// Check if user has permission to edit the specific post.
		// This ensures only posts that can be edited can be duplicated.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to duplicate this post.', 'et_builder_5' ) );
		}

		// Prepare post data for duplication.
		$post_data = [
			'post_title'   => sprintf( '%s %s', $original_post->post_title, __( '(Copy)', 'et_builder_5' ) ),
			'post_content' => $original_post->post_content,
			'post_excerpt' => $original_post->post_excerpt,
			'post_status'  => 'draft',
			'post_type'    => $original_post->post_type,
			'post_author'  => get_current_user_id(),
		];

		// Apply wp_slash to post_content for proper handling of Divi 5 content.
		$post_data['post_content'] = wp_slash( $post_data['post_content'] );

		// Insert the duplicated post.
		$new_post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $new_post_id ) ) {
			return self::response_error( 'duplication_failed', esc_html__( 'Failed to duplicate post.', 'et_builder_5' ) );
		}

		// Copy post meta.
		$meta_keys = get_post_meta( $post_id );
		foreach ( $meta_keys as $key => $values ) {
			foreach ( $values as $value ) {
				// Unserialize if needed, then re-serialize for storage.
				$meta_value = maybe_unserialize( $value );
				update_post_meta( $new_post_id, $key, $meta_value );
			}
		}

		// Copy taxonomies.
		$taxonomies = get_object_taxonomies( $original_post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_post_id, $terms, $taxonomy );
			}
		}

		$new_post_url = get_permalink( $new_post_id );
		$post_type    = get_post_type( $new_post_id );

		$data = [
			'id'        => $new_post_id,
			'title'     => get_the_title( $new_post_id ),
			'url'       => $new_post_url,
			'post_type' => $post_type,
		];

		return self::response_success( $data );
	}

	/**
	 * Creates a new post/page.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response object containing the created post information.
	 */
	public static function create( WP_REST_Request $request ): WP_REST_Response {
		$title       = $request->get_param( 'title' );
		$post_type   = $request->get_param( 'post_type' );
		$post_status = $request->get_param( 'post_status' ) ?? 'publish';
		$post_date   = $request->get_param( 'post_date' );

		if ( ! $title ) {
			return self::response_error( 'missing_title', esc_html__( 'Title is required.', 'et_builder_5' ) );
		}

		if ( ! $post_type ) {
			return self::response_error( 'missing_post_type', esc_html__( 'Post type is required.', 'et_builder_5' ) );
		}

		// Check if user has permission to create posts of this type.
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			return self::response_error( 'invalid_post_type', esc_html__( 'Invalid post type.', 'et_builder_5' ) );
		}

		if ( ! current_user_can( $post_type_object->cap->create_posts ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to create posts of this type.', 'et_builder_5' ) );
		}

		// Prepare post data for creation.
		$post_data = [
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => '',
			'post_status'  => sanitize_text_field( $post_status ),
			'post_type'    => sanitize_text_field( $post_type ),
			'post_author'  => get_current_user_id(),
		];

		// Add post date if provided (for scheduled posts).
		if ( $post_date ) {
			$post_data['post_date']     = sanitize_text_field( $post_date );
			$post_data['post_date_gmt'] = get_gmt_from_date( $post_date );
		}

		// Insert the new post.
		$new_post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $new_post_id ) ) {
			return self::response_error( 'creation_failed', esc_html__( 'Failed to create post.', 'et_builder_5' ) );
		}

		// Enable Divi Builder for the new post.
		update_post_meta( $new_post_id, '_et_pb_use_builder', 'on' );

		$new_post_url            = get_permalink( $new_post_id );
		$post_type_from_response = get_post_type( $new_post_id );

		$data = [
			'id'        => $new_post_id,
			'title'     => get_the_title( $new_post_id ),
			'url'       => $new_post_url,
			'post_type' => $post_type_from_response,
		];

		return self::response_success( $data );
	}

	/**
	 * Updates the order of posts for a specific post type.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response object.
	 */
	public static function update_order( WP_REST_Request $request ): WP_REST_Response {
		$post_type = $request->get_param( 'post_type' );
		$post_ids  = $request->get_param( 'post_ids' );

		if ( ! $post_type ) {
			return self::response_error( 'missing_post_type', esc_html__( 'Post type is required.', 'et_builder_5' ) );
		}

		if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
			return self::response_error( 'missing_post_ids', esc_html__( 'Post IDs array is required.', 'et_builder_5' ) );
		}

		// Validate post type.
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			return self::response_error( 'invalid_post_type', esc_html__( 'Invalid post type.', 'et_builder_5' ) );
		}

		// Check if user has permission to edit posts of this type.
		if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to reorder posts of this type.', 'et_builder_5' ) );
		}

		// Sanitize post IDs.
		$sanitized_post_ids = array_map( 'absint', $post_ids );

		// Verify all posts exist and user has permission to edit them.
		foreach ( $sanitized_post_ids as $post_id ) {
			if ( ! get_post( $post_id ) ) {
				return self::response_error( 'post_not_found', esc_html__( 'One or more posts not found.', 'et_builder_5' ) );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to edit one or more posts.', 'et_builder_5' ) );
			}

			// Verify post type matches.
			if ( get_post_type( $post_id ) !== $post_type ) {
				return self::response_error( 'post_type_mismatch', esc_html__( 'Post type mismatch.', 'et_builder_5' ) );
			}
		}

		// Save order as option (per post type).
		$order_meta_key = '_et_pb_page_manager_order_' . sanitize_key( $post_type );
		update_option( $order_meta_key, $sanitized_post_ids );

		return self::response_success( [] );
	}

	/**
	 * Moves a post to trash.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response object.
	 */
	public static function trash( WP_REST_Request $request ): WP_REST_Response {
		$post_id = $request->get_param( 'id' );

		if ( ! $post_id ) {
			return self::response_error( 'missing_post_id', esc_html__( 'Post ID is required.', 'et_builder_5' ) );
		}

		// Check if user has permission to edit the post.
		// This ensures only posts that can be edited can be trashed, providing consistency
		// with the duplicate action and better security.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to trash this post.', 'et_builder_5' ) );
		}

		// Check if user has permission to delete the post.
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to trash this post.', 'et_builder_5' ) );
		}

		$trashed = wp_trash_post( $post_id );

		if ( ! $trashed ) {
			return self::response_error( 'trash_failed', esc_html__( 'Failed to move post to trash.', 'et_builder_5' ) );
		}

		return self::response_success( [] );
	}

	/**
	 * Get the arguments for the index action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the index action.
	 */
	public static function index_args(): array {
		return [];
	}

	/**
	 * Get the arguments for the duplicate action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the duplicate action.
	 */
	public static function duplicate_args(): array {
		return [
			'id' => [
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Get the arguments for the create action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the create action.
	 */
	public static function create_args(): array {
		return [
			'title'       => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'post_type'   => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'post_status' => [
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'post_date'   => [
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Get the arguments for the update_order action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the update_order action.
	 */
	public static function update_order_args(): array {
		return [
			'post_type' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'post_ids'  => [
				'required'          => true,
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return [];
					}
					return array_map( 'absint', $value );
				},
			],
		];
	}

	/**
	 * Get the arguments for the trash action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the trash action.
	 */
	public static function trash_args(): array {
		return [
			'id' => [
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Get the permission status for the index action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder, `false` otherwise.
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}

	/**
	 * Get the permission status for the duplicate action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder
	 * and can edit posts (required for duplicating posts).
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder and can edit posts, `false` otherwise.
	 */
	public static function duplicate_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_posts' );
	}

	/**
	 * Get the permission status for the create action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder
	 * and can create posts (required for creating posts).
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder and can create posts, `false` otherwise.
	 */
	public static function create_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_posts' );
	}

	/**
	 * Get the permission status for the update_order action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder
	 * and can edit posts (required for reordering posts).
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder and can edit posts, `false` otherwise.
	 */
	public static function update_order_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_posts' );
	}

	/**
	 * Get the permission status for the trash action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder
	 * and can delete posts (required for trashing posts).
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder and can delete posts, `false` otherwise.
	 */
	public static function trash_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'delete_posts' );
	}
}
