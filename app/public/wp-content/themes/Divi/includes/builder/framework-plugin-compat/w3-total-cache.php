<?php
/**
 * Plugin compatibility for W3 Total Cache
 *
 * Handles JS minify conflicts between W3 Total Cache and the Divi Builder.
 *
 * @package Divi
 * @since ??
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin compatibility for W3 Total Cache
 *
 * @since ??
 * @link https://wordpress.org/plugins/w3-total-cache/
 */
class ET_Builder_Plugin_Compat_W3_Total_Cache extends ET_Builder_Plugin_Compat_Base {
	/**
	 * Constructor.
	 *
	 * @since ??
	 */
	public function __construct() {
		$this->plugin_id = 'w3-total-cache/w3-total-cache.php';
		$this->init_hooks();
	}

	/**
	 * Hook methods to WordPress.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Bail if there's no version found.
		if ( ! $this->get_plugin_version() ) {
			return;
		}

		if ( function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled() ) {
			add_action( 'divi_visual_builder_initialize', array( $this, 'maybe_disable_js_minify' ) );
		}
	}

	/**
	 * Maybe disable JS Minify.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function maybe_disable_js_minify() {
		// phpcs:ignore WordPress.Security.NonceVerification -- Nonce verification is not required here.
		if ( isset( $_GET['et_fb'] ) ) {
			// Disable JS Minify.
			add_filter( 'w3tc_minify_js_enable', '__return_false' );
		}
	}
}

new ET_Builder_Plugin_Compat_W3_Total_Cache();
