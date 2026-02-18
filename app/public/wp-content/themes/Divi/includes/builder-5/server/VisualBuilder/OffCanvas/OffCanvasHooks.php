<?php
/**
 * Off-Canvas Hooks for Divi 5 Visual Builder.
 *
 * @package ET\Builder\VisualBuilder\OffCanvas
 * @since ??
 */

namespace ET\Builder\VisualBuilder\OffCanvas;

use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET\Builder\Packages\Conversion\Utils\ConversionUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\CanvasUtils;
use ET\Builder\Packages\ModuleLibrary\CanvasPortal\CanvasPortalModule;
use ET\Builder\FrontEnd\Assets\StaticCSS;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\FrontEnd\Page;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\BlockParser\OrderIndexResetManager;
use ET\Builder\FrontEnd\BlockParser\SimpleBlockParser;
use ET\Builder\Framework\Utility\Conditions;
use ET_Core_PageResource;
use ET_Post_Stack;

/**
 * Off-Canvas Hooks class.
 *
 * Handles saving, loading, and rendering of off-canvas data including global canvases.
 *
 * @since ??
 */
class OffCanvasHooks {

	/**
	 * Post type name for global canvases.
	 *
	 * @since ??
	 */
	const GLOBAL_CANVAS_POST_TYPE = 'et_pb_canvas';



	/**
	 * Cache for off-canvas metadata (_divi_off_canvas_data).
	 * Prevents redundant database queries during the same request.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_off_canvas_metadata_cache = [];

	/**
	 * Cache for local canvases data.
	 * Prevents redundant database queries during the same request.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_local_canvases_cache = [];

	/**
	 * Cache for global canvases data.
	 * Prevents redundant database queries during the same request.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_global_canvases_cache = [];

	/**
	 * Cache for current post ID per request.
	 * Prevents redundant calls to _get_current_post_id().
	 * Note: This cache should be cleared when the post context changes (e.g., between header/post content/footer rendering).
	 *
	 * @since ??
	 * @var int|false|null
	 */
	private static $_current_post_id_cache = null;

	/**
	 * Cache for rendering context per request.
	 * Prevents redundant calls to _get_rendering_context().
	 * Note: This cache should be cleared when the rendering context changes (e.g., between header/post content/footer rendering).
	 *
	 * @since ??
	 * @var string|null
	 */
	private static $_rendering_context_cache = null;

	/**
	 * Cache for main post object per post ID.
	 * Prevents redundant get_post() calls during interaction detection.
	 *
	 * @since ??
	 * @var array<int, \WP_Post|null>
	 */
	private static $_main_post_cache = [];

	/**
	 * Initialize hooks.
	 *
	 * @since ??
	 */
	public static function init() {
		// Register global canvas post type.
		add_action( 'init', [ __CLASS__, 'register_global_canvas_post_type' ] );

		// Hook into the post save process to save off-canvas data.
		add_action( 'divi_visual_builder_rest_save_post', [ __CLASS__, 'save_off_canvas_data' ] );

		// Add REST endpoint to get off-canvas data.
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );

		// Reset orderIndex before HTML rendering starts to ensure consistent numbering.
		// This is necessary because CSS generation may have incremented orderIndex when
		// processing canvas content, so we need to reset it before HTML rendering.
		add_filter( 'the_content', [ __CLASS__, 'reset_order_index_before_rendering' ], 1 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'reset_order_index_before_rendering' ], 1 );

		// Reset orderIndex again right before do_blocks() runs, after canvas processing.
		// Canvas processing (process_canvas_content_above_main_content at priority 2) may parse
		// blocks for interaction detection, incrementing orderIndex. We need to reset again
		// right before actual HTML rendering via do_blocks() (which runs at priority 9).
		// Hook to both filters to ensure it runs for both Theme Builder layouts and regular post content.
		add_filter( 'the_content', [ __CLASS__, 'reset_order_index_before_do_blocks' ], 8 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'reset_order_index_before_do_blocks' ], 8 );

		// Process and prepend canvases that should be appended above main content.
		add_filter( 'the_content', [ __CLASS__, 'process_canvas_content_above_main_content' ], 2 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'process_canvas_content_above_main_content' ], 2 );

		// Hook into Divi's block processing pipeline to collect target IDs.
		add_filter( 'render_block_data', [ __CLASS__, 'detect_and_process_off_canvas_interactions' ], 5, 3 );

		// Process canvas content after all main content blocks are rendered (priority 998).
		// This ensures canvas content continues orderIndex sequence from main content.
		add_filter( 'the_content', [ __CLASS__, 'process_canvas_content_after_main_content' ], 998 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'process_canvas_content_after_main_content' ], 998 );

		// Hook into content rendering to inject pre-processed canvas content.
		add_filter( 'the_content', [ __CLASS__, 'inject_canvas_content_for_interactions' ], 999 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'inject_canvas_content_for_interactions' ], 999 );

		// Add canvas ID class to wrapper when rendering canvas content with z-index.
		add_filter( 'et_builder_layout_class', [ __CLASS__, 'add_canvas_id_to_wrapper_class' ] );
	}

	/**
	 * Register the global canvas post type.
	 *
	 * @since ??
	 */
	public static function register_global_canvas_post_type() {
		register_post_type(
			self::GLOBAL_CANVAS_POST_TYPE,
			[
				'label'               => __( 'Global Canvases', 'et_builder' ),
				'labels'              => [
					'name'               => __( 'Global Canvases', 'et_builder' ),
					'singular_name'      => __( 'Global Canvas', 'et_builder' ),
					'add_new'            => __( 'Add New', 'et_builder' ),
					'add_new_item'       => __( 'Add New Global Canvas', 'et_builder' ),
					'edit_item'          => __( 'Edit Global Canvas', 'et_builder' ),
					'new_item'           => __( 'New Global Canvas', 'et_builder' ),
					'view_item'          => __( 'View Global Canvas', 'et_builder' ),
					'search_items'       => __( 'Search Global Canvases', 'et_builder' ),
					'not_found'          => __( 'No global canvases found', 'et_builder' ),
					'not_found_in_trash' => __( 'No global canvases found in Trash', 'et_builder' ),
				],
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'can_export'          => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'supports'            => [ 'title', 'editor' ],
				'show_in_rest'        => true,
			]
		);
	}

	/**
	 * Save off-canvas data to post meta when page is saved.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_off_canvas_data( $post_id ) {
		// Security check: Verify user has permission to edit the parent post.
		// This ensures only users with edit privileges for the post can update canvases.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Get off-canvas data from the global variable set by SyncToServerController.
		// The caller is responsible for normalizing the data to array format.
		$canvas_data = $GLOBALS['divi_off_canvas_data'] ?? null;

		if ( ! $canvas_data || ! is_array( $canvas_data ) ) {
			return;
		}

		// Save canvas metadata (only activeCanvasId and mainCanvasName, not canvas data).
		$canvas_metadata = [
			'activeCanvasId' => $canvas_data['activeCanvasId'] ?? '',
			'mainCanvasName' => '', // Will be set if main canvas exists.
		];

		// Batch fetch all existing canvas posts to avoid N queries.
		// Fetch all canvas posts (both local to this post and global) in one query.
		$existing_canvas_posts_map = [];

		// Fetch local canvases for this post.
		$local_posts = CanvasUtils::get_local_canvas_posts( $post_id, [ 'post_status' => 'any' ] );

		// Fetch global canvases (no parent_post_id).
		$global_posts = CanvasUtils::get_canvas_posts( true, null, [ 'post_status' => 'any' ] );

		// Combine and build map of canvas_id => post.
		$all_existing_posts = array_merge( $local_posts, $global_posts );
		$append_to_main_map = [];
		$z_index_map        = [];
		$parent_post_id_map = [];
		if ( ! empty( $all_existing_posts ) ) {
			// Batch fetch all meta we need upfront in a single query instead of 4 queries.
			$post_ids           = array_map(
				function ( $post ) {
					return $post->ID;
				},
				$all_existing_posts
			);
			$all_meta           = DynamicAssetsUtils::_batch_get_post_meta(
				$post_ids,
				[ '_divi_canvas_id', '_divi_canvas_append_to_main', '_divi_canvas_z_index', '_divi_canvas_parent_post_id' ]
			);
			$canvas_id_map      = $all_meta['_divi_canvas_id'] ?? [];
			$append_to_main_map = $all_meta['_divi_canvas_append_to_main'] ?? [];
			$z_index_map        = $all_meta['_divi_canvas_z_index'] ?? [];
			$parent_post_id_map = $all_meta['_divi_canvas_parent_post_id'] ?? [];

			// Build map of canvas_id => post.
			foreach ( $all_existing_posts as $post ) {
				$canvas_id = $canvas_id_map[ $post->ID ] ?? '';
				if ( $canvas_id ) {
					$existing_canvas_posts_map[ $canvas_id ] = $post;
				}
			}
		}

		// Find and save main canvas name (but skip saving its content - it's in post_content).
		// First, get existing main canvas name from database to preserve it if main canvas is not in payload.
		// Use static cache to avoid duplicate queries if get_off_canvas_data_for_post was already called.
		if ( ! isset( self::$_off_canvas_metadata_cache[ $post_id ] ) ) {
			self::$_off_canvas_metadata_cache[ $post_id ] = get_post_meta( $post_id, '_divi_off_canvas_data', true );
		}
		$existing_canvas_metadata  = self::$_off_canvas_metadata_cache[ $post_id ];
		$existing_main_canvas_name = $existing_canvas_metadata['mainCanvasName'] ?? '';
		$main_canvas_name          = $existing_main_canvas_name; // Preserve existing name by default.

		if ( isset( $canvas_data['canvases'] ) && is_array( $canvas_data['canvases'] ) ) {
			foreach ( $canvas_data['canvases'] as $canvas_id => $canvas ) {
				$is_main_canvas = $canvas['isMain'] ?? false;
				$is_global      = $canvas['isGlobal'] ?? false;

				// Handle global canvases FIRST - save as posts in et_pb_canvas post type.
				// This must be checked before isMain check to ensure global canvases that are
				// set as main canvas are still saved as global canvas posts.
				// Only save if the canvas content actually changed.
				if ( $is_global ) {
					// Only process if canvas has content (was actually edited).
					if ( isset( $canvas['content'] ) && ! empty( $canvas['content'] ) ) {
						// Check for existing canvas using batch-fetched map.
						$existing_post = $existing_canvas_posts_map[ $canvas_id ] ?? null;

						if ( ! $existing_post ) {
							// New global canvas - save it.
							self::_save_global_canvas( $canvas_id, $canvas, $post_id );
						} else {
							// Existing canvas - check if content changed.
							$new_content            = self::_prepare_canvas_content( $canvas['content'] );
							$existing_content       = wp_unslash( $existing_post->post_content ?? '' );
							$normalized_new_content = wp_unslash( $new_content );

							if ( $normalized_new_content !== $existing_content ) {
								// Content changed - save and clear cache.
								// _save_global_canvas will handle conversion from local to global if needed.
								self::_save_global_canvas( $canvas_id, $canvas, $post_id );
							} else {
								// Content unchanged - only update metadata if it changed.
								// Use batch-fetched append_to_main value.
								$existing_append_to_main = $append_to_main_map[ $existing_post->ID ] ?? '';
								$new_append_to_main      = $canvas['appendToMainCanvas'] ?? null;

								// Normalize for comparison (null, empty string, and false are equivalent).
								$existing_append_to_main = ( '' === $existing_append_to_main || false === $existing_append_to_main ) ? null : $existing_append_to_main;
								$new_append_to_main      = ( '' === $new_append_to_main || false === $new_append_to_main ) ? null : $new_append_to_main;

								if ( $existing_append_to_main !== $new_append_to_main ) {
									update_post_meta( $existing_post->ID, '_divi_canvas_append_to_main', $new_append_to_main );
								}

								// Use batch-fetched z_index value.
								$existing_z_index = $z_index_map[ $existing_post->ID ] ?? '';
								$new_z_index      = $canvas['zIndex'] ?? null;

								// Normalize for comparison (null, empty string, and false are equivalent).
								$existing_z_index = ( '' === $existing_z_index || false === $existing_z_index ) ? null : $existing_z_index;
								$new_z_index      = ( '' === $new_z_index || false === $new_z_index ) ? null : $new_z_index;

								if ( $existing_z_index !== $new_z_index ) {
									update_post_meta( $existing_post->ID, '_divi_canvas_z_index', $new_z_index );
								}

								// Remove parent_post_id meta to convert local canvas to global (if converting).
								delete_post_meta( $existing_post->ID, '_divi_canvas_parent_post_id' );
							}
						}
					}

					// If this global canvas is also the main canvas, save its name.
					// Main canvas content is saved to post_content by the frontend.
					if ( $is_main_canvas ) {
						$main_canvas_name = $canvas['name'] ?? 'Main Canvas';
					}

					continue; // Skip adding to local metadata.
				}

				// Save main canvas name (but skip its content - it's in post_content).
				// This only applies to local canvases that are main.
				if ( $is_main_canvas ) {
					$main_canvas_name = $canvas['name'] ?? 'Main Canvas';
					continue;
				}

				// Save local canvas as a post in et_pb_canvas post type.
				// Only save if the canvas content actually changed.
				if ( isset( $canvas['content'] ) && ! empty( $canvas['content'] ) ) {
					// Check for existing local canvas using batch-fetched map, but also verify it's local (has parent_post_id).
					$existing_post = $existing_canvas_posts_map[ $canvas_id ] ?? null;
					// Verify it's actually a local canvas (has parent_post_id matching this post_id).
					// Use batch-fetched parent_post_id value.
					if ( $existing_post ) {
						$parent_post_id = $parent_post_id_map[ $existing_post->ID ] ?? '';
						if ( (int) $parent_post_id !== (int) $post_id ) {
							// This canvas exists but is not local to this post (might be global or local to another post).
							$existing_post = null;
						}
					}

					if ( ! $existing_post ) {
						// New canvas - save it.
						self::_save_local_canvas( $canvas_id, $canvas, $post_id );
					} else {
						// Existing canvas - check if content changed.
						$new_content            = self::_prepare_canvas_content( $canvas['content'] );
						$existing_content       = wp_unslash( $existing_post->post_content ?? '' );
						$normalized_new_content = wp_unslash( $new_content );

						if ( $normalized_new_content !== $existing_content ) {
							// Content changed - save and clear cache.
							self::_save_local_canvas( $canvas_id, $canvas, $post_id );
						} else {
							// Content unchanged - only update metadata if it changed.
							// Use batch-fetched append_to_main value.
							$existing_append_to_main = $append_to_main_map[ $existing_post->ID ] ?? '';
							$new_append_to_main      = $canvas['appendToMainCanvas'] ?? null;

							// Normalize for comparison (null, empty string, and false are equivalent).
							$existing_append_to_main = ( '' === $existing_append_to_main || false === $existing_append_to_main ) ? null : $existing_append_to_main;
							$new_append_to_main      = ( '' === $new_append_to_main || false === $new_append_to_main ) ? null : $new_append_to_main;

							if ( $existing_append_to_main !== $new_append_to_main ) {
								update_post_meta( $existing_post->ID, '_divi_canvas_append_to_main', $new_append_to_main );
							}

							// Use batch-fetched z_index value.
							$existing_z_index = $z_index_map[ $existing_post->ID ] ?? '';
							$new_z_index      = $canvas['zIndex'] ?? null;

							// Normalize for comparison (null, empty string, and false are equivalent).
							$existing_z_index = ( '' === $existing_z_index || false === $existing_z_index ) ? null : $existing_z_index;
							$new_z_index      = ( '' === $new_z_index || false === $new_z_index ) ? null : $new_z_index;

							if ( $existing_z_index !== $new_z_index ) {
								update_post_meta( $existing_post->ID, '_divi_canvas_z_index', $new_z_index );
							}

							// Update other metadata if changed.
							$existing_name = $existing_post->post_title;
							$new_name      = $canvas['name'] ?? 'Local Canvas';
							if ( $existing_name !== $new_name ) {
								wp_update_post(
									[
										'ID'         => $existing_post->ID,
										'post_title' => $new_name,
									]
								);
							}
						}
					}
				}
			}
		}

		// Save main canvas name to metadata.
		$canvas_metadata['mainCanvasName'] = $main_canvas_name;

		// Delete only the specific canvases that were explicitly marked as deleted (both global and local).
		// This is safer than deleting all canvases not in the payload, which could wipe
		// all canvases in case of an error.
		$deleted_canvas_ids = $canvas_data['deletedCanvasIds'] ?? [];

		foreach ( $deleted_canvas_ids as $deleted_canvas_id ) {
			if ( ! is_string( $deleted_canvas_id ) || empty( $deleted_canvas_id ) ) {
				continue;
			}

			// Find the canvas post by canvas ID (works for both global and local canvases).
			// First try to find as local canvas (has parent_post_id).
			$posts_to_delete = get_posts(
				[
					'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
					'posts_per_page' => 1,
					'post_status'    => 'any',
					'meta_query'     => [
						[
							'key'     => '_divi_canvas_id',
							'value'   => $deleted_canvas_id,
							'compare' => '=',
						],
						[
							'key'     => '_divi_canvas_parent_post_id',
							'value'   => $post_id,
							'compare' => '=',
						],
					],
				]
			);

			// If not found as local, try as global canvas (no parent_post_id).
			if ( empty( $posts_to_delete ) ) {
				$posts_to_delete = get_posts(
					[
						'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
						'posts_per_page' => 1,
						'post_status'    => 'any',
						'meta_query'     => [
							[
								'key'     => '_divi_canvas_id',
								'value'   => $deleted_canvas_id,
								'compare' => '=',
							],
							[
								'key'     => '_divi_canvas_parent_post_id',
								'compare' => 'NOT EXISTS',
							],
						],
					]
				);
			}

			if ( empty( $posts_to_delete ) ) {
				continue;
			}

			$post_to_delete = $posts_to_delete[0];
			wp_delete_post( $post_to_delete->ID, true );
		}

		// Save metadata (only activeCanvasId and mainCanvasName).
		if ( ! empty( $canvas_metadata['activeCanvasId'] ) || ! empty( $canvas_metadata['mainCanvasName'] ) ) {
			update_post_meta( $post_id, '_divi_off_canvas_data', $canvas_metadata );
			// Update cache with new value to keep it in sync.
			self::$_off_canvas_metadata_cache[ $post_id ] = $canvas_metadata;
		} else {
			// Clean up metadata if empty.
			delete_post_meta( $post_id, '_divi_off_canvas_data' );
			// Clear cache when metadata is deleted.
			unset( self::$_off_canvas_metadata_cache[ $post_id ] );
		}

		// Clean up global variable.
		unset( $GLOBALS['divi_off_canvas_data'] );
	}

	/**
	 * Register REST API routes for off-canvas data.
	 *
	 * @since ??
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'divi/v1',
			'/off-canvas/(?P<post_id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_off_canvas_data' ],
				'permission_callback' => [ __CLASS__, 'get_off_canvas_data_permission' ],
				'args'                => [
					'post_id' => [
						'required'    => true,
						'type'        => 'integer',
						'description' => 'Post ID to get off-canvas data for.',
					],
				],
			]
		);

		// REST endpoint for deleting global canvas.
		register_rest_route(
			'divi/v1',
			'/global-canvas/(?P<canvas_id>[a-zA-Z0-9-]+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ __CLASS__, 'delete_global_canvas' ],
				'permission_callback' => [ __CLASS__, 'global_canvas_permission' ],
				'args'                => [
					'canvas_id' => [
						'required'    => true,
						'type'        => 'string',
						'description' => 'Canvas ID to delete.',
					],
				],
			]
		);
	}

	/**
	 * Prepare canvas content for storage (serialize if needed).
	 * Note: Security sanitization (AttributeSecurity, DynamicContent) is handled automatically
	 * by the Security class via the update_post_metadata filter for post meta saves,
	 * and via wp_insert_post_data filter for global canvas post_content saves.
	 *
	 * @since ??
	 *
	 * @param mixed $canvas_content Canvas content (string or array).
	 *
	 * @return string Prepared content string.
	 */
	private static function _prepare_canvas_content( $canvas_content ) {
		if ( is_string( $canvas_content ) && strpos( $canvas_content, '<!-- wp:' ) === 0 ) {
			// Content is already serialized block format - sanitize like post_content.
			return SavingUtility::prepare_content_for_db( $canvas_content );
		}

		// Content is flat module objects - serialize like frontend does for post_content.
		$blocks = self::_convert_module_data_to_blocks( $canvas_content );

		// Sanitize blocks using the same method as post_content.
		$serialized_content = SavingUtility::serialize_sanitize_blocks( $blocks );

		// Wrap with divi/placeholder like main content (post_content).
		return "<!-- wp:divi/placeholder -->\n" . $serialized_content . "\n<!-- /wp:divi/placeholder -->";
	}

	/**
	 * Save a local canvas as a post in the et_pb_canvas post type.
	 * Local canvases are linked to their parent post via _divi_canvas_parent_post_id meta.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param array  $canvas    Canvas data.
	 * @param int    $post_id   Parent post ID.
	 */
	private static function _save_local_canvas( $canvas_id, $canvas, $post_id ) {
		// Security check: Verify user has permission to edit the parent post.
		// This is a defense-in-depth measure (main check is in save_off_canvas_data).
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Clear local canvases cache for this post since canvas data has changed.
		// Clear both parsed and unparsed cache entries.
		unset( self::$_local_canvases_cache[ $post_id . '_parsed' ] );
		unset( self::$_local_canvases_cache[ $post_id . '_unparsed' ] );
		// Check if local canvas post already exists.
		$existing_posts = get_posts(
			[
				'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => 1,
				'meta_query'     => [
					[
						'key'   => '_divi_canvas_id',
						'value' => $canvas_id,
					],
					[
						'key'   => '_divi_canvas_parent_post_id',
						'value' => $post_id,
					],
				],
			]
		);

		$post_data = [
			'post_title'  => $canvas['name'] ?? 'Local Canvas',
			'post_status' => 'publish',
			'post_type'   => self::GLOBAL_CANVAS_POST_TYPE,
			'meta_input'  => [
				'_divi_canvas_id'             => $canvas_id,
				'_divi_canvas_parent_post_id' => $post_id,
				'_divi_canvas_created_at'     => $canvas['createdAt'] ?? current_time( 'mysql' ),
				'_divi_canvas_append_to_main' => $canvas['appendToMainCanvas'] ?? null,
				'_divi_canvas_z_index'        => $canvas['zIndex'] ?? null,
			],
		];

		// Prepare content.
		if ( isset( $canvas['content'] ) && ! empty( $canvas['content'] ) ) {
			$post_data['post_content'] = wp_slash( self::_prepare_canvas_content( $canvas['content'] ) );
		}

		if ( ! empty( $existing_posts ) ) {
			// Update existing post.
			$post_data['ID'] = $existing_posts[0]->ID;
			wp_update_post( $post_data );
			// Update meta separately since wp_update_post doesn't always handle meta_input reliably.
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_append_to_main', $canvas['appendToMainCanvas'] ?? null );
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_z_index', $canvas['zIndex'] ?? null );
		} else {
			// Create new post.
			wp_insert_post( $post_data );
		}

		// Clear Dynamic Assets cache for the parent post when a local canvas content changes.
		ET_Core_PageResource::remove_static_resources( $post_id, 'all', true, 'dynamic', true );
		// Clear cached canvas IDs for this post.
		DynamicAssetsUtils::clear_canvas_ids_cache( $post_id );
	}

	/**
	 * Save a global canvas as a post in the et_pb_canvas post type.
	 * This method handles both new global canvases and converting local canvases to global.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param array  $canvas    Canvas data.
	 * @param int    $post_id   Parent post ID.
	 */
	private static function _save_global_canvas( $canvas_id, $canvas, $post_id ) {
		// Security check: Verify user has permission to edit the parent post.
		// This is a defense-in-depth measure (main check is in save_off_canvas_data).
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Clear global canvases cache since canvas data has changed.
		// Clear both parsed and unparsed cache entries.
		unset( self::$_global_canvases_cache['parsed'] );
		unset( self::$_global_canvases_cache['unparsed'] );
		// Find existing canvas post by canvas ID (could be local or global).
		$existing_posts = get_posts(
			[
				'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => [
					[
						'key'   => '_divi_canvas_id',
						'value' => $canvas_id,
					],
				],
			]
		);

		$post_data = [
			'post_title'  => $canvas['name'] ?? 'Global Canvas',
			'post_status' => 'publish',
			'post_type'   => self::GLOBAL_CANVAS_POST_TYPE,
			'meta_input'  => [
				'_divi_canvas_id'             => $canvas_id,
				'_divi_canvas_created_at'     => $canvas['createdAt'] ?? current_time( 'mysql' ),
				'_divi_canvas_append_to_main' => $canvas['appendToMainCanvas'] ?? null,
				'_divi_canvas_z_index'        => $canvas['zIndex'] ?? null,
			],
		];

		// Prepare content.
		if ( isset( $canvas['content'] ) && ! empty( $canvas['content'] ) ) {
			$post_data['post_content'] = wp_slash( self::_prepare_canvas_content( $canvas['content'] ) );
		}

		if ( ! empty( $existing_posts ) ) {
			// Update existing canvas post (could be converting from local to global).
			$post_data['ID'] = $existing_posts[0]->ID;
			wp_update_post( $post_data );
			// Update meta separately since wp_update_post doesn't always handle meta_input reliably.
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_append_to_main', $canvas['appendToMainCanvas'] ?? null );
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_z_index', $canvas['zIndex'] ?? null );
			// Remove parent_post_id meta to convert local canvas to global.
			delete_post_meta( $existing_posts[0]->ID, '_divi_canvas_parent_post_id' );
		} else {
			// Create new global canvas post.
			wp_insert_post( $post_data );
		}

		// Clear Dynamic Assets cache for all posts when a global canvas content changes.
		// Global canvases can be appended to any post, so we need to clear cache site-wide.
		// Preserve VB CSS files to prevent visual builder from losing its styles.
		ET_Core_PageResource::remove_static_resources( 'all', 'all', true, 'dynamic', true );
		// Clear cached canvas IDs for all posts.
		DynamicAssetsUtils::clear_canvas_ids_cache( 'all' );
	}

	/**
	 * Get off-canvas data for a post (public method for SettingsData system).
	 *
	 * NOTE: This function parses canvas content into module data format for Visual Builder.
	 * Parsing increments orderIndex, so this should ONLY be called in Visual Builder context
	 * (REST API), never during frontend rendering.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Off-canvas data including canvases, activeCanvasId, and mainCanvasName.
	 */
	public static function get_off_canvas_data_for_post( $post_id ) {
		// Get canvas metadata (only activeCanvasId and mainCanvasName now).
		// Use static cache to avoid duplicate queries if save_off_canvas_data was already called.
		if ( ! isset( self::$_off_canvas_metadata_cache[ $post_id ] ) ) {
			self::$_off_canvas_metadata_cache[ $post_id ] = get_post_meta( $post_id, '_divi_off_canvas_data', true );
		}
		$canvas_metadata = self::$_off_canvas_metadata_cache[ $post_id ];

		// Get cached canvas data for metadata (avoids DB queries).
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];

		// Collect post IDs for batch fetching.
		$canvas_post_ids = [];
		foreach ( $all_canvas_metadata as $canvas_meta ) {
			if ( ! empty( $canvas_meta['postId'] ) ) {
				$canvas_post_ids[] = $canvas_meta['postId'];
			}
		}

		// Reuse post objects from get_all_canvas_data_for_post if available (avoids duplicate get_posts query).
		$canvas_posts_map = [];
		$created_at_map   = [];
		if ( ! empty( $canvas_post_ids ) ) {
			// Check if post objects are cached from get_all_canvas_data_for_post.
			$cached_posts = DynamicAssetsUtils::get_cached_canvas_posts( $post_id );
			if ( ! empty( $cached_posts ) ) {
				// Use cached post objects - filter to only include the ones we need.
				foreach ( $canvas_post_ids as $canvas_post_id ) {
					if ( isset( $cached_posts[ $canvas_post_id ] ) ) {
						$canvas_posts_map[ $canvas_post_id ] = $cached_posts[ $canvas_post_id ];
					}
				}
			}

			// If we don't have all posts cached, fetch missing ones.
			$missing_post_ids = array_diff( $canvas_post_ids, array_keys( $canvas_posts_map ) );
			if ( ! empty( $missing_post_ids ) ) {
				$canvas_posts = get_posts(
					[
						'post__in'       => array_unique( $missing_post_ids ),
						'posts_per_page' => -1,
						'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
					]
				);
				foreach ( $canvas_posts as $canvas_post ) {
					$canvas_posts_map[ $canvas_post->ID ] = $canvas_post;
				}
			}

			// Build created_at_map from cached metadata first (if available).
			foreach ( $all_canvas_metadata as $canvas_meta ) {
				$post_id_for_meta = $canvas_meta['postId'] ?? null;
				// Check if createdAt exists in cached metadata (even if empty string).
				if ( $post_id_for_meta && isset( $canvas_meta['createdAt'] ) ) {
					$created_at_map[ $post_id_for_meta ] = $canvas_meta['createdAt'];
				}
			}

			// Only fetch createdAt from database if not in cached metadata.
			$missing_created_at_ids = array_diff( $canvas_post_ids, array_keys( $created_at_map ) );
			if ( ! empty( $missing_created_at_ids ) ) {
				// Single key mode returns [ post_id => meta_value ] directly.
				$fetched_created_at = DynamicAssetsUtils::_batch_get_post_meta( $missing_created_at_ids, '_divi_canvas_created_at' );
				$created_at_map     = array_merge( $created_at_map, $fetched_created_at );
			}
		}

		// Parse content for Visual Builder (this increments orderIndex, so only call in VB context).
		$canvases = [];
		foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
			$canvas_content = $canvas_meta['content'] ?? '';
			$module_data    = null;

			// Parse content into module data format for Visual Builder.
			if ( $canvas_content ) {
				try {
					$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );
					$module_data       = ConversionUtils::parseSerializedPostIntoFlatModuleObject( $unwrapped_content );
				} catch ( Exception $e ) {
					$module_data = null;
				}
			}

			// Get canvas post from batch-fetched map.
			$canvas_post       = $canvas_posts_map[ $canvas_meta['postId'] ] ?? null;
			$canvas_created_at = $created_at_map[ $canvas_meta['postId'] ] ?? null;
			$canvas_created_at = $canvas_created_at ? $canvas_created_at : ( $canvas_post ? $canvas_post->post_date : '' );

			$canvases[ $canvas_id ] = [
				'id'                 => $canvas_id,
				'name'               => $canvas_post ? $canvas_post->post_title : '',
				'isMain'             => false,
				'isGlobal'           => $canvas_meta['isGlobal'] ?? false,
				'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
				'zIndex'             => $canvas_meta['zIndex'] ?? null,
				'content'            => $module_data,
				'createdAt'          => $canvas_created_at,
			];
		}

		return [
			'canvases'       => $canvases,
			'activeCanvasId' => $canvas_metadata['activeCanvasId'] ?? '',
			'mainCanvasName' => $canvas_metadata['mainCanvasName'] ?? '', // Main canvas name (content is in post_content).
		];
	}

	/**
	 * Get off-canvas data for a post.
	 *
	 * @since ??
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_off_canvas_data( \WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );

		return rest_ensure_response(
			self::get_off_canvas_data_for_post( $post_id )
		);
	}

	/**
	 * Check if any canvases exist (local or global).
	 * Optimized to use cached canvas data when available to avoid queries.
	 *
	 * @since ??
	 *
	 * @param int|null $post_id Optional. Post ID to check for local canvases. If null, only checks global canvases.
	 *
	 * @return bool True if any canvases exist, false otherwise.
	 */
	private static function _has_any_canvases( $post_id = null ) {
		// If post_id is provided, try to use cached canvas data first (avoids queries).
		if ( $post_id ) {
			$cached_data = get_post_meta( $post_id, '_divi_dynamic_assets_canvases_used', true );
			if ( is_array( $cached_data ) && isset( $cached_data['all_canvas_metadata'] ) ) {
				// Cache exists - check if it has any canvases.
				$all_canvas_metadata = $cached_data['all_canvas_metadata'] ?? [];
				if ( ! empty( $all_canvas_metadata ) ) {
					return true;
				}
			}
		}

		// Cache miss or no post_id - fall back to lightweight queries.
		// Check global canvases first (most common case).
		// Use 'fields' => 'ids' and 'posts_per_page' => 1 for lightweight check.
		$global_posts = CanvasUtils::get_canvas_posts(
			true,
			null,
			[
				'posts_per_page' => 1,
				'fields'         => 'ids', // Only get IDs for performance.
			]
		);

		if ( ! empty( $global_posts ) ) {
			return true;
		}

		// Check local canvases if post_id provided.
		if ( $post_id ) {
			$local_posts = CanvasUtils::get_local_canvas_posts(
				$post_id,
				[
					'posts_per_page' => 1,
					'fields'         => 'ids', // Only get IDs for performance.
				]
			);

			if ( ! empty( $local_posts ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Permission callback for getting off-canvas data.
	 *
	 * @since ??
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	public static function get_off_canvas_data_permission( \WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Permission callback for global canvas operations.
	 *
	 * @since ??
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	public static function global_canvas_permission( \WP_REST_Request $request ) {
		// Verify request is valid (required by WordPress REST API callback signature).
		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}

		// User must be able to edit posts to manage global canvases.
		return current_user_can( 'edit_posts' );
	}


	/**
	 * Delete a global canvas.
	 *
	 * @since ??
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_global_canvas( \WP_REST_Request $request ) {
		$canvas_id = $request->get_param( 'canvas_id' );

		if ( ! $canvas_id ) {
			return new \WP_Error( 'missing_canvas_id', __( 'Canvas ID is required.', 'et_builder' ), [ 'status' => 400 ] );
		}

		// Find the global canvas post.
		$existing_posts = get_posts(
			[
				'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any', // Include all statuses to find the canvas even if status changed.
				'meta_query'     => [
					[
						'key'     => '_divi_canvas_id',
						'value'   => $canvas_id,
						'compare' => '=',
					],
					[
						'key'     => '_divi_canvas_parent_post_id',
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		if ( empty( $existing_posts ) ) {
			return new \WP_Error(
				'canvas_not_found',
				__( 'Global canvas not found.', 'et_builder' ),
				[
					'status'    => 404,
					'canvas_id' => $canvas_id,
				]
			);
		}

		$post_id = $existing_posts[0]->ID;

		// Delete the post permanently.
		$deleted = wp_delete_post( $post_id, true );

		if ( ! $deleted ) {
			return new \WP_Error(
				'delete_failed',
				__( 'Failed to delete global canvas.', 'et_builder' ),
				[
					'status'    => 500,
					'canvas_id' => $canvas_id,
				]
			);
		}

		return rest_ensure_response(
			[
				'success'   => true,
				'canvas_id' => $canvas_id,
			]
		);
	}


	/**
	 * Detect and process off-canvas interactions during block data processing.
	 * This runs early in Divi's block pipeline to pre-process needed canvas content.
	 *
	 * IMPORTANT: This method filters out same-canvas interactions at detection time.
	 * Only interactions targeting elements on a DIFFERENT canvas will:
	 * - Be stored in $GLOBALS['divi_off_canvas_target_ids']
	 * - Trigger canvas appending via _process_off_canvas_content_for_targets()
	 * - Potentially affect OrderIndexResetManager logic
	 *
	 * Same-canvas interactions (where both source and target are on the main canvas)
	 * are filtered out immediately and never enter the off-canvas processing pipeline.
	 *
	 * @since ??
	 *
	 * @param array         $parsed_block The block being rendered.
	 * @param array         $source_block An un-modified copy of $parsed_block.
	 * @param null|WP_Block $parent_block If this is a nested block, a reference to the parent block.
	 *
	 * @return array The unmodified parsed block.
	 */
	public static function detect_and_process_off_canvas_interactions( $parsed_block, $source_block, $parent_block ) {
		// Verify parameters are valid (required by WordPress block filter callback signature).
		if ( ! is_array( $parsed_block ) || ! is_array( $source_block ) ) {
			return $parsed_block;
		}

		// Verify parent_block is valid if provided (required by WordPress block filter callback signature).
		if ( null !== $parent_block && ! $parent_block instanceof \WP_Block ) {
			return $parsed_block;
		}

		// Only process Divi blocks.
		if ( ! isset( $parsed_block['blockName'] ) || strpos( $parsed_block['blockName'], 'divi/' ) !== 0 ) {
			return $parsed_block;
		}

		// Only run on frontend (not in admin or visual builder).
		if ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) {
			return $parsed_block;
		}

		// Check for interactions in block attributes.
		$block_attrs       = $parsed_block['attrs'] ?? [];
		$interactions_data = $block_attrs['module']['decoration']['interactions'] ?? null;

		if ( ! $interactions_data ) {
			return $parsed_block;
		}

		$interactions = $interactions_data['desktop']['value']['interactions'] ?? [];

		if ( ! is_array( $interactions ) || empty( $interactions ) ) {
			return $parsed_block;
		}

		// Extract target IDs from interactions.
		$target_ids = [];
		foreach ( $interactions as $interaction ) {
			$target_class = $interaction['target']['targetClass'] ?? '';

			if ( $target_class && preg_match( '/et-interaction-target-([a-zA-Z0-9]+)/', $target_class, $matches ) ) {
				$target_ids[] = $matches[1];
			}
		}

		if ( empty( $target_ids ) ) {
			return $parsed_block;
		}

		// Get the current post ID to track target IDs per template/post.
		// This ensures canvases are only appended to the template/post that targets them.
		$post_id = self::_get_current_post_id();
		if ( ! $post_id ) {
			return $parsed_block;
		}

		// Filter out targets that are on the main canvas BEFORE storing them.
		// This prevents same-canvas interactions from triggering any off-canvas processing,
		// including OrderIndexResetManager logic. Only interactions targeting elements on
		// a different canvas should cause canvas appending and affect order index.
		// Cache post object to avoid redundant get_post() calls for multiple blocks with interactions.
		// This is critical for performance when many blocks have interactions on large pages.
		if ( ! isset( self::$_main_post_cache[ $post_id ] ) ) {
			self::$_main_post_cache[ $post_id ] = get_post( $post_id );
		}
		$main_post           = self::$_main_post_cache[ $post_id ];
		$main_canvas_content = $main_post ? $main_post->post_content : '';

		if ( ! empty( $main_canvas_content ) ) {
			$target_ids = array_filter(
				$target_ids,
				function ( $target_id ) use ( $main_canvas_content ) {
					// Only keep target IDs that are NOT on the main canvas.
					return ! self::canvas_block_content_contains_target( $main_canvas_content, $target_id );
				}
			);
		}

		// Only store and process target IDs if there are actually off-canvas targets.
		// This ensures same-canvas interactions never affect OrderIndexResetManager or trigger canvas processing.
		if ( empty( $target_ids ) ) {
			return $parsed_block;
		}

		// Store target IDs per post_id for later processing after all main content blocks are rendered.
		// This ensures canvas content continues the orderIndex sequence from main content,
		// so CSS class names match between CSS generation and HTML output.
		// Keying by post_id ensures canvases are only appended to the template/post that targets them.
		$existing_target_ids = self::_get_per_post_global_value( 'divi_off_canvas_target_ids', $post_id, [] );
		$merged_target_ids   = array_unique( array_merge( $existing_target_ids, $target_ids ) );
		self::_set_per_post_global_value( 'divi_off_canvas_target_ids', $post_id, $merged_target_ids );

		return $parsed_block;
	}

	/**
	 * Reset orderIndex before HTML rendering starts.
	 *
	 * Uses the unified OrderIndexResetManager to handle reset logic, ensuring
	 * consistent orderIndex numbering across Theme Builder templates, canvases,
	 * and Canvas Portal modules.
	 *
	 * @since ??
	 *
	 * @param string $content The content being rendered.
	 *
	 * @return string The unmodified content.
	 */
	public static function reset_order_index_before_rendering( $content ) {
		// Initialize global canvas tracking at the start of a new page render.
		OrderIndexResetManager::init_page_render();

		// Clear caches when starting a new rendering context (header/post content/footer).
		// This ensures cached post ID and rendering context are refreshed for each area.
		self::reset_caches();

		// Use unified reset manager to handle reset logic.
		OrderIndexResetManager::maybe_reset( OrderIndexResetManager::PHASE_BEFORE_RENDERING );

		return $content;
	}

	/**
	 * Reset orderIndex right before do_blocks() runs, after canvas processing.
	 *
	 * Canvas processing (process_canvas_content_above_main_content) may parse blocks
	 * for interaction detection, which increments orderIndex. This filter runs at
	 * priority 8, right before et_builder_render_layout_do_blocks (priority 9) calls
	 * do_blocks(), ensuring orderIndex starts from 0 for actual HTML rendering.
	 *
	 * Uses the unified OrderIndexResetManager to handle reset logic.
	 *
	 * @since ??
	 *
	 * @param string $content The content being rendered.
	 *
	 * @return string The unmodified content.
	 */
	public static function reset_order_index_before_do_blocks( $content ) {
		// Use unified reset manager to handle reset logic.
		OrderIndexResetManager::maybe_reset( OrderIndexResetManager::PHASE_BEFORE_DO_BLOCKS );

		return $content;
	}

	/**
	 * Process canvas content that should be appended above main content.
	 * This renders canvases with appendToMainCanvas set to 'above'.
	 *
	 * @since ??
	 *
	 * @param string $content The main post content (passed through filter).
	 *
	 * @return string The unmodified content (rendering happens as side effect).
	 */
	public static function process_canvas_content_above_main_content( $content ) {
		// Only run on frontend (not in admin or visual builder).
		if ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) {
			return $content;
		}

		// Prevent infinite recursion when rendering canvas content.
		if ( ! empty( $GLOBALS['divi_off_canvas_rendering'] ) ) {
			return $content;
		}

		$current_post_id = self::_get_current_post_id();
		if ( ! $current_post_id ) {
			return $content;
		}

		// Skip canvas processing during the_content when in Theme Builder layout context.
		// This prevents post ID confusion when Post Content module renders content within TB layouts,
		// but allows canvas processing for regular posts rendered via the_content.
		if ( doing_filter( 'the_content' ) && self::_get_theme_builder_layout_post_id( $current_post_id ) !== null ) {
			return $content;
		}

		// Skip appending canvases when rendering inner content (e.g., Canvas Portal modules).
		// Append/prepend should only apply to the main canvas, not to Canvas Portal content.
		if ( BlockParserStore::is_rendering_inner_content() ) {
			return $content;
		}

		// In Theme Builder layouts, always use the layout post ID for canvas processing.
		$layout_post_id = self::_get_theme_builder_layout_post_id( $current_post_id );
		$canvas_post_id = null !== $layout_post_id ? $layout_post_id : $current_post_id;

		// Process canvases that should be appended above main content.
		// The flag is set inside _process_appended_canvases before rendering
		// so it's available during canvas rendering when PHASE_NEW_STORE_INSTANCE might be triggered.
		self::_process_appended_canvases( $canvas_post_id, 'above' );

		// Return content unchanged (rendering happens as side effect).
		return $content;
	}

	/**
	 * Process canvas content after all main content blocks are rendered.
	 * This ensures canvas content continues the orderIndex sequence from main content,
	 * so CSS class names match between CSS generation and HTML output.
	 *
	 * @since ??
	 *
	 * @param string $content The main post content (passed through filter).
	 *
	 * @return string The unmodified content (rendering happens as side effect).
	 */
	public static function process_canvas_content_after_main_content( $content ) {
		// Only run on frontend (not in admin or visual builder).
		if ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) {
			return $content;
		}

		// Prevent infinite recursion when rendering canvas content.
		if ( ! empty( $GLOBALS['divi_off_canvas_rendering'] ) ) {
			return $content;
		}

		$current_post_id = self::_get_current_post_id();
		if ( ! $current_post_id ) {
			return $content;
		}

		// Skip canvas processing during the_content when in Theme Builder layout context.
		// This prevents post ID confusion when Post Content module renders content within TB layouts,
		// but allows canvas processing for regular posts rendered via the_content.
		if ( doing_filter( 'the_content' ) && self::_get_theme_builder_layout_post_id( $current_post_id ) !== null ) {
			return $content;
		}

		// Skip appending canvases when rendering inner content (e.g., Canvas Portal modules).
		// Append/prepend should only apply to the main canvas, not to Canvas Portal content.
		if ( BlockParserStore::is_rendering_inner_content() ) {
			return $content;
		}

		// In Theme Builder layouts, always use the layout post ID for canvas processing.
		// This ensures interactions defined in layouts use the correct canvas storage location.
		$layout_post_id = self::_get_theme_builder_layout_post_id( $current_post_id );
		$canvas_post_id = null !== $layout_post_id ? $layout_post_id : $current_post_id;

		// Process target IDs for the canvas post (layout post in TB context).
		// This ensures canvases are processed using the correct post ID where they're stored.
		$target_ids = self::_get_per_post_global_value( 'divi_off_canvas_target_ids', $canvas_post_id, [] );

		// Process off-canvas content for interaction targets.
		if ( ! empty( $target_ids ) ) {
			// Process off-canvas content for these targets.
			// This runs after all main content blocks have been rendered (orderIndex assigned),
			// so canvas content continues the orderIndex sequence sequentially.
			self::_process_off_canvas_content_for_targets( $target_ids, $canvas_post_id );

			// Clean up target IDs for the canvas post.
			unset( $GLOBALS['divi_off_canvas_target_ids'][ $canvas_post_id ] );
		}

		// Process canvases that should be appended below main content.
		// Use the same canvas post ID for consistency.
		if ( $canvas_post_id ) {
			self::_process_appended_canvases( $canvas_post_id, 'below' );
		}

		// Return content unchanged (rendering happens as side effect).
		return $content;
	}

	/**
	 * Get the Theme Builder layout post ID if we're in a layout context.
	 * This is used to ensure canvas processing uses the correct post ID for Theme Builder layouts.
	 *
	 * @since ??
	 *
	 * @param int $current_post_id The current post ID.
	 *
	 * @return int|null Layout post ID if in TB context, null otherwise.
	 */
	private static function _get_theme_builder_layout_post_id( $current_post_id ) {
		// Check if we're in a Theme Builder layout context.
		if ( class_exists( '\ET_Post_Stack' ) ) {
			$stacked_post = \ET_Post_Stack::get();
			if ( $stacked_post && isset( $stacked_post->ID ) && $stacked_post->ID !== $current_post_id ) {
				// We're in a stacked post context (likely TB layout), use the stacked post ID.
				return $stacked_post->ID;
			}
		}

		// Check for active Theme Builder layout.
		if ( class_exists( '\ET_Theme_Builder_Layout' ) && method_exists( '\ET_Theme_Builder_Layout', 'get_theme_builder_layout_id' ) ) {
			$layout_id = \ET_Theme_Builder_Layout::get_theme_builder_layout_id();
			if ( $layout_id > 0 && $layout_id !== $current_post_id ) {
				return $layout_id;
			}
		}

		return null;
	}

	/**
	 * Get the current post ID, handling both regular posts and Theme Builder layouts.
	 * Cached per request to avoid redundant calls, but should be cleared when post context changes.
	 *
	 * @since ??
	 *
	 * @return int|false Post ID or false if not available.
	 */
	private static function _get_current_post_id() {
		// Return cached value if available.
		if ( null !== self::$_current_post_id_cache ) {
			return self::$_current_post_id_cache;
		}

		// Check if we're in a Theme Builder layout context.
		// When Layout::render() is called, it uses ET_Post_Stack::replace() to set the layout post.
		// For older Theme Builder code, check if we have an active layout ID.
		if ( class_exists( '\ET_Post_Stack' ) ) {
			$current_post = \ET_Post_Stack::get();
			if ( $current_post && isset( $current_post->ID ) ) {
				self::$_current_post_id_cache = $current_post->ID;
				return self::$_current_post_id_cache;
			}
		}

		// Check for Theme Builder layout context (fallback for older code).
		if ( class_exists( '\ET_Theme_Builder_Layout' ) && method_exists( '\ET_Theme_Builder_Layout', 'get_theme_builder_layout_id' ) ) {
			$layout_id = \ET_Theme_Builder_Layout::get_theme_builder_layout_id();
			if ( $layout_id > 0 ) {
				self::$_current_post_id_cache = $layout_id;
				return self::$_current_post_id_cache;
			}
		}

		// Fall back to get_the_ID() for regular posts.
		self::$_current_post_id_cache = get_the_ID();
		return self::$_current_post_id_cache;
	}

	/**
	 * Process off-canvas content for target IDs through Divi's rendering pipeline.
	 * This ensures proper CSS generation by using Divi's normal block processing.
	 *
	 * @since ??
	 *
	 * @param array $target_ids Array of interaction target IDs.
	 * @param int   $post_id    Post ID to track rendered canvases per template/post.
	 */
	private static function _process_off_canvas_content_for_targets( $target_ids, $post_id ) {
		if ( ! $post_id ) {
			return;
		}

		// Track processed canvases per post_id to avoid duplicate rendering.
		$processed_canvases = self::_get_per_post_global_value( 'divi_off_canvas_processed_canvases', $post_id, [] );

		// Get all canvas data (this will cache if not already cached).
		$canvas_data              = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$canvas_portal_canvas_ids = $canvas_data['canvas_portal_ids'] ?? [];
		$all_canvas_metadata      = $canvas_data['all_canvas_metadata'] ?? [];

		// Get main canvas content (post_content) to check if targets are on the main canvas.
		// If a target is on the main canvas, we don't need to process any canvas content for it.
		$main_canvas_content = '';
		$main_post           = get_post( $post_id );
		if ( $main_post && isset( $main_post->post_content ) ) {
			$main_canvas_content = $main_post->post_content;
		}

		// Convert metadata to the format expected by the rest of the function.
		$all_canvases = [];
		foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
			$all_canvases[ $canvas_id ] = [
				'id'                 => $canvas_id,
				'isMain'             => false,
				'isGlobal'           => $canvas_meta['isGlobal'] ?? false,
				'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
			];
		}

		// Find which canvases contain the target modules.
		$canvases_to_process = [];
		foreach ( $target_ids as $target_id ) {
			// Defense-in-depth: Skip targets that are on the main canvas.
			// Note: These should already be filtered out in detect_and_process_off_canvas_interactions(),
			// but we keep this check as a safety net to ensure same-canvas interactions never
			// trigger canvas appending, even if something changes in the detection logic.
			if ( ! empty( $main_canvas_content ) && self::canvas_block_content_contains_target( $main_canvas_content, $target_id ) ) {
				continue;
			}

			foreach ( $all_canvases as $canvas_id => $canvas_meta ) {
				// Skip already processed canvases.
				if ( in_array( $canvas_id, $processed_canvases, true ) ) {
					continue;
				}

				// Skip main canvas.
				if ( $canvas_meta['isMain'] ?? false ) {
					continue;
				}

				// Skip canvases that are already included via Canvas Portal.
				if ( in_array( $canvas_id, $canvas_portal_canvas_ids, true ) ) {
					continue;
				}

				// Get canvas content from cached metadata.
				$canvas_meta_data = $all_canvas_metadata[ $canvas_id ] ?? null;
				if ( ! $canvas_meta_data ) {
					continue;
				}

				$canvas_content = $canvas_meta_data['content'] ?? null;
				if ( ! $canvas_content ) {
					continue;
				}

				// Check if this canvas contains the target module.
				if ( ! self::canvas_block_content_contains_target( $canvas_content, $target_id ) ) {
					continue;
				}

				$is_global = $canvas_meta_data['isGlobal'] ?? false;

				$canvases_to_process[] = [
					'canvas_id' => $canvas_id,
					'is_global' => $is_global,
				];
				$processed_canvases[]  = $canvas_id;
			}
		}

		// Update processed canvases tracking.
		if ( ! empty( $processed_canvases ) ) {
			self::_set_per_post_global_value( 'divi_off_canvas_processed_canvases', $post_id, $processed_canvases );
		}

		if ( empty( $canvases_to_process ) ) {
			return;
		}

		// Process each canvas through Divi's rendering pipeline.
		foreach ( $canvases_to_process as $canvas_info ) {
			$canvas_id = $canvas_info['canvas_id'];

			self::_render_off_canvas_content_with_css( $canvas_id, $post_id );
		}
	}

	/**
	 * Render off-canvas content through Divi's normal block processing to generate CSS.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID to render.
	 * @param int    $post_id   Post ID (for CSS context).
	 */
	private static function _render_off_canvas_content_with_css( $canvas_id, $post_id ) {
		// Get canvas content from cached data.
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];
		$canvas_meta         = $all_canvas_metadata[ $canvas_id ] ?? null;

		if ( ! $canvas_meta ) {
			return;
		}

		$canvas_content = $canvas_meta['content'] ?? null;
		if ( ! $canvas_content ) {
			return;
		}

		// Unwrap the placeholder to get raw block content.
		$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );

		if ( ! $unwrapped_content ) {
			return;
		}

		// Set flag to prevent infinite recursion when rendering canvas content.
		$GLOBALS['divi_off_canvas_rendering'] = true;

		// Collect z-index data for CSS output and store canvas ID for wrapper class.
		$z_index = $all_canvas_metadata[ $canvas_id ]['zIndex'] ?? null;
		if ( null !== $z_index && '' !== $z_index && 'auto' !== $z_index ) {
			// Store canvas ID in global so wrapper filter can add the class.
			$GLOBALS['divi_off_canvas_current_id'] = $canvas_id;

			// Initialize global array if needed.
			if ( ! isset( $GLOBALS['divi_canvas_z_index_styles'] ) ) {
				$GLOBALS['divi_canvas_z_index_styles'] = [];
			}

			// Determine selector based on current post type context and canvas ID.
			$base_class = self::_get_base_layout_class_from_post_type();
			$selector   = '.' . $base_class . '--' . esc_attr( $canvas_id );

			// Store CSS rule for later output (deduplicated by selector).
			$GLOBALS['divi_canvas_z_index_styles'][ $selector ] = [
				'selector' => $selector,
				'z_index'  => $z_index,
			];
		}

		try {
			// Use Theme Builder's established pattern for rendering Divi content.
			// This ensures proper CSS generation and processing.
			$rendered_html = apply_filters( 'et_builder_render_layout', $unwrapped_content );
		} finally {
			// Always clear the flag, even if rendering throws an exception.
			unset( $GLOBALS['divi_off_canvas_rendering'] );
			// Clear canvas ID after rendering.
			if ( isset( $GLOBALS['divi_off_canvas_current_id'] ) ) {
				unset( $GLOBALS['divi_off_canvas_current_id'] );
			}
		}

		// Set up styles manager for this canvas content (following Theme Builder pattern).
		$result         = StaticCSS::setup_styles_manager( $post_id );
		$styles_manager = $result['manager'];
		if ( isset( $result['deferred'] ) ) {
			$deferred_styles_manager = $result['deferred'];
		}

		// Output styles if needed (following Theme Builder pattern).
		if ( StaticCSS::$forced_inline_styles || ! $styles_manager->has_file() || $styles_manager->forced_inline ) {
			$custom = Page::custom_css( $post_id );

			// Pass styles to the page resource.
			StaticCSS::style_output(
				[
					'styles_manager'          => $styles_manager,
					'deferred_styles_manager' => $deferred_styles_manager ?? null,
					'custom'                  => $custom,
					'element_id'              => $post_id,
				]
			);
		}

		// Store the rendered content for later injection, keyed by post_id.
		// This ensures canvases are only injected into the template/post that targeted them.
		$rendered_array               = self::_get_per_post_global_value( 'divi_off_canvas_rendered', $post_id, [] );
		$rendered_array[ $canvas_id ] = $rendered_html;
		self::_set_per_post_global_value( 'divi_off_canvas_rendered', $post_id, $rendered_array );
	}

	/**
	 * Process canvases that should be appended to main content.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post ID.
	 * @param string $position Position to append ('above' or 'below').
	 */
	private static function _process_appended_canvases( $post_id, $position ) {
		// Get cached canvas data (no parsing needed for rendering).
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];

		// Initialize global canvas tracking if needed.
		if ( ! isset( $GLOBALS['divi_off_canvas_global_rendered'] ) ) {
			$GLOBALS['divi_off_canvas_global_rendered'] = [];
		}

		// Convert metadata to the format expected by the rest of the function.
		$all_canvases = [];
		foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
			$all_canvases[ $canvas_id ] = [
				'id'                 => $canvas_id,
				'isMain'             => false,
				'isGlobal'           => $canvas_meta['isGlobal'] ?? false,
				'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
			];
		}

		// Find canvases that should be appended at this position.
		$canvases_to_process = [];
		foreach ( $all_canvases as $canvas_id => $canvas_meta ) {
			// Skip main canvas.
			if ( $canvas_meta['isMain'] ?? false ) {
				continue;
			}

			// Check if this canvas should be appended at this position.
			$append_to_main = $canvas_meta['appendToMainCanvas'] ?? null;
			if ( $append_to_main !== $position ) {
				continue;
			}

			$is_global = $canvas_meta['isGlobal'] ?? false;

			// For global canvases, check if they should be rendered.
			// Global canvases should only be rendered once, with priority:
			// post content > body template > header > footer.
			if ( $is_global ) {
				$rendering_context = self::_get_rendering_context();

				// If we're rendering in a template context, check if post content will render.
				// If post content will render, skip rendering in templates (post content has higher priority).
				if ( in_array( $rendering_context, [ 'header_template', 'footer_template', 'body_template' ], true ) ) {
					if ( self::_will_post_content_render() ) {
						continue;
					}
				}

				// Check if this global canvas has already been rendered.
				if ( isset( $GLOBALS['divi_off_canvas_global_rendered'][ $canvas_id ] ) ) {
					$rendered_context = $GLOBALS['divi_off_canvas_global_rendered'][ $canvas_id ];
					// Skip if already rendered in a higher priority context.
					if ( self::_is_higher_priority_context( $rendered_context, $rendering_context ) ) {
						continue;
					}
					// Current context has higher priority, so we should render here instead.
					// Remove the old entry so we can re-render in the higher priority context.
					unset( $GLOBALS['divi_off_canvas_global_rendered'][ $canvas_id ] );
				}

				// Store rendering context for marking after successful render.
				$canvases_to_process[] = [
					'canvas_id'         => $canvas_id,
					'is_global'         => $is_global,
					'rendering_context' => $rendering_context,
				];
			} else {
				$canvases_to_process[] = [
					'canvas_id' => $canvas_id,
					'is_global' => $is_global,
				];
			}
		}

		if ( empty( $canvases_to_process ) ) {
			return;
		}

		// Set flag BEFORE processing canvases so it's available when PHASE_NEW_STORE_INSTANCE
		// runs during main content rendering (after canvas rendering completes).
		// Track which layout type had the appended canvas so we don't skip resets for other layouts.
		$current_layout_type = BlockParserStore::get_layout_type();
		OrderIndexResetManager::set_appended_canvas_processed( $current_layout_type );

		// Process each canvas through Divi's rendering pipeline.
		foreach ( $canvases_to_process as $canvas_info ) {
			$canvas_id = $canvas_info['canvas_id'];
			$is_global = $canvas_info['is_global'];

			self::_render_off_canvas_content_with_css( $canvas_id, $post_id );

			// Mark global canvas as rendered after successful render.
			if ( $is_global && isset( $canvas_info['rendering_context'] ) ) {
				$GLOBALS['divi_off_canvas_global_rendered'][ $canvas_id ] = $canvas_info['rendering_context'];
			}
		}
	}

	/**
	 * Get the current rendering context.
	 * Returns the context type to determine priority for global canvas rendering.
	 * Cached per request to avoid redundant calls, but should be cleared when rendering context changes.
	 *
	 * @since ??
	 *
	 * @return string Rendering context: 'post_content', 'body_template', 'header_template', 'footer_template'.
	 */
	private static function _get_rendering_context() {
		// Return cached value if available.
		if ( null !== self::$_rendering_context_cache ) {
			return self::$_rendering_context_cache;
		}

		// Check if we're rendering Theme Builder template content.
		$current_post = null;
		if ( class_exists( '\ET_Post_Stack' ) ) {
			$current_post = \ET_Post_Stack::get();
		}

		// Also check global $post as fallback.
		if ( ! $current_post ) {
			global $post;
			$current_post = $post;
		}

		// Check for Theme Builder template post types.
		if ( $current_post && isset( $current_post->post_type ) ) {
			$post_type = $current_post->post_type;
			if ( 'et_body_layout' === $post_type ) {
				self::$_rendering_context_cache = 'body_template';
				return self::$_rendering_context_cache;
			}
			if ( 'et_header_layout' === $post_type ) {
				self::$_rendering_context_cache = 'header_template';
				return self::$_rendering_context_cache;
			}
			if ( 'et_footer_layout' === $post_type ) {
				self::$_rendering_context_cache = 'footer_template';
				return self::$_rendering_context_cache;
			}
		}

		// Default to post content context (the_content filter or regular posts).
		self::$_rendering_context_cache = 'post_content';
		return self::$_rendering_context_cache;
	}

	/**
	 * Check if post content will be rendered on this page.
	 * This helps determine if we should skip rendering global canvases in templates.
	 *
	 * @since ??
	 *
	 * @return bool True if post content will be rendered.
	 */
	private static function _will_post_content_render() {
		// Must be a singular page/post (not archive, search, etc.).
		if ( ! is_singular() ) {
			return false;
		}

		// Get the main post from the post stack (this works even when rendering templates).
		$main_post = null;
		if ( class_exists( '\ET_Post_Stack' ) ) {
			$main_post = \ET_Post_Stack::get_main_post();
		}

		// Fallback to global $wp_query.
		if ( ! $main_post ) {
			global $wp_query;
			$main_post = $wp_query->post ?? null;
		}

		// Check if we have a main post with content.
		return $main_post && isset( $main_post->post_content ) && ! empty( trim( $main_post->post_content ) );
	}

	/**
	 * Check if the first context has higher priority than the second.
	 * Priority order: post_content > body_template > header_template > footer_template.
	 *
	 * @since ??
	 *
	 * @param string $context1 First context.
	 * @param string $context2 Second context.
	 *
	 * @return bool True if context1 has higher priority than context2.
	 */
	private static function _is_higher_priority_context( $context1, $context2 ) {
		$priority = [
			'post_content'    => 4,
			'body_template'   => 3,
			'header_template' => 2,
			'footer_template' => 1,
		];

		$priority1 = $priority[ $context1 ] ?? 0;
		$priority2 = $priority[ $context2 ] ?? 0;

		return $priority1 > $priority2;
	}

	/**
	 * Inject canvas content for interactions and appended canvases on the frontend.
	 * This injects pre-processed off-canvas content that was rendered during block processing.
	 *
	 * @since ??
	 *
	 * @param string $content The main post content.
	 *
	 * @return string The content with injected canvas content if needed.
	 */
	public static function inject_canvas_content_for_interactions( $content ) {
		// Only run on frontend (not in admin or visual builder).
		if ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) {
			return $content;
		}

		// Prevent infinite recursion when rendering canvas content.
		if ( ! empty( $GLOBALS['divi_off_canvas_rendering'] ) ) {
			return $content;
		}

		$current_post_id = self::_get_current_post_id();
		if ( ! $current_post_id ) {
			return $content;
		}

		// Skip canvas injection during the_content when in Theme Builder layout context.
		// This prevents post ID confusion when Post Content module renders content within TB layouts,
		// but allows canvas injection for regular posts rendered via the_content.
		if ( doing_filter( 'the_content' ) && self::_get_theme_builder_layout_post_id( $current_post_id ) !== null ) {
			return $content;
		}

		// Skip injecting appended canvases when rendering inner content (e.g., Canvas Portal modules).
		// Append/prepend should only apply to the main canvas, not to Canvas Portal content.
		// However, we still need to inject interaction-targeted canvases even when rendering inner content,
		// as those are needed for interactions to work within Canvas Portal content.
		$is_rendering_inner_content = BlockParserStore::is_rendering_inner_content();

		// In Theme Builder layouts, always use the layout post ID for canvas injection.
		// This ensures interactions defined in layouts inject the correct canvas content.
		$layout_post_id = self::_get_theme_builder_layout_post_id( $current_post_id );
		$canvas_post_id = null !== $layout_post_id ? $layout_post_id : $current_post_id;

		// Only inject canvases that were rendered for the canvas post (layout post in TB context).
		// This ensures canvases are injected using the correct post ID where they're stored.
		$rendered_canvases = self::_get_per_post_global_value( 'divi_off_canvas_rendered', $canvas_post_id, [] );

		if ( empty( $rendered_canvases ) ) {
			return $content;
		}

		// Get all canvas data (this will cache if not already cached).
		$canvas_data              = DynamicAssetsUtils::get_all_canvas_data_for_post( $canvas_post_id );
		$canvas_portal_canvas_ids = $canvas_data['canvas_portal_ids'] ?? [];
		$all_canvas_metadata      = $canvas_data['all_canvas_metadata'] ?? [];

		// Convert metadata to the format expected by the rest of the function.
		$all_canvases = [];
		foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
			$all_canvases[ $canvas_id ] = [
				'id'                 => $canvas_id,
				'isMain'             => false,
				'isGlobal'           => $canvas_meta['isGlobal'] ?? false,
				'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
			];
		}

		// Separate canvases by position.
		$above_content       = '';
		$below_content       = '';
		$interaction_content = '';

		foreach ( $rendered_canvases as $canvas_id => $rendered_html ) {
			$canvas_meta = $all_canvases[ $canvas_id ] ?? null;
			if ( ! $canvas_meta ) {
				// If canvas metadata not found, treat as interaction-targeted (legacy behavior).
				// Skip if already included via Canvas Portal.
				if ( ! in_array( $canvas_id, $canvas_portal_canvas_ids, true ) ) {
					$interaction_content .= $rendered_html;
				}
				continue;
			}

			$append_to_main = $canvas_meta['appendToMainCanvas'] ?? null;

			// When rendering inner content (e.g., Canvas Portal), skip appended canvases.
			// Only inject interaction-targeted canvases, as those are needed for interactions.
			if ( $is_rendering_inner_content ) {
				// Skip appended canvases (above/below) when rendering inner content.
				if ( 'above' === $append_to_main || 'below' === $append_to_main ) {
					continue;
				}
				// Still inject interaction-targeted canvases.
				if ( ! in_array( $canvas_id, $canvas_portal_canvas_ids, true ) ) {
					$interaction_content .= $rendered_html;
				}
				continue;
			}

			// Normal rendering: handle all canvas types.
			if ( 'above' === $append_to_main ) {
				$above_content .= $rendered_html;
			} elseif ( 'below' === $append_to_main ) {
				$below_content .= $rendered_html;
			} elseif ( ! in_array( $canvas_id, $canvas_portal_canvas_ids, true ) ) {
				// Interaction-targeted canvas (no appendToMainCanvas setting).
				// Skip if already included via Canvas Portal.
				$interaction_content .= $rendered_html;
			}
		}

		// Build final content: above + main + below + interactions.
		// When rendering inner content, above_content and below_content will be empty.
		$final_content = $above_content . $content . $below_content . $interaction_content;

		return $final_content;
	}





	/**
	 * Check if canvas block content contains a module with the specified interaction target.
	 *
	 * @since ??
	 *
	 * @param string $canvas_content Canvas content in Gutenberg block format.
	 * @param string $target_id Target ID to search for.
	 *
	 * @return bool True if canvas contains the target module.
	 */
	public static function canvas_block_content_contains_target( $canvas_content, $target_id ) {
		// Optimize: Try fast checks first before expensive parsing.
		// Method 1: Quick string search for target ID - fastest check.
		// Use strict comparison for better performance.
		if ( false === strpos( $canvas_content, $target_id ) ) {
			// Target ID not found at all, skip expensive parsing.
			return false;
		}

		// Method 2: Check for interactionTarget attribute (target element).
		// Target elements have: module.decoration.interactionTarget = "{ID}".
		// Optimized: Use faster string search before regex when possible.
		// Check for the attribute name first to avoid regex if not present.
		if ( false === strpos( $canvas_content, 'interactionTarget' ) ) {
			return false;
		}

		// Only use regex if we found both the target ID and the attribute name.
		// Regex test: https://regex101.com/r/kPQf15/1.
		$pattern = '/"interactionTarget"\s*:\s*"' . preg_quote( $target_id, '/' ) . '"/';
		return 1 === preg_match( $pattern, $canvas_content );
	}

	/**
	 * Recursively search parsed blocks for a target ID.
	 *
	 * @since ??
	 *
	 * @param array  $blocks Parsed WordPress blocks.
	 * @param string $target_id Target ID to search for.
	 *
	 * @return bool True if target found.
	 */
	private static function _search_blocks_for_target( $blocks, $target_id ) {
		foreach ( $blocks as $block ) {
			// Search in block attributes (convert to JSON string for easy searching).
			if ( ! empty( $block['attrs'] ) ) {
				$attrs_json = wp_json_encode( $block['attrs'] );
				if ( strpos( $attrs_json, $target_id ) !== false ) {
					return true;
				}
			}

			// Search in inner blocks recursively.
			if ( ! empty( $block['innerBlocks'] ) ) {
				if ( self::_search_blocks_for_target( $block['innerBlocks'], $target_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get canvas content for canvas portal canvas IDs.
	 * Returns content from all canvases (local and global) referenced by canvas portal blocks.
	 *
	 * @since ??
	 *
	 * @param array $canvas_ids Array of canvas IDs from canvas portal blocks.
	 * @param int   $post_id Post ID to get local canvases from.
	 *
	 * @return string Combined canvas content from all matching canvases.
	 */
	public static function get_canvas_content_for_canvas_portals( $canvas_ids, $post_id ) {
		if ( empty( $canvas_ids ) || ! $post_id ) {
			return '';
		}

		// Remove duplicates to avoid processing same canvas multiple times.
		$canvas_ids = array_unique( $canvas_ids );

		// Get cached canvas data.
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];

		$canvas_contents        = [];
		$processed_canvas_ids   = [];
		$all_canvas_content_map = [];

		// Process each canvas ID using cached metadata.
		foreach ( $canvas_ids as $canvas_id ) {
			// Skip already processed canvases.
			if ( in_array( $canvas_id, $processed_canvas_ids, true ) ) {
				continue;
			}

			$canvas_meta = $all_canvas_metadata[ $canvas_id ] ?? null;
			if ( ! $canvas_meta ) {
				continue;
			}

			$canvas_content = $canvas_meta['content'] ?? null;
			if ( ! $canvas_content ) {
				continue;
			}

			// Store raw content in map for cache pre-population.
			$all_canvas_content_map[ $canvas_id ] = $canvas_content;

			// Unwrap placeholder block if needed.
			$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );
			if ( $unwrapped_content ) {
				$canvas_contents[]      = $unwrapped_content;
				$processed_canvas_ids[] = $canvas_id;
			}
		}

		// Pre-populate CanvasPortalModule cache with batch-fetched content.
		// This allows render_callback() to reuse cached content instead of fetching again.
		if ( ! empty( $all_canvas_content_map ) ) {
			CanvasPortalModule::pre_populate_canvas_content_cache( $all_canvas_content_map, $post_id );
		}

		// Combine all canvas contents.
		return implode( '', $canvas_contents );
	}

	/**
	 * Extract interaction target IDs from post content blocks.
	 * This is used by DynamicAssets to find which canvases need to be processed.
	 *
	 * @since ??
	 *
	 * @param string $content Post content in Gutenberg block format.
	 *
	 * @return array Array of unique target IDs found in interactions.
	 */
	public static function extract_interaction_target_ids_from_content( $content ) {
		$target_ids = [];

		if ( ! function_exists( 'parse_blocks' ) ) {
			return $target_ids;
		}

		// Early exit: Use DynamicAssets detection to check if content has interactions before expensive parsing.
		if ( ! DetectFeature::has_interactions_enabled( $content, [ 'has_block' => true ] ) ) {
			return $target_ids;
		}

		$blocks = parse_blocks( $content );
		if ( empty( $blocks ) ) {
			return $target_ids;
		}

		// Recursively search blocks for interactions.
		self::_extract_target_ids_from_blocks( $blocks, $target_ids );

		$unique_target_ids = array_unique( $target_ids );

		return $unique_target_ids;
	}

	/**
	 * Recursively extract target IDs from blocks.
	 *
	 * @since ??
	 *
	 * @param array $blocks Parsed WordPress blocks.
	 * @param array $target_ids Array to populate with target IDs (passed by reference).
	 */
	private static function _extract_target_ids_from_blocks( $blocks, &$target_ids ) {
		foreach ( $blocks as $block ) {
			// Check for interactions in block attributes.
			if ( ! empty( $block['attrs'] ) ) {
				$block_attrs       = $block['attrs'];
				$interactions_data = $block_attrs['module']['decoration']['interactions'] ?? null;

				if ( $interactions_data ) {
					$interactions = $interactions_data['desktop']['value']['interactions'] ?? [];

					if ( is_array( $interactions ) && ! empty( $interactions ) ) {
						foreach ( $interactions as $interaction ) {
							$target_class = $interaction['target']['targetClass'] ?? '';

							if ( $target_class && preg_match( '/et-interaction-target-([a-zA-Z0-9]+)/', $target_class, $matches ) ) {
								$target_ids[] = $matches[1];
							}
						}
					}
				}
			}

			// Search in inner blocks recursively.
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::_extract_target_ids_from_blocks( $block['innerBlocks'], $target_ids );
			}
		}
	}

	/**
	 * Get base layout class from current post type.
	 *
	 * Returns the base layout class (e.g., 'et-l--post', 'et-l--header', 'et-l--footer')
	 * based on the current post type, using the same logic as et_builder_get_layout_opening_wrapper().
	 *
	 * @since ??
	 *
	 * @param string $post_type Optional. Post type. Defaults to current post type.
	 *
	 * @return string Base layout class.
	 */
	private static function _get_base_layout_class_from_post_type( $post_type = '' ) {
		if ( empty( $post_type ) ) {
			$post_type = get_post_type();
		}

		// Use same logic as et_builder_get_layout_opening_wrapper().
		switch ( $post_type ) {
			case ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE:
				return 'et-l--header';

			case ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE:
				return 'et-l--body';

			case ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE:
				return 'et-l--footer';

			default:
				return 'et-l--post';
		}
	}

	/**
	 * Add canvas ID class to wrapper when rendering canvas content with z-index.
	 *
	 * This filter adds a canvas-specific class to the `.et-l` wrapper
	 * (e.g., `.et-l--post--{canvas_id}`) so the CSS selector can target it.
	 *
	 * @since ??
	 *
	 * @param array $layout_class Array of layout classes.
	 *
	 * @return array Modified array of layout classes.
	 */
	public static function add_canvas_id_to_wrapper_class( $layout_class ) {
		// Only add canvas class when rendering canvas content with z-index.
		if ( ! isset( $GLOBALS['divi_off_canvas_rendering'] ) || ! isset( $GLOBALS['divi_off_canvas_current_id'] ) ) {
			return $layout_class;
		}

		$canvas_id = $GLOBALS['divi_off_canvas_current_id'];
		if ( ! $canvas_id ) {
			return $layout_class;
		}

		// Get base class using the same logic as et_builder_get_layout_opening_wrapper().
		$base_class = self::_get_base_layout_class_from_post_type();

		// Add canvas ID class: et-l--post--{canvas_id} or et-l--header--{canvas_id}, etc.
		$canvas_class   = $base_class . '--' . esc_attr( $canvas_id );
		$layout_class[] = $canvas_class;

		return $layout_class;
	}

	/**
	 * Get canvas content for interaction target IDs.
	 * Returns content from all canvases (local and global) that contain the target modules.
	 *
	 * @since ??
	 *
	 * @param array $target_ids Array of interaction target IDs.
	 * @param int   $post_id Post ID to get local canvases from.
	 *
	 * @return string Combined canvas content from all matching canvases.
	 */
	public static function get_canvas_content_for_targets( $target_ids, $post_id ) {
		if ( empty( $target_ids ) || ! $post_id ) {
			return '';
		}

		// Get cached canvas data.
		$canvas_data              = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata      = $canvas_data['all_canvas_metadata'] ?? [];
		$interaction_targets      = $canvas_data['interaction_targets'] ?? [];
		$canvas_portal_canvas_ids = $canvas_data['canvas_portal_ids'] ?? [];

		// Collect canvas IDs for all targets from cache.
		$canvas_ids_to_process = [];
		foreach ( $target_ids as $target_id ) {
			$canvas_ids_for_target = $interaction_targets[ $target_id ] ?? [];
			$canvas_ids_to_process = array_merge( $canvas_ids_to_process, $canvas_ids_for_target );
		}

		// Remove duplicates.
		$canvas_ids_to_process = array_unique( $canvas_ids_to_process );

		// Filter out canvases that are already included via Canvas Portal.
		$canvas_ids_to_process = array_filter(
			$canvas_ids_to_process,
			function ( $canvas_id ) use ( $canvas_portal_canvas_ids ) {
				return ! in_array( $canvas_id, $canvas_portal_canvas_ids, true );
			}
		);

		if ( empty( $canvas_ids_to_process ) ) {
			return '';
		}

		$canvas_contents = [];

		// Process canvases using cached metadata.
		foreach ( $canvas_ids_to_process as $canvas_id ) {
			$canvas_meta = $all_canvas_metadata[ $canvas_id ] ?? null;
			if ( ! $canvas_meta ) {
				continue;
			}

			$canvas_content = $canvas_meta['content'] ?? '';
			if ( ! $canvas_content ) {
				continue;
			}

			// Unwrap placeholder block if needed.
			$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );
			if ( $unwrapped_content ) {
				$canvas_contents[ $canvas_id ] = $unwrapped_content;
			}
		}

		// Combine all canvas contents.
		return implode( '', $canvas_contents );
	}

	/**
	 * Get canvas content for canvases that are appended to main canvas (above or below).
	 * Returns content from all canvases (local and global) with appendToMainCanvas set.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID to get local canvases from.
	 *
	 * @return string Combined canvas content from all appended canvases.
	 */
	public static function get_canvas_content_for_appended( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}

		// Get cached canvas data.
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];
		$appended_above      = $canvas_data['appended_above'] ?? [];
		$appended_below      = $canvas_data['appended_below'] ?? [];

		// Combine above and below canvas IDs.
		$appended_canvas_ids = array_merge( $appended_above, $appended_below );

		if ( empty( $appended_canvas_ids ) ) {
			return '';
		}

		$canvas_contents = [];

		// Process appended canvases using cached metadata.
		foreach ( $appended_canvas_ids as $canvas_id ) {
			$canvas_meta = $all_canvas_metadata[ $canvas_id ] ?? null;
			if ( ! $canvas_meta ) {
				continue;
			}

			$canvas_content = $canvas_meta['content'] ?? '';
			if ( ! $canvas_content ) {
				continue;
			}

			// Unwrap placeholder block if needed.
			$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );
			if ( $unwrapped_content ) {
				$canvas_contents[] = $unwrapped_content;
			}
		}

		// Combine all canvas contents.
		return implode( '', $canvas_contents );
	}

	/**
	 * Get all appended canvas content (both interaction-targeted and explicitly appended).
	 * This is used to extract global color IDs from canvases that will be rendered on the front end.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post ID to get local canvases from.
	 * @param string $main_content Main post content to extract interaction target IDs from.
	 *
	 * @return string Combined canvas content from all appended canvases.
	 */
	public static function get_all_appended_canvas_content( $post_id, $main_content = '' ) {
		if ( ! $post_id ) {
			return '';
		}

		// Skip expensive canvas content fetching when not in a cacheable frontend request.
		// The builder doesn't need this for dynamic assets detection, and it causes
		// performance issues during builder load. Canvas content will be handled
		// on the client side in the builder.
		if ( ! DynamicAssetsUtils::is_dynamic_front_end_request() ) {
			return '';
		}

		// Early exit: Check if any canvases exist before doing expensive operations.
		// This avoids database queries and content parsing when no canvases exist.
		if ( ! self::_has_any_canvases( $post_id ) ) {
			return '';
		}

		$all_canvas_content = '';

		// Get explicitly appended canvases (above/below).
		$appended_content = self::get_canvas_content_for_appended( $post_id );
		if ( ! empty( $appended_content ) ) {
			$all_canvas_content .= $appended_content;
		}

		// Get all canvas data (this will cache if not already cached).
		$canvas_data = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id, $main_content );

		// Get interaction-targeted canvases.
		if ( ! empty( $main_content ) ) {
			$target_ids = self::extract_interaction_target_ids_from_content( $main_content );

			if ( ! empty( $target_ids ) ) {
				// Filter out targets that are on the main canvas.
				// When an interaction on the main canvas targets an element on the same canvas,
				// the target already exists in the rendered HTML, so we don't need to process any canvas content.
				// This prevents orderIndex from being incremented incorrectly during CSS generation.
				$filtered_target_ids = [];
				foreach ( $target_ids as $target_id ) {
					if ( ! self::canvas_block_content_contains_target( $main_content, $target_id ) ) {
						$filtered_target_ids[] = $target_id;
					}
				}

				if ( ! empty( $filtered_target_ids ) ) {
					$interaction_content = self::get_canvas_content_for_targets( $filtered_target_ids, $post_id );
					if ( ! empty( $interaction_content ) ) {
						$all_canvas_content .= $interaction_content;
					}
				}
			}

			// Get canvas portal canvases.
			$canvas_portal_ids = $canvas_data['canvas_portal_ids'] ?? [];
			if ( ! empty( $canvas_portal_ids ) ) {
				$canvas_portal_content = self::get_canvas_content_for_canvas_portals( $canvas_portal_ids, $post_id );
				if ( ! empty( $canvas_portal_content ) ) {
					$all_canvas_content .= $canvas_portal_content;
				}
			}
		}

		return $all_canvas_content;
	}

	/**
	 * Convert flat module objects to WordPress block array.
	 * Based on the pattern from FlexboxMigration and GlobalColorMigration.
	 *
	 * @since ??
	 *
	 * @param array $flat_objects The flat module objects.
	 *
	 * @return array The block array structure.
	 */
	private static function _convert_module_data_to_blocks( $flat_objects ) {
		if ( ! is_array( $flat_objects ) ) {
			return [];
		}

		// Find the actual root object (should have no parent or parent=null).
		$root = null;
		foreach ( $flat_objects as $id => $object ) {
			// Look for object with no parent or null parent (this is the actual root).
			if ( ! isset( $object['parent'] ) || null === $object['parent'] || 'no-parent' === $object['parent'] ) {
				$root = $object;
				break;
			}
		}

		if ( ! $root ) {
			// Try to find root by ID.
			$root = $flat_objects['root'] ?? null;
		}

		if ( ! $root ) {
			return [];
		}

		$blocks = [];
		foreach ( $root['children'] ?? [] as $child_id ) {
			$block = self::_build_block_from_flat( $child_id, $flat_objects );
			if ( $block ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Recursively build a block from a flat object.
	 * Based on the pattern from FlexboxMigration and GlobalColorMigration.
	 *
	 * @since ??
	 *
	 * @param string $id The object ID.
	 * @param array  $flat_objects The flat module objects.
	 *
	 * @return array The block array.
	 */
	private static function _build_block_from_flat( $id, $flat_objects ) {
		if ( ! isset( $flat_objects[ $id ] ) ) {
			return null;
		}

		$object = $flat_objects[ $id ];
		$block  = [
			'blockName'    => $object['name'],
			'attrs'        => $object['props']['attrs'] ?? [],
			'innerBlocks'  => [],
			'innerContent' => [],
		];

		if ( ! empty( $object['children'] ) ) {
			foreach ( $object['children'] as $child_id ) {
				$child_block = self::_build_block_from_flat( $child_id, $flat_objects );
				if ( $child_block ) {
					$block['innerBlocks'][]  = $child_block;
					$block['innerContent'][] = null; // Placeholder, will be filled by serializer.
				}
			}
		}

		if ( isset( $object['props']['innerHTML'] ) ) {
			$block['innerContent'][] = $object['props']['innerHTML'];
		}

		return $block;
	}

	/**
	 * Get a value from a per-post global array.
	 * Returns the value if it exists, or the default value if not.
	 *
	 * @since ??
	 *
	 * @param string $global_key    Global variable key (e.g., 'divi_off_canvas_target_ids').
	 * @param int    $post_id       Post ID.
	 * @param mixed  $default_value Default value to return if not found. Default empty array.
	 *
	 * @return mixed The value from the array or the default value.
	 */
	private static function _get_per_post_global_value( $global_key, $post_id, $default_value = [] ) {
		if ( ! isset( $GLOBALS[ $global_key ] ) || ! isset( $GLOBALS[ $global_key ][ $post_id ] ) ) {
			return $default_value;
		}

		return $GLOBALS[ $global_key ][ $post_id ];
	}

	/**
	 * Set a value in a per-post global array.
	 * Initializes the global array structure if needed.
	 *
	 * @since ??
	 *
	 * @param string $global_key Global variable key (e.g., 'divi_off_canvas_target_ids').
	 * @param int    $post_id    Post ID.
	 * @param mixed  $value      Value to set.
	 *
	 * @return void
	 */
	private static function _set_per_post_global_value( $global_key, $post_id, $value ) {
		if ( ! isset( $GLOBALS[ $global_key ] ) ) {
			$GLOBALS[ $global_key ] = [];
		}

		$GLOBALS[ $global_key ][ $post_id ] = $value;
	}

	/**
	 * Reset caches for testing or when rendering context changes.
	 *
	 * This method clears cached values that may become stale when the post context
	 * or rendering context changes (e.g., when moving from header to post content to footer).
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_caches() {
		self::$_current_post_id_cache   = null;
		self::$_rendering_context_cache = null;
		self::$_main_post_cache         = [];
	}
}
