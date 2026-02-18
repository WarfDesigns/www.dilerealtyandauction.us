<?php
/**
 * Module: DynamicContentUtils class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentOptions;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\Shortcode\ShortcodeUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicContentUtils class.
 *
 * This class provides utility functions for retrieving dynamic content.
 *
 * This includes:
 * - Processing dynamic content value.
 * - Filtering the dynamic content value to resolve the value.
 * - Get custom meta option label.
 * - Get default option setting value.
 *
 * @since ??
 */
class DynamicContentUtils {

	/**
	 * Get the formatted custom meta label based on the given key.
	 *
	 * This function replaces underscores and dashes with spaces in the key, capitalizes
	 * the first letter of each word, and removes any leading or trailing spaces.
	 *
	 * @since ??
	 *
	 * @param string $key The custom meta key.
	 *
	 * @return string The custom meta label.
	 *
	 * @example:
	 * ```php
	 *  $key = 'my_custom_key';
	 *  $label = DynamicContentUtils::get_custom_meta_label($key);
	 *  echo $label;
	 * ```
	 *
	 * @output:
	 * ```php
	 * 'My Custom Key'
	 * ```
	 *
	 * @example:
	 * ```php
	 *  $key = 'another-custom-key';
	 *  $label = DynamicContentUtils::get_custom_meta_label($key);
	 *  echo $label;
	 * ```
	 *
	 * @output:
	 * ```php
	 *  'Another Custom Key'
	 * ```
	 *
	 * @example:
	 * ```php
	 *  $key = 'this_is-a_key';
	 *  $label = DynamicContentUtils::get_custom_meta_label($key);
	 *  echo $label;
	 * ```
	 *
	 * @output:
	 * ```php
	 *  'This Is A Key'
	 * ```
	 */
	public static function get_custom_meta_label( string $key ): string {
		$label = str_replace( [ '_', '-' ], ' ', $key );
		$label = ucwords( $label );
		$label = trim( $label );
		return $label;
	}

	/**
	 * Get the default value of a setting.
	 *
	 * Retrieves the default value of a setting based on the provided arguments. If the name or setting is not provided,
	 * an empty string is returned. The function uses the `DynamicContentOptions::get_options()` to retrieve the options for the
	 * specified post ID and then accesses the corresponding default value based on the provided name and setting.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   An array of arguments that define the context for retrieving the default value.
	 *
	 *   @type int    $post_id Optional. The ID of the post for which to retrieve the default value. Default is 0.
	 *   @type string $option  Optional. Option name. Default empty string.
	 *   @type string $setting Optional. Option settings. Default empty string.
	 * }
	 *                    - 'post_id'  (int)    The ID of the post for which to retrieve the default value. Default is 0.
	 *                    - 'name'     (string) The name of the option. Default is an empty string.
	 *                    - 'setting'  (string) The name of the setting. Default is an empty string.
	 *
	 * @return string The default value of the specified setting or an empty string if the name or setting is not provided.
	 *
	 * @example:
	 * ```php
	 * $args = [
	 *     'post_id' => 123,
	 *     'name'    => 'example_option',
	 *     'setting' => 'example_setting',
	 * ];
	 * $default_value = DynamicContentUtils::get_default_setting_value($args);
	 *
	 * echo $default_value;
	 * ```
	 *
	 * @output:
	 * ```php
	 * Example Default Value
	 * ```
	 */
	public static function get_default_setting_value( array $args ): string {
		$post_id = $args['post_id'] ?? 0;
		$name    = $args['name'] ?? '';
		$setting = $args['setting'] ?? '';

		if ( ! $name || ! $setting ) {
			return '';
		}

		$options = DynamicContentOptions::get_options( $post_id, 'edit' );

		return $options[ $name ]['fields'][ $setting ]['default'] ?? '';
	}

	/**
	 * Get the label of the post type.
	 *
	 * This function retrieves the post type label based on the provided post ID.
	 * If `get_post_type( $post_id )` return an empty value or the post type is a layout post type,
	 * the function returns the translated string 'Post'.
	 * Otherwise, it fetches the singular name of the post type from the post type object (via `get_post_type_object`).
	 *
	 * @since ??
	 *
	 * @param int $post_id The ID of the post.
	 *
	 * @return string The label of the post type.
	 *
	 * @example:
	 * ```php
	 * $post_type_label = DynamicContentUtils::get_post_type_label( $post_id );
	 * echo 'Post Type Label: ' . $post_type_label;
	 * ```
	 *
	 * @output:
	 * ```php
	 * Post Type Label: Blog Post
	 * ```
	 */
	public static function get_post_type_label( int $post_id ): string {
		$post_type = get_post_type( $post_id );

		// phpcs:ignore ET.Comments.Todo.TodoFound -- TODO has issue reference (#25149) but doesn't match exact PHPCS format requirement.
		// TODO feat(D5, Theme Builder): Replace `et_theme_builder_is_layout_post_type` once
		// the Theme Builder is implemented in D5.
		// @see https://github.com/elegantthemes/Divi/issues/25149.
		if ( ! $post_type || et_theme_builder_is_layout_post_type( $post_type ) ) {
			return esc_html__( 'Post', 'et_builder_5' );
		}

		return get_post_type_object( $post_type )->labels->singular_name;
	}

	/**
	 * Get common loop field definitions for dynamic content options.
	 *
	 * This function returns the common field definitions (before, after, loop_position)
	 * that are used across multiple dynamic content option classes.
	 *
	 * @since ??
	 *
	 * @return array The common field definitions.
	 */
	public static function get_common_loop_fields(): array {
		return [
			'before'        => [
				'label'   => esc_html__( 'Before', 'et_builder_5' ),
				'type'    => 'text',
				'default' => '',
			],
			'after'         => [
				'label'   => esc_html__( 'After', 'et_builder_5' ),
				'type'    => 'text',
				'default' => '',
			],
			'loop_position' => [
				'label'       => esc_html__( 'Loop Position', 'et_builder_5' ),
				'type'        => 'text',
				'default'     => '',
				'renderAfter' => 'n',
			],
		];
	}





	/**
	 * Get date format field definitions for dynamic content options.
	 *
	 * This function returns the common date format field definitions (date_format, custom_date_format)
	 * that are used across multiple dynamic content option classes that handle date values.
	 *
	 * @since ??
	 *
	 * @return array The date format field definitions.
	 */
	public static function get_date_format_fields(): array {
		return [
			'date_format'        => [
				'label'   => esc_html__( 'Date Format', 'et_builder_5' ),
				'type'    => 'select',
				'options' => [
					'default' => et_builder_i18n( 'Default' ),
					'M j, Y'  => esc_html__( 'Aug 6, 1999 (M j, Y)', 'et_builder_5' ),
					'F d, Y'  => esc_html__( 'August 06, 1999 (F d, Y)', 'et_builder_5' ),
					'm/d/Y'   => esc_html__( '08/06/1999 (m/d/Y)', 'et_builder_5' ),
					'm.d.Y'   => esc_html__( '08.06.1999 (m.d.Y)', 'et_builder_5' ),
					'j M, Y'  => esc_html__( '6 Aug, 1999 (j M, Y)', 'et_builder_5' ),
					'l, M d'  => esc_html__( 'Tuesday, Aug 06 (l, M d)', 'et_builder_5' ),
					'custom'  => esc_html__( 'Custom', 'et_builder_5' ),
				],
				'default' => 'default',
			],
			'custom_date_format' => [
				'label'   => esc_html__( 'Custom Date Format', 'et_builder_5' ),
				'type'    => 'text',
				'default' => '',
				'show_if' => [
					'date_format' => 'custom',
				],
			],
		];
	}

	/**
	 * Get processed dynamic content value.
	 *
	 * Retrieves the resolved value of dynamic content based on the provided arguments.
	 * If the name of the dynamic content is empty, an empty string will be returned.
	 *
	 * @since ??
	 *
	 * @param array       $value            Array of dynamic content values.
	 * @param int|null    $loop_id          Optional. The loop post ID for loop context. Default `null`.
	 * @param string|null $loop_query_type  Optional. The loop query type. Default `null`.
	 * @param mixed       $loop_object      Optional. The loop object (WP_Post, WP_User, WP_Term, etc.). Default `null`.
	 *
	 * @return string The resolved value of the dynamic content.
	 *
	 * @example:
	 * ```php
	 * $value = [
	 *   'name'     => 'dynamic_content_name',
	 *   'settings' => [
	 *     'setting1' => 'value1',
	 *     'setting2' => 'value2',
	 *   ],
	 * ];
	 * $processed_content = DynamicContentUtils::get_processed_dynamic_content( $value );
	 * ```
	 */
	public static function get_processed_dynamic_content( array $value, ?int $loop_id = null, ?string $loop_query_type = null, $loop_object = null ): string {
		$name = $value['name'] ?? '';

		if ( empty( $name ) ) {
			return '';
		}

		$settings = $value['settings'] ?? [];
		$post_id  = $value['post_id'] ?? (
		\ET_Theme_Builder_Layout::is_theme_builder_layout() && \ET_Post_Stack::get_main_post()
			? \ET_Post_Stack::get_main_post_id()
			: (
				// For archive pages with no posts, use -1 instead of layout ID.
				// so dynamic content can resolve archive titles properly.
				is_archive()
					? -1
					: get_the_ID()
			) );

		// Sanitize settings with enhanced security validation.
		$sanitized_settings = [];
		foreach ( $settings as $key => $setting ) {
			// Type validation: ensure $setting is a string to prevent type confusion attacks.
			if ( ! is_string( $setting ) ) {
				continue;
			}

			// Validate key is also a string to prevent array key manipulation.
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( in_array( $key, [ 'before', 'after' ], true ) ) {
				// Handle escaped backslashes from JSON decoding using WordPress's recommended wp_unslash().
				// This is safer than stripslashes() as it's designed for WordPress data handling.
				$unescaped_setting          = wp_unslash( $setting );
				$sanitized_settings[ $key ] = wp_kses_post( $unescaped_setting );
			} else {
				// Apply consistent sanitization to all other fields.
				$sanitized_settings[ $key ] = wp_kses_post( $setting );
			}
		}

		// Determine context: 'edit' for Visual Builder (REST API requests), 'display' for frontend.
		// This ensures permission checks work correctly - custom fields should be restricted in edit mode
		// when permission is off, but always render on frontend for backward compatibility.
		$context = Conditions::is_rest_api_request() ? 'edit' : 'display';

		// Detect if this is a manual entry (user manually entered custom field name).
		// Manual entries should bypass permission check - this matches D4 behavior where
		// manual entries work even when permission is off.
		$is_manual_entry = false;
		if ( 'post_meta_key' === $name ) {
			// Check if it's a manual entry: either direct meta_key or select_meta_key is manual.
			$selected_meta_key = $sanitized_settings['select_meta_key'] ?? '';
			if ( 'custom_meta_manual_custom_field_value' === $selected_meta_key || ( empty( $selected_meta_key ) && ! empty( $sanitized_settings['meta_key'] ) ) ) {
				$is_manual_entry = true;
			}
		} elseif ( 'custom_meta_manual_custom_field_value' === $name ) {
			// Simple format: custom_meta_manual_custom_field_value is always a manual entry.
			$is_manual_entry = true;
		}

		$resolved_value = self::get_resolved_value(
			[
				'name'                  => sanitize_text_field( $name ),
				'settings'              => $sanitized_settings,
				'post_id'               => $post_id,
				'context'               => $context,
				'loop_id'               => $loop_id,
				'loop_query_type'       => $loop_query_type,
				'loop_object'           => $loop_object,
				'is_manual_entry'       => $is_manual_entry,

				// By default, empty value is allowed to make sure we follow the same behavior as D4
				// where the before and after text can be displayed even if the custom meta value is
				// empty or not set.
				'allow_render_on_empty' => true,
			]
		);

		return $resolved_value;
	}

	/**
	 * Get dynamic content resolved value based on the given arguments.
	 *
	 * This function retrieves the resolved value of a dynamic content option based on the specified arguments.
	 * This function runs the value through the `divi_module_dynamic_content_resolved_value` and
	 * `divi_module_dynamic_content_resolved_value_{$name}` filters.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   An array of arguments.
	 *
	 *   @type string  $name            Optional. The name of the option. Default empty string.
	 *   @type array   $settings        Optional. The settings of the option. Default `[]`.
	 *   @type integer $post_id         Optional. The ID of the post associated with the option. Default `null`.
	 *   @type string  $context         Optional. The context in which the option is used, e.g., `display` or `edit`. Default empty string.
	 *   @type array   $overrides       Optional. An associative array of option_name => value pairs to override the option value. Default `[]`.
	 *   @type bool    $is_content      Optional. Whether the dynamic content is used in the module's main_content field. Default `false`.
	 *   @type integer $loop_id         Optional. The loop post ID for loop context. Default `null`.
	 *   @type string  $loop_query_type Optional. The loop query type. Default `null`.
	 *   @type mixed   $loop_object     Optional. The loop object (WP_Post, WP_User, WP_Term, etc.). Default `null`.
	 * }
	 *
	 * @return string The resolved value of the dynamic content option.
	 *
	 * @example:
	 * ```php
	 *  $resolved_value = DynamicContentUtils::get_resolved_value(
	 *    [
	 *      'name'     => 'post_title',
	 *      'settings' => [],
	 *      'post_id'  => 123,
	 *      'context'  => 'display',
	 *    ]
	 *  );
	 * ```
	 */
	public static function get_resolved_value( array $args ): string {
		$name       = $args['name'] ?? '';
		$is_content = $args['is_content'] ?? false;
		$data_args  = [
			'name'                  => $name,
			'settings'              => $args['settings'] ?? [],
			'post_id'               => $args['post_id'] ?? null,
			'context'               => $args['context'] ?? '',
			'overrides'             => $args['overrides'] ?? [],
			'is_content'            => $is_content,
			'loop_id'               => $args['loop_id'] ?? null,
			'loop_query_type'       => $args['loop_query_type'] ?? null,
			'loop_object'           => $args['loop_object'] ?? null,
			'is_manual_entry'       => $args['is_manual_entry'] ?? false,

			// By default, empty value is allowed to make sure we follow the same behavior as D4
			// where the before and after text can be displayed even if the custom meta value is
			// empty or not set.
			'allow_render_on_empty' => $args['allow_render_on_empty'] ?? true,
		];

		$value = '';
		/**
		 * Filter dynamic content value to resolve based on given options and post.
		 *
		 * @since ??
		 *
		 * @param string $value     Dynamic content resolved value.
		 * @param array  $data_args {
		 *     An array of arguments.
		 *
		 *     @type string  $name            Option name.
		 *     @type array   $settings        Option settings.
		 *     @type integer $post_id         Post Id.
		 *     @type string  $context         Context e.g `edit`, `display`.
		 *     @type array   $overrides       An associative array of option_name => value to override option value.
		 *     @type bool    $is_content      Whether dynamic content used in module's main_content field.
		 *     @type integer $loop_id         The loop post ID for loop context.
		 *     @type string  $loop_query_type The loop query type.
		 *     @type mixed   $loop_object     The loop object (WP_Post, WP_User, WP_Term, etc.).
		 * }
		 */
		$value = apply_filters( 'divi_module_dynamic_content_resolved_value', $value, $data_args );

		/**
		 * Filter option-specific dynamic content value to resolve based on a given option and post.
		 *
		 * @since ??
		 *
		 * @param string $value     Dynamic content resolved value.
		 * @param array  $data_args {
		 *     An array of arguments.
		 *
		 *     @type string  $name            Option name.
		 *     @type array   $settings        Option settings.
		 *     @type integer $post_id         Post Id.
		 *     @type string  $context         Context e.g `edit`, `display`.
		 *     @type array   $overrides       An associative array of option_name => value to override option value.
		 *     @type bool    $is_content      Whether dynamic content used in module's main_content field.
		 *     @type integer $loop_id         The loop post ID for loop context.
		 *     @type string  $loop_query_type The loop query type.
		 *     @type mixed   $loop_object     The loop object (WP_Post, WP_User, WP_Term, etc.).
		 * }
		 */
		$value = apply_filters( "divi_module_dynamic_content_resolved_value_{$name}", $value, $data_args );

		$value = $is_content ? ShortcodeUtils::get_processed_embed_shortcode( $value ) : $value;

		return $is_content ? do_shortcode( $value ) : $value;
	}

	/**
	 * This function only strip the Dynamic Content D4 format. We keep it here to back port
	 * `get_strip_dynamic_content` function and as fallback just in case the old
	 * post content still contains Dynamic Content D4 format.
	 *
	 * @since ??
	 *
	 * @param string $content Post Content.
	 *
	 * @return string
	 */
	public static function get_strip_dynamic_content( $content ) {
		return preg_replace( '/@ET-DC@(.*?)@/', '', $content );
	}
}
