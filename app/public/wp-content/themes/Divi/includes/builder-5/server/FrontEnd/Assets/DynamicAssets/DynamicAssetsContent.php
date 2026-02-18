<?php
/**
 * Dynamic Assets Content Handler.
 *
 * Handles content retrieval and manipulation for dynamic assets processing.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\CacheState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\FeatureState;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;

/**
 * Dynamic Assets Content class.
 *
 * Handles content retrieval and manipulation for dynamic assets processing.
 *
 * @since ??
 */
class DynamicAssetsContent {

	/**
	 * Cache state container.
	 *
	 * @var CacheState
	 */
	private CacheState $cache_state;

	/**
	 * Feature state container.
	 *
	 * @var FeatureState
	 */
	private FeatureState $feature_state;

	/**
	 * Constructor.
	 *
	 * @since ??
	 *
	 * @param CacheState   $cache_state  Cache state container.
	 * @param FeatureState $feature_state Feature state container.
	 */
	public function __construct( CacheState $cache_state, FeatureState $feature_state ) {
		$this->cache_state   = $cache_state;
		$this->feature_state = $feature_state;
	}

	/**
	 * Gets the all content for dynamic asset processing.
	 *
	 * @since ??
	 *
	 * @return string The content to process for dynamic assets.
	 */
	public function get_all_content(): string {
		return $this->cache_state->all_content;
	}

	/**
	 * Sets the all content for dynamic asset processing.
	 *
	 * @since ??
	 *
	 * @param string $all_content The content to set.
	 *
	 * @return void
	 */
	public function set_all_content( string $all_content ): void {
		$this->cache_state->all_content = $all_content;
	}

	/**
	 * Get Theme Builder template content for Font Awesome detection.
	 *
	 * Retrieves content from active Theme Builder templates (header, body, footer)
	 * to scan for Font Awesome icons when main page content is empty.
	 *
	 * @since ??
	 *
	 * @return string Combined content from all active Theme Builder templates.
	 */
	public function get_theme_builder_template_content(): string {
		// Get Theme Builder template IDs that are already available in the class.
		if ( empty( $this->cache_state->tb_template_ids ) || ! is_array( $this->cache_state->tb_template_ids ) ) {
			return '';
		}

		$template_contents = [];

		// Check each Theme Builder template type (header, body, footer).
		foreach ( $this->cache_state->tb_template_ids as $template_key => $template_id ) {
			// Template ID can be numeric or string.
			$template_id_int = is_numeric( $template_id ) ? intval( $template_id ) : 0;

			if ( $template_id_int > 0 ) {
				$template_post = \get_post( $template_id_int );
				if ( $template_post && $template_post instanceof \WP_Post && ! empty( $template_post->post_content ) ) {
					$template_contents[] = $template_post->post_content;
				}
			}
		}

		return implode( ' ', $template_contents );
	}

	/**
	 * Adds global modules' content (if any) on top of post content so that
	 * that all blocks can be properly registered.
	 *
	 * @since ??
	 *
	 * @param string $content The post content.
	 *
	 * @return string Content with global modules prepended.
	 */
	public function maybe_add_global_modules_content( string $content ): string {
		// Get a list of any global modules used in the post content.
		$found_global_modules = DetectFeature::get_global_module_ids( $content );

		// Deduplicate the new global modules with the existing global modules.
		$global_modules = DynamicAssetsUtils::get_unique_array_values( $found_global_modules, $this->feature_state->global_modules );

		// When a Global module is added, the block is also added in post content. But afterwards if the Global
		// module is changed, the respective block in post content doesn't change accordingly.
		// Here We are detecting the changes using the `global_module` attribute. We are appending the *actual*
		// Global module content at the end, and we need to put the Global module content at beginning,
		// otherwise the Dynamic Asset mechanism won't be able to detect the changes.
		if ( ! empty( $global_modules ) ) {
			foreach ( $global_modules as $global_post_id ) {
				$global_module = get_post( $global_post_id );

				if ( isset( $global_module->post_content ) ) {
					$content = $global_module->post_content . $content;
				}
			}
		}

		return $content;
	}

	/**
	 * Adds appended canvas content (local or global) for canvases that are:
	 * - Targeted by interactions from the main content
	 * - Explicitly appended above or below the main canvas
	 * - Included via canvas portals
	 * This ensures DynamicAssets processes all blocks that will be added to the page.
	 *
	 * @since ??
	 *
	 * @param string $content The post content.
	 * @param int    $post_id Post ID to get local canvases from.
	 *
	 * @return string Content with canvas content appended.
	 */
	public function maybe_add_appended_canvas_content( string $content, int $post_id ): string {
		if ( ! $post_id ) {
			return $content;
		}

		// Skip expensive canvas content fetching when in admin/builder context.
		// The builder doesn't need this for dynamic assets detection, and it causes
		// performance issues during builder load. Canvas content will be handled
		// on the client side in the builder.
		if ( Conditions::is_admin_request()
			|| Conditions::is_vb_enabled()
			|| Conditions::is_rest_api_request() ) {
			return $content;
		}

		// Get all appended canvas content (both interaction-targeted and explicitly appended).
		// This includes:
		// 1. Canvases with modules targeted by interactions from the main content.
		// 2. Canvases explicitly appended above or below the main canvas.
		$canvas_content = OffCanvasHooks::get_all_appended_canvas_content( $post_id, $content );

		// For Theme Builder templates, canvases are stored with the template post ID as parent,
		// not the current page post ID. Check for canvases using all active template post IDs
		// (header, body, and footer) to ensure early detection works for all template types.
		// Use existing utility to get all active Theme Builder template IDs.
		$tb_template_ids = DynamicAssetsUtils::get_theme_builder_template_ids();
		foreach ( $tb_template_ids as $tb_template_id ) {
			// Only check TB template if it's different from the current post ID.
			if ( $tb_template_id !== $post_id ) {
				$tb_canvas_content = OffCanvasHooks::get_all_appended_canvas_content( $tb_template_id, $content );
				if ( $tb_canvas_content ) {
					$canvas_content .= $tb_canvas_content;
				}
			}
		}

		if ( $canvas_content ) {
			// Append canvas content to post content so DynamicAssets can process it.
			$content = $content . $canvas_content;
		}

		return $content;
	}
}
