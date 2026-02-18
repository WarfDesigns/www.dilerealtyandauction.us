<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Api_Actions
{
    private static $instance = null;

    public function __construct()
    {
        add_action( 'admin_post_nopriv_et_login', array( $this, 'et_login_send_temporary_login_url' ) );
        add_action( 'admin_post_et_login', array( $this, 'et_login_send_temporary_login_url' ) );
        add_action( 'template_redirect', array( $this, 'handle_divi_dash_secure_temporary_login' ) );
    }

    public static function get_instance()
    {
        if ( self::$instance == null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function et_get_authorize_success_msg()
    {
        return 'Authorization successful';
    }

    public function et_fetch_website_themes_and_plugins_data()
    {
        $website_data = $this->get_website_data();

        if ( empty( $website_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_FETCHED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::FETCHED, '', $website_data );
    }

    public function get_website_data()
    {
        try {
            require_once ET_DIVI_DASH_PLUGIN_DIR . 'api/data/class-divi-dash-website-data-getter.php';
            include_once ABSPATH . '/wp-admin/includes/update.php';
            include_once ABSPATH . '/wp-admin/includes/plugin.php';

            wp_update_themes();
            wp_update_plugins();

            $website_core_data = Divi_Dash_Website_Data_Getter::et_get_website_core_data();

            return array (
                'core' => $website_core_data,
                'themes' => Divi_Dash_Website_Data_Getter::et_get_themes_data( $website_core_data ),
                'plugins' => Divi_Dash_Website_Data_Getter::et_get_plugins_data( $website_core_data ),
                'subsites' => Divi_Dash_Website_Data_Getter::et_get_subsites_data( $website_core_data ),
            );
        } catch (\Throwable $th) {
            divi_dash_kernel()->dh_log_error('fetch website data : ' . $th->getMessage() );
            return array();
        }
    }

    public function et_login_send_temporary_login_url()
    {
        $remote_address = Divi_Dash_Helper::et_get_remote_address();

        if ( ! Divi_Dash_Helper::et_check_rate_limit( $remote_address ) ) {
            wp_send_json_error( 'Too many login attempts. Please wait a few minutes and try again.' );
        }

	    if ( ! Divi_Dash_Security::request_has( 'user_to_login') || ! Divi_Dash_Security::request_has( 'key') ) {
		    wp_send_json_error( 'Authentication failed.' );
	    }

        $secret_message = Divi_Dash_Connection_Manager::divi_dash_get_decrypted_param( 'key', Divi_Dash_Kernel::WP_LOGIN_SECRET_PARAM_NAME );

        if ( $secret_message !== Divi_Dash_Plugin_Settings::divi_dash_get_option( Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION_KEYS['endpoint_url'] ) ) {
            wp_send_json_error( 'Authentication failed.' );
        }

        $user_id = Divi_Dash_Connection_Manager::divi_dash_get_decrypted_param( 'user_to_login', Divi_Dash_Kernel::WP_LOGIN_SECRET_PARAM_NAME );

        $user_id = intval( $user_id );

        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            wp_send_json_error( 'Authentication failed.' );
        }

        $login_url = divi_dash_wordpress_login_helper()->generate_secure_temporary_login_url( $user_id );

        Divi_Dash_Helper::et_reset_check_limit( $remote_address );

        wp_send_json_success( array( 'login_url' => $login_url ) );
    }

    public function handle_divi_dash_secure_temporary_login()
    {
        if ( empty( $_GET[Divi_Dash_WordPress_Login_Helper::LOGIN_TOKEN_QUERY_ARG] ) || 
            empty( $_GET[Divi_Dash_WordPress_Login_Helper::USERNAME_QUERY_ARG] ) ) {
            return;
        }

        $remote_address = Divi_Dash_Helper::et_get_remote_address();

        if ( ! Divi_Dash_Helper::et_check_rate_limit( $remote_address ) ) {
            wp_die( 'Too many login attempts. Please wait a few minutes and try again.' );
        }

        $token = sanitize_text_field( $_GET[Divi_Dash_WordPress_Login_Helper::LOGIN_TOKEN_QUERY_ARG] );
        $username = sanitize_text_field( $_GET[Divi_Dash_WordPress_Login_Helper::USERNAME_QUERY_ARG] );

        $user = get_user_by( 'login', $username );

        if ( ! is_user_logged_in() ) {
            if ( ! $user ) {
                wp_die('Invalid login token.');
            }

            if ( ! divi_dash_wordpress_login_helper()->is_valid_token( $user->ID, $token ) ) {
                wp_die('Invalid or expired login token.');
            }

            clean_user_cache( $user->ID ) ;
            wp_clear_auth_cookie();
            wp_set_current_user( $user->ID, $user->user_login );
            wp_set_auth_cookie( $user->ID );

            do_action( 'wp_login', $user->user_login, $user );
            update_user_caches( $user );
        }

        divi_dash_wordpress_login_helper()->delete_secure_temporary_login_token( $user->ID );

        Divi_Dash_Helper::et_reset_check_limit( $remote_address );

        wp_safe_redirect( admin_url() );
        exit;
    }

    public function et_update_theme( $api_request_data )
    {
        include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . '/wp-admin/includes/misc.php';
        include_once ABSPATH . '/wp-admin/includes/file.php';
        include_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-wp-upgrader-skin.php';

        if ( $api_request_data['is_marketplace_product'] && ! Divi_Dash_Helper::et_is_marketplace_theme_has_valid_license( $api_request_data['theme_to_update'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_INVALID_LICENSE );
        }

        $et_skin = new Divi_Dash_WP_Upgrader_Skin();
        $upgrader = new Theme_Upgrader( $et_skin );
        $result = $upgrader->upgrade( $api_request_data['theme_to_update'] );

        if ( ! $result || is_wp_error( $result ) || is_null( $result ) ) {
            $update_errors = Divi_Dash_Helper::et_get_failed_update_error_messages( $et_skin );
            Divi_Dash_Helper::et_make_and_send_update_response( $update_errors );
        }

        Divi_Dash_Helper::et_make_and_send_update_response( $result );
    }

    public function et_check_is_theme_updated( $theme_slug )
    {
        include_once ABSPATH . '/wp-admin/includes/update.php';

        $theme_updates = get_theme_updates();

        if ( empty( $theme_updates ) ) {
            wp_update_themes();
            $theme_updates = get_theme_updates();
        }

        return empty( $theme_updates ) || empty( $theme_updates[$theme_slug] );
    }

    public function et_check_is_plugin_updated( $plugin_name )
    {
        include_once ABSPATH . '/wp-admin/includes/update.php';
        include_once ABSPATH . '/wp-admin/includes/plugin.php';

        $plugin_updates = get_plugin_updates();

        if( empty( $plugin_updates ) ) {
            wp_update_plugins();
            $plugin_updates = get_plugin_updates();
        }

        return empty( $plugin_updates ) || empty( $plugin_updates[$plugin_name] );
    }

    public function et_update_plugin( $api_request_data )
    {
        $is_regular_site_plugin_active = is_plugin_active( $api_request_data['plugin_to_update'] );
        $is_network_wide_plugin_active = is_plugin_active_for_network($api_request_data['plugin_to_update']);

        if ( $api_request_data['is_marketplace_product'] && ! Divi_Dash_Helper::et_is_marketplace_plugin_has_valid_license( $api_request_data['plugin_to_update'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_INVALID_LICENSE );
        }

        include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . '/wp-admin/includes/misc.php';
        include_once ABSPATH . '/wp-admin/includes/file.php';
        include_once ABSPATH . '/wp-admin/includes/plugin.php';
        include_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-wp-upgrader-skin.php';

        $et_skin = new Divi_Dash_WP_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $et_skin );
        $result = $upgrader->upgrade( $api_request_data['plugin_to_update'] );

        if ( $is_regular_site_plugin_active || $is_network_wide_plugin_active) {
            $this->et_activate_plugin( $api_request_data['plugin_to_update'], $is_network_wide_plugin_active );
        }

        if ( ! $result || is_wp_error( $result ) || is_null( $result ) ) {
            $update_errors = Divi_Dash_Helper::et_get_failed_update_error_messages( $et_skin );
            Divi_Dash_Helper::et_make_and_send_update_response( $update_errors );
        }

        Divi_Dash_Helper::et_make_and_send_update_response( $result );
    }

    public function et_update_core()
    {
        include_once ABSPATH . '/wp-admin/includes/misc.php';
        require_once ABSPATH . '/wp-admin/includes/file.php';
        include_once ABSPATH . '/wp-admin/includes/update.php';
        include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
        include_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-wp-upgrader-skin.php';

        $wordpress_core_updates = get_core_updates();

        $et_skin = new Divi_Dash_WP_Upgrader_Skin();
        $upgrader = new Core_Upgrader( $et_skin );
        $upgrade_result = $upgrader->upgrade( $wordpress_core_updates[0] );

        if ( ! $upgrade_result || is_wp_error( $upgrade_result ) || is_null( $upgrade_result ) ) {
            $update_errors = Divi_Dash_Helper::et_get_failed_update_error_messages( $et_skin );
            Divi_Dash_Helper::et_make_and_send_update_response( $update_errors, false );
        }

        Divi_Dash_Helper::et_make_and_send_update_response( true );
    }

    public function et_is_core_updated()
    {
        include_once ABSPATH . '/wp-admin/includes/update.php';

        $core_updates = get_core_updates();
        $response = isset( $core_updates[0] ) && isset( $core_updates[0]->response ) ? $core_updates[0]->response : '';

        return 'latest' === $response;
    }

    public function et_network_activate_plugin( $plugin_file )
    {
        return $this->et_activate_plugin( $plugin_file, true );
    }

    public function et_activate_plugin( $plugin_file, $network_wide = false )
    {
        $onFatalError = function ($level, $message, $file, $line) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        };

        $try_silent_mode = false;
        $error_message = null;
        try {
            set_error_handler($onFatalError);
            $result = activate_plugins($plugin_file, '', $network_wide);

            if ( ! $result || is_wp_error($result) || is_null($result) ) {
                $error_message = $result->get_error_message();
                $try_silent_mode = true;
            } elseif ( ($network_wide && !is_plugin_active_for_network($plugin_file)) || !is_plugin_active($plugin_file) ) {
                $error_message = 'Plugin not activated by unknown reason';
                $try_silent_mode = true;
            }
        } catch ( \Throwable $th ) {
            $try_silent_mode = true;
            $error_message = $th->getMessage();
        } finally {
            restore_error_handler();
        }

        if ( $try_silent_mode ) {
            divi_dash_kernel()->dh_log_error('activating plugin with silent option', $error_message);
            $result = activate_plugins($plugin_file, '', $network_wide, true);
        }

        $is_active = $network_wide
            ? is_plugin_active_for_network($plugin_file)
            : is_plugin_active($plugin_file);


        if (!$is_active) {
            divi_dash_kernel()->dh_log_error('Unexpected failure: Plugin not activated despite silent activation attempt.', $error_message);
        }

        return $is_active;
    }

    public function et_deactivate_plugin( $plugin_file, $network_wide = null )
    {
        deactivate_plugins( $plugin_file, true, $network_wide );
    }

    public function et_network_deactivate_plugin( $plugin_file )
    {
        $this->et_deactivate_plugin( $plugin_file, true );
    }

    public function et_switch_theme( $api_request_data )
    {
        if ( $api_request_data['is_subsite_url_set'] && ! $api_request_data['blog_id'] ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        if ( $api_request_data['blog_id'] ) {
            switch_to_blog( $api_request_data['blog_id'] );
        }

        if ( $api_request_data['is_subsite_url_set'] && ! is_multisite() ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE );
        }

        if ( $api_request_data['is_subsite_url_set'] && ! wp_get_theme( $api_request_data['theme_to_activate'] )->is_allowed() ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NOT_ALLOWED_FOR_MULTISITE );
        }

        $all_themes = array_keys( wp_get_themes() );
        if ( ! in_array( $api_request_data['theme_to_activate'], $all_themes ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NOT_INSTALLED );
        }

        if ( wp_get_theme()->get_stylesheet() === $api_request_data['theme_to_activate'] ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_ALREADY_ACTIVE );
        }

        switch_theme( $api_request_data['theme_to_activate'] );

        if ( wp_get_theme()->get_stylesheet() === $api_request_data['theme_to_activate'] ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_ACTIVATED );
        }

        if ( $api_request_data['blog_id'] ) {
            restore_current_blog();
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_ACTIVATION_FAILED );
    }

    public function et_network_activate_theme( $theme_slug )
    {
        $all_themes = array_keys( wp_get_themes() );

        if ( ! is_multisite() ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE );
        }

        if ( ! in_array( $theme_slug, $all_themes ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NOT_INSTALLED );
        }

        if ( Divi_Dash_Website_Info_Getter::et_is_theme_allowed_on_the_network( $theme_slug ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_ALREADY_NETWORK_ACTIVE );
        }

        WP_Theme::network_enable_theme( $theme_slug );

        if ( Divi_Dash_Website_Info_Getter::et_is_theme_allowed_on_the_network( $theme_slug ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NETWORK_ACTIVATED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NETWORK_ACTIVATION_FAILED );

    }

    public function et_network_deactivate_theme( $theme_slug )
    {
        $all_themes = array_keys( wp_get_themes() );

        if ( ! is_multisite() ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE );
        }

        if ( ! in_array( $theme_slug, $all_themes ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NOT_INSTALLED );
        }

        if ( ! Divi_Dash_Website_Info_Getter::et_is_theme_allowed_on_the_network( $theme_slug ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_ALREADY_NETWORK_DEACTIVE );
        }

        WP_Theme::network_disable_theme( $theme_slug );

        if ( ! Divi_Dash_Website_Info_Getter::et_is_theme_allowed_on_the_network( $theme_slug ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NETWORK_DEACTIVATED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NETWORK_DEACTIVATION_FAILED );
    }

    public function et_delete_theme( $theme_slug )
    {
        include_once ABSPATH . '/wp-admin/includes/theme.php';
        include_once ABSPATH . '/wp-admin/includes/file.php';

        $all_themes = array_keys( wp_get_themes() );

        if ( ! in_array( $theme_slug, $all_themes, true ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NOT_INSTALLED );
        }

        if ( wp_get_theme()->get_stylesheet() == $theme_slug ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_ACTIVE );
        }

        $is_deleted_theme = delete_theme( $theme_slug );
        if ( $is_deleted_theme ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_DELETED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_DELETION_FAILED );
    }

    public function et_delete_plugin( $plugin_file )
    {
        include_once ABSPATH . '/wp-admin/includes/plugin.php';
        include_once ABSPATH . '/wp-admin/includes/file.php';

        if ( ! in_array( $plugin_file, array_keys( get_plugins() ) ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED );
        }

        if ( is_plugin_active( $plugin_file ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ACTIVE );
        }

        $is_deleted_plugin = delete_plugins( [ $plugin_file ] );
        if ( $is_deleted_plugin ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_DELETED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_DELETION_FAILED );

    }

    public function et_send_optimization_data( $api_request_data )
    {
        require_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-optimization-actions-helper.php';

        if ( $api_request_data['blog_id'] ) {
            switch_to_blog( $api_request_data['blog_id'] );
        }

        $data = [
            'spam_comments' => Divi_Dash_Optimization_Actions_Helper::et_get_spammed_comments_count(),
            'post_revisions' => Divi_Dash_Optimization_Actions_Helper::et_get_post_revisions_count(),
            'page_revisions' => Divi_Dash_Optimization_Actions_Helper::et_get_page_revisions_count(),
            'page_trash' => Divi_Dash_Optimization_Actions_Helper::et_get_page_trash_count(),
            'post_trash' => Divi_Dash_Optimization_Actions_Helper::et_get_post_trash_count()
        ];

        if ( $api_request_data['blog_id'] ) {
            restore_current_blog();
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::OPTIMIZATION_FETCHED, '', $data );
    }

    public function et_send_site_users_data( $api_request_data )
    {
        require_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-user-actions-helper.php';

        if ( $api_request_data['is_network_site'] ) {
            $data = Divi_Dash_User_Actions_Helper::et_site_users_list_all_subsites( $api_request_data['page'] );
        } else {
            if ( $api_request_data['blog_id'] ) {
                switch_to_blog( $api_request_data['blog_id'] );
            }

            $data = Divi_Dash_User_Actions_Helper::et_site_users_list( $api_request_data['page'] );

            if ( $api_request_data['blog_id'] ) {
                restore_current_blog();
            }
        }

        if ( empty( $data['site_users'] ) && ! is_array( $data['site_users'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_USER_DATA_NOT_FETCHED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_USER_DATA_FETCHED, '', $data );
    }

    public function et_send_site_user_info( $api_request_data )
    {
        require_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-user-actions-helper.php';

        if ( $api_request_data['blog_id'] ) {
            switch_to_blog( $api_request_data['blog_id'] );
        }

        $user_info = Divi_Dash_User_Actions_Helper::et_get_site_user_info( $api_request_data['user_id'] );

        if ( $api_request_data['blog_id'] ) {
            restore_current_blog();
        }

        if ( empty( $user_info ) && ! is_array( $user_info ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_EXIST );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_USER_INFO_FETCHED, '', $user_info );
    }

    public function et_send_server_data()
    {
        require_once ET_DIVI_DASH_PLUGIN_DIR . 'api/class-divi-dash-kernel.php';

        if ( Divi_Dash_Website_Info_Getter::et_is_divi( wp_get_theme()->get_stylesheet() ) ) {
            $server_data = Divi_Dash_Website_Info_Getter::et_make_divi_server_data();
        } else {
            $server_data = Divi_Dash_Website_Info_Getter::et_make_non_divi_server_data();
        }

        foreach ( $server_data as $status_key => $status ) {
            if ( ! in_array( $status_key , Divi_Dash_Kernel::WEBSITE_SERVER_DATA ) ) {
                unset( $server_data[$status_key] );
            }
        }

        if ( empty( $server_data ) || ! is_array( $server_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SERVER_DATA_NOT_FETCHED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SERVER_DATA_FETCHED, '', $server_data );
    }

    public function et_optimization_delete( $api_request_data )
    {
        require_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-optimization-actions-helper.php';

        switch ( $api_request_data['optimization_to_delete'] ) {
            case 'all':
                Divi_Dash_Optimization_Actions_Helper::et_delete_post_revisions( $api_request_data['blog_id'] );
                Divi_Dash_Optimization_Actions_Helper::et_delete_spammed_comments( $api_request_data['blog_id'] );
                Divi_Dash_Optimization_Actions_Helper::et_delete_page_revisions( $api_request_data['blog_id'] );
                Divi_Dash_Optimization_Actions_Helper::et_delete_page_trash( $api_request_data['blog_id'] );
                Divi_Dash_Optimization_Actions_Helper::et_delete_post_trash( $api_request_data['blog_id'] );
                break;

            case 'spam-comments':
                Divi_Dash_Optimization_Actions_Helper::et_delete_spammed_comments( $api_request_data['blog_id'] );
                break;

            case 'post-revisions':
                Divi_Dash_Optimization_Actions_Helper::et_delete_post_revisions( $api_request_data['blog_id'] );
                break;

            case 'page-revisions':
                Divi_Dash_Optimization_Actions_Helper::et_delete_page_revisions( $api_request_data['blog_id'] );
                break;

            case 'page-trash':
                Divi_Dash_Optimization_Actions_Helper::et_delete_page_trash( $api_request_data['blog_id'] );
                break;

            case 'post-trash':
                Divi_Dash_Optimization_Actions_Helper::et_delete_post_trash( $api_request_data['blog_id'] );
                break;
        }
    }

    public function et_add_site_user( $api_request_data )
    {
        require_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-user-actions-helper.php';

        if ( ! $api_request_data['add_existing_user'] && username_exists( $api_request_data['user_data']['username'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::USERNAME_ALREADY_EXISTS );
        }

        if ( ! $api_request_data['add_existing_user'] && email_exists( $api_request_data['user_data']['email'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::EMAIL_ALREADY_EXISTS );
        }

        $user_id = Divi_Dash_User_Actions_Helper::et_add_user_based_on_site_type( $api_request_data['user_data'], $api_request_data['add_existing_user'], $api_request_data['blog_id'] );

        if ( is_wp_error( $user_id ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_ADDED );
        }

        if ( $user_id === true ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::CONFIRMATION_EMAIL_SENT );
        }

        $response_data = array(
            'wordpress_user_id' => $user_id,
        );

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::ADDED, '',  $response_data );
    }

    public function et_edit_site_user( $api_request_data )
    {
        $data = $api_request_data['user_data'];

        $user_data = array(
            'ID'              => $data['wordpress_user_id'],
            'user_url'        => $data['website_url'],
            'user_email'      => $data['email'],
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'description'     => $data['bio'],
        );

        if ( ! $api_request_data['is_network_site'] && $data['role'] != 'none' ) {
            $user_data['role'] = $data['role'];
        }

        if ( $api_request_data['blog_id']) {
            switch_to_blog( $api_request_data['blog_id'] );
        }

        $user_info = get_userdata( $data['wordpress_user_id'] );

        if ( ! $user_info ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_EXIST );
        }

        $this_user_roles = $user_info->roles;

        if ( in_array( 'administrator', $this_user_roles ) && $data['role'] !== 'administrator' ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::ADMIN_USER_ROLE );
        }

        $user_id = wp_update_user( $user_data );

        if ( $api_request_data['is_network_site'] && ! get_active_blog_for_user( $data['wordpress_user_id'] ) ) {
            remove_user_from_blog( $user_id );
        }

        if ( ! is_wp_error( $user_id ) && ! empty( $data['password'] ) ) {
            wp_set_password( $data['password'], $user_id );
        }

        if ( $api_request_data['blog_id']) {
            restore_current_blog();
        }

        if ( is_wp_error( $user_id ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_EDITED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::EDITED );
    }

    public function et_delete_site_user( $api_request_data )
    {
        if ( is_multisite() ) {
            require_once( ABSPATH . 'wp-admin/includes/ms.php' );
        } else {
            require_once( ABSPATH . 'wp-admin/includes/user.php' );
        }
        require_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-user-actions-helper.php';

        $user_id = $api_request_data['user_id'];

        $reassign_user_id = $api_request_data['reassign_user_id'];

        $blog_id = $api_request_data['blog_id'];

        if ( $blog_id ) {
            switch_to_blog( $blog_id );
        }

        $user_info = get_userdata( $user_id );

        if ( ! $user_info ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_EXIST );
        }

        $this_user_roles = $user_info->roles;

        if ( $blog_id ) {
            restore_current_blog();
        }

        if ( is_multisite() && empty( $blog_id ) ) {
            $this_user_roles = Divi_Dash_User_Actions_Helper::get_user_roles_from_all_subsites( $user_id );
        }

        if ( in_array( 'administrator', $this_user_roles ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::ADMIN_USER );
        }

        if ( is_multisite() ) {
            if ( $blog_id ) {
                $user_deleted = remove_user_from_blog( $user_id, $blog_id, $reassign_user_id );
            } else {
                $this->et_delete_user_from_all_sub_sites( $user_id, $reassign_user_id );
                $user_deleted = wpmu_delete_user( $user_id );
            }
        } else {
            $user_deleted = wp_delete_user( $user_id, $reassign_user_id );
        }

        if ( $user_deleted ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::DELETED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
    }

    protected function et_delete_user_from_all_sub_sites( $user_id, $reassign_user_id )
    {
        $sites = get_sites();

        if ( empty( $sites ) || empty( $reassign_user_id ) ) {
            return ;
        }

        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );

            remove_user_from_blog( $user_id, $site->blog_id, $reassign_user_id );

            restore_current_blog();
        }
    }

    public function et_send_roles( $api_request_data )
    {
        if ( $api_request_data['blog_id'] ) {
            switch_to_blog( $api_request_data['blog_id'] );
        }

        global $wp_roles;

        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }

        $response_data = $wp_roles->get_names();

        if ( $api_request_data['blog_id'] ) {
            restore_current_blog();
        }

        if ( empty( $response_data ) || ! is_array( $response_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::ROLES_NOT_FETCHED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::ROLES_FETCHED, '', $response_data );
    }

    public function et_network_activate_plugin_and_get_response( $plugin_to_activate )
    {
        include_once ABSPATH . '/wp-admin/includes/plugin.php';

        if ( ! is_multisite() ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE );
        }

        if ( ! in_array( $plugin_to_activate, array_keys( get_plugins() ) ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED );
        }

        if ( is_plugin_active_for_network( $plugin_to_activate ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ALREADY_NETWORK_ACTIVATE );
        }

        $is_plugin_activated = $this->et_network_activate_plugin( $plugin_to_activate );
        if ( $is_plugin_activated && is_plugin_active_for_network( $plugin_to_activate ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NETWORK_ACTIVATED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NETWORK_ACTIVATION_FAILED );
    }

    public function et_activate_plugin_and_catch_response_status_data( $api_request_data )
    {
        include_once ABSPATH . '/wp-admin/includes/plugin.php';

        if ( $api_request_data['is_subsite_url_set'] && ! $api_request_data['blog_id'] ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        if ( $api_request_data['blog_id'] ) {
            switch_to_blog( $api_request_data['blog_id'] );
        }

        if ( $api_request_data['is_subsite_url_set'] && ! is_multisite() ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE );
        }

        if ( is_multisite() && is_plugin_active_for_network( $api_request_data['plugin_to_activate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_IS_NETWORK_ACTIVATED );
        }

        if ( ! in_array( $api_request_data['plugin_to_activate'], array_keys( get_plugins() ) ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED );
        }

        if ( is_plugin_active( $api_request_data['plugin_to_activate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ALREADY_ACTIVATED );
        }

        $is_plugin_activated = $this->et_activate_plugin( $api_request_data['plugin_to_activate'] );
        if ( $is_plugin_activated && is_plugin_active( $api_request_data['plugin_to_activate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ACTIVATED );
        }

        if ( $api_request_data['blog_id'] ) {
            restore_current_blog();
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ACTIVATION_FAILED );
    }

    public function et_network_deactivate_plugin_and_get_response( $plugin_to_deactivate )
    {
        require_once ABSPATH . '/wp-admin/includes/plugin.php';

        if ( ! is_multisite() ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE );
        }

        if ( ! in_array( $plugin_to_deactivate, array_keys( get_plugins() ) ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED );
        }

        if ( ! is_plugin_active_for_network( $plugin_to_deactivate ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ALREADY_NETWORK_DEACTIVATE );
        }

        $this->et_network_deactivate_plugin( $plugin_to_deactivate );
        if ( ! is_plugin_active_for_network( $plugin_to_deactivate ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NETWORK_DEACTIVATED );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NETWORK_DEACTIVATION_FAILED );

    }

    public function et_deactivate_plugin_and_get_response( $api_request_data )
    {
        include_once ABSPATH . '/wp-admin/includes/plugin.php';

        if ( $api_request_data['is_subsite_url_set'] && ! $api_request_data['blog_id'] ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        if ( $api_request_data['blog_id'] ) {
            switch_to_blog( $api_request_data['blog_id'] );
        }

        if ( $api_request_data['is_subsite_url_set'] && ! is_multisite() ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE );
        }

        if ( is_multisite() && is_plugin_active_for_network( $api_request_data['plugin_to_deactivate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_IS_NETWORK_ACTIVATED );
        }

        if ( ! in_array( $api_request_data['plugin_to_deactivate'], array_keys( get_plugins() ) ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED );
        }

        if ( ! is_plugin_active( $api_request_data['plugin_to_deactivate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ALREADY_DEACTIVATE );
        }

        $this->et_deactivate_plugin( $api_request_data['plugin_to_deactivate'] );
        if ( ! is_plugin_active( $api_request_data['plugin_to_deactivate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_DEACTIVATED );
        }

        if ( $api_request_data['blog_id'] ) {
            restore_current_blog();
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_DEACTIVATION_FAILED );
    }

    public function et_send_subsites(): void
    {
        if ( is_multisite() ) {
            $response_data['subsite_urls'] = Divi_Dash_Website_Info_Getter::et_get_subsite_urls();
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITES_FETCHED, '', $response_data );
        }

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITES_NOT_FETCHED );
    }

    public function et_check_is_plugin_network_activated(): bool
    {
        include_once ABSPATH . '/wp-admin/includes/plugin.php';

        if ( is_plugin_active_for_network( ET_DIVI_DASH_PLUGIN_BASENAME ) ) {
            return true;
        }

        return false;
    }

    public function et_install_divi_plugin( $api_request_data ): void
    {
        include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . '/wp-admin/includes/misc.php';
        include_once ABSPATH . '/wp-admin/includes/file.php';
        include_once ABSPATH . '/wp-admin/includes/plugin.php';
        include_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-wp-upgrader-skin.php';

        $postData = $api_request_data['plugin_to_install'];

        $plugin_slug = $postData['package-slug'];
        $plugin_file = "{$plugin_slug}/{$plugin_slug}.php";

        $all_plugins = get_plugins();

        if ( ! empty( $all_plugins[$plugin_file] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ALREADY_INSTALLED );
        }

        $plugin_zip = divi_dash_kernel()->get_divi_package_download_url( $postData );

        $et_skin = new Divi_Dash_WP_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $et_skin );
        $result = $upgrader->install( $plugin_zip );

        if ( ! $result || is_wp_error( $result ) || is_null( $result ) ) {
            $install_errors = Divi_Dash_Helper::et_get_failed_update_error_messages( $et_skin );
            Divi_Dash_Helper::et_make_and_send_install_response( $install_errors );
        }

        $this->et_activate_plugin( $plugin_file, is_multisite() ? true : null);

        Divi_Dash_Helper::et_update_account_api_key_username( $postData['username'], $postData['api_key'] );

        Divi_Dash_Helper::et_make_and_send_install_response( $result );
    }

    public function et_install_divi_theme( $api_request_data ): void
    {
        include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . '/wp-admin/includes/misc.php';
        include_once ABSPATH . '/wp-admin/includes/file.php';
        include_once ET_DIVI_DASH_PLUGIN_DIR . 'api/api-actions/class-divi-dash-wp-upgrader-skin.php';

        $postData = $api_request_data['theme_to_install'];

        $theme_slug = $postData['package-slug'];

        $all_themes = wp_get_themes();

        if ( !empty( $all_themes[$theme_slug] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_ALREADY_INSTALLED );
        }

        $theme_zip = divi_dash_kernel()->get_divi_package_download_url( $postData );

        $et_skin = new Divi_Dash_WP_Upgrader_Skin();
        $upgrader = new Theme_Upgrader( $et_skin );
        $result = $upgrader->install( $theme_zip );

        if ( ! $result || is_wp_error( $result ) || is_null( $result ) ) {
            $install_errors = Divi_Dash_Helper::et_get_failed_update_error_messages( $et_skin );
            Divi_Dash_Helper::et_make_and_send_install_response( $install_errors );
        }

        Divi_Dash_Helper::et_update_account_api_key_username( $postData['username'], $postData['api_key'] );

        Divi_Dash_Helper::et_make_and_send_install_response( $result );
    }

    public function et_check_is_plugin_installed( $api_request_data )
    {
        include_once ABSPATH . '/wp-admin/includes/plugin.php';
        include_once ABSPATH . '/wp-admin/includes/file.php';

        $postData = $api_request_data['plugin_to_install'];
        $plugin_slug = $postData['package-slug'];
        $plugin_file = "{$plugin_slug}/{$plugin_slug}.php";

        return in_array( $plugin_file, array_keys( get_plugins() ));
    }

    public function et_check_is_theme_installed( $api_request_data )
    {
        $postData = $api_request_data['theme_to_install'];
        $theme_slug = $postData['package-slug'];

        return in_array( $theme_slug, array_keys( wp_get_themes() ));
    }

    public static function et_disconnect_website()
    {
        Divi_Dash_Plugin_Settings::delete_divi_dash_config();
        Divi_Dash_Plugin_Settings::delete_divi_dash_unauth_request_option();
        divi_dash_kernel()->dh_log_error( 'Website successfully disconnected .');

        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_DISCONNECTED );
    }
}

function divi_dash_api_actions()
{
    return Divi_Dash_Api_Actions::get_instance();
}
