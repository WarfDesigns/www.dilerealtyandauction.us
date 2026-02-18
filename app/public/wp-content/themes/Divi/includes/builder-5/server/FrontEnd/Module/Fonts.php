<?php
/**
 * Frontend Font Loader
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\FrontEnd\Module;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\Conditions;

/**
 * Font Loader class.
 *
 * Responsible for loading fonts in the frontend.
 *
 * @since ??
 */
class Fonts {

	/**
	 * Keep track of Fonts added.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public static $_fonts_added = [];

	/**
	 * Add a font family to the store.
	 *
	 * Enqueue a given font family for use in the Builder.
	 *
	 * @since ??
	 *
	 * @param string $font_family The name of the font family.
	 *
	 * @return void
	 *
	 * @example: Enqueue the 'Open Sans' font family.
	 * ```php
	 * add_font_family( 'Open Sans' );
	 * ```
	 */
	public static function add( string $font_family ): void {
		if ( ! empty( $font_family ) && ! in_array( $font_family, self::$_fonts_added, true ) ) {
			self::$_fonts_added[] = $font_family;

			// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
			// TODO feat(D5, FE Rendering): Need to rewrite et_builder_enqueue_font in D5.
			et_builder_enqueue_font( $font_family );
		}
	}

	/**
	 * Enqueue user custom fonts
	 *
	 * This function is used to enqueue custom fonts specified by the user. It takes in an array of
	 * font URLs and registers them using the WordPress `wp_enqueue_style` function. This allows the
	 * fonts to be loaded on the front-end of the website.
	 *
	 * @since ??
	 *
	 * @see wp_enqueue_style() To register and enqueue the custom font stylesheets.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$heading_font        = et_get_option( 'heading_font', 'Open Sans' );
		$body_font           = et_get_option( 'body_font', 'Open Sans' );
		$body_font_weight    = et_get_option( 'body_font_weight', '500' );
		$heading_font_weight = et_get_option( 'heading_font_weight', '500' );

		// Map legacy Google Fonts names to their modern equivalents.
		// Google Fonts renamed "Source Sans Pro" to "Source Sans 3" in their API.
		// This ensures D4 sites with "Source Sans Pro" in Theme Customizer options
		// automatically load the correct modern font in D5.
		$font_aliases = [
			'Source Sans Pro'       => 'Source Sans 3',
			'Source Sans Pro Light' => 'Source Sans 3',
		];

		// Apply font aliases to heading and body fonts.
		if ( isset( $font_aliases[ $heading_font ] ) ) {
			$heading_font = $font_aliases[ $heading_font ];
		}

		if ( isset( $font_aliases[ $body_font ] ) ) {
			$body_font = $font_aliases[ $body_font ];
		}

		// Apply fallback to 'Open Sans' if font is empty to match CSS variable defaults.
		$heading_font = $heading_font ? $heading_font : 'Open Sans';
		$body_font    = $body_font ? $body_font : 'Open Sans';

		// Enqueue the fonts so they are actually loaded on the frontend.
		self::add( $heading_font );
		self::add( $body_font );

		$global_font_vars = '';
		$customizer_fonts = [
			'--et_global_heading_font'        => $heading_font,
			'--et_global_body_font'           => $body_font,
			'--et_global_heading_font_weight' => $heading_font_weight ? $heading_font_weight : '500',
			'--et_global_body_font_weight'    => $body_font_weight ? $body_font_weight : '500',
		];

		foreach ( $customizer_fonts as $var_name => $value ) {
			if ( ! empty( $value ) ) {
				// Quote all font family values except CSS keywords.
				$quote             = ( substr( $var_name, -5 ) === '_font' && 'none' !== $value ) ? "'" : '';
				$global_font_vars .= esc_html( $var_name ) . ': ' . $quote . esc_html( $value ) . $quote . ';';
			}
		}

		// Only load on FE. VB loads this via the modules root component.
		if ( ! empty( $global_font_vars ) && ! Conditions::is_vb_enabled() ) {
			$global_fonts_style = ':root{' . $global_font_vars . '}';

			echo '<style class="et-vb-global-data et-vb-global-fonts">';
			echo et_core_esc_previously( ( $global_fonts_style ) );
			echo '</style>';
		}
	}
}
