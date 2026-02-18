<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Plugin_Settings
{
    const DIVI_DASH_CONFIG_OPTION             = 'divi_dash_config';
    const DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY = 'divi_dash_unauth_request';
    const DIVI_DASH_CONNECTION_KEY_TRANSIENT  = 'divi_dash_connection_key';
    const DIVI_DASH_CONNECTION_KEY_TIMEOUT    = 10 * MINUTE_IN_SECONDS;    //10 minutes

    const DIVI_DASH_CONFIG_OPTION_KEYS = [
        'endpoint_url' => 'endpoint_url',
        'divi_hub_server_url' => 'divi_hub_server_url',
        'last_sync_date_timestamp' => 'last_sync_date_timestamp',
    ];

    public static function divi_dash_get_option( $option_name, $default = null )
    {
        return self::get_divi_dash_config( $option_name, $default );
    }

    public static function reset_divi_dash_config_on_plugin_activation( $plugin, $network_activation )
    {
        if ( $plugin !== ET_DIVI_DASH_PLUGIN_BASENAME ) {
            return;
        }

        add_option( self::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY, array() );

        if ( empty( self::get_divi_dash_config() ) || empty( self::divi_dash_get_option( self::DIVI_DASH_CONFIG_OPTION_KEYS['endpoint_url'] ) ) ) {
            self::delete_divi_dash_config();

            delete_transient( Divi_Dash_Connection_Manager::DIVI_DASH_ZIP_FILE_EXISTS_TRANSIENT );
        }
    }

    public static function divi_dash_register_unauth_request()
    {
        $unauth_request_data  = is_string( get_option( self::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY ) ) ? unserialize( get_option( self::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY ) ) : get_option( self::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY );
        $current_request_address = Divi_Dash_Helper::et_get_remote_address();

        $attempts_count = isset( $unauth_request_data[$current_request_address]['attempts'] ) ? (int) $unauth_request_data[$current_request_address]['attempts'] : 0;

        if ( $attempts_count > 9 ) {
            $unauth_request_data[$current_request_address] = array(
                'last_attemtp_datetime' => time(),
                'attempts' => $attempts_count + 1,
                'banned' => true
            );
            update_option( self::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY, ( $unauth_request_data ) );
            divi_dash_kernel()->send_error( 'Error: Your addr was banned.' );
        } else {
            $unauth_request_data[$current_request_address] = array(
                'last_attemtp_datetime' => time(),
                'attempts' => $attempts_count + 1,
                'banned' => false
            );
            update_option( self::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY, ( $unauth_request_data ) );
        }
    }

    public static function divi_dash_maybe_already_banned_addr()
    {
        $unauth_request_data  = get_option( self::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY );
        $current_request_address = Divi_Dash_Helper::et_get_remote_address();

        if ( isset( $unauth_request_data[$current_request_address]['banned'] ) && (bool) $unauth_request_data[$current_request_address]['banned'] ) {
            $attempts_count = ! empty ( $unauth_request_data[$current_request_address]['attempts'] ) ? (int) $unauth_request_data[$current_request_address]['attempts'] : 0;

            $seconds_to_unban = self::get_ban_time_in_seconds_based_on_number_of_attempts( $attempts_count );

            if ( time() - (int) $unauth_request_data[$current_request_address]['last_attemtp_datetime'] > $seconds_to_unban ) {
                return false;
            }

            return true;
        }

        return false;
    }

    private static function get_ban_time_in_seconds_based_on_number_of_attempts( $attempts_count )
    {
        if ( $attempts_count < 15 ) {
            return 3600; // 1 hour
        }

        if ( $attempts_count < 30 ) {
            return 24 * 3600; // 24 hours
        }

        return 24 * 30 * 3600; // 30 days
    }

    public static function divi_dash_reset_banned_addr()
    {
        $unauth_request_data  = ( get_option( self::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY ) );
        $current_request_address = Divi_Dash_Helper::et_get_remote_address();

        if ( isset( $unauth_request_data[$current_request_address] ) ) {
            $unauth_request_data[$current_request_address] = array(
                'last_attemtp_datetime' => 0,
                'attempts' => 0,
                'banned' => false
            );

            update_option( self::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY, ( $unauth_request_data ) );
        }
    }

    public static function get_divi_dash_connection_key()
    {
        return get_site_transient( Divi_Dash_Plugin_Settings::DIVI_DASH_CONNECTION_KEY_TRANSIENT );
    }

    public static function update_divi_dash_connection_key( $connection_key )
    {
        set_site_transient( Divi_Dash_Plugin_Settings::DIVI_DASH_CONNECTION_KEY_TRANSIENT, $connection_key, self::DIVI_DASH_CONNECTION_KEY_TIMEOUT );
    }

    public static function delete_divi_dash_connection_key()
    {
        delete_site_transient( Divi_Dash_Plugin_Settings::DIVI_DASH_CONNECTION_KEY_TRANSIENT );
    }

    public static function get_divi_dash_config( $key = null, $default = null )
    {
        $config_array = json_decode( get_site_option( self::DIVI_DASH_CONFIG_OPTION ), true );

        if ( ! empty( $key ) && is_array( $config_array ) ) {
            return $config_array[$key] ?? $default;
        }

        if ( ! empty( $key ) ) {
            return $default;
        }

        return $config_array;
    }

    public static function add_divi_dash_config( array $config_array )
    {
        add_site_option( Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION , wp_json_encode( $config_array ) );
    }

    public static function update_divi_dash_config( array $config_array )
    {
        update_site_option( Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION , wp_json_encode( $config_array ) );
    }

    public static function delete_divi_dash_config()
    {
        delete_site_option( Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION );
    }

    public static function delete_divi_dash_unauth_request_option()
    {
        delete_site_option( Divi_Dash_Plugin_Settings::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY );
    }
}
