<?php
/**
 * Plugin Name: Divi Dash
 * Plugin URI: https://www.elegantthemes.com/dash/
 * Version: 1.0.6
 * Description: Manage all of your websites from one place using the Divi Dash site manager.
 * Author: Elegant Themes
 * Author URI: https://www.elegantthemes.com
 * License: GPLv2 or later
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * Text Domain: divi-dash
 * Domain Path: /lang
 **/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ET_DIVI_DASH_PLUGIN_FILE', __FILE__ );

define( 'ET_DIVI_DASH_PLUGIN_VERSION', '1.0.6' );

define( 'ET_DIVI_DASH_PLUGIN_BASENAME', plugin_basename( ET_DIVI_DASH_PLUGIN_FILE ) );

define( 'ET_DIVI_DASH_PLUGIN_ZIP_FILE', 'divi-dash.zip' );

define( 'ET_DIVI_DASH_PLUGIN_DIR', trailingslashit( dirname( ET_DIVI_DASH_PLUGIN_FILE ) ) );

define( 'ET_DIVI_DASH_PLUGIN_URI', plugins_url( '', ET_DIVI_DASH_PLUGIN_FILE) );

define( 'ET_DIVI_DASH_ADMIN_TEMPLATES_DIR', ET_DIVI_DASH_PLUGIN_DIR . 'admin/templates' );

define( 'ET_DIVI_DASH_ADMIN_ASSETS_URI', ET_DIVI_DASH_PLUGIN_URI . '/admin/assets' );

class ET_Divi_Dash
{
    private static $_this;

    private $api_actions;

    private $admin_auto_login_handler;

    private $divi_dash_health_checker;

    const DH_ENDPOINT = 'dh_endpoint';

    const CONNECTION_ENDPOINT_PREFIX = 'et-';

    public function __construct()
    {
        // Don't allow more than one instance of the class
		if ( isset( self::$_this ) ) {
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', 'divi-dash' ),
				get_class( $this ) )
			);
		}

        self::$_this = $this;

        $this->require_files();

        $this->api_actions = divi_dash_api_actions();

        $this->admin_auto_login_handler = divi_dash_admin_auto_login_handler();

        $this->divi_dash_health_checker = new Divi_Dash_Health_Checker;

        add_action( 'init', array( $this, 'divi_dash_init_endpoint' ) );

        add_action( 'activated_plugin', array( 'Divi_Dash_Plugin_Settings', 'reset_divi_dash_config_on_plugin_activation' ), 10, 2 );

        add_action( 'init', array( $this, 'add_localization' ), 11 );

        add_action( 'plugins_loaded', array( $this, 'divi_dash_maybe_load_core' ) );
    }

    public static function get_instance()
    {
        if ( self::$_this === null ) {
            self::$_this = new self();
        }

        return self::$_this;
    }

    function require_files()
    {
        $files_to_require = array(
            'api/class-divi-dash-api-action-name.php',
            'api/class-divi-dash-api-response-status.php',
            'api/class-divi-dash-env-settings.php',
            'api/class-divi-dash-kernel.php',
            'includes/class-divi-dash-helper.php',
            'api/class-divi-dash-admin-auto-login-handler.php',
            'api/data/class-divi-dash-website-info-getters.php',
            'admin/class-divi-dash-plugin-settings.php',
            'admin/class-divi-dash-connection-manager.php',
            'admin/class-divi-dash-admin-menu.php',
            'admin/class-divi-dash-popup.php',
            'api/class-divi-dash-api-actions.php',
	        'includes/class-divi-dash-security.php',
            'includes/divi-dash-deactivation-popup.php',
            'includes/class-divi-dash-health-checker.php',
            'api/api-actions/class-divi-dash-format-api-request-data.php',
            'api/api-actions/class-divi-dash-wordpress-login-helper.php',
        );

        foreach ( $files_to_require as $file ) {
            require_once __DIR__ . '/' . $file;
        }
    }

    /**
	 * Adds plugin localization
	 * Domain: divi-dash
	 *
	 * @return void
	 */
	function add_localization()
    {
		load_plugin_textdomain( 'divi-dash', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}

    function divi_dash_connection_and_actions_handler( $endpoint_slug_from_request )
    {
        $this->divi_dash_health_checker->perform_site_health_check();

        $this->admin_auto_login_handler->recover_saved_request_data();

        $endpoint_url = Divi_Dash_Plugin_Settings::divi_dash_get_option( Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION_KEYS['endpoint_url'] );
        $divi_dash_connection_key = Divi_Dash_Plugin_Settings::get_divi_dash_connection_key();

        divi_dash_kernel()->dh_log_error( 'connection and actiond handler' );

        $isConnected = Divi_Dash_Connection_Manager::is_divi_dash_site_connected();
        $isStandardConfigInstallationRequest = self::CONNECTION_ENDPOINT_PREFIX.'config_installation' === $endpoint_slug_from_request;
        $isConnectionKeyConfigInstallationRequest = self::CONNECTION_ENDPOINT_PREFIX.$divi_dash_connection_key === $endpoint_slug_from_request;

        if ( ! $isConnected ) {
            $this->divi_dash_health_checker->set_connection_attempted_option();
        }

        if ( ! extension_loaded( 'openssl' ) ) {
            divi_dash_kernel()->send_error( 'Error: openssl extension isn\'t installed' );
        }

        if ( $isStandardConfigInstallationRequest && current_user_can('install_plugins') && current_user_can('activate_plugins') ) {
            divi_dash_kernel()->dh_log_error( 'Standard connection is being initiated' );
            $this->divi_dash_install_config();
        }

        if ( $isConnectionKeyConfigInstallationRequest ) {
            divi_dash_kernel()->dh_log_error( 'CK connection is being initiated' );
            $this->divi_dash_install_config();
        }

        if ( ! $isConnected &&
            ! empty( $endpoint_slug_from_request ) &&
            ! $isStandardConfigInstallationRequest &&
            ! divi_dash_kernel()->is_test_mode_on()
        ) {
            divi_dash_kernel()->dh_log_error( 'Connection key is not valid or expired' );
            wp_send_json_error( 'Connection key is not valid or expired, please generate it again.', 403);
        }

        divi_dash_kernel()->dh_log_error( 'before running api action');

        if ( $endpoint_url === $endpoint_slug_from_request ) {
            divi_dash_kernel()->dh_log_error( 'main api communication flow preparing' );
            $postData = Divi_Dash_Connection_Manager::verify_and_decrypt_api_action_data();
            $action = $postData['action'] ?? null;
            $is_confirming_action = $postData['is_confirming_action'] ?? false;
            $action_data = ! empty( $postData['action_data'] ) ? Divi_Dash_Helper::et_decode_request_data( $postData['action_data'] ) : null;

            if ( ! empty( $postData ) ) {
                divi_dash_kernel()->set_current_action( $action );

                $api_request_data = (new Divi_Dash_Format_Api_Request_Data($action_data, $action, $is_confirming_action))->get_formatted_api_request_data();

                divi_dash_kernel()->check_update_and_confirming_flow( $api_request_data, $is_confirming_action );

                divi_dash_kernel()->dh_log_error( 'main api flow initiated' );

                $this->admin_auto_login_handler->do_auto_login_admin_user( $postData );

                if ( ! $is_confirming_action ) {
                    switch ( $action ) {
                        case Divi_Dash_Api_Action_Name::AUTHORIZE:
                            Divi_Dash_Helper::et_send_response( null, $this->api_actions->et_get_authorize_success_msg() );
                            break;

                        case Divi_Dash_Api_Action_Name::DISCONNECT_WP_SITE:
                            $this->api_actions->et_disconnect_website();
                            break;

                        case Divi_Dash_Api_Action_Name::FETCH_WEBSITE_THEME_AND_PLUGIN_UPDATE_DATA:
                            Divi_Dash_Connection_Manager::update_last_sync_date_in_divi_dash_config();
                            $this->api_actions->et_fetch_website_themes_and_plugins_data();
                            break;

                        case Divi_Dash_Api_Action_Name::UPDATE_THEME:
                            $this->api_actions->et_update_theme( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::UPDATE_PLUGIN:
                            $this->api_actions->et_update_plugin( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::UPDATE_WORDPRESS:
                            $this->api_actions->et_update_core();
                            break;

                        case Divi_Dash_Api_Action_Name::ACTIVATE_THEME:
                            $this->api_actions->et_switch_theme( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::NETWORK_ACTIVATE_THEME:
                            $this->api_actions->et_network_activate_theme( $api_request_data['theme_to_network_activate'] );
                            break;

                        case Divi_Dash_Api_Action_Name::NETWORK_DEACTIVATE_THEME:
                            $this->api_actions->et_network_deactivate_theme( $api_request_data['theme_to_network_deactivate'] );
                            break;

                        case Divi_Dash_Api_Action_Name::NETWORK_ACTIVATE_PLUGIN:
                            $this->api_actions->et_network_activate_plugin_and_get_response( $api_request_data['plugin_to_network_activate'] );
                            break;

                        case Divi_Dash_Api_Action_Name::NETWORK_DEACTIVATE_PLUGIN:
                            $this->api_actions->et_network_deactivate_plugin_and_get_response( $api_request_data['plugin_to_network_deactivate'] );
                            break;

                        case Divi_Dash_Api_Action_Name::ACTIVATE_PLUGIN:
                            $this->api_actions->et_activate_plugin_and_catch_response_status_data( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::DEACTIVATE_PLUGIN:
                            $this->api_actions->et_deactivate_plugin_and_get_response( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::DELETE_THEME:
                            $this->api_actions->et_delete_theme( $api_request_data['theme_to_delete'] );
                            break;

                        case Divi_Dash_Api_Action_Name::DELETE_PLUGIN:
                            $this->api_actions->et_delete_plugin( $api_request_data['plugin_to_delete'] );
                            break;

                        case Divi_Dash_Api_Action_Name::OPTIMIZATION:
                            $this->api_actions->et_send_optimization_data( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::OPTIMIZATION_DELETE:
                            $this->api_actions->et_optimization_delete( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::SERVER_DATA:
                            $this->api_actions->et_send_server_data();
                            break;

                        case Divi_Dash_Api_Action_Name::SITE_USER_DATA:
                            $this->api_actions->et_send_site_users_data( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::ADD_NEW_SITE_USER:
                            $this->api_actions->et_add_site_user( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::EDIT_SITE_USER:
                            $this->api_actions->et_edit_site_user( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::DELETE_SITE_USER:
                            $this->api_actions->et_delete_site_user( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::GET_ROLES:
                            $this->api_actions->et_send_roles( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::FETCH_SUBSITES:
                            $this->api_actions->et_send_subsites();
                            break;

                        case Divi_Dash_Api_Action_Name::INSTALL_DIVI_THEME:
                            $this->api_actions->et_install_divi_theme( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::INSTALL_DIVI_PLUGIN:
                            $this->api_actions->et_install_divi_plugin( $api_request_data );
                            break;

                        case Divi_Dash_Api_Action_Name::FETCH_SITE_USER_INFO:
                            $this->api_actions->et_send_site_user_info( $api_request_data );
                            break;
                    }
                }

                if ( ! divi_dash_kernel()->is_test_mode_on() ) {
                    wp_send_json_error( 'Error: Unable to execute action.' );
                }
            }

            if ( ! divi_dash_kernel()->is_test_mode_on() ) {
                wp_send_json_error( 'Error: Unable to auth with recived key.' );
            }
        }
    }

    function divi_dash_install_config()
    {
        divi_dash_kernel()->dh_log_error( 'config installation :: initiated' );

        $new_config_json = Divi_Dash_Connection_Manager::divi_dash_get_decrypted_param( 'config', Divi_Dash_Kernel::CONFIG_INSTALLATION_PARAM_NAME );

        divi_dash_kernel()->dh_log_error( 'config installation :: decrypted_cfg' );

        // do not add connection config if site is multisite and plugin is not network activated
        if ( is_multisite() && ! divi_dash_api_actions()->et_check_is_plugin_network_activated() ) {
            wp_send_json_error( 'Divi Dash plugin is not network active' );
        }

        $new_config_array = json_decode( $new_config_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            divi_dash_kernel()->dh_log_error( 'Decrypted config data is not valid JSON' );
            wp_send_json_error( 'Error: Corrupted config data' );
        }

        $current_config_array = Divi_Dash_Plugin_Settings::get_divi_dash_config();

        if ( ! empty( $current_config_array ) ) {
            divi_dash_kernel()->dh_log_error( 'preparing config data' );
            foreach ( $new_config_array as $key => $value ) {
                $current_config_array[$key] = $value;
            }

            Divi_Dash_Plugin_Settings::update_divi_dash_config( $current_config_array );
        } else {
            Divi_Dash_Plugin_Settings::add_divi_dash_config( $new_config_array );
        }

        Divi_Dash_Connection_Manager::update_last_sync_date_in_divi_dash_config();

        divi_dash_kernel()->dh_log_error( 'config installation :: pass OK' );

        $this->divi_dash_health_checker->delete_connection_attempted_option();

        // if site is multisite send subsite urls with success
        if ( is_multisite() ) {
            divi_dash_kernel()->dh_log_error( 'before sending success with subsite urls' );

            divi_dash_kernel()->dh_log_error( 'subsite urls:', [Divi_Dash_Website_Info_Getter::et_get_subsite_urls()] );

            Divi_Dash_Plugin_Settings::delete_divi_dash_connection_key();

            wp_send_json_success( Divi_Dash_Website_Info_Getter::et_get_subsite_urls() );
        }

        Divi_Dash_Plugin_Settings::delete_divi_dash_connection_key();

        wp_send_json_success( 'ok' );
    }

    function divi_dash_init_endpoint()
    {
        if ( empty( $_SERVER['QUERY_STRING'] ) || empty( $_SERVER['REQUEST_METHOD'] ) ) {
            return;
        }

        if ( Divi_Dash_Helper::is_wpe_site() ) {
            add_rewrite_rule(
                '^' . Divi_Dash_Admin_Auto_Login_Handler::REDIRECT_PATH . '/?',
                'index.php?' . $_SERVER['QUERY_STRING'],
                'top'
            );
        }

        $nonce_param = Divi_Dash_Admin_Auto_Login_Handler::QUERY_PARAM_NONCE;
        $nonce_action = Divi_Dash_Admin_Auto_Login_Handler::NONCE_ACTION;
        $request_id = Divi_Dash_Admin_Auto_Login_Handler::QUERY_PARAM_REQUEST_ID;

        $is_post_request = $_SERVER['REQUEST_METHOD'] === 'POST';
        $is_get_request_with_valid_nonce = $_SERVER['REQUEST_METHOD'] === 'GET' && ! empty( $_REQUEST[$nonce_param] ) && $this->admin_auto_login_handler->verify_nonce( $_REQUEST[$nonce_param], $nonce_action );

        if ( ( $is_post_request || $is_get_request_with_valid_nonce ) && strpos( $_SERVER['QUERY_STRING'], self::DH_ENDPOINT . '=' ) === 0 ) {
            $url_parts = wp_parse_url( $_SERVER['REQUEST_URI'] );

            divi_dash_kernel()->dh_log_error( 'valid request + endpoint found' );

            if ( isset( $url_parts['query'] ) ) {
                parse_str( $url_parts['query'], $query_params );

                divi_dash_kernel()->dh_log_error( 'verifying query params before handling connection & actions' );

                $has_valid_dh_endpoint = count( $query_params ) === 1 && isset( $query_params[self::DH_ENDPOINT] );
                $has_valid_dh_endpoint_with_query_params = count( $query_params ) === 3 && isset( $query_params[self::DH_ENDPOINT] ) && isset( $query_params[$nonce_param] ) && isset( $query_params[$request_id] );

                if ( $has_valid_dh_endpoint || $has_valid_dh_endpoint_with_query_params ) {
                    $this->divi_dash_connection_and_actions_handler( $query_params[self::DH_ENDPOINT] );
                }
            }
        }
    }

    /**
     * Loads the latest core framework and set up core.
     *
     * @return void
     */
    function divi_dash_maybe_load_core()
    {
        if ( ! defined( 'ET_CORE' ) ) {
            require_once __DIR__ . '/core/init.php';
            et_core_setup();
        }

        et_core_enable_automatic_updates(ET_DIVI_DASH_PLUGIN_URI, ET_DIVI_DASH_PLUGIN_VERSION );
    }
}

new ET_Divi_Dash();
