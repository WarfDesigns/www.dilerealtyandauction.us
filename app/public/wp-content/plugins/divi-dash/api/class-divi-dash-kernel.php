<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Kernel
{
    private static $instance        = null;
    private static $current_action   = null;
    private static $is_test_mode      = false;
    const CURRENT_CORE_VERSION_STATUS = 'latest';
    const UPGRADE_CORE_VERSION_STATUS = 'upgrade';

    public const WEBSITE_SERVER_DATA = array(
        'is_wp_writable',
        'is_wp_includes_writable',
        'is_wp_admin_writable',
        'is_cache_writable',
        'php_version',
        'wp_version',
        'mysql_version',
        'php_memory_limit',
        'post_max_size',
        'max_execution_time',
        'upload_max_filesize',
        'max_input_time',
        'max_input_vars',
        'display_errors',
        'dynamic_css',
        'dynamic_framework',
        'dynamic_javascript',
        'critical_css',
        'static_css',
    );

    public const DOT_COLOR = array(
        'red' => 'red',
        'green' => 'green',
    );

    const WP_LOGIN_SECRET_PARAM_NAME = 'dh-wp-login-secret';

    const CONFIG_INSTALLATION_PARAM_NAME = 'dh-wp-config';

    const DIVI_DASH_SERVER_DEFAULT_URL = 'https://www.elegantthemes.com/members-area/dash';

    const API_ACTION_LIFETIME = 10;

    const API_RECORDS_PER_PAGE = 1000;

    private static $available_actions = array(
        Divi_Dash_Api_Action_Name::AUTHORIZE,
        Divi_Dash_Api_Action_Name::FETCH_WEBSITE_THEME_AND_PLUGIN_UPDATE_DATA,
        Divi_Dash_Api_Action_Name::UPDATE_THEME,
        Divi_Dash_Api_Action_Name::UPDATE_PLUGIN,
        Divi_Dash_Api_Action_Name::UPDATE_WORDPRESS,
        Divi_Dash_Api_Action_Name::ACTIVATE_THEME,
        Divi_Dash_Api_Action_Name::NETWORK_ACTIVATE_THEME,
        Divi_Dash_Api_Action_Name::NETWORK_DEACTIVATE_THEME,
        Divi_Dash_Api_Action_Name::NETWORK_ACTIVATE_PLUGIN,
        Divi_Dash_Api_Action_Name::NETWORK_DEACTIVATE_PLUGIN,
        Divi_Dash_Api_Action_Name::ACTIVATE_PLUGIN,
        Divi_Dash_Api_Action_Name::DEACTIVATE_PLUGIN,
        Divi_Dash_Api_Action_Name::DELETE_THEME,
        Divi_Dash_Api_Action_Name::DELETE_PLUGIN,
        Divi_Dash_Api_Action_Name::OPTIMIZATION,
        Divi_Dash_Api_Action_Name::SITE_USER_DATA,
        Divi_Dash_Api_Action_Name::SERVER_DATA,
        Divi_Dash_Api_Action_Name::OPTIMIZATION_DELETE,
        Divi_Dash_Api_Action_Name::ADD_NEW_SITE_USER,
        Divi_Dash_Api_Action_Name::EDIT_SITE_USER,
        Divi_Dash_Api_Action_Name::DELETE_SITE_USER,
        Divi_Dash_Api_Action_Name::GET_ROLES,
        Divi_Dash_Api_Action_Name::FETCH_SUBSITES,
        Divi_Dash_Api_Action_Name::INSTALL_DIVI_PLUGIN,
        Divi_Dash_Api_Action_Name::INSTALL_DIVI_THEME,
        Divi_Dash_Api_Action_Name::FETCH_SITE_USER_INFO,
        Divi_Dash_Api_Action_Name::DISCONNECT_WP_SITE,
    );

    protected static $actions_with_confirmations = array(
        Divi_Dash_Api_Action_Name::UPDATE_PLUGIN,
        Divi_Dash_Api_Action_Name::UPDATE_THEME,
        Divi_Dash_Api_Action_Name::UPDATE_WORDPRESS,
        Divi_Dash_Api_Action_Name::INSTALL_DIVI_PLUGIN,
        Divi_Dash_Api_Action_Name::INSTALL_DIVI_THEME,
    );

    private static $available_statuses = array(
        'fetch_website_theme_and_plugin_update_data' => array(
            Divi_Dash_API_Response_Status::FETCHED,
            Divi_Dash_API_Response_Status::NOT_FETCHED,
        ),
        'activate_plugin'  => array(
            Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE,
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::PLUGIN_IS_NETWORK_ACTIVATED,
            Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED,
            Divi_Dash_API_Response_Status::PLUGIN_ALREADY_ACTIVATED,
            Divi_Dash_API_Response_Status::PLUGIN_ACTIVATED,
            Divi_Dash_API_Response_Status::PLUGIN_ACTIVATION_FAILED,
        ),
        'activate_theme' => array(
            Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE,
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::THEME_NOT_ALLOWED_FOR_MULTISITE,
            Divi_Dash_API_Response_Status::THEME_NOT_INSTALLED,
            Divi_Dash_API_Response_Status::THEME_ALREADY_ACTIVE,
            Divi_Dash_API_Response_Status::THEME_ACTIVATED,
            Divi_Dash_API_Response_Status::THEME_ACTIVATION_FAILED,
        ),
        'network_activate_theme' => array(
            Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE,
            Divi_Dash_API_Response_Status::THEME_NOT_INSTALLED,
            Divi_Dash_API_Response_Status::THEME_ALREADY_NETWORK_ACTIVE,
            Divi_Dash_API_Response_Status::THEME_NETWORK_ACTIVATED,
            Divi_Dash_API_Response_Status::THEME_NETWORK_ACTIVATION_FAILED,
        ),
        'network_deactivate_theme' => array(
            Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE,
            Divi_Dash_API_Response_Status::THEME_NOT_INSTALLED,
            Divi_Dash_API_Response_Status::THEME_ALREADY_NETWORK_DEACTIVE,
            Divi_Dash_API_Response_Status::THEME_NETWORK_DEACTIVATED,
            Divi_Dash_API_Response_Status::THEME_NETWORK_DEACTIVATION_FAILED,
        ),
        'network_activate_plugin' => array(
            Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE,
            Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED,
            Divi_Dash_API_Response_Status::PLUGIN_ALREADY_NETWORK_ACTIVATE,
            Divi_Dash_API_Response_Status::PLUGIN_NETWORK_ACTIVATED,
            Divi_Dash_API_Response_Status::PLUGIN_NETWORK_ACTIVATION_FAILED,
        ),
        'network_deactivate_plugin' => array(
            Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE,
            Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED,
            Divi_Dash_API_Response_Status::PLUGIN_ALREADY_NETWORK_DEACTIVATE,
            Divi_Dash_API_Response_Status::PLUGIN_NETWORK_DEACTIVATED,
            Divi_Dash_API_Response_Status::PLUGIN_NETWORK_DEACTIVATION_FAILED,
        ),
        'deactivate_plugin' => array(
            Divi_Dash_API_Response_Status::SITE_NOT_MULTISITE,
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::PLUGIN_IS_NETWORK_ACTIVATED,
            Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED,
            Divi_Dash_API_Response_Status::PLUGIN_ALREADY_DEACTIVATE,
            Divi_Dash_API_Response_Status::PLUGIN_DEACTIVATED,
            Divi_Dash_API_Response_Status::PLUGIN_DEACTIVATION_FAILED,
        ),
        'delete_theme' => array(
            Divi_Dash_API_Response_Status::THEME_NOT_INSTALLED,
            Divi_Dash_API_Response_Status::THEME_ACTIVE,
            Divi_Dash_API_Response_Status::THEME_DELETED,
            Divi_Dash_API_Response_Status::THEME_DELETION_FAILED,
        ),
        'delete_plugin' => array(
            Divi_Dash_API_Response_Status::PLUGIN_NOT_INSTALLED,
            Divi_Dash_API_Response_Status::PLUGIN_ACTIVE,
            Divi_Dash_API_Response_Status::PLUGIN_DELETED,
            Divi_Dash_API_Response_Status::PLUGIN_DELETION_FAILED,
        ),
        'update_plugin'    => array(
            Divi_Dash_API_Response_Status::UPDATED,
            Divi_Dash_API_Response_Status::NOT_UPDATED,
            Divi_Dash_API_Response_Status::PLUGIN_INVALID_LICENSE,
        ),
        'update_theme'     => array(
            Divi_Dash_API_Response_Status::UPDATED,
            Divi_Dash_API_Response_Status::NOT_UPDATED,
            Divi_Dash_API_Response_Status::THEME_INVALID_LICENSE,
        ),
        'update_wordpress' => array(
            Divi_Dash_API_Response_Status::UPDATED,
            Divi_Dash_API_Response_Status::NOT_UPDATED,
        ),
        'delete_site_user' => array(
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::DELETED,
            Divi_Dash_API_Response_Status::NOT_DELETED,
            Divi_Dash_API_Response_Status::ADMIN_USER,
            Divi_Dash_API_Response_Status::NOT_EXIST,
        ),
        'edit_site_user' => array(
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::EDITED,
            Divi_Dash_API_Response_Status::NOT_EDITED,
            Divi_Dash_API_Response_Status::ADMIN_USER_ROLE,
            Divi_Dash_API_Response_Status::NOT_EXIST,
        ),
        'optimization_delete' => array(
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::DELETED,
            Divi_Dash_API_Response_Status::NOT_DELETED,
        ),
        'add_new_site_user' => array(
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::ADDED,
            Divi_Dash_API_Response_Status::NOT_ADDED,
            Divi_Dash_API_Response_Status::USERNAME_ALREADY_EXISTS,
            Divi_Dash_API_Response_Status::EMAIL_ALREADY_EXISTS,
            Divi_Dash_API_Response_Status::CONFIRMATION_EMAIL_SENT,
        ),
        'server_data' => array(
            Divi_Dash_API_Response_Status::SERVER_DATA_FETCHED,
            Divi_Dash_API_Response_Status::SERVER_DATA_NOT_FETCHED,
        ),
        'site_user_data' => array(
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::SITE_USER_DATA_FETCHED,
            Divi_Dash_API_Response_Status::SITE_USER_DATA_NOT_FETCHED,
        ),
        'get_roles' => array(
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::ROLES_FETCHED,
            Divi_Dash_API_Response_Status::ROLES_NOT_FETCHED,
        ),
        'optimization' => array(
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::OPTIMIZATION_FETCHED,
            Divi_Dash_API_Response_Status::OPTIMIZATION_NOT_FETCHED,
        ),
        'fetch_subsites' => array(
            Divi_Dash_API_Response_Status::SUBSITES_FETCHED,
            Divi_Dash_API_Response_Status::SUBSITES_NOT_FETCHED,
        ),
        'install_divi_plugin' => array(
            Divi_Dash_API_Response_Status::INSTALLED,
            Divi_Dash_API_Response_Status::NOT_INSTALLED,
            Divi_Dash_API_Response_Status::PLUGIN_ALREADY_INSTALLED,
        ),
        'install_divi_theme' => array(
            Divi_Dash_API_Response_Status::INSTALLED,
            Divi_Dash_API_Response_Status::NOT_INSTALLED,
            Divi_Dash_API_Response_Status::THEME_ALREADY_INSTALLED,
        ),
        'fetch_site_user_info' => array(
            Divi_Dash_API_Response_Status::SITE_USER_INFO_FETCHED,
            Divi_Dash_API_Response_Status::SITE_USER_INFO_NOT_FETCHED,
            Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND,
            Divi_Dash_API_Response_Status::NOT_EXIST,
        ),
        'disconnect_wp_site' => array(
            Divi_Dash_API_Response_Status::SITE_DISCONNECTED,
        ),
    );

    protected static $actions_requires_admin_auto_login = array(
        Divi_Dash_Api_Action_Name::UPDATE_PLUGIN,
        Divi_Dash_Api_Action_Name::UPDATE_THEME,
        Divi_Dash_Api_Action_Name::UPDATE_WORDPRESS,
        Divi_Dash_Api_Action_Name::ACTIVATE_THEME,
        Divi_Dash_Api_Action_Name::NETWORK_ACTIVATE_THEME,
        Divi_Dash_Api_Action_Name::NETWORK_DEACTIVATE_THEME,
        Divi_Dash_Api_Action_Name::NETWORK_ACTIVATE_PLUGIN,
        Divi_Dash_Api_Action_Name::NETWORK_DEACTIVATE_PLUGIN,
        Divi_Dash_Api_Action_Name::ACTIVATE_PLUGIN,
        Divi_Dash_Api_Action_Name::DEACTIVATE_PLUGIN,
        Divi_Dash_Api_Action_Name::DELETE_THEME,
        Divi_Dash_Api_Action_Name::DELETE_PLUGIN,
        Divi_Dash_Api_Action_Name::INSTALL_DIVI_PLUGIN,
        Divi_Dash_Api_Action_Name::INSTALL_DIVI_THEME,
    );

    final private function __construct()
    {
    }

    final protected function __clone()
    {
    }

    final public function __wakeup()
    {
    }

    public static function get_instance()
    {
        if ( self::$instance == null ) {
            self::$instance = new Divi_Dash_Kernel();
        }

        return self::$instance;
    }

    private function make_response_with_status_dto( $status, $message = '', $data = array() )
    {
        $response_with_status_DTO = array( 'action_response_status' => $status );

        if ( ! empty( $message ) ) {
            $response_with_status_DTO['action_response_message'] = $message;
        }

        if ( ! empty( $data ) ) {
            $response_with_status_DTO['action_response_data'] = $data;
        }

        return $response_with_status_DTO;
    }

    private static function process_response_status( $status, $message = '', $data = '', $send_response_immediately = false )
    {
        if ( ! isset( self::$available_statuses[ self::$current_action ] ) && ! in_array( $status, self::$available_statuses[ self::$current_action ], true )) {
            throw new Exception( 'Unknown response status for the current action' );
        }

        $response_DTO = self::$instance->make_response_with_status_dto( $status, $message, $data );

        if ( $send_response_immediately ) {
            Divi_Dash_Helper::et_send_response( $response_DTO );
        }

        return $response_DTO;
    }

    public static function set_current_action( $action_name )
    {
        if ( ! in_array( $action_name, self::$available_actions, true ) ) {
            divi_dash_kernel()->send_error( 'Error: Unable to execute requested action.' );
        }

        self::$current_action = $action_name;
    }

    public static function get_response_with_status( $status, $message = '', $data = array() )
    {
        return self::process_response_status( $status, $message, $data );
    }

    public static function send_response_with_status( $status, $message = '', $data = array() )
    {
        return self::process_response_status( $status, $message, $data, true );
    }

    public static function turn_on_test_mode()
    {
        if ( 'cli' === php_sapi_name() ) {
            self::$is_test_mode = true;
        }
    }

    public static function is_test_mode_on()
    {
        return 'cli' === php_sapi_name() && self::$is_test_mode;
    }

    public static function get_current_world_unix_time()
    {
        $url = Divi_Dash_Env_Settings::Divi_Dash_Time_Server;

        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            divi_dash_kernel()->send_error( 'Failed to get unix_time' );
        }

        $body = wp_remote_retrieve_body( $response );

        $unix_time = (int) $body;

        if ( ! $unix_time  ) {
            divi_dash_kernel()->send_error( 'Unix_time not valid' );
        }
        return $unix_time;
    }

    public static function is_update_and_confirmed_action(): bool
    {
        if ( empty( self::$current_action ) ) {
            divi_dash_kernel()->send_error( 'Error: Current action is not set' );
        }

        return in_array( self::$current_action, self::$actions_with_confirmations, true );
    }

    public static function check_update_and_confirming_flow( $api_request_data, $is_confirming_action )
    {
        if ( self::is_update_and_confirmed_action() ) {
            switch ( self::$current_action ) {
                case Divi_Dash_Api_Action_Name::UPDATE_PLUGIN:
                    $plugin_name = $api_request_data['plugin_to_update'];
                    if ( divi_dash_api_actions()->et_check_is_plugin_updated( $plugin_name ) ) {
                        Divi_Dash_Helper::et_make_and_send_update_response( $is_confirming_action ? true : array( 'the plugin has already been updated' ) );
                    }
                    break;
                case Divi_Dash_Api_Action_Name::UPDATE_THEME:
                    $theme_name = $api_request_data['theme_to_update'];
                    if ( divi_dash_api_actions()->et_check_is_theme_updated( $theme_name ) ) {
                        Divi_Dash_Helper::et_make_and_send_update_response( $is_confirming_action ? true : array( 'the theme has already been updated' ) );
                    }
                    break;
                case Divi_Dash_Api_Action_Name::UPDATE_WORDPRESS:
                    if ( divi_dash_api_actions()->et_is_core_updated() ) {
                        Divi_Dash_Helper::et_make_and_send_update_response( $is_confirming_action ? true : array( 'the core has already been updated' ) );
                    }
                    break;
                case Divi_Dash_Api_Action_Name::INSTALL_DIVI_PLUGIN:
                    if (divi_dash_api_actions()->et_check_is_plugin_installed( $api_request_data )) {
                        Divi_Dash_Helper::et_make_and_send_install_response( true, true, $is_confirming_action ? Divi_Dash_API_Response_Status::INSTALLED : Divi_Dash_API_Response_Status::PLUGIN_ALREADY_INSTALLED );
                    }
                    break;
                case Divi_Dash_Api_Action_Name::INSTALL_DIVI_THEME:
                    if (divi_dash_api_actions()->et_check_is_theme_installed( $api_request_data )) {
                        Divi_Dash_Helper::et_make_and_send_install_response( true, true, $is_confirming_action ? Divi_Dash_API_Response_Status::INSTALLED : Divi_Dash_API_Response_Status::THEME_ALREADY_INSTALLED );
                    }
                    break;
            }
        }
    }

    public function dh_log_error( $message, $params = [] ) {
        if ( self::$is_test_mode ) {
            return;
        }

        if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) ) {
            return;
        }

        $dh_error_prefix = ' Divi Dash Log: ';
        $message = $dh_error_prefix . $message;
        if ( ! is_array( $params ) ) {
            $params = array( $params );
        }

        if ( empty( $params ) ) {
            error_log( $message );
        } else {
            $params_printed = [];
            foreach ( $params as $key => $param ) {
                $params_printed[$key] = print_r( $param, true );
            }
            error_log( $message.', log params: ' . print_r( $params_printed, true ) );
        }
    }

    public function is_upgrade_action( array $postData )
    {
        $action = Divi_Dash_Helper::et_get_request_action( $postData );

        return in_array( $action, self::$actions_with_confirmations );
    }

    public function is_admin_auto_login_required( array $postData )
    {
        $action = Divi_Dash_Helper::et_get_request_action( $postData );

        return in_array( $action, self::$actions_requires_admin_auto_login );
    }

    public function send_error( $error, $params = [] ): void
    {
        divi_dash_kernel()->dh_log_error( $error, $params);

        if ( wp_is_json_request() ) {
            wp_send_json_error( $error );
        }

        wp_die( $error );
    }

    public static function get_divi_package_download_url( array $postData ): string
    {
        return 'https://www.elegantthemes.com/api/api_downloads.php?'.build_query([
            'api_update' => 1,
            'theme' => $postData['package-path'],
            'username' => $postData['username'],
            'api_key' => $postData['api_key'],
        ]);
    }
}

function divi_dash_kernel()
{
    return Divi_Dash_Kernel::get_instance();
}
