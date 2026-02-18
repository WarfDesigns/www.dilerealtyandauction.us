<?php
/**
 * Shortcode: ShortcodeUtils class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * ShortcodeUtils class.
 *
 * This class provides utility methods for handling shortcodes.
 *
 * @since ??
 */
class ShortcodeUtils {

	/**
	 * Original shortcode tags before wrapping.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_original_shortcode_tags = [];

	/**
	 * Wrap all registered shortcodes with Theme Builder context handling.
	 *
	 * Called lazily from Layout::render() when Theme Builder is actually rendering.
	 * Wraps third-party shortcodes to automatically fix the $post context during rendering.
	 *
	 * Excludes Divi's internal shortcodes (et_pb_*) to avoid regressions.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function wrap_shortcodes_for_theme_builder(): void {
		global $shortcode_tags;

		// Avoid double-wrapping if already wrapped.
		if ( ! empty( self::$_original_shortcode_tags ) ) {
			return;
		}

		// Save original tags.
		self::$_original_shortcode_tags = $shortcode_tags;

		// Wrap each third-party shortcode with our Theme Builder-aware wrapper.
		// Skip Divi's internal shortcodes (et_pb_*) to avoid regressions.
		foreach ( $shortcode_tags as $tag => $callback ) {
			// Skip Divi shortcodes - they may intentionally use layout post context.
			if ( 0 === strpos( $tag, 'et_pb_' ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily wrapping shortcodes for Theme Builder context fix.
			$shortcode_tags[ $tag ] = [ __CLASS__, 'shortcode_wrapper' ];
		}
	}

	/**
	 * Restore original shortcode callbacks after Theme Builder rendering.
	 *
	 * Called from Layout::render() after content is rendered to unwrap shortcodes.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function unwrap_shortcodes_for_theme_builder(): void {
		global $shortcode_tags;

		// Restore original shortcode tags if we wrapped them.
		if ( ! empty( self::$_original_shortcode_tags ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original shortcode callbacks.
			$shortcode_tags                 = self::$_original_shortcode_tags;
			self::$_original_shortcode_tags = [];
		}
	}

	/**
	 * Wrapper function for third-party shortcodes that fixes Theme Builder context.
	 *
	 * This wrapper is only registered for non-Divi shortcodes. It ensures they
	 * execute with the correct post context by temporarily replacing the global
	 * $post with the actual displayed post instead of the Theme Builder layout post.
	 *
	 * @since ??
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Shortcode content.
	 * @param string       $tag     Shortcode tag.
	 *
	 * @return string|mixed Shortcode output with correct Theme Builder context.
	 */
	public static function shortcode_wrapper( $atts, $content = null, $tag = '' ) {
		// Get original callback.
		$original_callback = self::$_original_shortcode_tags[ $tag ] ?? null;

		if ( ! $original_callback || ! is_callable( $original_callback ) ) {
			return '';
		}

		// Get the actual displayed post ID.
		$main_post_id    = class_exists( '\ET_Post_Stack' ) ? \ET_Post_Stack::get_main_post_id() : 0;
		$current_post_id = get_the_ID();

		// Get the actual displayed post object.
		$main_post = 0 < $main_post_id ? get_post( $main_post_id ) : null;

		// No context switching needed if post IDs match or main post invalid.
		if ( ! $main_post || $current_post_id === $main_post_id ) {
			return call_user_func( $original_callback, $atts, $content, $tag );
		}

		global $post;

		// Save the original post.
		$original_post = $post;

		// Temporarily replace global $post with the actual displayed post.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for Theme Builder shortcode context.
		$post = $main_post;

		// Set up post data for template tags.
		setup_postdata( $main_post );

		try {
			// Execute the original shortcode callback with correct post context.
			$output = call_user_func( $original_callback, $atts, $content, $tag );
		} finally {
			// Always restore original post, even if shortcode throws exception.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original state.
			$post = $original_post;

			// Reset post data.
			wp_reset_postdata();
		}

		return $output;
	}

	/**
	 * Get processed `embed` shortcode if the content has `embed` shortcode.
	 *
	 * This function checks if the provided content contains the `[embed][/embed]` shortcode and
	 * processes it using `$wp_embed->run_shortcode` from the global `$wp_embed` object.
	 *
	 * @since ??
	 *
	 * @param string $content Content to search for shortcodes.
	 *
	 * @return string Content with processed embed shortcode.
	 *
	 * @example:
	 * ```php
	 * $content = '[embed]http://www.wordpress.test/watch?v=embed-shortcode[/embed]';
	 * $processedContent = ShortcodeUtils::get_processed_embed_shortcode( $content );
	 * echo $processedContent;
	 *
	 * // Output: <a href="http://www.wordpress.test/watch?v=embed-shortcode">http://www.wordpress.test/watch?v=embed-shortcode</a>
	 * ```
	 */
	public static function get_processed_embed_shortcode( string $content ): string {
		if ( has_shortcode( $content, 'embed' ) ) {
			global $wp_embed;
			$content = $wp_embed->run_shortcode( $content );
		}

		return $content;
	}
}
