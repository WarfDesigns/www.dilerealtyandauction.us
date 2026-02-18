<?php
/**
 * Loop QueryResults: QueryResultsController.
 *
 * @package Builder\Packages\Module\Options\Loop\QueryResults
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Loop\QueryResults;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;
use ET\Builder\Packages\Module\Options\Loop\WooCommerceLoopHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_Query;
use WP_User_Query;
use WP_Term_Query;
use ET_Post_Stack;

/**
 * Query Result REST Controller class.
 *
 * @since ??
 */
class QueryResultsController extends RESTController {

	/**
	 * Default items per page for all query types.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 10;

	/**
	 * Return query results based on the specified query_type.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$query_type = $request->get_param( 'query_type' );
		$params     = $request->get_params();

		// Get current post ID with full fallback logic.
		$params['current_post_id'] = self::_get_current_post_id( $params );
		$result                    = [];

		switch ( $query_type ) {
			case 'current_page':
				$result = self::_format_pagination_response(
					LoopUtils::generate_dummy_posts( $params['posts_per_page'] ),
					$params['posts_per_page'] * 3,
					$params['posts_per_page'],
					1
				);
				break;

			case 'post_type':
				$result = self::_get_post_type_results( $params );
				break;

			case 'terms':
				$result = self::_get_terms_results( $params );
				break;

			case 'users':
				$result = self::_get_users_results( $params );
				break;

			case 'menus':
				$result = self::get_menus_results( $params );
				break;

			case 'repeater':
				$result = self::_get_repeater_results( $params );
				break;

			default:
				return rest_ensure_response( self::response_error( 'Invalid query_type specified' ) );
		}

		return self::response_success( $result );
	}

	/**
	 * Get current post ID with fallback mechanisms.
	 *
	 * @since ??
	 *
	 * @param array $params Request parameters.
	 *
	 * @return int Current post ID.
	 */
	public static function get_current_post_id( array $params = [] ): int {
		return self::_get_current_post_id( $params );
	}

	/**
	 * Get current post ID with fallback mechanisms (private implementation).
	 *
	 * @since ??
	 *
	 * @param array $params Request parameters.
	 *
	 * @return int Current post ID.
	 */
	private static function _get_current_post_id( array $params = [] ): int {
		// Try to get from request params first (but only if it's a valid post ID).
		if ( isset( $params['current_post_id'] ) && $params['current_post_id'] > 0 ) {
			return absint( $params['current_post_id'] );
		}

		// Use et_core_get_main_post_id() which handles VB context properly.
		if ( function_exists( 'et_core_get_main_post_id' ) ) {
			$main_post_id = et_core_get_main_post_id();
			if ( ! empty( $main_post_id ) ) {
				return absint( $main_post_id );
			}
		}

		// Use the existing VB post ID detection function for AJAX requests.
		if ( class_exists( '\ET_Builder_Element' ) ) {
			$vb_post_id = \ET_Builder_Element::get_current_post_id_reverse();
			if ( false !== $vb_post_id && ! empty( $vb_post_id ) ) {
				return absint( $vb_post_id );
			}
		}

		// Fall back to global state.
		// Use ET_Post_Stack::get_main_post_id() for Theme Builder compatibility.
		$main_post_id    = \ET_Post_Stack::get_main_post_id();
		$current_post_id = $main_post_id ?? get_the_ID();
		if ( ! empty( $current_post_id ) ) {
			return absint( $current_post_id );
		}

		// Last resort: try to get from HTTP referer.
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer_url   = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$referer_parts = wp_parse_url( $referer_url );
			$referer_query = [];

			if ( isset( $referer_parts['query'] ) ) {
				parse_str( $referer_parts['query'], $referer_query );
			}

			// Try to get post ID from URL query parameters.
			if ( isset( $referer_query['post'] ) ) {
				return absint( $referer_query['post'] );
			}

			// Try to extract post ID from URL path (e.g., /1625-2/ -> 1625).
			if ( isset( $referer_parts['path'] ) && ! empty( $referer_parts['path'] ) ) {
				$path = trim( $referer_parts['path'], '/' );

				// Check if path looks like a post ID or post slug.
				if ( is_numeric( $path ) ) {
					// Direct numeric ID.
					return absint( $path );
				} elseif ( function_exists( 'url_to_postid' ) ) {
					// Try to convert full URL to post ID.
					$post_id = url_to_postid( $referer_url );
					if ( $post_id > 0 ) {
						return absint( $post_id );
					}
				}

				// Fallback: try regex for paths starting with numeric ID (e.g., "1625-2").
				if ( preg_match( '/^(\d+)/', $path, $matches ) ) {
					return absint( $matches[1] );
				}
			}
		}

		return 0;
	}

	/**
	 * Check if a parameter represents a boolean true value.
	 *
	 * @since ??
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool Whether the value represents true.
	 */
	private static function _is_true( $value ): bool {
		return 'on' === $value;
	}

	/**
	 * Extract and format meta_query parameters from request params.
	 *
	 * This method is optional - if no meta_query parameters are provided,
	 * it returns an empty array and meta filtering is skipped.
	 * Multiple meta query clauses are combined with 'OR' relation by default.
	 *
	 * @since ??
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array Formatted meta_query array for WP_Query.
	 */
	private static function _get_meta_query_from_params( array $params ): array {
		// Meta query is optional - return empty array if not provided.
		if ( ! isset( $params['meta_query'] ) || ! is_array( $params['meta_query'] ) ) {
			return [];
		}

		// Use the unified meta query builder from LoopUtils.
		return LoopUtils::build_meta_query( $params['meta_query'] );
	}

	/**
	 * Get pagination parameters for a query.
	 *
	 * @since ??
	 *
	 * @param array  $params        Query parameters.
	 * @param string $per_page_key  Query-specific per page key.
	 * @param string $offset_key    Query-specific offset key.
	 *
	 * @return array Array containing per_page, page, and offset.
	 */
	private static function _get_pagination_params( array $params, string $per_page_key, string $offset_key ): array {
		$per_page = isset( $params['per_page'] ) && '' !== $params['per_page'] ?
			(int) $params['per_page'] : self::DEFAULT_PER_PAGE;

		// Support for query-specific per_page parameter.
		if ( isset( $params[ $per_page_key ] ) && '' !== $params[ $per_page_key ] ) {
			$per_page = (int) $params[ $per_page_key ];
		}

		// Ensure per_page is at least 1 to prevent WordPress errors.
		$per_page = max( 1, $per_page );

		// Always start page numbering from 1, regardless of offset.
		$page = isset( $params['page'] ) ? (int) $params['page'] : 1;

		// Check if direct offset parameter is provided.
		if ( isset( $params[ $offset_key ] ) && '' !== $params[ $offset_key ] ) {
			$offset = (int) $params[ $offset_key ];
		} else {
			// Calculate offset from page if no direct offset is provided.
			$offset = ( $page - 1 ) * $per_page;
		}

		return [
			'per_page' => $per_page,
			'page'     => $page,
			'offset'   => $offset,
		];
	}

	// _add_ordering_params method removed as it's no longer used.

	/**
	 * Format pagination response.
	 *
	 * @since ??
	 *
	 * @param array $items      Result items.
	 * @param int   $total      Total number of items.
	 * @param int   $per_page   Items per page.
	 * @param int   $page       Current page.
	 * @param int   $offset     Applied offset.
	 *
	 * @return array Formatted response with pagination info.
	 */
	private static function _format_pagination_response( array $items, int $total, int $per_page, int $page, int $offset = 0 ): array {
		// Calculate available items after offset is applied.
		$available_items = max( 0, $total - $offset );

		// Calculate total pages based on available items, not the full database count.
		$total_pages = $available_items > 0 ? ceil( $available_items / $per_page ) : 0;

		return [
			'items'       => $items,
			'total_items' => $available_items,
			'total_pages' => $total_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	/**
	 * Filter out non-existent term IDs for a specific taxonomy.
	 *
	 * @since ??
	 *
	 * @param array  $term_ids Array of term IDs to validate.
	 * @param string $taxonomy Taxonomy name to validate terms against.
	 *
	 * @return array Array of valid term IDs.
	 */
	private static function _filter_invalid_term_ids( array $term_ids, string $taxonomy ): array {
		$valid_term_ids = [];

		foreach ( $term_ids as $term_id ) {
			$term_id = intval( $term_id );
			$term    = term_exists( $term_id, $taxonomy );
			if ( ! empty( $term ) ) {
				$valid_term_ids[] = $term_id;
			}
		}

		return $valid_term_ids;
	}

	/**
	 * Get post type query results.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Post type query results.
	 */
	private static function _get_post_type_results( array $params ): array {
		$post_type       = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : 'post';
		$pagination      = self::_get_pagination_params( $params, 'posts_per_page', 'post_offset' );
		$current_post_id = isset( $params['current_post_id'] ) ? (int) $params['current_post_id'] : 0;

		// Handle multiple post types.
		if ( is_string( $post_type ) && strpos( $post_type, ',' ) !== false ) {
			// Filter out non-public post types.
			$post_type = array_filter(
				array_map( 'sanitize_key', array_map( 'trim', explode( ',', $post_type ) ) ),
				function ( $post_type ) {
					return et_builder_is_post_type_public( $post_type );
				}
			);
		} elseif ( 'any' !== $post_type && ! et_builder_is_post_type_public( $post_type ) ) {
			$post_type = [];
		}

		// Handle empty post_type parameter - set to 'any' to query all post types.
		if ( empty( $post_type ) ) {
			$post_type = 'any';
		}

		$query_args = [
			'post_type'      => $post_type,
			'posts_per_page' => $pagination['per_page'],
			'offset'         => $pagination['offset'],
			'post_status'    => 'attachment' === $post_type ? [ 'inherit', 'private' ] : 'publish',
		];

		// Handle post status for attachments and 'any' post type.
		if ( 'any' === $post_type ) {
			// When querying all post types, include all relevant statuses.
			$query_args['post_status'] = [ 'publish', 'inherit', 'private' ];
		} elseif ( is_array( $post_type ) ) {
			if ( in_array( 'attachment', $post_type, true ) ) {
				$query_args['post_status'] = [ 'publish', 'inherit', 'private' ];
			}
		} elseif ( 'attachment' === $post_type ) {
			$query_args['post_status'] = [ 'inherit', 'private' ];
		}

		// Handle taxonomy filtering with intersection logic.
		$include_taxonomies = [];
		$exclude_taxonomies = [];

		// Parse parameters and collect taxonomy data.
		foreach ( $params as $param_key => $param_value ) {
			// Handle taxonomy__in parameter (nested array format).
			if ( 'taxonomy__in' === $param_key && is_array( $param_value ) ) {
				foreach ( $param_value as $taxonomy => $terms_string ) {
					if ( ! empty( $terms_string ) ) {
						$sanitized_taxonomy = sanitize_key( $taxonomy );
						$term_ids           = is_string( $terms_string ) && strpos( $terms_string, ',' ) !== false
							? array_map( 'intval', array_map( 'trim', explode( ',', sanitize_text_field( $terms_string ) ) ) )
							: [ (int) sanitize_text_field( $terms_string ) ];

						// Filter out non-existent terms.
						$term_ids = self::_filter_invalid_term_ids( $term_ids, $sanitized_taxonomy );

						if ( ! empty( $term_ids ) ) {
							$include_taxonomies[ $sanitized_taxonomy ] = $term_ids;
						}
					}
				}
			}

			// Handle taxonomy__not_in parameter (nested array format).
			if ( 'taxonomy__not_in' === $param_key && is_array( $param_value ) ) {
				foreach ( $param_value as $taxonomy => $terms_string ) {
					if ( ! empty( $terms_string ) ) {
						$sanitized_taxonomy = sanitize_key( $taxonomy );
						$term_ids           = is_string( $terms_string ) && strpos( $terms_string, ',' ) !== false
							? array_map( 'intval', array_map( 'trim', explode( ',', sanitize_text_field( $terms_string ) ) ) )
							: [ (int) sanitize_text_field( $terms_string ) ];

						// Filter out non-existent terms.
						$term_ids = self::_filter_invalid_term_ids( $term_ids, $sanitized_taxonomy );

						if ( ! empty( $term_ids ) ) {
							$exclude_taxonomies[ $sanitized_taxonomy ] = $term_ids;
						}
					}
				}
			}
		}

		// Build taxonomy queries with proper include/exclude logic.
		$tax_query_parts = [];

		// Process inclusion queries (remove any terms that also appear in exclude for same taxonomy).
		foreach ( $include_taxonomies as $taxonomy => $include_terms ) {
			// If this taxonomy also has exclusions, remove conflicting terms from includes.
			if ( isset( $exclude_taxonomies[ $taxonomy ] ) ) {
				$final_include_terms = array_diff( $include_terms, $exclude_taxonomies[ $taxonomy ] );
			} else {
				$final_include_terms = $include_terms;
			}

			if ( ! empty( $final_include_terms ) ) {
				$tax_query_parts[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array_values( $final_include_terms ),
					'operator' => 'IN',
				];
			}
		}

		// Process exclusion queries (create separate NOT IN clauses for all exclude conditions).
		foreach ( $exclude_taxonomies as $taxonomy => $exclude_terms ) {
			if ( ! empty( $exclude_terms ) ) {
				$tax_query_parts[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $exclude_terms,
					'operator' => 'NOT IN',
				];
			}
		}

		// Apply tax_query if we have parts.
		if ( ! empty( $tax_query_parts ) ) {
			if ( count( $tax_query_parts ) === 1 ) {
				// Single taxonomy condition.
				$query_args['tax_query'] = $tax_query_parts;
			} else {
				// Multiple taxonomy conditions.
				// Use AND relation when we have exclude conditions to ensure they override includes.
				$has_exclude_conditions = false;
				foreach ( $tax_query_parts as $part ) {
					if ( 'NOT IN' === $part['operator'] ) {
						$has_exclude_conditions = true;
						break;
					}
				}

				if ( $has_exclude_conditions ) {
					// When exclude conditions exist, use AND relation so excludes take priority.
					$query_args['tax_query'] = array_merge( [ 'relation' => 'AND' ], $tax_query_parts );
				} else {
					// Only include conditions, use OR relation.
					$query_args['tax_query'] = array_merge( [ 'relation' => 'OR' ], $tax_query_parts );
				}
			}
		}

		// Handle post__in and post__not_in parameters with intersection logic.
		$post_in  = [];
		$post_out = [];

		// Parse post__in parameter.
		if ( isset( $params['post__in'] ) && ! empty( $params['post__in'] ) ) {
			if ( is_string( $params['post__in'] ) && strpos( $params['post__in'], ',' ) !== false ) {
				$post_in = array_map( 'intval', array_map( 'trim', explode( ',', $params['post__in'] ) ) );
			} else {
				$post_in = [ (int) $params['post__in'] ];
			}
		}

		// Parse post__not_in parameter.
		if ( isset( $params['post__not_in'] ) && ! empty( $params['post__not_in'] ) ) {
			if ( is_string( $params['post__not_in'] ) && strpos( $params['post__not_in'], ',' ) !== false ) {
				$post_out = array_map( 'intval', array_map( 'trim', explode( ',', $params['post__not_in'] ) ) );
			} else {
				$post_out = [ (int) $params['post__not_in'] ];
			}
		}

		// Apply exclude-override-include logic: if both include and exclude are provided,
		// remove conflicting posts from include and apply both conditions separately.
		if ( ! empty( $post_in ) && ! empty( $post_out ) ) {
			// Remove any post IDs that appear in both include and exclude from the include list.
			$final_post_in = array_diff( $post_in, $post_out );

			// Apply include condition only if there are remaining posts to include.
			if ( ! empty( $final_post_in ) ) {
				$query_args['post__in'] = array_values( $final_post_in );
			}

			// Always apply exclude condition.
			$query_args['post__not_in'] = isset( $query_args['post__not_in'] ) ?
				array_merge( $query_args['post__not_in'], $post_out ) :
				$post_out;
		} elseif ( ! empty( $post_in ) ) {
			// Only inclusion specified.
			$query_args['post__in'] = $post_in;
		} elseif ( ! empty( $post_out ) ) {
			// Only exclusion specified.
			$query_args['post__not_in'] = isset( $query_args['post__not_in'] ) ?
				array_merge( $query_args['post__not_in'], $post_out ) :
				$post_out;
		}

		// Handle meta_query parameters.
		$meta_query = self::_get_meta_query_from_params( $params );
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		// Add search query if specified.
		if ( isset( $params['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $params['search'] );
		}

		// Add ordering parameters.
		if ( isset( $params['order_by'] ) ) {
			// Sanitize and validate that this is a supported order_by parameter.
			$order_by       = sanitize_key( $params['order_by'] );
			$valid_order_by = [
				'none',
				'ID',
				'author',
				'title',
				'name',
				'type',
				'date',
				'modified',
				'parent',
				'rand',
				'comment_count',
				'menu_order',
			];

			// Add WooCommerce specific options if any post type is product.
			$post_types = is_array( $post_type ) ? $post_type : [ $post_type ];
			if ( in_array( 'product', $post_types, true ) && function_exists( 'WC' ) ) {
				$valid_order_by[] = 'price';
				$valid_order_by[] = 'popularity';
				$valid_order_by[] = 'rating';
			}

			if ( in_array( $order_by, $valid_order_by, true ) ) {
				$query_args['orderby'] = $order_by;
			}
		}

		if ( isset( $params['order'] ) ) {
			$order_param         = sanitize_key( $params['order'] );
			$order               = 'descending' === $order_param ? 'DESC' : 'ASC';
			$query_args['order'] = $order;
		}

		// Handle exclude_current_post for all post types.
		if ( isset( $params['exclude_current_post'] ) ) {
			if ( self::_is_true( $params['exclude_current_post'] ) ) {
				$excluded_ids = [];

				// Add current post ID if available.
				if ( 0 !== $current_post_id ) {
					$excluded_ids[] = $current_post_id;
				}

				// If post_id parameter is provided, exclude that specific post.
				if ( isset( $params['post_id'] ) && ! empty( $params['post_id'] ) ) {
					// Handle multiple post IDs.
					if ( is_string( $params['post_id'] ) && strpos( $params['post_id'], ',' ) !== false ) {
						$post_ids     = array_map( 'intval', array_map( 'trim', explode( ',', $params['post_id'] ) ) );
						$excluded_ids = array_merge( $excluded_ids, $post_ids );
					} else {
						$excluded_ids[] = (int) $params['post_id'];
					}
				}

				if ( ! empty( $excluded_ids ) ) {
					$query_args['post__not_in'] = isset( $query_args['post__not_in'] ) ?
						array_merge( $query_args['post__not_in'], $excluded_ids ) :
						$excluded_ids;
				}
			}
		}

		// Handle sticky posts for post type 'post' or when querying all post types ('any').
		$post_types = is_array( $post_type ) ? $post_type : [ $post_type ];
		if ( 'any' === $post_type || in_array( 'post', $post_types, true ) ) {
			// Handle explicit ignore_sticky_posts parameter.
			if ( isset( $params['ignore_sticky_posts'] ) && self::_is_true( $params['ignore_sticky_posts'] ) ) {
				// Ensure sticky posts are completely ignored.
				$query_args['ignore_sticky_posts'] = 1;

				// Get all sticky posts.
				$sticky_posts = get_option( 'sticky_posts' );

				if ( ! empty( $sticky_posts ) ) {
					if ( isset( $query_args['post__not_in'] ) ) {
						// Add sticky posts to the existing exclusion list.
						$query_args['post__not_in'] = array_unique(
							array_merge( $query_args['post__not_in'], $sticky_posts )
						);
					} else {
						// Create a new exclusion list with sticky posts.
						$query_args['post__not_in'] = $sticky_posts;
					}
				}
			}
		}

		$query = new WP_Query( $query_args );
		$posts = [];

		foreach ( $query->posts as $post ) {
			// Get thumbnail size from request parameters, default to 'large'.
			$thumbnail_size = isset( $params['thumbnail_size'] ) ? sanitize_text_field( $params['thumbnail_size'] ) : 'large';
			// Handle attachment post types specially - they ARE the images themselves.
			if ( 'attachment' === $post->post_type ) {
				$thumbnail_url = wp_get_attachment_image_url( $post->ID, $thumbnail_size );
			} else {
				$thumbnail_url = get_the_post_thumbnail_url( $post->ID, $thumbnail_size );
			}
			$date_created = get_the_date( '', $post );

			// Get author URLs and name fields for link and name format support.
			$author_archive_url = '';
			$author_website_url = '';
			$author_data        = [];
			if ( post_type_supports( $post->post_type, 'author' ) && $post->post_author ) {
				$author             = get_userdata( $post->post_author );
				$author_archive_url = $author ? get_author_posts_url( $author->ID ) : '';
				$author_website_url = $author ? $author->user_url : '';
				if ( $author ) {
					$author_data = [
						'display_name' => $author->display_name,
						'first_name'   => $author->first_name,
						'last_name'    => $author->last_name,
						'nickname'     => $author->nickname,
						'username'     => $author->user_login,
					];
				}
			}

			// Get full excerpt/content for Visual Builder to handle truncation based on user's word limit.
			// Use manual excerpt if available, otherwise get full content (not truncated).
			$excerpt_value = '';
			if ( ! empty( $post->post_excerpt ) ) {
				// Manual excerpt: use as-is (full, not truncated).
				$excerpt_value = $post->post_excerpt;
			} elseif ( et_pb_is_pagebuilder_used( $post->ID ) ) {
				// For Divi Builder posts, get the rendered content and strip tags.
				// Set global flag to prevent loop processing during excerpt generation.
				$GLOBALS['divi_generating_excerpt'] = true;

				try {
					$rendered_content = apply_filters( 'the_content', $post->post_content );
					$excerpt_value    = wp_strip_all_tags( $rendered_content );
				} finally {
					$GLOBALS['divi_generating_excerpt'] = false;
				}
			} else {
				// For non-Divi posts, strip shortcodes and tags to match wp_trim_excerpt() behavior.
				$excerpt_value = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			}

			$post_data = [
				'id'                 => $post->ID,
				'title'              => $post->post_title,
				'excerpt'            => $excerpt_value,
				'permalink'          => get_permalink( $post->ID ),
				'date'               => $date_created,
				'author'             => get_the_author_meta( 'display_name', $post->post_author ),
				'author_avatar'      => get_avatar_url( $post->post_author ),
				'author_data'        => $author_data,
				'author_archive_url' => $author_archive_url,
				'author_website_url' => $author_website_url,
				'thumbnail'          => $thumbnail_url,
				'post_type'          => $post->post_type,
				'post_comment_count' => $post->comment_count,
				'post_modified'      => get_the_modified_date( '', $post ),
			];

			// Add both categories and tags data for loop terms support.
			// Frontend will choose which to display based on taxonomy_type setting.

			// Get categories.
			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
				$category_objects = [];
				foreach ( $categories as $category ) {
					$category_objects[] = [
						'name' => $category->name,
						'url'  => get_category_link( $category->term_id ),
					];
				}
				$post_data['categories'] = wp_json_encode( $category_objects );
			} else {
				$post_data['categories'] = wp_json_encode( [] );
			}

			// Get tags.
			$tags = get_the_tags( $post->ID );
			if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
				$tag_objects = [];
				foreach ( $tags as $tag ) {
					$tag_objects[] = [
						'name' => $tag->name,
						'url'  => get_tag_link( $tag->term_id ),
					];
				}
				$post_data['tags'] = wp_json_encode( $tag_objects );
			} else {
				$post_data['tags'] = wp_json_encode( [] );
			}

			// Get all custom taxonomies for this post type.
			$post_taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
			if ( ! empty( $post_taxonomies ) ) {
				foreach ( $post_taxonomies as $taxonomy_slug => $taxonomy_object ) {
					// Skip core taxonomies (already handled above) and non-public taxonomies.
					if ( in_array( $taxonomy_slug, [ 'category', 'post_tag' ], true ) || ! $taxonomy_object->public ) {
						continue;
					}

					// Get terms for this taxonomy.
					$terms = get_the_terms( $post->ID, $taxonomy_slug );
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$term_objects = [];
						foreach ( $terms as $term ) {
							$term_objects[] = [
								'name' => $term->name,
								'url'  => get_term_link( $term->term_id, $taxonomy_slug ),
							];
						}
						$post_data[ $taxonomy_slug ] = wp_json_encode( $term_objects );
					} else {
						$post_data[ $taxonomy_slug ] = wp_json_encode( [] );
					}
				}
			}

			// Keep 'terms' for backward compatibility - default to categories.
			$post_data['terms'] = ! empty( $post_data['categories'] ) && '[]' !== $post_data['categories'] ? $post_data['categories'] : $post_data['tags'];

			if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $post->ID );

				if ( $product ) {
					$post_data['post_date']           = $date_created;
					$post_data['post_featured_image'] = $thumbnail_url;
					$post_data['price_regular']       = WooCommerceLoopHandler::get_loop_content( 'loop_product_price_regular', $post );
					$post_data['price_sale']          = WooCommerceLoopHandler::get_loop_content( 'loop_product_price_sale', $post );
					$post_data['price_current']       = WooCommerceLoopHandler::get_loop_content( 'loop_product_price_current', $post );
					$post_data['description']         = $product->get_description();
					$post_data['short_description']   = $product->get_short_description();
					$post_data['stock_quantity']      = $product->get_stock_quantity();
					$post_data['stock_status']        = $product->get_stock_status();
					$post_data['reviews_count']       = $product->get_review_count();
					$post_data['sku']                 = $product->get_sku();
				}
			}

			$posts[] = $post_data;
		}

		return self::_format_pagination_response(
			$posts,
			$query->found_posts,
			$pagination['per_page'],
			$pagination['page'],
			$pagination['offset']
		);
	}

	/**
	 * Get terms query results.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Terms query results.
	 */
	private static function _get_terms_results( array $params ): array {
		$taxonomy = isset( $params['taxonomy'] ) ? sanitize_text_field( $params['taxonomy'] ) : '';
		// If taxonomy is empty, get all public taxonomies excluding system ones.
		if ( empty( $taxonomy ) ) {
			$excluded_taxonomies = LoopUtils::get_excluded_taxonomies();

			$taxonomy = array_values( array_diff( get_taxonomies(), $excluded_taxonomies ) );
		}

		$pagination = self::_get_pagination_params( $params, 'terms_per_page', 'term_offset' );

		// Handle multiple taxonomies.
		if ( is_string( $taxonomy ) && strpos( $taxonomy, ',' ) !== false ) {
			$taxonomy = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $taxonomy ) ) );
		}

		$query_args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => $pagination['per_page'],
			'offset'     => $pagination['offset'],
		];

		// Add search query if specified.
		if ( isset( $params['search'] ) ) {
			$query_args['search'] = sanitize_text_field( $params['search'] );
		}

		// Add ordering parameters.
		if ( isset( $params['order_by'] ) ) {
			// Sanitize and validate that this is a supported order_by parameter.
			$order_by       = sanitize_key( $params['order_by'] );
			$valid_order_by = [
				'name',
				'slug',
				'term_id',
				'id',
				'description',
				'count',
				'none',
				'parent',
				'term_order',
			];

			// If WooCommerce is active and it's a product category, add meta_value_num.
			$taxonomies = is_array( $taxonomy ) ? $taxonomy : [ $taxonomy ];
			if ( function_exists( 'WC' ) && in_array( 'product_cat', $taxonomies, true ) ) {
				$valid_order_by[] = 'meta_value_num';
			}

			if ( in_array( $order_by, $valid_order_by, true ) ) {
				$query_args['orderby'] = $order_by;
			}
		}

		if ( isset( $params['order'] ) ) {
			$order_param         = sanitize_key( $params['order'] );
			$order               = 'descending' === $order_param ? 'DESC' : 'ASC';
			$query_args['order'] = $order;
		}

		$meta_query = self::_get_meta_query_from_params( $params );
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		$term_query = new WP_Term_Query( $query_args );
		$terms      = [];

		if ( ! empty( $term_query->terms ) ) {
			foreach ( $term_query->terms as $term ) {
				$featured_image = '';
				$thumbnail_id   = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
				if ( $thumbnail_id > 0 ) {
					$image_url = wp_get_attachment_url( $thumbnail_id );
					if ( $image_url ) {
						$featured_image = esc_url( $image_url );
					}
				}

				$terms[] = [
					'id'             => $term->term_id,
					'name'           => $term->name,
					'slug'           => $term->slug,
					'description'    => $term->description,
					'count'          => $term->count,
					'permalink'      => get_term_link( $term ),
					'taxonomy'       => $term->taxonomy,
					'featured_image' => $featured_image,
				];
			}
		}

		// Count total terms for pagination.
		$count_args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'fields'     => 'count',
		];

		if ( isset( $params['search'] ) ) {
			$count_args['search'] = sanitize_text_field( $params['search'] );
		}

		if ( ! empty( $meta_query ) ) {
			$count_args['meta_query'] = $meta_query;
		}

		$total_terms = wp_count_terms( $count_args );

		return self::_format_pagination_response(
			$terms,
			$total_terms,
			$pagination['per_page'],
			$pagination['page'],
			$pagination['offset']
		);
	}

	/**
	 * Get users query results.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Users query results.
	 */
	private static function _get_users_results( array $params ): array {
		$pagination = self::_get_pagination_params( $params, 'users_per_page', 'user_offset' );

		$query_args = [
			'number' => $pagination['per_page'],
			'offset' => $pagination['offset'],
		];

		// Add role filter if specified.
		if ( isset( $params['role'] ) ) {
			// Handle multiple roles.
			if ( is_string( $params['role'] ) && strpos( $params['role'], ',' ) !== false ) {
				$roles                  = array_map( 'sanitize_key', array_map( 'trim', explode( ',', sanitize_text_field( $params['role'] ) ) ) );
				$query_args['role__in'] = $roles;
			} else {
				$query_args['role'] = sanitize_key( $params['role'] );
			}
		}

		// Add search query if specified.
		if ( isset( $params['search'] ) ) {
			$query_args['search'] = '*' . sanitize_text_field( $params['search'] ) . '*';
		}

		// Add ordering parameters.
		if ( isset( $params['order_by'] ) ) {
			// Sanitize and validate that this is a supported order_by parameter.
			$order_by       = sanitize_key( $params['order_by'] );
			$valid_order_by = [
				'login',
				'nicename',
				'email',
				'url',
				'registered',
				'display_name',
				'name',
				'ID',
				'post_count',
			];

			if ( in_array( $order_by, $valid_order_by, true ) ) {
				$query_args['orderby'] = $order_by;
			}
		}

		if ( isset( $params['order'] ) ) {
			$order_param         = sanitize_key( $params['order'] );
			$order               = 'descending' === $order_param ? 'DESC' : 'ASC';
			$query_args['order'] = $order;
		}

		$user_query = new WP_User_Query( $query_args );
		$users      = [];

		foreach ( $user_query->get_results() as $user ) {
			$users[] = [
				'id'          => $user->ID,
				'name'        => $user->display_name,
				'username'    => $user->user_login,
				'email'       => $user->user_email,
				'avatar'      => get_avatar_url( $user->ID ),
				'description' => $user->description,
				'url'         => get_author_posts_url( $user->ID ),
				'roles'       => $user->roles,
			];
		}

		return self::_format_pagination_response(
			$users,
			$user_query->get_total(),
			$pagination['per_page'],
			$pagination['page'],
			$pagination['offset']
		);
	}

	/**
	 * Get menus query results.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Menus query results.
	 */
	public static function get_menus_results( array $params ): array {
		// Accept both 'menu_id' and 'menus' parameters for compatibility.
		$menu_id = isset( $params['menu_id'] ) ? sanitize_text_field( $params['menu_id'] ) : '';
		if ( empty( $menu_id ) && isset( $params['menus'] ) ) {
			$menu_id = sanitize_text_field( $params['menus'] );
		}

		// If no menu_id provided, return empty results.
		if ( empty( $menu_id ) ) {
			return self::_format_pagination_response(
				[],
				0,
				self::DEFAULT_PER_PAGE,
				1,
				0
			);
		}

		$pagination = self::_get_pagination_params( $params, 'menus_per_page', 'menu_offset' );

		// Parse menu IDs - handle comma-separated string or array.
		$menu_ids = [];
		if ( is_string( $menu_id ) && strpos( $menu_id, ',' ) !== false ) {
			// Comma-separated string.
			$menu_ids = array_map( 'trim', explode( ',', $menu_id ) );
		} elseif ( is_string( $menu_id ) ) {
			// Single menu ID as string.
			$menu_ids = [ $menu_id ];
		} elseif ( is_array( $menu_id ) ) {
			// Array of menu IDs.
			$menu_ids = $menu_id;
		}

		// Sanitize menu IDs and filter out empty values.
		$menu_ids = array_filter( array_map( 'absint', $menu_ids ) );

		// If no valid menu IDs, return empty results.
		if ( empty( $menu_ids ) ) {
			return self::_format_pagination_response(
				[],
				0,
				$pagination['per_page'],
				$pagination['page'],
				$pagination['offset']
			);
		}

		// Fetch menu items from all menus and merge them.
		$all_menu_items = [];
		foreach ( $menu_ids as $id ) {
			$menu_items = wp_get_nav_menu_items( $id );
			if ( ! empty( $menu_items ) && ! is_wp_error( $menu_items ) ) {
				$all_menu_items = array_merge( $all_menu_items, $menu_items );
			}
		}

		// If no menu items found, return empty results.
		if ( empty( $all_menu_items ) ) {
			return self::_format_pagination_response(
				[],
				0,
				$pagination['per_page'],
				$pagination['page'],
				$pagination['offset']
			);
		}

		$menu_items = $all_menu_items;

		// Handle ordering.
		$order_by = isset( $params['order_by'] ) ? sanitize_key( $params['order_by'] ) : 'menu_order';
		$order    = isset( $params['order'] ) ? sanitize_key( $params['order'] ) : 'DESC';

		// Validate order_by against whitelist of supported options.
		$valid_order_by = [ 'menu_order', 'title', 'id' ];

		// If order_by is not valid, default to menu_order.
		if ( ! in_array( $order_by, $valid_order_by, true ) ) {
			$order_by = 'menu_order';
		}

		// Convert 'ascending'/'descending' and 'asc'/'desc' to 'ASC'/'DESC'.
		// Note: sanitize_key() lowercases values, so we handle both lowercase and original forms.
		if ( 'ascending' === $order || 'asc' === $order ) {
			$order = 'ASC';
		} elseif ( 'descending' === $order || 'desc' === $order ) {
			$order = 'DESC';
		}

		// Sort menu items if needed.
		// Note: wp_get_nav_menu_items() returns items in descending order (reverse of WordPress admin display).
		// Default is descending, so we keep WordPress order as-is. For ascending, we reverse the array.
		if ( 'menu_order' === $order_by ) {
			// For ascending order with menu_order, reverse the array to match WordPress admin order.
			if ( 'ASC' === $order ) {
				$menu_items = array_reverse( $menu_items );
			}
			// For descending order (default), keep the original order (which is already descending from WordPress).
		} elseif ( 'title' === $order_by ) {
			// Sort by menu item title (link text) - case-insensitive.
			usort(
				$menu_items,
				static function ( $a, $b ) use ( $order ) {
					$result = strcasecmp( $a->title, $b->title );
					return 'DESC' === $order ? -$result : $result;
				}
			);
		} elseif ( 'id' === $order_by ) {
			// Sort by menu item ID.
			usort(
				$menu_items,
				static function ( $a, $b ) use ( $order ) {
					$result = $a->ID <=> $b->ID;
					return 'DESC' === $order ? -$result : $result;
				}
			);
		}

		// Apply pagination.
		$total_items = count( $menu_items );
		$offset      = $pagination['offset'];
		$per_page    = $pagination['per_page'];
		$menu_items  = array_slice( $menu_items, $offset, $per_page );

		$items = [];
		foreach ( $menu_items as $menu_item ) {
			// Get the menu item URL.
			$url = $menu_item->url ?? '';

			// If URL is empty or starts with '#', it might be a custom link or placeholder.
			// For post/page links, get_permalink() is already set in url.
			// For taxonomy links, get_term_link() is already set in url.

			// Get CSS classes - convert array to space-separated string.
			$classes = $menu_item->classes ?? [];
			if ( ! is_array( $classes ) ) {
				$classes = [];
			}
			$classes_string = ! empty( $classes ) ? implode( ' ', $classes ) : '';

			$items[] = [
				'id'          => $menu_item->ID,
				'text'        => $menu_item->title ?? '',
				'link'        => $url,
				'menu_order'  => $menu_item->menu_order ?? 0,
				'attr_title'  => $menu_item->attr_title ?? '',
				'classes'     => $classes_string,
				'xfn'         => $menu_item->xfn ?? '',
				'description' => $menu_item->description ?? '',
			];
		}

		return self::_format_pagination_response(
			$items,
			$total_items,
			$pagination['per_page'],
			$pagination['page'],
			$pagination['offset']
		);
	}

	/**
	 * Get repeater query results.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Repeater query results.
	 */
	private static function _get_repeater_results( array $params ): array {
		$params['query_all_posts'] = true;
		return DynamicContentACFUtils::get_repeater_results( $params );
	}

	/**
	 * Index action arguments.
	 *
	 * Endpoint arguments as used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [
			'query_type'           => [
				'required'          => true,
				'type'              => 'string',
				'description'       => esc_html__( 'Type of query to perform (post_type, terms, users, current_page, repeater, or repeater_field_name)', 'et_builder_5' ),
				'validate_callback' => function ( $param ) {
					$valid_types = [ 'post_type', 'terms', 'users', 'menus', 'current_page', 'repeater' ];
					return in_array( $param, $valid_types, true ) || DynamicContentACFUtils::is_repeater_query( $param );
				},
			],
			'post_type'            => [
				'type'        => 'string',
				'description' => esc_html__( 'Post type to query (when query_type is post_type). Can be a single post type or comma-separated list. If empty, queries all post types.', 'et_builder_5' ),
			],
			'taxonomy'             => [
				'type'        => 'string',
				'description' => esc_html__( 'Taxonomy to query (when query_type is terms). Can be a single taxonomy or comma-separated list.', 'et_builder_5' ),
			],
			'term_id'              => [
				'oneOf'       => [
					[
						'type' => 'integer',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Term ID to filter by (when query_type is post_type). Can be a single ID or comma-separated list.', 'et_builder_5' ),
			],
			'role'                 => [
				'type'        => 'string',
				'description' => esc_html__( 'User role to filter by (when query_type is users). Can be a single role or comma-separated list.', 'et_builder_5' ),
			],
			'menu_id'              => [
				'oneOf'       => [
					[
						'type' => 'integer',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Menu ID to query (when query_type is menus).', 'et_builder_5' ),
			],
			'search'               => [
				'type'        => 'string',
				'description' => esc_html__( 'Search term', 'et_builder_5' ),
			],
			'per_page'             => [
				'oneOf'       => [
					[
						'type'    => 'integer',
						'minimum' => 1,
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Number of items per page', 'et_builder_5' ),
				'default'     => self::DEFAULT_PER_PAGE,
			],
			'posts_per_page'       => [
				'oneOf'       => [
					[
						'type'    => 'integer',
						'minimum' => 1,
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Number of posts per page (used when query_type is post_type)', 'et_builder_5' ),
				'default'     => self::DEFAULT_PER_PAGE,
			],
			'terms_per_page'       => [
				'oneOf'       => [
					[
						'type'    => 'integer',
						'minimum' => 1,
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Number of terms per page (used when query_type is terms)', 'et_builder_5' ),
				'default'     => self::DEFAULT_PER_PAGE,
			],
			'users_per_page'       => [
				'oneOf'       => [
					[
						'type'    => 'integer',
						'minimum' => 1,
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Number of users per page (used when query_type is users)', 'et_builder_5' ),
				'default'     => self::DEFAULT_PER_PAGE,
			],
			'page'                 => [
				'type'        => 'integer',
				'description' => esc_html__( 'Current page', 'et_builder_5' ),
				'default'     => 1,
			],
			'post_offset'          => [
				'oneOf'       => [
					[
						'type' => 'integer',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Offset for posts query (overrides page calculation)', 'et_builder_5' ),
				'default'     => 0,
			],
			'term_offset'          => [
				'oneOf'       => [
					[
						'type' => 'integer',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Offset for terms query (overrides page calculation)', 'et_builder_5' ),
				'default'     => 0,
			],
			'user_offset'          => [
				'oneOf'       => [
					[
						'type' => 'integer',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Offset for users query (overrides page calculation)', 'et_builder_5' ),
				'default'     => 0,
			],
			'menus_per_page'       => [
				'oneOf'       => [
					[
						'type'    => 'integer',
						'minimum' => 1,
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Number of menu items per page (used when query_type is menus)', 'et_builder_5' ),
				'default'     => self::DEFAULT_PER_PAGE,
			],
			'menu_offset'          => [
				'oneOf'       => [
					[
						'type' => 'integer',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Offset for menu items query (overrides page calculation)', 'et_builder_5' ),
				'default'     => 0,
			],
			'order_by'             => [
				'type'        => 'string',
				'description' => esc_html__( 'Field to order results by (directly passed to the WordPress query)', 'et_builder_5' ),
				'default'     => 'date',
			],
			'order'                => [
				'type'        => 'string',
				'description' => esc_html__( 'Order direction (ascending or descending)', 'et_builder_5' ),
				'default'     => 'descending',
				'enum'        => [ 'ascending', 'descending' ],
			],
			'exclude_current_post' => [
				'oneOf'       => [
					[
						'type' => 'boolean',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Whether to exclude the current post from results (used when query_type is post_type, except for attachments)', 'et_builder_5' ),
				'default'     => false,
			],
			'ignore_sticky_posts'  => [
				'oneOf'       => [
					[
						'type' => 'boolean',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Whether to ignore sticky posts in results order (used only when query_type is post_type and post_type is post)', 'et_builder_5' ),
				'default'     => false,
			],
			'current_post_id'      => [
				'type'        => 'integer',
				'description' => esc_html__( 'The ID of the current post (used for exclude_current_post)', 'et_builder_5' ),
				'default'     => 0,
			],
			'post_id'              => [
				'oneOf'       => [
					[
						'type' => 'integer',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Specific post ID to exclude when exclude_current_post is true. Can be a single ID or comma-separated list.', 'et_builder_5' ),
				'default'     => 0,
			],
			'repeater_name'        => [
				'type'        => 'string',
				'description' => esc_html__( 'Name, key, or label of the ACF repeater field to query (used when query_type is repeater)', 'et_builder_5' ),
			],
			'repeater_per_page'    => [
				'oneOf'       => [
					[
						'type'    => 'integer',
						'minimum' => 1,
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Number of repeater items per page (used when query_type is repeater)', 'et_builder_5' ),
				'default'     => self::DEFAULT_PER_PAGE,
			],
			'repeater_offset'      => [
				'description' => esc_html__( 'Offset for repeater query (overrides page calculation)', 'et_builder_5' ),
				'default'     => 0,
			],
			'post__in'             => [
				'oneOf'       => [
					[
						'type' => 'integer',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Specific post IDs to include in the query. Can be a single ID or comma-separated list.', 'et_builder_5' ),
			],
			'post__not_in'         => [
				'oneOf'       => [
					[
						'type' => 'integer',
					],
					[
						'type' => 'string',
					],
				],
				'description' => esc_html__( 'Specific post IDs to exclude from the query. Can be a single ID or comma-separated list.', 'et_builder_5' ),
			],
			'meta_query'           => [
				'type'        => 'array',
				'required'    => false,
				'description' => esc_html__( 'Meta query parameters for filtering posts by custom fields. Array of meta query clauses. Multiple clauses are combined with AND relation.', 'et_builder_5' ),
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'key'     => [
							'type'        => 'string',
							'description' => esc_html__( 'Meta key to query.', 'et_builder_5' ),
							'required'    => true,
						],
						'value'   => [
							'type'        => 'string',
							'description' => esc_html__( 'Meta value to compare against.', 'et_builder_5' ),
							'required'    => true,
						],
						'compare' => [
							'type'        => 'string',
							'description' => esc_html__( 'Comparison operator.', 'et_builder_5' ),
							'default'     => '=',
							'enum'        => [
								'=',
								'!=',
								'>',
								'>=',
								'<',
								'<=',
								'LIKE',
								'NOT LIKE',
								'IN',
								'NOT IN',
								'BETWEEN',
								'NOT BETWEEN',
								'EXISTS',
								'NOT EXISTS',
								'REGEXP',
								'NOT REGEXP',
								'RLIKE',
							],
						],
						'type'    => [
							'type'        => 'string',
							'description' => esc_html__( 'Meta value type.', 'et_builder_5' ),
							'default'     => 'CHAR',
							'enum'        => [
								'NUMERIC',
								'BINARY',
								'CHAR',
								'DATE',
								'DATETIME',
								'DECIMAL',
								'SIGNED',
								'TIME',
								'UNSIGNED',
							],
						],
					],
				],
			],
			'thumbnail_size'       => [
				'type'        => 'string',
				'description' => esc_html__( 'WordPress image size to use for thumbnails (e.g., thumbnail, medium, large, full).', 'et_builder_5' ),
				'default'     => 'large',
			],
		];
	}

	/**
	 * Index action permission.
	 *
	 * Endpoint permission callback as used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
