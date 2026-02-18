<?php
/**
 * ThemeBuilder: Class for ThemeBuilder Admin.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\ThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\VisualBuilder\Assets\AssetsUtility;
use ET\Builder\VisualBuilder\Assets\PackageBuildManager;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;

/**
 * Theme Builder Admin class.
 *
 * @since ??
 */
class Admin implements DependencyInterface {
	/**
	 * Load the class.
	 */
	public function load(): void {
		if ( et_builder_is_tb_admin_screen() ) {
			add_action( 'et_theme_builder_enqueue_scripts', [ $this, 'load_top_window_visual_builder_dependencies' ] );

			// WordPress 6.9+ automatically enqueues wp-core-commands on all admin pages.
			// Remove the action hook directly here to prevent conflicts with Divi 4's React 16
			// that's used by the Theme Builder UI. The action is registered in default-filters.php
			// which loads early, so it's safe to remove it here.
			remove_action( 'admin_enqueue_scripts', 'wp_enqueue_command_palette_assets' );
		}
	}

	/**
	 * Enqueue scripts and styles on Theme Bulder's admin page.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load_top_window_visual_builder_dependencies(): void {
		// Load Divi Cloud class to enable import and export functionality.
		if ( defined( 'ET_BUILDER_PLUGIN_ACTIVE' ) ) {
			require_once ET_BUILDER_PLUGIN_DIR . '/cloud/cloud-app.php';
		} else {
			require_once get_template_directory() . '/cloud/cloud-app.php';
		}

		\ET_Cloud_App::load_js( false, true );

		// Injected script that is required early (loading external .js will be too late).
		AssetsUtility::inject_preboot_script();

		AssetsUtility::enqueue_visual_builder_dependencies();

		// Injected style that is required early (loading external .css will be too late).
		AssetsUtility::inject_preboot_style();

		wp_register_script(
			'react-tiny-mce',
			ET_BUILDER_5_URI . '/visual-builder/assets/tinymce/tinymce.min.js',
			[],
			ET_BUILDER_VERSION,
			false
		);

		// Enqueue Google Maps API if needed.
		if ( et_pb_enqueue_google_maps_script() ) {
			wp_enqueue_script(
				'google-maps-api',
				esc_url(
					add_query_arg(
						[
							'key' => et_pb_get_google_api_key(),
						],
						is_ssl() ? 'https://maps.googleapis.com/maps/api/js' : 'http://maps.googleapis.com/maps/api/js'
					)
				),
				[],
				'3',
				true
			);
		}

		// Enqueue visual builder's core dependencies, which are built by WebPack as externals e.g. react, wp-data, wp-blocks.
		AssetsUtility::enqueue_visual_builder_dependencies();

		// Register package builds.
		PackageBuildManager::register_divi_package_builds();

		// Enqueue visual builder's packages' styles and scripts.
		PackageBuildManager::enqueue_scripts();
		PackageBuildManager::enqueue_styles();

		wp_enqueue_style( 'wp-color-picker' );

		// Enqueue cor font family, which is used for Visual Builder UI.
		et_core_load_main_fonts();

		// Enqueue FontAwesome icons CSS for icon picker functionality.
		// This is needed because DynamicAssets class (which normally handles FA loading).
		// only runs on frontend contexts, not in admin contexts like Theme Builder.
		// Bootstrap.php specifically excludes FrontEnd.php from admin requests (line 74-76).
		$fa_css_file = get_template_directory() . '/includes/builder/feature/dynamic-assets/assets/css/icons_fa_all.css';

		if ( file_exists( $fa_css_file ) ) {
			$product_dir      = get_template_directory_uri();
			$no_protocol_path = str_replace( [ 'http:', 'https:' ], '', $product_dir );

			// Initialize WordPress filesystem API to safely read CSS file contents.
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$css_content = $wp_filesystem->get_contents( $fa_css_file );

			if ( ! empty( $css_content ) ) {
				$processed_css = preg_replace( '/#dynamic-product-dir/i', $no_protocol_path, $css_content );

				// Create dedicated FontAwesome CSS handle and enqueue as inline styles.
				wp_register_style( 'divi-theme-builder-fontawesome', false, [], ET_BUILDER_VERSION );
				wp_enqueue_style( 'divi-theme-builder-fontawesome' );
				wp_add_inline_style( 'divi-theme-builder-fontawesome', $processed_css );
			}
		}

		/**
		 * Validate enqueued scripts dependencies and trigger error if non-existing script is found.
		 *
		 * By default, WordPress lacks a validation mechanism for script dependencies. This leads to a silent failure
		 * and the script is not enqueued if any of its dependencies are missing. Given our extensive use of scripts
		 * a missing dependency can lead us down to the rabbit hole.
		 */
		AssetsUtility::validate_enqueue_script_dependencies();
	}
}
