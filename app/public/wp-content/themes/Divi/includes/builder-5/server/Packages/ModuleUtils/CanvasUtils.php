<?php
/**
 * Canvas Utils Class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleUtils;

use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * CanvasUtils class.
 *
 * This class provides utility methods for working with canvas content.
 *
 * @since ??
 */
class CanvasUtils {

	/**
	 * Cache for canvas posts by canvas ID and post ID.
	 * Prevents redundant get_posts() queries during rendering.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_canvas_content_cache = [];

	/**
	 * Get canvas content by canvas ID for a specific post context.
	 *
	 * Each canvas is stored as a unique post in the `et_pb_canvas` post type, identified
	 * by the `_divi_canvas_id` meta field.
	 *
	 * A canvas can be either:
	 * - **Local**: Has `_divi_canvas_parent_post_id` meta pointing to a specific post (post-specific)
	 * - **Global**: Has no `_divi_canvas_parent_post_id` meta (shared across all posts)
	 *
	 * When rendering a post, we check if the canvas is local to that post. If not, we check
	 * if it's a global canvas (which can be used by any post). We do not return local canvases
	 * that belong to other posts.
	 *
	 * **Cache Strategy:**
	 * Results are cached using a composite key of `"{$canvas_id}_{$post_id}"` to cache
	 * the resolution result for this specific canvas/post combination.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID to look up.
	 * @param int    $post_id   Post ID that provides context for local canvas lookup and cache key.
	 *
	 * @return string|null Canvas content (post_content from the canvas post) or null if not found.
	 */
	public static function get_canvas_content( string $canvas_id, int $post_id ): ?string {
		if ( empty( $canvas_id ) || ! $post_id ) {
			return null;
		}

		// Use cache to avoid redundant get_posts() queries for the same canvas/post combination.
		$cache_key = "{$canvas_id}_{$post_id}";
		if ( isset( self::$_canvas_content_cache[ $cache_key ] ) ) {
			return self::$_canvas_content_cache[ $cache_key ];
		}

		// Check if canvas is local to this post. If not, check if it's a global canvas
		// (which can be used by any post). We do not return local canvases that belong to other posts.
		$canvas_content = self::_fetch_canvas_post_content( $canvas_id, $post_id )
			?? self::_fetch_canvas_post_content( $canvas_id );

		// Cache the result (even if null) to avoid redundant queries.
		self::$_canvas_content_cache[ $cache_key ] = $canvas_content;

		return $canvas_content;
	}

	/**
	 * Fetch canvas post content by canvas ID.
	 *
	 * @since ??
	 *
	 * @param string   $canvas_id Canvas ID.
	 * @param int|null $parent_post_id Optional. Parent post ID for local canvas lookup.
	 *
	 * @return string|null Canvas post content or null if not found.
	 */
	private static function _fetch_canvas_post_content( string $canvas_id, ?int $parent_post_id = null ): ?string {
		$meta_query = [
			[
				'key'   => '_divi_canvas_id',
				'value' => $canvas_id,
			],
		];

		if ( null === $parent_post_id ) {
			// Global canvas: parent_post_id meta key should not exist.
			$meta_query[] = [
				'key'     => '_divi_canvas_parent_post_id',
				'compare' => 'NOT EXISTS',
			];
		} else {
			// Local canvas: parent_post_id must match.
			$meta_query[] = [
				'key'   => '_divi_canvas_parent_post_id',
				'value' => $parent_post_id,
			];
		}

		$posts = get_posts(
			[
				'post_type'      => 'et_pb_canvas',
				'posts_per_page' => 1,
				'meta_query'     => $meta_query,
			]
		);

		return ! empty( $posts ) ? $posts[0]->post_content : null;
	}

	/**
	 * Pre-populate canvas content cache with batch-fetched canvas content.
	 *
	 * This allows render_callback() to reuse cached content instead of fetching again.
	 *
	 * @since ??
	 *
	 * @param array $canvas_content_map Map of canvas_id => post_content.
	 * @param int   $post_id            Post ID for cache key.
	 *
	 * @return void
	 */
	public static function pre_populate_cache( array $canvas_content_map, int $post_id ): void {
		foreach ( $canvas_content_map as $canvas_id => $canvas_content ) {
			$cache_key = "{$canvas_id}_{$post_id}";
			if ( ! isset( self::$_canvas_content_cache[ $cache_key ] ) ) {
				self::$_canvas_content_cache[ $cache_key ] = $canvas_content;
			}
		}
	}

	/**
	 * Get canvas posts (local or global).
	 *
	 * Local canvases are linked to their parent post via `_divi_canvas_parent_post_id` meta.
	 * Global canvases do not have `_divi_canvas_parent_post_id` meta (they are shared across all posts).
	 *
	 * @since ??
	 *
	 * @param bool  $is_global Whether to fetch global canvas posts. If true, ignores $post_id.
	 * @param ?int  $post_id   Parent post ID (required when $is_global is false).
	 * @param array $args      Optional. Additional arguments to pass to get_posts(). Defaults to:
	 *                         - 'posts_per_page' => -1.
	 *                         - 'post_status' => 'publish'.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public static function get_canvas_posts( bool $is_global, ?int $post_id = null, array $args = [] ): array {
		$defaults = [
			'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		];

		if ( $is_global ) {
			$defaults['meta_query'] = [
				[
					'key'     => '_divi_canvas_parent_post_id',
					'compare' => 'NOT EXISTS',
				],
			];
		} else {
			if ( null === $post_id ) {
				return [];
			}
			$defaults['meta_query'] = [
				[
					'key'   => '_divi_canvas_parent_post_id',
					'value' => $post_id,
				],
			];
		}

		// Merge user args, but preserve meta_query structure.
		if ( isset( $args['meta_query'] ) ) {
			// If user provided meta_query, merge it with our required one.
			$args['meta_query'] = array_merge( $defaults['meta_query'], $args['meta_query'] );
		}

		$query_args = array_merge( $defaults, $args );

		return get_posts( $query_args );
	}

	/**
	 * Get local canvas posts for a specific post.
	 *
	 * Local canvases are linked to their parent post via `_divi_canvas_parent_post_id` meta.
	 *
	 * @since ??
	 *
	 * @param int   $post_id Parent post ID.
	 * @param array $args    Optional. Additional arguments to pass to get_posts(). Defaults to:
	 *                       - 'posts_per_page' => -1.
	 *                       - 'post_status' => 'publish'.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public static function get_local_canvas_posts( int $post_id, array $args = [] ): array {
		return self::get_canvas_posts( false, $post_id, $args );
	}
}
