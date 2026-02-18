<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Format_Api_Request_Data
{
    public $action_data = null;

    public $action_name = '';

    public $is_confirming_action = false;

    public function __construct( $action_data = null, $action_name = '', $is_confirming_action = false )
    {
        $this->action_data = $action_data;
        $this->action_name = $action_name;
        $this->is_confirming_action = $is_confirming_action;
    }

    public function get_formatted_api_request_data(): ?array
    {
        switch ( $this->action_name ) {
            case Divi_Dash_Api_Action_Name::UPDATE_THEME:
                return $this->get_update_theme_api_request_data();

            case Divi_Dash_Api_Action_Name::UPDATE_PLUGIN:
                return $this->get_update_plugin_api_request_data();

            case Divi_Dash_Api_Action_Name::ACTIVATE_THEME:
                return $this->get_switch_theme_api_request_data();

            case Divi_Dash_Api_Action_Name::ACTIVATE_PLUGIN:
                return $this->get_activate_plugin_api_request_data();

            case Divi_Dash_Api_Action_Name::DEACTIVATE_PLUGIN:
                return $this->get_deactivate_plugin_api_request_data();

            case Divi_Dash_Api_Action_Name::OPTIMIZATION_DELETE:
                return $this->get_optimization_delete_api_request_data();

            case Divi_Dash_Api_Action_Name::SITE_USER_DATA:
                return $this->get_send_site_users_api_request_data();

            case Divi_Dash_Api_Action_Name::ADD_NEW_SITE_USER:
                return $this->get_add_site_user_api_request_data();

            case Divi_Dash_Api_Action_Name::EDIT_SITE_USER:
                return $this->get_edit_site_user_api_request_data();

            case Divi_Dash_Api_Action_Name::DELETE_SITE_USER:
                return $this->get_delete_site_user_api_request_data();

            case Divi_Dash_Api_Action_Name::INSTALL_DIVI_THEME:
                if ( $this->is_confirming_action ) {
                    return $this->get_check_is_theme_installed_api_request_data();
                }

                return $this->get_install_divi_theme_api_request_data();

            case Divi_Dash_Api_Action_Name::INSTALL_DIVI_PLUGIN:
                if ($this->is_confirming_action) {
                    return $this->get_check_is_plugin_installed_api_request_data();
                }

                return $this->get_install_divi_plugin_api_request_data();

            case Divi_Dash_Api_Action_Name::NETWORK_ACTIVATE_THEME:
                return $this->get_network_activate_theme_api_request_data();

            case Divi_Dash_Api_Action_Name::NETWORK_DEACTIVATE_THEME:
                return $this->get_network_deactivate_theme_api_request_data();
            
            case Divi_Dash_Api_Action_Name::NETWORK_ACTIVATE_PLUGIN:
                return $this->get_network_activate_plugin_api_request_data();

            case Divi_Dash_Api_Action_Name::NETWORK_DEACTIVATE_PLUGIN:
                return $this->get_network_deactivate_plugin_api_request_data();

            case Divi_Dash_Api_Action_Name::DELETE_THEME:
                return $this->get_delete_theme_api_request_data();

            case Divi_Dash_Api_Action_Name::DELETE_PLUGIN:
                return $this->get_delete_plugin_api_request_data();

            case Divi_Dash_Api_Action_Name::OPTIMIZATION:
                return $this->get_optimization_api_request_data();

            case Divi_Dash_Api_Action_Name::GET_ROLES:
                return $this->get_fetch_roles_api_request_data();
            
            case Divi_Dash_Api_Action_Name::FETCH_SITE_USER_INFO:
                return $this->get_fetch_site_user_info_api_request_data();
        }

        return $this->action_data;
    }

    private function get_update_theme_api_request_data(): array
    {
        if ( empty( $this->action_data ) ) {
            Divi_Dash_Helper::et_make_and_send_update_response( array (
                'Empty Requested Data'
            ) );
        }

        if ( ! is_array( $this->action_data ) || empty( $this->action_data['theme_to_update'] ) || ! is_string( $this->action_data['theme_to_update'] ) ) {
            Divi_Dash_Helper::et_make_and_send_update_response( array (
                'Invalid Requested Data'
            ) );
        }

        $is_marketplace_product = $this->action_data['is_marketplace_product'] ?? false;

        return array(
            'theme_to_update' => $this->action_data['theme_to_update'],
            'is_marketplace_product' => $is_marketplace_product,
        );
    }

    private function get_update_plugin_api_request_data(): array
    {
        if ( empty( $this->action_data ) ) {
            Divi_Dash_Helper::et_make_and_send_update_response( array (
                'Empty Requested Data'
            ) );
        }

        if ( ! is_array( $this->action_data ) || empty( $this->action_data['plugin_to_update'] ) || ! is_string( $this->action_data['plugin_to_update'] ) ) {
            Divi_Dash_Helper::et_make_and_send_update_response( array (
                'Invalid Requested Data'
            ) );
        }

        $is_marketplace_product = $this->action_data['is_marketplace_product'] ?? false;

        return array(
            'plugin_to_update' => $this->action_data['plugin_to_update'],
            'is_marketplace_product' => $is_marketplace_product,
        );
    }

    private function get_switch_theme_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_ACTIVATION_FAILED );
        }

        if ( empty( $this->action_data['theme_to_activate'] ) || ! is_string( $this->action_data['theme_to_activate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_ACTIVATION_FAILED );
        }

        $theme_slug = $this->action_data['theme_to_activate'];
        $is_subsite_url_set = isset( $this->action_data['subsite_url'] );
        $blog_id = ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;
        
        return array(
            'theme_to_activate' => $theme_slug,
            'is_subsite_url_set' => $is_subsite_url_set,
            'blog_id' => $blog_id,
        );
    }

    private function get_activate_plugin_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ACTIVATION_FAILED );
        }

        if ( empty( $this->action_data['plugin_to_activate'] ) || ! is_string( $this->action_data['plugin_to_activate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_ACTIVATION_FAILED );
        }

        $is_subsite_url_set = isset( $this->action_data['subsite_url'] );
        $blog_id = ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;

        return array(
            'plugin_to_activate' => $this->action_data['plugin_to_activate'],
            'is_subsite_url_set' => $is_subsite_url_set,
            'blog_id' => $blog_id,
        );
    }

    private function get_deactivate_plugin_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_DEACTIVATION_FAILED );
        }

        if ( empty( $this->action_data['plugin_to_deactivate'] ) || ! is_string( $this->action_data['plugin_to_deactivate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_DEACTIVATION_FAILED );
        }

        $is_subsite_url_set = isset( $this->action_data['subsite_url'] );
        $blog_id = ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;

        return array(
            'plugin_to_deactivate' => $this->action_data['plugin_to_deactivate'],
            'is_subsite_url_set' => $is_subsite_url_set,
            'blog_id' => $blog_id,
        );
    }

    private function get_install_divi_plugin_api_request_data(): array
    {
        if ( empty( $this->action_data ) ) {
            Divi_Dash_Helper::et_make_and_send_install_response( array (
                'Empty Requested Data'
            ) );
        }

        if ( ! is_array( $this->action_data ) || empty( $this->action_data['plugin_to_install'] ) || ! is_array( $this->action_data['plugin_to_install'] ) ) {
            Divi_Dash_Helper::et_make_and_send_install_response( array (
                'Invalid Requested Data'
            ) );
        }

        $plugin_to_install_data = $this->action_data['plugin_to_install'];

        $required_fields = ['package-slug', 'package-path', 'username', 'api_key'];

        foreach ( $required_fields as $field ) {
            if ( empty( $plugin_to_install_data[$field] ) ) {
                Divi_Dash_Helper::et_make_and_send_install_response( array (
                    'Empty Required Fields'
                ) );
            }
        }
        
        return array (
            'plugin_to_install' => $plugin_to_install_data
        );
    }

    private function get_install_divi_theme_api_request_data(): array
    {
        if ( empty( $this->action_data ) ) {
            Divi_Dash_Helper::et_make_and_send_install_response( array (
                'Empty Requested Data'
            ) );
        }

        if ( ! is_array( $this->action_data ) || empty( $this->action_data['theme_to_install'] ) || ! is_array( $this->action_data['theme_to_install'] ) ) {
            Divi_Dash_Helper::et_make_and_send_install_response( array (
                'Invalid Requested Data'
            ) );
        }

        $theme_to_install_data = $this->action_data['theme_to_install'];

        $required_fields = ['package-slug', 'package-path', 'username', 'api_key'];

        foreach ( $required_fields as $field ) {
            if ( empty( $theme_to_install_data[$field] ) ) {
                Divi_Dash_Helper::et_make_and_send_install_response( array (
                    'Empty Required Fields'
                ) );
            }
        }
        
        return array (
            'theme_to_install' => $theme_to_install_data
        );
    }

    private function get_check_is_theme_installed_api_request_data(): array
    {
        $theme_to_install_data = ! empty ( $this->action_data['theme_to_install'] ) && is_array ( $this->action_data['theme_to_install'] )
            ? $this->action_data['theme_to_install']
            : array();

        $theme_to_install_data['package-slug'] = ! empty ( $theme_to_install_data['package-slug'] ) ? $theme_to_install_data['package-slug'] : '';

        return array (
            'theme_to_install' => $theme_to_install_data
        );
    }

    private function get_check_is_plugin_installed_api_request_data(): array
    {
        $plugin_to_install_data = ! empty ( $this->action_data['plugin_to_install'] ) && is_array ( $this->action_data['plugin_to_install'] )
            ? $this->action_data['plugin_to_install']
            : array();

        $plugin_to_install_data['package-slug'] = ! empty ( $plugin_to_install_data['package-slug'] ) ? $plugin_to_install_data['package-slug'] : '';

        return array (
            'plugin_to_install' => $plugin_to_install_data
        );
    }

    private function get_add_site_user_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->dh_log_error('Add New User :: Empty Requested Data');
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_ADDED );
        }

        if ( empty( $this->action_data['user_data'] ) || ! is_array( $this->action_data['user_data'] ) ) {
            divi_dash_kernel()->dh_log_error('Add New User :: Empty User Data');
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_ADDED );
        }

        $request_user_data = $user_data = $this->action_data['user_data'];

        $add_existing_user = $request_user_data['add_existing_user_to_blog'] ?? false;

        $blog_id = ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;

        $is_subsite = isset( $this->action_data['subsite_url'] );

        if ( $is_subsite && ! $blog_id ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        self::validate_add_new_user_fields( $request_user_data, is_multisite(), $is_subsite, $add_existing_user );

        if ( ! is_multisite() ) {
            $user_data = array (
                'username' => $request_user_data['username'],
                'password' => $request_user_data['password'],
                'email' => $request_user_data['email'],
                'role' => $request_user_data['role'],
                'first_name' => $request_user_data['first_name'] ?? '',
                'last_name' => $request_user_data['last_name'] ?? '',
                'bio' => $request_user_data['bio'] ?? '',
                'website_url' => $request_user_data['website_url'] ?? '',
                'send_email_notification' => $request_user_data['send_email_notification'] ?? false,
            );
        } else if ( $is_subsite ) {
            $user_data['skip_confirmation_email'] = $request_user_data['skip_confirmation_email'] ?? false;
        }

        return array(
            'user_data' => $user_data,
            'add_existing_user' => $add_existing_user,
            'blog_id' => $blog_id,
        );
    }

    private function validate_add_new_user_fields( $user_data, $is_multisite = false, $is_subsite = false, $add_existing_user = false ): void
    {        
        $required_fields = $add_existing_user ? ['wordpress_user_id'] :  ['username', 'email'];

        if ( ! $is_multisite || $is_subsite ) {
            $required_fields[] = 'role';
        }
    
        if ( ! $is_multisite ) {
            $required_fields[] = 'password';
        }

        foreach ( $required_fields as $field ) {
            if ( empty( $user_data[$field] ) ) {
                divi_dash_kernel()->dh_log_error('Add New User :: Empty Required Fields');
                divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_ADDED );
            }
        }
    }

    private function get_optimization_delete_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
        }

        if ( empty( $this->action_data['optimization_to_delete'] ) || ! is_string( $this->action_data['optimization_to_delete'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
        }

        $blog_id = is_multisite() && ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;

        if ( isset( $this->action_data['subsite_url'] ) && ! $blog_id ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        return array(
            'optimization_to_delete' => $this->action_data['optimization_to_delete'],
            'blog_id' => $blog_id,
        );
    }

    private function get_edit_site_user_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_EDITED );
        }

        if ( empty( $this->action_data['user_data'] ) || ! is_array( $this->action_data['user_data'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_EDITED );
        }

        $request_user_data = $this->action_data['user_data'];
        $is_network_site = is_multisite() && empty( $this->action_data['subsite_url'] );
        $blog_id = ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;

        if ( isset( $this->action_data['subsite_url'] ) && ! $blog_id ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        if ( empty( $request_user_data['wordpress_user_id'] ) || empty( $request_user_data['email'] ) || ( ! $is_network_site && empty( $request_user_data['role'] ) ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_EDITED );
        }

        $user_data = array(
            'wordpress_user_id' => $request_user_data['wordpress_user_id'],
            'email' => $request_user_data['email'],
            'website_url' => $request_user_data['website_url'] ?? '',
            'first_name' => $request_user_data['first_name'] ?? '',
            'last_name' => $request_user_data['last_name'] ?? '',
            'bio' => $request_user_data['bio'] ?? '',
            'role' => $request_user_data['role'] ?? 'none',
            'password' => $request_user_data['password'] ?? null,
        );

        return array(
            'user_data' => $user_data,
            'blog_id' => $blog_id,
            'is_network_site' => $is_network_site,
        );
    }

    private function get_delete_site_user_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
        }

        if ( empty( $this->action_data['user_id'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
        }

        $user_id = $this->action_data['user_id'];

        $reassign_user_id = ! empty( $this->action_data['reassign_user_id'] ) ? $this->action_data['reassign_user_id'] : 0;

        $subsite_url = ! empty( $this->action_data['subsite_url'] ) ? $this->action_data['subsite_url'] : null;

        $blog_id = $subsite_url ? Divi_Dash_Helper::et_get_blog_id_from_url( $subsite_url ) : null;

        if ( isset( $this->action_data['subsite_url'] ) && ! $blog_id ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        return array(
            'user_id' => $user_id,
            'reassign_user_id' => $reassign_user_id,
            'blog_id' => $blog_id,
        );
    }

    private function get_send_site_users_api_request_data(): array
    {
        $blog_id = ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;

        if ( isset( $this->action_data['subsite_url'] ) && ! $blog_id ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        $is_network_site = isset( $this->action_data['network'] ) ? $this->action_data['network'] : false;
        $page = empty( $this->action_data['page'] ) ? 1 : (int) $this->action_data['page'];

        return array(
            'is_network_site' => $is_network_site,
            'blog_id' => $blog_id,
            'page' => $page,
        );
    }

    private function get_network_activate_theme_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NETWORK_ACTIVATION_FAILED );
        }

        if ( empty( $this->action_data['theme_to_network_activate'] ) || ! is_string( $this->action_data['theme_to_network_activate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NETWORK_ACTIVATION_FAILED );
        }

        return array(
            'theme_to_network_activate' => $this->action_data['theme_to_network_activate'],
        );
    }

    private function get_network_deactivate_theme_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NETWORK_DEACTIVATION_FAILED );
        }

        if ( empty( $this->action_data['theme_to_network_deactivate'] ) || ! is_string( $this->action_data['theme_to_network_deactivate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_NETWORK_DEACTIVATION_FAILED );
        }

        return array(
            'theme_to_network_deactivate' => $this->action_data['theme_to_network_deactivate'],
        );
    }

    private function get_network_activate_plugin_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NETWORK_ACTIVATION_FAILED );
        }

        if ( empty( $this->action_data['plugin_to_network_activate'] ) || ! is_string( $this->action_data['plugin_to_network_activate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NETWORK_ACTIVATION_FAILED );
        }

        return array(
            'plugin_to_network_activate' => $this->action_data['plugin_to_network_activate'],
        );
    }

    private function get_network_deactivate_plugin_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NETWORK_DEACTIVATION_FAILED );
        }

        if ( empty( $this->action_data['plugin_to_network_deactivate'] ) || ! is_string( $this->action_data['plugin_to_network_deactivate'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_NETWORK_DEACTIVATION_FAILED );
        }

        return array(
            'plugin_to_network_deactivate' => $this->action_data['plugin_to_network_deactivate'],
        );
    }

    private function get_delete_theme_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_DELETION_FAILED );
        }

        if ( empty( $this->action_data['theme_to_delete'] ) || ! is_string( $this->action_data['theme_to_delete'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::THEME_DELETION_FAILED );
        }

        return array(
            'theme_to_delete' => $this->action_data['theme_to_delete'],
        );
    }

    private function get_delete_plugin_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_DELETION_FAILED );
        }

        if ( empty( $this->action_data['plugin_to_delete'] ) || ! is_string( $this->action_data['plugin_to_delete'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::PLUGIN_DELETION_FAILED );
        }

        return array(
            'plugin_to_delete' => $this->action_data['plugin_to_delete'],
        );
    }

    private function get_optimization_api_request_data(): array
    {
        $blog_id = is_array( $this->action_data ) && ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;

        if ( isset( $this->action_data['subsite_url'] ) && ! $blog_id ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        return array(
            'blog_id' => $blog_id,
        );
    }

    private function get_fetch_roles_api_request_data(): array
    {
        $blog_id = is_array( $this->action_data ) && ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;

        if ( isset( $this->action_data['subsite_url'] ) && ! $blog_id ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        return array(
            'blog_id' => $blog_id,
        );
    }

    private function get_fetch_site_user_info_api_request_data(): array
    {
        if ( empty( $this->action_data ) || ! is_array( $this->action_data ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SITE_USER_INFO_NOT_FETCHED );
        }

        if ( empty( $this->action_data['user_id'] ) || ! is_numeric( $this->action_data['user_id'] ) ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_EXIST );
        }

        $blog_id = ! empty( $this->action_data['subsite_url'] ) ? Divi_Dash_Helper::et_get_blog_id_from_url( $this->action_data['subsite_url'] ) : null;

        if ( isset( $this->action_data['subsite_url'] ) && ! $blog_id ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::SUBSITE_NOT_FOUND );
        }

        return array(
            'user_id' => $this->action_data['user_id'],
            'blog_id' => $blog_id,
        );
    }
}
