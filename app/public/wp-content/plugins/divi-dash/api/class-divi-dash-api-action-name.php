<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Api_Action_Name
{
    public const AUTHORIZE = 'authorize';

    public const DISCONNECT_WP_SITE = 'disconnect_wp_site';

    public const FETCH_WEBSITE_THEME_AND_PLUGIN_UPDATE_DATA = 'fetch_website_theme_and_plugin_update_data';

    public const UPDATE_THEME = 'update_theme';

    public const UPDATE_PLUGIN = 'update_plugin';

    public const UPDATE_WORDPRESS = 'update_wordpress';

    public const ACTIVATE_THEME = 'activate_theme';

    public const NETWORK_ACTIVATE_THEME = 'network_activate_theme';

    public const NETWORK_DEACTIVATE_THEME = 'network_deactivate_theme';

    public const NETWORK_ACTIVATE_PLUGIN = 'network_activate_plugin';

    public const NETWORK_DEACTIVATE_PLUGIN = 'network_deactivate_plugin';

    public const ACTIVATE_PLUGIN = 'activate_plugin';

    public const DEACTIVATE_PLUGIN = 'deactivate_plugin';

    public const DELETE_THEME = 'delete_theme';

    public const DELETE_PLUGIN = 'delete_plugin';

    public const OPTIMIZATION = 'optimization';

    public const SITE_USER_DATA = 'site_user_data';

    public const SERVER_DATA = 'server_data';

    public const OPTIMIZATION_DELETE = 'optimization_delete';

    public const ADD_NEW_SITE_USER = 'add_new_site_user';

    public const EDIT_SITE_USER = 'edit_site_user';

    public const DELETE_SITE_USER = 'delete_site_user';

    public const GET_ROLES = 'get_roles';

    public const FETCH_SUBSITES = 'fetch_subsites';

    public const INSTALL_DIVI_PLUGIN = 'install_divi_plugin';

    public const INSTALL_DIVI_THEME = 'install_divi_theme';

    public const FETCH_SITE_USER_INFO = 'fetch_site_user_info';
}
