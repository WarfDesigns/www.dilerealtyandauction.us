<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Helper {
    public static function et_get_remote_address() {
        if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
        }

        if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            return trim( $ip_list[0] );
        }

        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    }

    public static function et_get_plugin_slug_from_uri( $plugin_uri )
    {
        $plugin_slug = explode( '/', $plugin_uri )[0];

        if ( str_contains( $plugin_slug, '.php' ) ) {
            str_replace( '.php', '', $plugin_slug );
        }

        return $plugin_slug;
    }

    public static function et_sanitize_request_data( $request_data )
    {
        if ( ! is_array( $request_data ) ) {
            throw new Exception( 'the passing request data is wrong!' );
        }

        foreach ( $request_data as $key => &$value ) {
            if ( is_array( $value ) ) {
                if ( $key === 'subsite_urls' ) {
                    foreach ( $value as $url_key => $url ) {
                        $escaped_urls[$url_key] = esc_url_raw( $url );
                    }

                    $value = $escaped_urls;

                    continue;
                }

                $value = self::et_sanitize_request_data( $value );

                continue;
            }

            switch ( $key )  {
                case 'website_url':
                    $value = esc_url_raw( $value );
                    break;

                case 'email':
                    $value = sanitize_email( $value );
                    break;

                case 'avatar':
                    $value = esc_url( $value );
                    break;

                default:
                    $value = sanitize_text_field( $value );
                    break;
            }
        }

        return $request_data;
    }

    public static function et_encode_request_data( $request_data )
    {
        return base64_encode( wp_json_encode( self::et_sanitize_request_data( $request_data ) ) );
    }

    public static function et_decode_request_data( $request_data )
    {
        return self::et_sanitize_request_data( json_decode( base64_decode( $request_data ), true ) );
    }

    public static function et_get_site_hostname()
    {
        $hostname = get_site_url();

        if ( ! $hostname ) {
            return 'Not found';
        }

        $hostname = str_ireplace( 'www.', '', wp_parse_url( $hostname, PHP_URL_HOST ) );

        return $hostname;
    }

    public static function et_prepare_url_to_response( $url )
    {
        return strtr( $url, '?', '|' );
    }

    /**
     * ET-ToDo: that comments should be deleted berfore release
     * processCallbackRequest,
     *
     * @param array $data - should contain some callback Data which will be used in the callbackActions on the HUB.
     * @param $log_message - should contains 'some service msg' hardcoded in the code.
     * @return void
     */
    public static function et_send_response( $data = [], $log_message = '' )
    {
        if ( ( empty( $data ) && empty( $log_message ) ) || ( ! empty( $data ) && ! is_array( $data ) ) || ( ! empty( $log_message ) && ! is_string( $log_message ) ) ) {
            wp_send_json_error( 'wrong response params' );
        }

        $data = [
            'response_data' => ! empty( $data ) ? self::et_encode_request_data( $data ) : null,
            'log' => $log_message,
        ];

        if ( divi_dash_kernel()->is_test_mode_on() ) {
            $response = ['success' => true];
            if ( isset( $data ) ) {
                $response['data'] = $data;
            }

            $output = wp_json_encode( $response );
            throw new Exception( $output );
        } else {
            wp_send_json_success( $data );
        }
    }

    public static function et_make_and_send_response( $results, $success_status, $fail_status, $is_successful = null )
    {
        $status = $fail_status;
        $message = '';

        if ( $results === true || $is_successful === true ) {
            $status = $success_status;
        }

        if ( ! empty( $results ) && is_array( $results ) ) {
            $status = $fail_status;
            $message = self::et_make_failed_update_message( $results );
        }

        divi_dash_kernel()->send_response_with_status( $status, $message );
    }

    public static function et_make_and_send_install_response( $install_results, $is_successful = null, $success_status = Divi_Dash_API_Response_Status::INSTALLED )
    {
        self::et_make_and_send_response( $install_results, $success_status, Divi_Dash_API_Response_Status::NOT_INSTALLED, $is_successful );
    }

    public static function et_make_and_send_update_response( $update_results, $is_update_successful = null )
    {
        self::et_make_and_send_response( $update_results, Divi_Dash_API_Response_Status::UPDATED, Divi_Dash_API_Response_Status::NOT_UPDATED, $is_update_successful );
    }

    public static function et_make_failed_update_message( $current_update_results )
    {
        if ( is_string( $current_update_results ) || ! is_array( $current_update_results ) ) {
            return $current_update_results;
        }

        return end( $current_update_results );
    }

    // Rate limiting function using WordPress Transients
    public static function et_check_rate_limit( $ip )
    {
        $request_count = get_transient( "et_login_attempt_{$ip}" );

        if ( $request_count === false ) {
            // IP not found in cache, setting up the first attempt.
            set_transient( "et_login_attempt_{$ip}", 1, 10 * MINUTE_IN_SECONDS ); // Set a 10-minute window

            return true;
        }

        if ( $request_count >= 5 ) { // Assuming max 5 attempts are allowed in 10 minutes
            return false;
        }

        // Incrementing attempt count and resetting the transient
        set_transient( "et_login_attempt_{$ip}", $request_count + 1, 10 * MINUTE_IN_SECONDS );

        return true;
    }

    public static function et_reset_check_limit( $ip ): void
    {
        delete_transient( "et_login_attempt_{$ip}" );
    }

    public static function et_get_failed_update_error_messages( $et_skin )
    {
        $update_errors = [];
        foreach ( $et_skin->feedback_messages as $message ) {
            $update_errors[] = $message;
        }

        return $update_errors;
    }

    public static function et_get_request_action( array $post_data )
    {
        return ! empty( $post_data['action'] ) ? $post_data['action'] : null;
    }

    public static function et_get_divi_dash_site_url()
    {
        $divi_hub_server_url = Divi_Dash_Plugin_Settings::divi_dash_get_option( Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION_KEYS['divi_hub_server_url'] );

        return ! empty( $divi_hub_server_url ) ? $divi_hub_server_url : Divi_Dash_Kernel::DIVI_DASH_SERVER_DEFAULT_URL;
    }

    public static function et_get_domain_and_path_from_subsite_url( $subsite_url )
    {
        if ( empty( $subsite_url ) ) {
            return [
                'domain' => '',
                'path' => '',
            ];
        }

        return [
            'domain' => wp_parse_url( $subsite_url, PHP_URL_HOST ),
            'path' => wp_parse_url( $subsite_url, PHP_URL_PATH ) . '/',
        ];
    }

    public static function et_get_blog_id_from_url( $subsite_url )
    {
        $domain_and_path = self::et_get_domain_and_path_from_subsite_url( $subsite_url );

        return get_blog_id_from_url( $domain_and_path['domain'], $domain_and_path['path'] );
    }

    public static function et_divi_dash_get_uploads_directory()
    {
        $uploads_dir_array = wp_upload_dir();

        return $uploads_dir_array['basedir'];
    }

    public static function et_update_account_api_key_username( $username, $api_key ) {
        $username = sanitize_text_field( $username );
        $api_key  = sanitize_text_field( $api_key );
        $et_updates_settings = get_option( 'et_automatic_updates_options' );
        if( !empty( $et_updates_settings['username'] ) ){
            return false;
        }
        $result = update_site_option(
            'et_automatic_updates_options',
            [
                'username' => $username,
                'api_key'  => $api_key,
            ]
        );
       return $result;
    }

    public static function is_wpe_site()
    {
        return defined('WPE_APIKEY');
    }

    public static function et_is_marketplace_plugin_has_valid_license( $plugin_file )
    {
        $update_plugins = get_site_transient( 'update_plugins' );

        if ( ! is_object( $update_plugins ) ) {
            return false;
        }

        if (
            isset( $update_plugins->response ) &&
            is_array( $update_plugins->response ) &&
            isset($update_plugins->response[$plugin_file]) &&
            property_exists($update_plugins->response[$plugin_file], 'package')
            ) {
            return true;
        }

        if (
            isset( $update_plugins->no_update ) &&
            is_array( $update_plugins->no_update ) &&
            isset($update_plugins->no_update[$plugin_file]) &&
            property_exists($update_plugins->no_update[$plugin_file], 'package')
            ) {
            return true;
        }

        return false;
    }

    public static function et_is_marketplace_theme_has_valid_license( $theme_slug )
    {
        $theme_slug = sanitize_key( $theme_slug );

        $update_themes = get_site_transient( 'update_themes' );

        if ( ! is_object( $update_themes ) ) {
            return false;
        }

        if (
            isset( $update_themes->response ) &&
            is_array( $update_themes->response ) &&
            isset( $update_themes->response[$theme_slug] ) &&
            isset( $update_themes->response[$theme_slug]['package'] )
        ) {
            return true;
        }

        if (
            isset( $update_themes->no_update ) &&
            is_array( $update_themes->no_update ) &&
            isset( $update_themes->no_update[$theme_slug] ) &&
            isset( $update_themes->no_update[$theme_slug]['package'] )
        ) {
            return true;
        }

        return false;
    }
}
