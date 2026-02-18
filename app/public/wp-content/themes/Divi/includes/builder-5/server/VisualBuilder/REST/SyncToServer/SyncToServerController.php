<?php
/**
 * REST: SyncToServerController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\SyncToServer;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Security\AttributeSecurity\AttributeSecurity;
use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET\Builder\VisualBuilder\Workspace\Workspace;
use ET\Builder\Framework\Revision\Revision;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;
use ET\Builder\VisualBuilder\REST\Portability\PortabilityController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * SyncToServerController class.
 *
 * This class extends the RESTController class and provides functionality for syncing data to the server.
 *
 * @since ??
 */
class SyncToServerController extends RESTController {

	/**
	 * Update post content and status.
	 *
	 * This function updates the post content and status based on the given parameters.
	 * It retrieves the post ID, post status, content, and preferences from the provided `$request` object.
	 * It then calls the `wp_update_post()` function to update the post with the new content and status.
	 * If there is an autosave that is newer, it deletes the existing autosave.
	 * It also calls `et_save_post` and `divi_visual_builder_rest_save_post` action hooks to perform actions before the update
	 * as well as `et_update_post`, and `divi_visual_builder_rest_update_post` action hooks and also `et_fb_ajax_save_verification_result`,
	 * and `divi_visual_builder_rest_save_post_save_verification` filters all applied after the update.
	 * Finally, it sanitizes and updates the app preferences using the `SavingUtility::sanitize_app_preferences()`, filters the save
	 * verification result, and returns the updated post status, save verification status, and rendered content as a response.
	 *
	 * @since 3.2.0
	 *
	 * @deprecated 5.0.0 Use `divi_visual_builder_rest_save_post` hook instead.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error `WP_REST_Response` object on success, `WP_Error` object on failure.
	 */
	public static function update( WP_REST_Request $request ) {
		$post_id       = (int) $request->get_param( 'post_id' );
		$post_status   = $request->get_param( 'post_status' );
		$content       = $request->get_param( 'content' );
		$preferences   = $request->get_param( 'preferences' );
		$page_settings = $request->get_param( 'pageSettings' );
		$sync_type     = $request->get_param( 'syncType' );
		$options       = $request->get_param( 'options' );
		$workspace     = $request->get_param( 'workspace' );

		if ( isset( $page_settings['customCss'] ) ) {
			// update_post_meta() in et_builder_update_settings() removes backslashes from the value, this will be problematic for
			// Codemirror custom css, so we have to double escape the backslashes, to make sure backslashes are saved
			// correctly into the database. Context: https://github.com/elegantthemes/submodule-builder/pull/10314.
			$page_settings['customCss'] = str_replace( '\\', '\\\\', $page_settings['customCss'] );
		}

		$post_content = isset( $content['post_content'] ) ? $content['post_content'] : '';

		// Extract off-canvas data and make it available for hooks.
		$off_canvas_data = $request->get_param( 'off_canvas_data' );
		if ( $off_canvas_data ) {
			// Normalize to array format - decode JSON string if needed.
			$normalized_canvas_data = self::_normalize_off_canvas_data( $off_canvas_data );
			if ( $normalized_canvas_data ) {
				// Store off-canvas data globally so hooks can access it.
				$GLOBALS['divi_off_canvas_data'] = $normalized_canvas_data;
			}
		}

		// Check if there is an autosave that is newer.
		$post_author = get_current_user_id();

		// Store one autosave per author. If there is already an autosave, overwrite it.
		$autosave = wp_get_post_autosave( $post_id, $post_author );

		if ( ! empty( $autosave ) ) {
			wp_delete_post_revision( $autosave->ID );
		}

		// Check if what is being synced to server is preview post.
		$is_saving_preview = 'preview' === $sync_type;

		// Preview post aims to have similar post previewing experience as WordPress block editor.
		// How preview post works in WordPress block editor:
		// 1. Preview button is clicked.
		// 2. REST request is sent to `/wp-json/wp/v2/pages/POST_ID/autosaves?_locale=user` endpoint.
		// 3. An autosave post for current post is created. Autosave post is another record on `wp_post` table that:
		// - has post_status: `inherit`
		// - has post_type: `revision`
		// - has post_parent pointing to the published / draft post ID
		// 4. Basically the autosave post is the most recent revision of the post. A user commonly can have only have
		// one autosave per post.
		// 5. Once autosave post is created, the preview post URL is generated and returned to the client.
		// 6. User is being redirected to the preview post URL
		// For WordPress block editor's preview post mechanism, see: `WP_REST_Autosaves_Controller->create_item()`.

		if ( $is_saving_preview ) {
			// Create new autosave as post revision that will be used for preview.
			$revision_id = Revision::put_post_revision(
				[
					'ID'           => $post_id,
					'post_title'   => $page_settings['postTitle'] ?? '',

					// `_wp_put_post_revision()` already does the `wp_slash()` for the content. No need apply `wp_slash()` here.
					'post_content' => $post_content,
				],
				true
			);

			// Create preview post link.
			// Equivalent of this on WordPress block editor can be seen at WP_REST_Autosaves_Controller->prepare_item_for_response().
			$preview_url = get_preview_post_link(
				$post_id,
				[
					'preview_id'    => $post_id,
					'preview_nonce' => wp_create_nonce( 'post_preview_' . $post_id ),
				]
			);

			return self::response_success(
				[
					'previewId'         => $revision_id,
					'previewUrl'        => $preview_url,
					'syncType'          => 'preview',
					'post_status'       => $post_status,
					'save_verification' => $revision_id ? true : false,
				]
			);
		}

		// Build the post update array.
		$post_update_data = [
			'ID'           => $post_id,
			'post_content' => wp_slash( $post_content ),
			'post_status'  => $post_status,
		];

		// Include post_title and post_excerpt in this update to avoid a second wp_update_post() call.
		// This prevents duplicate revisions and duplicate filter processing.
		// Note: wp_update_post() internally calls sanitize_post() which sanitizes both fields,
		// but we sanitize here for consistency with the existing approach in et_builder_update_settings().
		if ( $page_settings ) {
			if ( isset( $page_settings['postTitle'] ) ) {
				$post_update_data['post_title'] = sanitize_text_field( $page_settings['postTitle'] );
			}

			if ( isset( $page_settings['postExcerpt'] ) ) {
				// Use wp_kses_post for excerpt to allow certain HTML tags (same as et_builder_update_settings).
				$post_update_data['post_excerpt'] = wp_kses_post( $page_settings['postExcerpt'] );
			}
		}

		$update = wp_update_post( $post_update_data );

		// Save page settings.
		if ( $page_settings ) {
			SavingUtility::save_page_settings( $page_settings, $post_id );
		}

		/**
		 * Action hook to fire when the Post is being saved.
		 *
		 * This is for backward compatibility with hooks written for Divi version <5.0.0.
		 *
		 * @since 3.20
		 * @deprecated 5.0.0 Use `divi_visual_builder_rest_save_post` hook instead.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action(
			'et_save_post',
			$post_id
		);

		/**
		 * Action hook to fire when the Post is being saved.
		 *
		 * @since ??
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'divi_visual_builder_rest_save_post', $post_id );

		// Prime page cache AFTER off-canvas data and other post meta is saved.
		// This ensures that when the cache priming request triggers early detection,
		// all post meta (including appended canvas content) is available.
		SavingUtility::prime_page_cache_on_save( $post_id );

		if ( $update && ! is_wp_error( $update ) ) {
			// Update post meta so we know D5 is used and Readiness migrator will skip it.
			update_post_meta( $post_id, '_et_pb_use_divi_5', 'on' );
			// Also set the legacy meta for backward compatibility with D4 components.
			update_post_meta( $post_id, '_et_pb_use_builder', 'on' );

			$preferences = SavingUtility::sanitize_app_preferences( $preferences );
			foreach ( $preferences as $preference_key => $preference_value ) {
				$option_name = 'et_fb_pref_' . $preference_key;
				et_update_option( $option_name, $preference_value );
			}

			// Updated last used workspace.
			$last_used_workspace = $workspace['last-used'] ?? [];

			Workspace::update_item( 'builtIn', 'last-used', $last_used_workspace );

			/**
			 * Action hook to fire when the Post is updated.
			 *
			 * This is for backward compatibility with hooks written for Divi version <5.0.0.
			 *
			 * @param int $post_id Post ID.
			 *
			 * @since 3.29
			 * @deprecated 5.0.0 Use `divi_visual_builder_rest_update_post` hook instead.
			 */
			do_action(
				'et_update_post',
				$post_id
			);

			/**
			 * Action hook to fire when the Post is updated.
			 *
			 * @param int $post_id Post ID.
			 *
			 * @since ??
			 */
			do_action( 'divi_visual_builder_rest_update_post', $post_id );

			$saved_post_content = get_post_field( 'post_content', $update );
			$verification       = self::_verify_post_content_matches_after_sanitization( $post_content, $saved_post_content );

			/**
			 * Filter to modify the save verification result.
			 *
			 * @since ??
			 * @deprecated 5.0.0 Use the {@see 'divi_visual_builder_rest_save_verification'} filter instead.
			 *
			 * @param bool $verification Whether to save the verification result.
			 */
			$verification = apply_filters(
				'et_fb_ajax_save_verification_result',
				$verification
			);

			/**
			 * Filter to modify the save verification result.
			 *
			 * @since ??
			 *
			 * @param bool $verification Whether to save the verification result.
			 */
			$save_verification_filtered = apply_filters(
				'divi_visual_builder_rest_save_post_save_verification',
				$verification
			);

			$return_rendered_content = $options['return_rendered_content'] ?? false;

			if ( $return_rendered_content ) {
				// Replace dynamic data in the content with the actual value.
				$normalized_content = DynamicData::get_processed_dynamic_data( $saved_post_content, $post_id, true );

				// Apply the_content filter to the content.
				$rendered_content = apply_filters( 'the_content', $normalized_content );
			} else {
				$rendered_content = $saved_post_content;
			}

			return self::response_success(
				[
					'post_status'       => get_post_status( $update ),
					'save_verification' => $save_verification_filtered,
					'rendered_content'  => $rendered_content,
				]
			);
		}

		if ( is_wp_error( $update ) ) {
			return self::response_error( $update->get_error_code(), $update->get_error_message(), $update->get_error_data() );
		}

		return self::response_error( 'unknown_error', esc_html__( 'Unknown error.', 'et_builder_5' ) );
	}

	/**
	 * Update action arguments.
	 *
	 * Retrieves the arguments for the update action endpoint.
	 * These arguments are used in `register_rest_route()` to define the endpoint parameters.
	 *
	 * @since ??
	 *
	 * @return array  An associative array of arguments for the update action endpoint.
	 *
	 * @example:
	 * ```php
	 * $args = SyncToServer::update_args();
	 *
	 * // Returns an associative array of arguments for the update action endpoint.
	 * ```
	 */
	public static function update_args(): array {
		return [
			'post_id'         => [
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'get_post',
			],
			'post_status'     => [
				'default'           => 'draft',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'syncType'        => [
				'default'           => 'draft',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'content'         => [
				'default'           => [],
				'sanitize_callback' => [ __CLASS__, 'sanitize_content' ],
			],
			'pageSettings'    => [
				'default'           => [],
				'sanitize_callback' => [ __CLASS__, 'sanitize_page_settings' ],
			],
			'off_canvas_data' => [
				'default'           => '',
				'sanitize_callback' => [ PortabilityController::class, 'sanitize_json_param' ],
			],
		];
	}

	/**
	 * Update action permission for a post.
	 *
	 * Checks if the current user has permission to update the post with the given ID and status.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request {
	 *     The REST request object.
	 *
	 *     @type string $post_id     The ID of the post to check permission for.
	 *     @type string $post_status The status of the post to check permission for.
	 * }
	 *
	 * @return bool|WP_Error Returns `true` if the current user has permission, `WP_Error` object otherwise.
	 *
	 * @example:
	 * ```php
	 * $request = new WP_REST_Request();
	 * $request->set_param( 'post_id', $post_id );
	 * $request->set_param( 'post_status', $post_status );
	 * $result = SyncToServer::update_permission( $request );
	 * ```
	 */
	public static function update_permission( WP_REST_Request $request ) {
		$post_id     = $request->get_param( 'post_id' );
		$post_status = $request->get_param( 'post_status' );

		if ( ! et_fb_current_user_can_save( $post_id, $post_status ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Sanitize the content array by preparing each content item for database storage.
	 *
	 * This function takes an array of contents and loops through the array and calls the
	 * `SavingUtility::prepare_content_for_db()` to sanitize each content item.
	 * The sanitized contents are then stored in a new array and returned.
	 *
	 * @since ??
	 *
	 * @param array $contents The array of contents to be sanitized.
	 *
	 * @return array The sanitized contents array
	 *
	 * @example:
	 * ```php
	 * $contents = [
	 *    'location1' => '<p>Content 1</p>',
	 *    'location2' => '<script>alert("Content 2")</script>'
	 * ];
	 *
	 * $sanitizedContents = SyncToServer::sanitize_content($contents);
	 * // Returns the sanitized contents array
	 * ```
	 */
	public static function sanitize_content( array $contents ): array {
		$sanitized = [];

		foreach ( $contents as $location => $content ) {
			$sanitized[ $location ] = SavingUtility::prepare_content_for_db( $content );
		}

		return $sanitized;
	}

	/**
	 * Sanitize the page settings array by preparing each content item for database storage.
	 *
	 * @since ??
	 *
	 * @param array $page_settings The array of page settings to be sanitized.
	 *
	 * @return array The sanitized page settings array
	 */
	public static function sanitize_page_settings( array $page_settings ): array {
		return SavingUtility::sanitize_page_settings( $page_settings );
	}


	/**
	 * Verify that post content matches after accounting for AttributeSecurity sanitization.
	 *
	 * This method handles the fact that AttributeSecurity may modify content during save
	 * (stripping JavaScript URLs, normalizing attribute names, etc.). It applies the same
	 * sanitization to the original content to check if the differences are just from
	 * sanitization rather than save failure.
	 *
	 * @since ??
	 *
	 * @param string $original_content The original post content sent from frontend.
	 * @param string $saved_content    The content that was actually saved to database.
	 *
	 * @return bool True if contents match after accounting for sanitization, false otherwise.
	 */
	private static function _verify_post_content_matches_after_sanitization( $original_content, $saved_content ): bool {
		// If contents match exactly, return true immediately.
		if ( $original_content === $saved_content ) {
			return true;
		}

		// Check if the content contains custom attributes that might have been sanitized.
		if ( strpos( $original_content, '"decoration":{' ) === false
			|| strpos( $original_content, '"attributes":{' ) === false ) {
			// No custom attributes present, use simple comparison.
			return $original_content === $saved_content;
		}

		// Apply the same AttributeSecurity sanitization to the original content.
		$sanitized_original = self::_apply_attribute_security_to_content( $original_content );

		return $sanitized_original === $saved_content;
	}

	/**
	 * Apply AttributeSecurity sanitization to content.
	 *
	 * This uses the actual AttributeSecurity class to sanitize content in exactly
	 * the same way it would be sanitized during the wp_insert_post_data hook.
	 *
	 * @since ??
	 *
	 * @param string $content The post content to sanitize.
	 *
	 * @return string The content after applying AttributeSecurity sanitization.
	 */
	private static function _apply_attribute_security_to_content( $content ): string {
		if ( empty( $content ) ) {
			return $content;
		}

		// Create a mock post data array to pass to AttributeSecurity.
		$post_data = [
			'post_content' => wp_slash( $content ),
		];

		// Create an instance of AttributeSecurity and apply its sanitization.
		$attribute_security = new AttributeSecurity();
		$sanitized_data     = $attribute_security->sanitize_custom_attributes_fields( $post_data );

		// Return the sanitized content, unslashed.
		return wp_unslash( $sanitized_data['post_content'] );
	}

	/**
	 * Normalize off-canvas data to array format.
	 *
	 * Decodes JSON string if needed, ensuring the caller always passes
	 * data in a single expected format (array) to downstream handlers.
	 *
	 * @since ??
	 *
	 * @param mixed $off_canvas_data Off-canvas data (array or JSON string).
	 *
	 * @return array|null Normalized array data, or null if invalid.
	 */
	private static function _normalize_off_canvas_data( $off_canvas_data ) {
		// If already an array, return as-is.
		if ( is_array( $off_canvas_data ) ) {
			return $off_canvas_data;
		}

		// If not a string, return null.
		if ( ! is_string( $off_canvas_data ) ) {
			return null;
		}

		// Try to decode JSON string.
		$decoded = json_decode( $off_canvas_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Try with stripslashes in case of escaped JSON.
			$decoded = json_decode( stripslashes( $off_canvas_data ), true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return null;
			}
		}

		// Ensure decoded result is an array.
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return $decoded;
	}
}
