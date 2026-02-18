<?php
/**
 * Loop: LoopHooks.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Loop;

use ET\Builder\Packages\ModuleLibrary\LoopQueryRegistry;
use ET_Core_PageResource;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Loop option custom hooks.
 */
class LoopHooks {

	/**
	 * Register the loop option custom hooks for cache invalidation.
	 *
	 * This method registers WordPress hooks that fire when posts, terms, or users
	 * are created, updated, or deleted. When these hooks fire, the LoopQueryRegistry
	 * cache is cleared to ensure loop queries use current post/term/user counts.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register(): void {
		// Post hooks - invalidate when posts are created/published or change status.
		add_action( 'wp_insert_post', [ __CLASS__, 'invalidate_cache_on_post_insert' ], 10, 3 );
		add_action( 'transition_post_status', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 3 );
		add_action( 'delete_post', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 1 );
		add_action( 'wp_trash_post', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 1 );
		add_action( 'trashed_post', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 1 );
		add_action( 'wp_untrash_post', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 1 );

		// Term hooks - fires when terms are created or deleted.
		add_action( 'created_term', [ __CLASS__, 'invalidate_cache_on_content_change' ], 10, 1 );
		add_action( 'delete_term', [ __CLASS__, 'invalidate_cache_on_content_change' ], 10, 1 );

		// User hooks - fires when users are registered or deleted.
		add_action( 'user_register', [ __CLASS__, 'invalidate_cache_on_content_change' ], 10, 1 );
		add_action( 'delete_user', [ __CLASS__, 'invalidate_cache_on_content_change' ], 10, 1 );
	}

	/**
	 * Invalidate cache when posts are inserted (created).
	 *
	 * This callback fires when posts are inserted, including new posts with any status.
	 * It clears both loop query cache and static CSS cache for all new posts.
	 *
	 * @since ??
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public static function invalidate_cache_on_post_insert( $post_id, $post, $update ): void {
		// Only clear cache for new posts (not updates).
		if ( $update ) {
			return;
		}

		// Skip if this is an autosave or revision.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Skip if this is not a post type we care about (only clear cache for public post types).
		if ( isset( $post->post_type ) && ! et_builder_is_post_type_public( $post->post_type ) ) {
			return;
		}

		// Clear all cache when a new post is created (post count changed).
		self::_invalidate_cache();
	}

	/**
	 * Invalidate cache when posts change (status, delete, trash, untrash).
	 *
	 * This callback handles multiple post-related hooks:
	 * - transition_post_status: receives ($new_status, $old_status, $post)
	 * - delete_post, wp_trash_post, trashed_post, wp_untrash_post: receive ($post_id)
	 *
	 * @since ??
	 *
	 * @param mixed $post_id_or_status Post ID (for delete/trash hooks) or new status (for transition_post_status).
	 * @param mixed $old_status         Old status (for transition_post_status only).
	 * @param mixed $post               Post object (for transition_post_status only).
	 *
	 * @return void
	 */
	public static function invalidate_cache_on_post_change( $post_id_or_status, $old_status = null, $post = null ): void {
		// For transition_post_status hook: only invalidate if status actually changed.
		// WordPress fires this hook even when saving without status change.
		if ( null !== $old_status && null !== $post ) {
			// This is transition_post_status hook - check if status actually changed.
			$new_status = $post_id_or_status;
			if ( $new_status === $old_status ) {
				// Status didn't change - don't invalidate cache.
				return;
			}
			// Status changed - invalidate cache for this post.
			self::_invalidate_cache_for_post( $post );
			return;
		}

		// For other hooks (delete_post, wp_trash_post, etc.): always invalidate.
		$post = null !== $post ? $post : get_post( $post_id_or_status );
		if ( $post ) {
			self::_invalidate_cache_for_post( $post );
		}
	}

	/**
	 * Invalidate cache when terms or users change.
	 *
	 * This callback handles term and user hooks (created_term, delete_term, user_register, delete_user).
	 * No post type checking is needed for these hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function invalidate_cache_on_content_change(): void {
		self::_invalidate_cache();
	}

	/**
	 * Invalidate cache for a specific post if it's a public post type.
	 *
	 * @since ??
	 *
	 * @param WP_Post|null $post Post object.
	 *
	 * @return void
	 */
	private static function _invalidate_cache_for_post( $post ): void {
		if ( ! $post || ! isset( $post->post_type ) ) {
			return;
		}

		// Skip if this is not a post type we care about (only clear cache for public post types).
		if ( ! et_builder_is_post_type_public( $post->post_type ) ) {
			return;
		}

		// Clear all cache when a post is deleted or status changes (post count/visibility changed).
		self::_invalidate_cache();
	}

	/**
	 * Clear both loop query cache and static CSS cache.
	 *
	 * Always clears all cache because post/term/user count changes affect all loop queries.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _invalidate_cache(): void {
		LoopQueryRegistry::clear();
		ET_Core_PageResource::remove_static_resources( 'all', 'all' );
	}
}
