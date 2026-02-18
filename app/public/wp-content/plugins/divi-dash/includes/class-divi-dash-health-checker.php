<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Health_Checker
{
    const DIVI_DASH_CONNECTION_ATTEMPTED_OPTION = 'divi_dash_connection_attempted_option';

    const PUBLIC_KEY_SERVER = 'public_key_server';

    const TIME_SERVER = 'time_server';

    const OPENSSL = 'openssl';

    const HTTPS_STATUS = 'https_status';
    
    const TRANSIENTS_STATUS = 'transients_status';

    const SITE_HEALTH_CRITICAL_DATA_KEYS = array(
        self::PUBLIC_KEY_SERVER,
        self::TRANSIENTS_STATUS,
        self::TIME_SERVER,
        self::OPENSSL,
        self::HTTPS_STATUS,
    );

    const RECOMMENDED_VALUES = array(
        self::PUBLIC_KEY_SERVER => 'Available',
        self::TIME_SERVER => 'Available',
        self::OPENSSL => 'Installed',
        self::HTTPS_STATUS => 'Secure Connection (HTTPS)',
        self::TRANSIENTS_STATUS => 'Working',
    );
       
    const FAILED_VALUES = array(
        self::PUBLIC_KEY_SERVER => 'UnAvailable',
        self::TIME_SERVER => 'UnAvailable',
        self::OPENSSL => 'Not Installed',
        self::HTTPS_STATUS => 'Insecure Connection (HTTP)',
        self::TRANSIENTS_STATUS => 'Not Working',
    );

    const PASSED = 'True';

    const FAILED = 'False';

    const CURRENT_VALUE_KEY = 'current_value';

    const RECOMMENDED_KEY = 'recommended';

    const CHECK_PASSED_KEY = 'check_passed';

    const ERROR_KEY = 'error';

    const PUBLIC_KEY_KEY = 'public_key';

    const REQUEST_TIMEOUT_IN_SECONDS = 5;

    public function perform_site_health_check( $log = true )
    {
        $site_health = $this->get_site_health_checks();

        if ( $log ) {
            divi_dash_kernel()->dh_log_error( 'site health check: ', $site_health );
        }

        return $site_health;
    }

    private function get_site_health_checks()
    {
        if ( Divi_Dash_Connection_Manager::is_divi_dash_site_connected() ) {
            divi_dash_kernel()->dh_log_error( 'site is connected :: running only https and openssl check' );

            return array(
                self::OPENSSL => $this->check_openssl_extension(),
                self::HTTPS_STATUS => $this->check_https_status(),
            );
        }

        $site_health = array(
            self::PUBLIC_KEY_SERVER => $this->check_public_key_server_status(),
            self::TIME_SERVER => $this->check_time_server_status(),
            self::OPENSSL => $this->check_openssl_extension(),
            self::HTTPS_STATUS => $this->check_https_status(),
            self::TRANSIENTS_STATUS => $this->check_transients_status(),
        );

        $server_data = Divi_Dash_Website_Info_Getter::et_make_non_divi_server_data();

        foreach ( $server_data as $key => $data ) {
            $site_health[$key] = $this->get_formatted_site_health_check_result(
                $data['value'],
                $data['recommended'], 
                $data['dot'] === Divi_Dash_Kernel::DOT_COLOR['green']
            );
        }

        return $site_health;
    }

    public function is_site_health_check_passed()
    {
        $site_health = $this->perform_site_health_check( false );

        $health_check_passed_count = 0;

        foreach ( self::SITE_HEALTH_CRITICAL_DATA_KEYS as $key ) {
            if ( ! isset( $site_health[$key] ) || $site_health[$key][self::CHECK_PASSED_KEY] === self::PASSED ) {
                $health_check_passed_count++;
            }
        }

        $health_check_passed = $health_check_passed_count === count( self::SITE_HEALTH_CRITICAL_DATA_KEYS );

        if ( ! $health_check_passed  ) {
            divi_dash_kernel()->dh_log_error( 'site health check: ', $site_health);
        }

        return $health_check_passed;
    }
   
    private function check_public_key_server_status()
    {
        $key_server = Divi_Dash_Env_Settings::Divi_Dash_Key_Server;

        $status = $this->check_remote_server_status( self::PUBLIC_KEY_SERVER, $key_server, 'Failed to download the public key' );

        if ( $status[self::CHECK_PASSED_KEY] === self::PASSED ) {
            $status[self::PUBLIC_KEY_KEY] = Divi_Dash_Connection_Manager::divi_dash_get_public_key();
        }

        return $status;
    }

    private function check_time_server_status()
    {
        $time_server = Divi_Dash_Env_Settings::Divi_Dash_Time_Server;

        return $this->check_remote_server_status( self::TIME_SERVER, $time_server, 'Failed to get unix time' );
    }

    private function check_remote_server_status( $key, $server, $default_error_message )
    {
        $current_value = $recommended = self::RECOMMENDED_VALUES[$key];
        $error = '';

        $response = wp_remote_get($server, array(
            'timeout' => self::REQUEST_TIMEOUT_IN_SECONDS,
            'sslverify' => true,
        ));

        $wp_error = is_wp_error( $response );
        $wp_response_code = wp_remote_retrieve_response_code( $response );

        if ( $wp_error || $wp_response_code !== 200 ) {
            $error_message = $wp_error ? $response->get_error_message() : 'HTTP error ' . $wp_response_code;

            $error = sprintf(
                '%s: Error: %s',
                $default_error_message,
                $error_message
            );

            $current_value = self::FAILED_VALUES[$key];
        }

        return $this->get_formatted_site_health_check_result( $current_value, $recommended, $current_value === $recommended, $error );
    }

    private function check_openssl_extension()
    {
        $openssl_installed = extension_loaded('openssl');
        
        $recommended = self::RECOMMENDED_VALUES[self::OPENSSL];
        $current_value = $openssl_installed ? $recommended : self::FAILED_VALUES[self::OPENSSL]; 

        return $this->get_formatted_site_health_check_result( $current_value, $recommended, $current_value === $recommended );
    }

    private function check_https_status()
    {
        $is_ssl = is_ssl();
        
        $recommended = self::RECOMMENDED_VALUES[self::HTTPS_STATUS];
        $current_value = $is_ssl ? $recommended : self::FAILED_VALUES[self::HTTPS_STATUS];

        return $this->get_formatted_site_health_check_result( $current_value, $recommended, $current_value === $recommended );
    }

    private function get_formatted_site_health_check_result( $current_value, $recommended, $check_passed, $error = '' )
    {
        $formatted_result = array (
            self::CURRENT_VALUE_KEY => $current_value,
            self::RECOMMENDED_KEY => $recommended,
            self::CHECK_PASSED_KEY => $check_passed ? self::PASSED : self::FAILED,
        );

        if ( $error ) { 
            $formatted_result[self::ERROR_KEY] = $error;
        }

        return $formatted_result;
    }

    public function set_connection_attempted_option()
    {
        update_site_option( self::DIVI_DASH_CONNECTION_ATTEMPTED_OPTION, 'yes' );
    }

    public function delete_connection_attempted_option()
    {
        delete_site_option( self::DIVI_DASH_CONNECTION_ATTEMPTED_OPTION );
    }

    public function get_connection_attempted_option()
    {
        return get_site_option( self::DIVI_DASH_CONNECTION_ATTEMPTED_OPTION, 'no' ) === 'yes';
    }

    public function should_display_troubleshoot_screen()
    {
        return $this->get_connection_attempted_option() && ! $this->is_site_health_check_passed();
    }

    private function check_transients_status()
    {
        $test_key = 'et_dash_transient_test_key';
        $test_value = wp_generate_password( 12, false );

        set_transient( $test_key, $test_value, 60 );

        $recommended = self::RECOMMENDED_VALUES[self::TRANSIENTS_STATUS];
        $current_value = get_transient( $test_key ) === $test_value ? $recommended : self::FAILED_VALUES[self::TRANSIENTS_STATUS];

        return $this->get_formatted_site_health_check_result( $current_value, $recommended, $current_value === $recommended );
    }

}
