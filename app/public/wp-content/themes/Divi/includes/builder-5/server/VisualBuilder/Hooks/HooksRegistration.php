<?php
/**
 * Hooks: Hooks class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\VisualBuilder\Fonts\FontsUtility;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\VisualBuilder\REST\Portability\PortabilityController;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentElements;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;

/**
 * `HooksRegistration` class is consisted of WordPress hook functions used in Visual Builder, It registers them upon calling `load()`.
 *
 * This is a dependency class and can be used as dependency for `DependencyTree`.
 *
 * @since ??
 */
class HooksRegistration implements DependencyInterface {

	/**
	 * Check the file type and extension for font files.
	 *
	 * Filters the "real" file type of the given font file.
	 *
	 * @since ??
	 *
	 * @param array  $checked_filetype_and_ext {
	 *     Values for the extension, mime type, and corrected filename.
	 *     An associative array containing the file extension and file type.
	 *
	 *     @type string|false $ext             File extension, or false if the file doesn't match a mime type.
	 *     @type string|false $type            File mime type, or false if the file doesn't match a mime type.
	 *     @type string|false $proper_filename File name with its correct extension, or false if it cannot be determined.
	 * }
	 * @param string $file                     The full path to the font file.
	 * @param string $filename                 The name of the font file. (may differ from `$file` due to
	 *                                          `$file` being in a tmp directory).
	 *
	 * @return array An associative array containing the file extension, file type, and the sanitized file name.
	 *
	 * @example
	 * ```php
	 *      $checked_filetype_and_ext = array(
	 *          'ext'  => 'ttf',
	 *          'type' => 'application/octet-stream',
	 *      );
	 *      $file = '/path/to/file.ttf';
	 *      $filename = 'font.ttf';
	 *
	 *      FontsUtility::check_filetype_and_ext_font( $checked_filetype_and_ext, $file, $filename );
	 * ```
	 *
	 * @example:
	 * ```php
	 *      $checked_filetype_and_ext = array(
	 *          'ext'  => false,
	 *          'type' => false,
	 *      );
	 *      $file = '/path/to/invalid_file.ttf';
	 *      $filename = 'invalid_font.ttf';
	 *
	 *      FontsUtility::check_filetype_and_ext_font( $checked_filetype_and_ext, $file, $filename );
	 * ```
	 */
	public static function check_filetype_and_ext_font( array $checked_filetype_and_ext, string $file, string $filename ): array {
		$mimes_font = FontsUtility::mime_types_font();

		// Only process if the file exist and PHP extension "fileinfo" is loaded.
		if ( file_exists( $file ) && extension_loaded( 'fileinfo' ) ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );
			// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
			$ext = $ext ? strtolower( $ext ) : $ext;

			if ( $ext && $ext !== $filename && isset( $mimes_font[ $ext ] ) ) {
				// Get the real mime type.
				$finfo     = finfo_open( FILEINFO_MIME_TYPE );
				$real_mime = finfo_file( $finfo, $file );
				finfo_close( $finfo );

				if ( $real_mime && in_array( $real_mime, $mimes_font[ $ext ], true ) ) {
					return [
						'ext'             => $ext,
						'type'            => $real_mime,
						'proper_filename' => sanitize_file_name( $filename ),
					];
				}
			}

			return [
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			];
		}

		$ext  = isset( $checked_filetype_and_ext['ext'] ) ? $checked_filetype_and_ext['ext'] : false;
		$type = isset( $checked_filetype_and_ext['type'] ) ? $checked_filetype_and_ext['type'] : false;
		// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
		$ext = $ext ? strtolower( $ext ) : $ext;

		if ( $ext && $type && isset( $mimes_font[ $ext ] ) && in_array( $type, $mimes_font[ $ext ], true ) ) {
			return $checked_filetype_and_ext;
		}

		return [
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		];
	}

	/**
	 * Filters the "real" file type of the given JSON file.
	 *
	 * @since ??
	 *
	 * @param array  $checked_filetype_and_ext {
	 *     Values for the extension, mime type, and corrected filename.
	 *
	 *     @type string|false $ext             File extension, or false if the file doesn't match a mime type.
	 *     @type string|false $type            File mime type, or false if the file doesn't match a mime type.
	 *     @type string|false $proper_filename File name with its correct extension, or false if it cannot be determined.
	 * }
	 *
	 * @param string $file                      Full path to the file.
	 * @param string $filename                  The name of the file (may differ from $file due to
	 *                                          $file being in a tmp directory).
	 *
	 * @return array
	 */
	public static function check_filetype_and_ext_json( array $checked_filetype_and_ext, string $file, string $filename ): array {
		$mimes_json = PortabilityController::mime_types_json();

		// Only process if the file exist and PHP extension "fileinfo" is loaded.
		if ( file_exists( $file ) && extension_loaded( 'fileinfo' ) ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );
			// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
			$ext = $ext ? strtolower( $ext ) : $ext;

			if ( $ext && $ext !== $filename && isset( $mimes_json[ $ext ] ) ) {
				// Get the real mime type.
				$finfo     = finfo_open( FILEINFO_MIME_TYPE );
				$real_mime = finfo_file( $finfo, $file );
				finfo_close( $finfo );

				// sometimes finfo_file() returns "text/html" or similar for JSON files/JSON content.
				// in this case, we need to check if the file has valid JSON content.
				// if it is, we can safely assume that the file is a JSON file.
				// see https://github.com/elegantthemes/Divi/issues/39203.
				if ( ! in_array( $real_mime, $mimes_json[ $ext ], true ) && 'json' === $ext ) {
					global $wp_filesystem;

					json_decode( $wp_filesystem->get_contents( $file ) );

					if ( json_last_error() === JSON_ERROR_NONE ) {
						$real_mime = 'application/json';
					}
				}

				if ( $real_mime && in_array( $real_mime, $mimes_json[ $ext ], true ) ) {
					return [
						'ext'             => $ext,
						'type'            => $real_mime,
						'proper_filename' => sanitize_file_name( $filename ),
					];
				}
			}

			return [
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			];
		}

		$ext  = $checked_filetype_and_ext['ext'] ?? false;
		$type = $checked_filetype_and_ext['type'] ?? false;
		// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
		$ext = $ext ? strtolower( $ext ) : $ext;

		if ( $ext && $type && isset( $mimes_json[ $ext ] ) && in_array( $type, $mimes_json[ $ext ], true ) ) {
			return $checked_filetype_and_ext;
		}

		return [
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		];
	}

	/**
	 * Set uploads dir for the custom font files.
	 *
	 * Adds a custom subdirectory '/et-fonts' to the upload directory paths and URLs for font file uploads.
	 * If the $directory argument is passed with a 'basedir' key, the function will append the '/et-fonts' subdirectory to the directory path.
	 * If the $directory argument is passed with a 'baseurl' key, the function will append the '/et-fonts' subdirectory to the directory URL.
	 * Additionally, it sets the 'subdir' key in the $directory array to '/et-fonts'.
	 *
	 * @since ??
	 *
	 * @param array $directory {
	 *     An array of upload directory information.
	 *
	 *     @type string $basedir The base directory path for the upload directory.
	 *     @type string $path    The full path to the upload directory including the subdirectory '/et-fonts'.
	 *     @type string $url     The full URL to the upload directory including the subdirectory '/et-fonts'.
	 *     @type string $subdir  The subdirectory '/et-fonts'.
	 * }
	 *
	 * @return array The modified $directory array with the 'path', 'url', and 'subdir' keys.
	 *
	 * @example:
	 * ```php
	 *   Example 1: Adding '/et-fonts' subdirectory to the upload directory
	 *
	 *   $directory = array(
	 *       'basedir' => '/var/www/uploads',
	 *       'baseurl' => 'http://example.com/uploads'
	 *   );
	 *
	 *   $modified_directory = HooksRegistration::upload_dir_font( $directory );
	 * ```
	 *
	 * @output:
	 * ```php
	 *   // The $modified_directory array will be:
	 *   array (
	 *       'basedir' => '/var/www/uploads',
	 *       'path'    => '/var/www/uploads/et-fonts',
	 *       'baseurl' => 'http://example.com/uploads',
	 *       'url'     => 'http://example.com/uploads/et-fonts',
	 *       'subdir'  => '/et-fonts',
	 *   )
	 * ```
	 */
	public static function upload_dir_font( array $directory ): array {
		$subdir = '/et-fonts';

		if ( isset( $directory['basedir'] ) ) {
			$directory['path'] = $directory['basedir'] . $subdir;
		}

		if ( isset( $directory['baseurl'] ) ) {
			$directory['url'] = $directory['baseurl'] . $subdir;
		}

		$directory['subdir'] = $subdir;

		return $directory;
	}

	/**
	 * Load and register hook functions used in Visual Builder.
	 *
	 * Adds actions to update cached assets when custom fonts are added or removed.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		// Add action to update cached assets because custom fonts are included in static helpers.
		add_action( 'divi_visual_builder_fonts_custom_font_added', 'et_fb_delete_builder_assets' );
		add_action( 'divi_visual_builder_fonts_custom_font_removed', 'et_fb_delete_builder_assets' );

		// Dynamic Content Resolved Value.
		add_filter( 'divi_module_dynamic_content_resolved_value', [ $this, 'divi_module_theme_builder_default_dynamic_content_resolved_value' ], 15, 2 );

		// SVG Support: Enable SVG uploads when SVG plugins are detected.
		add_filter( 'upload_mimes', [ $this, 'enable_svg_upload_mimes' ], 10, 1 );

		// Initialize ACF taxonomy field processing hooks.
		DynamicContentACFUtils::init_hooks();
	}

	/**
	 * Resolve placeholder content for built-in dynamic content fields for Theme Builder layouts.
	 *
	 * @since ??
	 *
	 * @param mixed $content     The current value of the post featured image option.
	 * @param array $args {
	 *     Optional. An array of arguments for retrieving the post featured image.
	 *     Default `[]`.
	 *
	 *     @type string  $name       Optional. Option name. Default empty string.
	 *     @type array   $settings   Optional. Option settings. Default `[]`.
	 *     @type integer $post_id    Optional. Post Id. Default `null`.
	 *     @type string  $context    Optional. Context. Default `''`.
	 *     @type array   $overrides  Optional. An associative array of `option_name => value` to override option value.
	 *                               Default `[]`.
	 * }
	 *
	 * @return string
	 */
	public static function divi_module_theme_builder_default_dynamic_content_resolved_value( $content, array $args = [] ) {
		$name     = $args['name'] ?? '';
		$settings = $args['settings'] ?? [];
		$post_id  = $args['post_id'] ?? null;

		// Get post type from post id.
		$post_type = get_post_type( $post_id );

		if ( ! et_theme_builder_is_layout_post_type( $post_type ) && ! is_et_theme_builder_template_preview() ) {
			return $content;
		}

		// For search results, use real dynamic content instead of placeholders.
		if ( is_search() && 'post_title' === $name ) {
			return $content;
		}

		$placeholders = [
			'post_title'          => __( 'Your Dynamic Post Title Will Display Here', 'et_builder_5' ),
			'post_excerpt'        => __( 'Your dynamic post excerpt will display here. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus auctor urna eleifend diam eleifend sollicitudin a fringilla turpis. Curabitur lectus enim.', 'et_builder_5' ),
			'post_date'           => time(),
			'post_comment_count'  => 12,
			'post_categories'     => [
				__( 'Category 1', 'et_builder_5' ),
				__( 'Category 2', 'et_builder_5' ),
				__( 'Category 3', 'et_builder_5' ),
			],
			'post_tags'           => [
				__( 'Tag 1', 'et_builder_5' ),
				__( 'Tag 2', 'et_builder_5' ),
				__( 'Tag 3', 'et_builder_5' ),
			],
			'post_author'         => [
				'display_name'    => __( 'John Doe', 'et_builder_5' ),
				'first_last_name' => __( 'John Doe', 'et_builder_5' ),
				'last_first_name' => __( 'Doe, John', 'et_builder_5' ),
				'first_name'      => __( 'John', 'et_builder_5' ),
				'last_name'       => __( 'Doe', 'et_builder_5' ),
				'nickname'        => __( 'John', 'et_builder_5' ),
				'username'        => __( 'johndoe', 'et_builder_5' ),
			],
			'post_author_bio'     => __( 'Your dynamic author bio will display here. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus auctor urna eleifend diam eleifend sollicitudin a fringilla turpis. Curabitur lectus enim.', 'et_builder_5' ),
			'post_featured_image' => ET_BUILDER_PLACEHOLDER_LANDSCAPE_IMAGE_DATA,
			'term_description'    => __( 'Your dynamic category description will display here. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus auctor urna eleifend diam eleifend sollicitudin a fringilla turpis. Curabitur lectus enim.', 'et_builder_5' ),
			'site_logo'           => 'https://www.elegantthemes.com/img/divi.png',
		];

		$_        = et_();
		$wrapped  = false;
		$defaults = static function ( $post_id, $option, $setting ) {
			return DynamicContentUtils::get_default_setting_value(
				[
					'post_id' => $post_id,
					'name'    => $option,
					'setting' => $setting,
				]
			);
		};

		switch ( $name ) {
			case 'post_title':
				$content = et_core_intentionally_unescaped( $placeholders[ $name ], 'cap_based_sanitized' );
				break;

			case 'post_excerpt':
				$words     = (int) ( $settings['words'] ?? $defaults( $post_id, $name, 'words' ) );
				$read_more = $settings['read_more_label'] ?? $defaults( $post_id, $name, 'read_more_label' );
				$content   = esc_html( $placeholders[ $name ] );

				if ( $words > 0 ) {
					$content = wp_trim_words( $content, $words );
				}

				if ( ! empty( $read_more ) ) {
					$content .= sprintf(
						' <a href="%1$s">%2$s</a>',
						'#',
						esc_html( $read_more )
					);
				}
				break;

			case 'post_date':
				$format        = $settings['date_format'] ?? $defaults( $post_id, $name, 'date_format' );
				$custom_format = $settings['custom_date_format'] ?? $defaults( $post_id, $name, 'custom_date_format' );

				if ( 'default' === $format ) {
					$format = strval( get_option( 'date_format' ) );
				}

				if ( 'custom' === $format ) {
					$format = $custom_format;
				}

				$content = esc_html( gmdate( $format, $placeholders[ $name ] ) );
				break;

			case 'post_comment_count':
				$link    = $settings['link_to_comments_page'] ?? $defaults( $post_id, $name, 'link_to_comments_page' );
				$link    = 'on' === $link;
				$content = esc_html( $placeholders[ $name ] );

				if ( $link ) {
					$wrapped_content = DynamicContentElements::get_wrapper_element(
						[
							'post_id'  => $post_id,
							'name'     => $name,
							'value'    => $content,
							'settings' => $settings,
						]
					);
					$content         = sprintf(
						'<a href="%1$s">%2$s</a>',
						'#',
						et_core_esc_previously( $wrapped_content )
					);
					$wrapped         = true;
				}
				break;

			case 'post_categories': // Intentional fallthrough.
			case 'post_tags':
				$link      = $settings['link_to_term_page'] ?? $defaults( $post_id, $name, 'link_to_term_page' );
				$link      = 'on' === $link;
				$url       = '#';
				$separator = $settings['separator'] ?? $defaults( $post_id, $name, 'separator' );
				$separator = ! empty( $separator ) ? $separator : $defaults( $post_id, $name, 'separator' );
				$content   = $placeholders[ $name ];

				foreach ( $content as $index => $item ) {
					$content[ $index ] = esc_html( $item );

					if ( $link ) {
						$content[ $index ] = sprintf(
							'<a href="%1$s" target="%2$s">%3$s</a>',
							esc_url( $url ),
							esc_attr( '_blank' ),
							et_core_esc_previously( $content[ $index ] )
						);
					}
				}

				$content = implode( esc_html( $separator ), $content );
				break;

			case 'post_link':
				$text        = $settings['text'] ?? $defaults( $post_id, $name, 'text' );
				$custom_text = $settings['custom_text'] ?? $defaults( $post_id, $name, 'custom_text' );
				$label       = 'custom' === $text ? $custom_text : $placeholders['post_title'];
				$content     = sprintf(
					'<a href="%1$s">%2$s</a>',
					'#',
					esc_html( $label )
				);
				break;

			case 'post_author':
				$name_format = $settings['name_format'] ?? $defaults( $post_id, $name, 'name_format' );
				$link        = $settings['link'] ?? $defaults( $post_id, $name, 'link' );
				$link        = 'on' === $link;
				$label       = isset( $placeholders[ $name ][ $name_format ] ) ? $placeholders[ $name ][ $name_format ] : '';
				$url         = '#';

				$content = esc_html( $label );

				if ( $link && ! empty( $url ) ) {
					$content = sprintf(
						'<a href="%1$s" target="%2$s">%3$s</a>',
						esc_url( $url ),
						esc_attr( '_blank' ),
						et_core_esc_previously( $content )
					);
				}
				break;

			case 'post_author_bio':
				$content = et_core_intentionally_unescaped( $placeholders[ $name ], 'cap_based_sanitized' );
				break;

			case 'term_description':
				$content = et_core_intentionally_unescaped( $placeholders[ $name ], 'cap_based_sanitized' );
				break;

			case 'post_link_url':
				$content = '#';
				break;

			case 'post_author_url':
				$content = '#';
				break;

			case 'post_featured_image':
				$content = et_core_intentionally_unescaped( $placeholders[ $name ], 'fixed_string' );
				break;

			case 'site_logo':
				if ( empty( $content ) ) {
					$content = esc_url( $placeholders[ $name ] );
				} else {
					$wrapped = true;
				}
				break;

			default:
				// Avoid unhandled cases being wrapped twice by the default resolve and this one.
				$wrapped = true;
				break;
		}

		if ( $_->starts_with( $name, 'custom_meta_' ) ) {
			$meta_key   = substr( $name, strlen( 'custom_meta_' ) );
			$meta_value = get_post_meta( $post_id, $meta_key, true );
			if ( empty( $meta_value ) ) {
				$content = DynamicContentUtils::get_custom_meta_label( $meta_key );
			} else {
				$wrapped = true;
			}
		}

		if ( ! $wrapped ) {
			$content = DynamicContentElements::get_wrapper_element(
				[
					'post_id'  => $post_id,
					'name'     => $name,
					'value'    => $content,
					'settings' => $settings,
				]
			);
			$wrapped = true;
		}

		return $content;
	}

	/**
	 * Filters the "real" file type of the given image file.
	 *
	 * @since ??
	 *
	 * @param array  $checked_filetype_and_ext {
	 *     Values for the extension, mime type, and corrected filename.
	 *
	 *     @type string|false $ext             File extension, or false if the file doesn't match a mime type.
	 *     @type string|false $type            File mime type, or false if the file doesn't match a mime type.
	 *     @type string|false $proper_filename File name with its correct extension, or false if it cannot be determined.
	 * }
	 *
	 * @param string $file                      Full path to the file.
	 * @param string $filename                  The name of the file (may differ from $file due to
	 *                                          $file being in a tmp directory).
	 *
	 * @return array
	 */
	public static function check_filetype_and_ext_image( array $checked_filetype_and_ext, string $file, string $filename ): array {
		// Supported media mime types (images and videos) for portability import.
		$mimes_media = [
			// Images.
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpe'  => 'image/jpeg',
			'gif'  => 'image/gif',
			'png'  => 'image/png',
			'bmp'  => 'image/bmp',
			'tiff' => 'image/tiff',
			'tif'  => 'image/tiff',
			'webp' => 'image/webp',
			'avif' => 'image/avif',
			'ico'  => 'image/x-icon',
			'heic' => 'image/heic',
			// Videos.
			'mp4'  => 'video/mp4',
			'webm' => 'video/webm',
			'ogv'  => 'video/ogg',
			'avi'  => 'video/avi',
			'mov'  => 'video/quicktime',
			'wmv'  => 'video/x-ms-wmv',
			'flv'  => 'video/x-flv',
		];

		$allowed_mimes = get_allowed_mime_types();

		if ( in_array( 'image/svg+xml', $allowed_mimes, true ) ) {
			$mimes_media['svg']  = 'image/svg+xml';
			$mimes_media['svgz'] = 'image/svg+xml';
		}

		// Only process if the file exists and PHP extension "fileinfo" is loaded.
		if ( file_exists( $file ) && extension_loaded( 'fileinfo' ) ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );
			// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
			$ext = $ext ? strtolower( $ext ) : $ext;

			if ( $ext && isset( $mimes_media[ $ext ] ) ) {
				// Get the real mime type.
				$finfo     = finfo_open( FILEINFO_MIME_TYPE );
				$real_mime = finfo_file( $finfo, $file );
				finfo_close( $finfo );

				$is_valid_mime = ( $real_mime === $mimes_media[ $ext ] );

				if ( ! $is_valid_mime && in_array( $ext, [ 'svg', 'svgz' ], true ) ) {
					$is_valid_mime = in_array( $real_mime, [ 'image/svg+xml', 'text/xml', 'application/xml' ], true );
				}

				if ( $real_mime && $is_valid_mime ) {
					return [
						'ext'             => $ext,
						'type'            => $real_mime,
						'proper_filename' => sanitize_file_name( $filename ),
					];
				}
			}

			return [
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			];
		}

		$ext  = $checked_filetype_and_ext['ext'] ?? false;
		$type = $checked_filetype_and_ext['type'] ?? false;
		// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
		$ext = $ext ? strtolower( $ext ) : $ext;

		$is_valid_type = ( isset( $mimes_media[ $ext ] ) && $type === $mimes_media[ $ext ] );

		if ( ! $is_valid_type && in_array( $ext, [ 'svg', 'svgz' ], true ) ) {
			$is_valid_type = in_array( $type, [ 'image/svg+xml', 'text/xml', 'application/xml' ], true );
		}

		if ( $ext && $type && isset( $mimes_media[ $ext ] ) && $is_valid_type ) {
			return $checked_filetype_and_ext;
		}

		return [
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		];
	}

	/**
	 * Enable SVG uploads when SVG plugins are detected.
	 *
	 * This method ensures SVG files are properly supported in all contexts, including Visual Builder,
	 * when SVG plugins are available and the user has permission to upload SVG files.
	 *
	 * @since ??
	 *
	 * @param array $mimes Allowed mime types.
	 *
	 * @return array Modified mime types array.
	 */
	public function enable_svg_upload_mimes( array $mimes ): array {
		// Check if any SVG plugin is available.
		if ( ! $this->_is_svg_plugin_available() ) {
			return $mimes;
		}

		// Check user permission and allow site owners to override.
		$can_upload = (bool) apply_filters(
			'divi_current_user_can_upload_svg',
			(bool) apply_filters( 'safe_svg_current_user_can_upload', current_user_can( 'upload_files' ) )
		);

		if ( ! $can_upload ) {
			return $mimes;
		}

		// Add SVG mime types.
		if ( ! isset( $mimes['svg'] ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		if ( ! isset( $mimes['svgz'] ) ) {
			$mimes['svgz'] = 'image/svg+xml';
		}

		return $mimes;
	}

	/**
	 * Check if any SVG plugin is available (not necessarily active in current context).
	 *
	 * @since ??
	 *
	 * @return bool True if SVG plugin is available.
	 */
	private function _is_svg_plugin_available(): bool {
		// Check for Safe SVG plugin.
		if ( class_exists( 'SafeSvg\\safe_svg' ) ) {
			return true;
		}

		// Check for SVG Support plugin.
		if ( function_exists( 'bodhi_svgs_init' ) ) {
			return true;
		}

		// Check for Enable SVG Upload plugin.
		if ( function_exists( 'wp_svg_allowed' ) ) {
			return true;
		}

		// Check for Easy SVG Support plugin.
		if ( function_exists( 'esw_add_support' ) ) {
			return true;
		}

		// Do not fallback to generic allowed mime detection to avoid enabling
		// SVG without a known sanitizer plugin present.
		return false;
	}
}
