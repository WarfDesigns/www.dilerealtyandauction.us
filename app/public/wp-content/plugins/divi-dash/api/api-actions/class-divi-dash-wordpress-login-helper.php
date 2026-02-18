<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_WordPress_Login_Helper
{
    private static $_this;

    const SECURE_TEMPORARY_LOGIN_TOKEN_META_KEY = 'divi_dash_secure_temporary_login_token';

    const SECURE_TEMPORARY_LOGIN_EXPIRY_META_KEY = 'divi_dash_secure_temporary_login_expiry';

    const CLEANUP_EXPIRED_TOKENS_EVENT_NAME = 'divi_dash_cleanup_expired_tokens_event';

    const TOKEN_EXPIRY_TIME_IN_SECONDS = 300;

    const URL_ARGUMENTS_PREFIX = 'et-dash-';

    const LOGIN_TOKEN_QUERY_ARG = self::URL_ARGUMENTS_PREFIX . 'login-token';

    const USERNAME_QUERY_ARG = self::URL_ARGUMENTS_PREFIX . 'username';

    public function __construct()
    {
       add_action( self::CLEANUP_EXPIRED_TOKENS_EVENT_NAME, array( $this, 'cleanup_expired_login_tokens' ) );

       if ( ! wp_next_scheduled( self::CLEANUP_EXPIRED_TOKENS_EVENT_NAME ) ) {
           wp_schedule_event( time(), 'daily', self::CLEANUP_EXPIRED_TOKENS_EVENT_NAME );
       }
    }

    public static function get_instance()
	{
		if ( self::$_this === null ) {
			self::$_this = new self();
		}

		return self::$_this;
	}

    public function generate_secure_temporary_login_url( $user_id )
    {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return false;
        }
    
        $token = wp_generate_password(32, false);

        $hashed_token = wp_hash_password($token);
    
        update_user_meta( $user_id, self::SECURE_TEMPORARY_LOGIN_TOKEN_META_KEY, $hashed_token );
        update_user_meta( $user_id, self::SECURE_TEMPORARY_LOGIN_EXPIRY_META_KEY, time() + self::TOKEN_EXPIRY_TIME_IN_SECONDS );
    
        return add_query_arg(
            array(
                self::LOGIN_TOKEN_QUERY_ARG => $token,
                self::USERNAME_QUERY_ARG => $user->user_login,
            ),
            set_url_scheme( site_url( '/' ),  Divi_Dash_Env_Settings::SITE_URL_SCHEME )
        );
    }

    public function is_valid_token( $user_id, $token )
    {
        $token_expiry = get_user_meta( $user_id, self::SECURE_TEMPORARY_LOGIN_EXPIRY_META_KEY, true );

        if ( time() > $token_expiry ) {
            $this->delete_secure_temporary_login_token( $user_id );
            return false;
        }

        $stored_hash = get_user_meta( $user_id, self::SECURE_TEMPORARY_LOGIN_TOKEN_META_KEY, true );

        return wp_check_password( $token, $stored_hash );
    }

    public function delete_secure_temporary_login_token( $user_id )
    {
        delete_user_meta( $user_id, self::SECURE_TEMPORARY_LOGIN_TOKEN_META_KEY );
        delete_user_meta( $user_id, self::SECURE_TEMPORARY_LOGIN_EXPIRY_META_KEY );
    }

    public function cleanup_expired_login_tokens()
    {
        global $wpdb;

        $users_with_expired_tokens = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value <= %d",
                self::SECURE_TEMPORARY_LOGIN_EXPIRY_META_KEY,
                time()
            )
        );

        foreach ( $users_with_expired_tokens as $user ) {
            $this->delete_secure_temporary_login_token( $user->user_id );
        }
    }
}

add_action( 'plugins_loaded', 'divi_dash_wordpress_login_helper' );

function divi_dash_wordpress_login_helper()
{
	return Divi_Dash_WordPress_Login_Helper::get_instance();
}
