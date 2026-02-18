<?php
/**
 * Theme Customizer CSS output filters.
 *
 * @package Divi
 * @since ??
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Add #main-content background CSS for non-Divi Builder pages when Theme Customizer background is set.
 *
 * @since ??
 *
 * @param string $css Theme Customizer CSS output.
 *
 * @return string Modified CSS output.
 */
function et_divi_add_main_content_background_css( $css ) {
	$post_id          = et_core_page_resource_get_the_ID();
	$is_pagebuilder   = et_pb_is_pagebuilder_used( $post_id );
	$background_image = get_theme_mod( 'background_image', '' );
	$background_color = get_theme_mod( 'background_color', '' );

	if ( $is_pagebuilder ) {
		return $css;
	}

	$main_content_css = '';
	if ( $background_image ) {
		$main_content_css = '#main-content { background-color: transparent; }';
	} elseif ( $background_color ) {
		$background_color_with_hash = maybe_hash_hex_color( $background_color );
		$main_content_css           = sprintf(
			'#main-content { background-color: %s; }',
			esc_html( $background_color_with_hash )
		);
	}

	if ( $main_content_css ) {
		$css .= $main_content_css;
	}

	return $css;
}

add_filter( 'et_divi_theme_customizer_css_output', 'et_divi_add_main_content_background_css' );
