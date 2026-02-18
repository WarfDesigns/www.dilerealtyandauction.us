<?php
/**
 * MediaUtility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * MediaUtility class.
 *
 * This class contains methods for WordPress media and image operations.
 *
 * @since ??
 */
class MediaUtility {

	/**
	 * Get available WordPress image sizes for dropdown options.
	 *
	 * @since ??
	 *
	 * @return array Array of image sizes with labels and values.
	 */
	public static function get_image_sizes_options(): array {
		$sizes       = [];
		$image_sizes = get_intermediate_image_sizes();

		// Add full size first.
		$sizes['full'] = esc_html__( 'Full Size (Original)', 'et_builder_5' );

		// Add intermediate sizes.
		foreach ( $image_sizes as $size ) {
			$size_data = wp_get_additional_image_sizes()[ $size ] ?? [];

			if ( ! empty( $size_data ) ) {
				$width  = $size_data['width'] ?? 0;
				$height = $size_data['height'] ?? 0;
				$label  = ucfirst( str_replace( '_', ' ', $size ) );

				if ( $width && $height ) {
					$sizes[ $size ] = sprintf( '%s (%dx%d)', $label, $width, $height );
				} else {
					$sizes[ $size ] = $label;
				}
			} else {
				// For default WordPress sizes.
				switch ( $size ) {
					case 'thumbnail':
						$width          = get_option( 'thumbnail_size_w', 150 );
						$height         = get_option( 'thumbnail_size_h', 150 );
						$sizes[ $size ] = sprintf( '%s (%dx%d)', esc_html__( 'Thumbnail', 'et_builder_5' ), $width, $height );
						break;
					case 'medium':
						$width          = get_option( 'medium_size_w', 300 );
						$height         = get_option( 'medium_size_h', 300 );
						$sizes[ $size ] = sprintf( '%s (%dx%d)', esc_html__( 'Medium', 'et_builder_5' ), $width, $height );
						break;
					case 'large':
						$width          = get_option( 'large_size_w', 1024 );
						$height         = get_option( 'large_size_h', 1024 );
						$sizes[ $size ] = sprintf( '%s (%dx%d)', esc_html__( 'Large', 'et_builder_5' ), $width, $height );
						break;
					default:
						$sizes[ $size ] = ucfirst( str_replace( '_', ' ', $size ) );
				}
			}
		}

		return $sizes;
	}
}
