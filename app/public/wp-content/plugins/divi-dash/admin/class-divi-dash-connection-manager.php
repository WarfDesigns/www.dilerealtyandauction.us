<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Connection_Manager
{
    const DIVI_DASH_ZIP_FILE_EXISTS_TRANSIENT = 'divi_dash_zip_files_exists_in_uploads_folder_transient';

    public static function get_divi_dash_connection_data()
    {
        $divi_dash_config_array = Divi_Dash_Plugin_Settings::get_divi_dash_config();
        $last_sync_date_timestamp = Divi_Dash_Plugin_Settings::divi_dash_get_option( Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION_KEYS['last_sync_date_timestamp'] );

        if( empty( $divi_dash_config_array ) || empty( $last_sync_date_timestamp ) ) {
            return array(
                'status' => false,
                'last_sync_text' => null,
            );
        }

        $last_sync = self::get_last_sync_human_readable_time( $last_sync_date_timestamp );

        return array(
            'status' => true,
            'last_sync_text' => sprintf( __( 'This site is connected and was last sync’d with Divi Dash %s ago.', 'divi-dash' ), $last_sync ),
        );
    }

    private static function get_last_sync_human_readable_time( $last_sync_date_timestamp )
    {
        $current_timestamp = divi_dash_kernel()::get_current_world_unix_time();

        return human_time_diff( $last_sync_date_timestamp, $current_timestamp );
    }

    public static function is_divi_dash_site_connected()
    {
        $last_sync_date_timestamp = Divi_Dash_Plugin_Settings::divi_dash_get_option( Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION_KEYS['last_sync_date_timestamp'] );

        return ! empty( $last_sync_date_timestamp );
    }

    public static function is_divi_dash_connection_failed()
    {
        if ( self::is_divi_dash_site_connected() ) {
            return false;
        }

        $files_exists_transient = get_transient( self::DIVI_DASH_ZIP_FILE_EXISTS_TRANSIENT );

        if ( $files_exists_transient === 'yes' ) {
            return true;
        }

        if ( $files_exists_transient === 'no' ) {
            return false;
        }

        $existing_files = self::get_divi_dash_plugin_old_zip_file_paths_from_uploads_directory();

        $files_exists = ! empty( $existing_files );

        set_transient( self::DIVI_DASH_ZIP_FILE_EXISTS_TRANSIENT, $files_exists ? 'yes' : 'no', 3600 ); // Expiration time is 1 hour

        return $files_exists;
    }

    public static function generate_divi_dash_connection_key()
    {
        $connection_key = wp_generate_password(64, false);
        $connection_key_splited = implode( '-', str_split( $connection_key, 18 ) );

        Divi_Dash_Plugin_Settings::update_divi_dash_connection_key( $connection_key_splited );

        return $connection_key_splited;
    }

    public static function update_last_sync_date_in_divi_dash_config()
    {
        $divi_dash_config_array = Divi_Dash_Plugin_Settings::get_divi_dash_config();

        if ( empty( $divi_dash_config_array ) ) {
            return;
        }

        $divi_dash_config_array['last_sync_date_timestamp'] = divi_dash_kernel()::get_current_world_unix_time();

        Divi_Dash_Plugin_Settings::update_divi_dash_config( $divi_dash_config_array );
    }

    public static function get_divi_dash_connection_details()
    {
        $connection_data = self::get_divi_dash_connection_data();
        $connection_key = Divi_Dash_Plugin_Settings::get_divi_dash_connection_key();

        if ( $connection_data['status'] === false && empty ( $connection_key ) ) {
            $connection_key = self::generate_divi_dash_connection_key();
        }

        return array(
            'divi_dash_connection_data' => $connection_data,
            'divi_dash_connection_key' => $connection_key,
            'contact_support_link' => 'https://www.elegantthemes.com/contact/',
            'successful_connection_image_link' => ET_DIVI_DASH_ADMIN_ASSETS_URI . '/images/divi-dash-successful-connection.svg',
        );
    }

    public static function get_divi_dash_plugin_old_zip_file_paths_from_uploads_directory()
    {
        $files = array();

        $uploads_directory = Divi_Dash_Helper::et_divi_dash_get_uploads_directory();

        if ( ! is_dir( $uploads_directory ) || ! is_writable( $uploads_directory ) ) {
            return $files;
        }

        $divi_dash_plugin_zip_filename = ET_DIVI_DASH_PLUGIN_ZIP_FILE;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $uploads_directory, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $file ) {
            // Validate that the file is within the specified directory
            $realPath = $file->getRealPath();
            if ( strpos( $realPath, realpath( $uploads_directory ) ) !== 0 ) {
                continue;
            }

            if ( $file->isFile() && $file->getFilename() === $divi_dash_plugin_zip_filename ) {
                $file_path = $file->getRealPath();
                $files[] = $file_path;
            }
        }

        return $files;
    }

    public static function delete_divi_dash_plugin_old_zip_files_from_uploads_directory()
    {
        $files_to_delete = self::get_divi_dash_plugin_old_zip_file_paths_from_uploads_directory();

        if ( empty( $files_to_delete ) || ! is_array( $files_to_delete ) ) {
            divi_dash_kernel()->dh_log_error( 'No files to delete' );
            return true;
        }

        foreach ( $files_to_delete as $file_path ) {
            divi_dash_kernel()->dh_log_error( 'Attempting to delete file: ' . $file_path );

            if ( unlink( $file_path ) ) {
                divi_dash_kernel()->dh_log_error( 'Successfully deleted file: ' . $file_path );
            } else {
                divi_dash_kernel()->dh_log_error( 'Failed to delete file: ' . $file_path );
            }
        }

        set_transient( self::DIVI_DASH_ZIP_FILE_EXISTS_TRANSIENT, 'no', 3600 ); // Expiration time is 1 hour

        return true;
    }

    public static function divi_dash_get_public_key()
    {
        $url = Divi_Dash_Env_Settings::Divi_Dash_Key_Server;

        $response = wp_remote_get($url);

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
            $public_key = wp_remote_retrieve_body( $response );
            return base64_decode( $public_key );
        }

        $error_message = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP error ' . wp_remote_retrieve_response_code( $response );
        divi_dash_kernel()->dh_log_error( 'Failed to download the public key: ' . $error_message );
    }

    public static function divi_dash_get_decrypted_data_from_post( $post_key = null )
    {
        divi_dash_kernel()->dh_log_error( 'Verification and decryption initiated' );

        if ( ! extension_loaded( 'openssl' ) ) {
            divi_dash_kernel()->dh_log_error( 'OpenSSL extension is not loaded' );
            return false;
        }

        if ( Divi_Dash_Plugin_Settings::divi_dash_maybe_already_banned_addr() ) {
            divi_dash_kernel()->send_error( 'Error: Your address has been banned' );
            return false;
        }

        $post_encrypted_data = [];
        $public_key = self::divi_dash_get_public_key();

        if ( ! $public_key ) {
            divi_dash_kernel()->send_error( 'Error: Unable to decrypt data, public key not available' );
            return null;
        }

        if ( $post_key ) {
            if ( ! Divi_Dash_Security::request_has( $post_key ) ) {
                divi_dash_kernel()->dh_log_error( 'The post key is not set' );
                return false;
            }
            $post_encrypted_data[] = Divi_Dash_Security::get_request_data( $post_key );
        } else {
            $post_encrypted_data = $_POST;
        }

        $decrypted_data_json = '';
        foreach ( $post_encrypted_data as $post_encrypted_data_item ) {

            $encrypted_data = base64_decode( $post_encrypted_data_item );

            $decrypted_data = '';
            if ( ! openssl_public_decrypt( $encrypted_data, $decrypted_data, $public_key, OPENSSL_PKCS1_PADDING ) ) {
                Divi_Dash_Plugin_Settings::divi_dash_register_unauth_request();
                divi_dash_kernel()->send_error( 'Error: Unable to decrypt data' );
                return null;
            }

            $decrypted_data_json .= $decrypted_data;
        }

        $decrypted_data = json_decode( $decrypted_data_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decrypted_data ) ) {
            divi_dash_kernel()->send_error( 'Error: Invalid Request! Corrupted data', ['Decrypted data is not valid JSON'] );
            return null;
        }

        Divi_Dash_Plugin_Settings::divi_dash_reset_banned_addr();
        divi_dash_kernel()->dh_log_error( 'Data verification and decryption successful' );

        if ( ! isset( $decrypted_data['timestamp'] ) ) {
            divi_dash_kernel()->send_error( 'Error: Invalid Request! The timestamp is missing' );
            return null;
        }

        $timestamp = $decrypted_data['timestamp'];
        divi_dash_kernel()->dh_log_error( 'The recived timestamp is ' . $timestamp );

        $current_timestamp = divi_dash_kernel()::get_current_world_unix_time();
        $time_difference = $current_timestamp - $timestamp;
        if ( $time_difference > Divi_Dash_Kernel::API_ACTION_LIFETIME ) {
            divi_dash_kernel()->send_error( 'Unable to proceed, Request payload is expired.' );
            return null;
        }

        return self::sanitize_decrypted_data( $decrypted_data );
    }

    private static function sanitize_decrypted_data( $decrypted_data )
    {
        $is_json = false;

        if ( is_string( $decrypted_data ) ) {
            $decoded_data = json_decode( $decrypted_data, true );

            if ( is_array( $decoded_data ) && json_last_error() == JSON_ERROR_NONE ) {
                $is_json = true;
                $decrypted_data = $decoded_data;
            }
        }

        if ( ! is_array( $decrypted_data ) ) {
            return sanitize_text_field( $decrypted_data );
        }

        foreach ( $decrypted_data as $key => $value ) {
            switch ($key) {
                case Divi_Dash_Kernel::CONFIG_INSTALLATION_PARAM_NAME:
                    $decrypted_data[$key] = self::sanitize_decrypted_data( $value );
                    break;

                case Divi_Dash_Kernel::WP_LOGIN_SECRET_PARAM_NAME:
                    // will be encrypted key/endpoint_url or user id ( encrypted key should not be sanitized )
                    $decrypted_data[$key] = $value;
                    break;

                case 'action':
                    $decrypted_data[$key] = sanitize_text_field( $value );
                    break;

                case 'action_data':
                    // action_data is encrypted value, do not sanitize
                    $decrypted_data[$key] = $value;
                    break;

                case 'is_confirming_action':
                    $decrypted_data[$key] = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    break;

                case 'timestamp':
                    $decrypted_data[$key] = intval( $value );
                    break;

                case Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION_KEYS['endpoint_url']:
                    $decrypted_data[$key] = sanitize_text_field( $value );
                    break;

                case Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION_KEYS['divi_hub_server_url']:
                    $decrypted_data[$key] = esc_url_raw( $value );
                    break;

                default:
                    if ( is_array( $value ) ) {
                        divi_dash_kernel()->dh_log_error( 'array for sanitization: ' . $key );

                        $decrypted_data[$key] = self::sanitize_decrypted_data( $value );
                    }

                    divi_dash_kernel()->dh_log_error( 'Unexpected key for sanitization: ' . $key );
                    break;
            }
        }

        if( $is_json === true ) {
            return wp_json_encode( $decrypted_data );
        }

        return $decrypted_data;
    }

    public static function divi_dash_get_decrypted_param( $post_key, $param_key )
    {
        $decrypted_data = self::divi_dash_get_decrypted_data_from_post( $post_key );

        if ( ! isset( $decrypted_data[$param_key] ) ) {
            divi_dash_kernel()->dh_log_error( 'The decrypted data is not valid JSON or the required key is missing' );
            return null;
        }

        return $decrypted_data[$param_key];
    }

    public static function verify_and_decrypt_api_action_data()
    {
        divi_dash_kernel()->dh_log_error( 'Verification and decryption of API action data initiated' );

        $data = self::divi_dash_get_decrypted_data_from_post();

        if ( ! is_array( $data ) ) {
            $error_message = "Error: Data is not in correct format. Missing required fields";

            divi_dash_kernel()->send_error( $error_message );
            return false;
        }

        $required_keys = array( 'action', 'action_data', 'is_confirming_action', 'timestamp' );

        if ( array_keys( $data ) !== $required_keys ) {
            $error_message = "Error: Missing required fields";

            divi_dash_kernel()->send_error( $error_message );
            return false;
        }

        if ( empty( $data['action'] ) ) {
            divi_dash_kernel()->send_error( 'Error: Corrupted data - required keys have empty values' );
            return false;
        }

        return $data;
    }
}
