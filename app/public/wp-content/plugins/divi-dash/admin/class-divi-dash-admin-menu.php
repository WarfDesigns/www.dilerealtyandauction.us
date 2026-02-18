<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Admin_Menu
{
    const ET_DIVI_DASH_PAGE_TEMPLATES = ET_DIVI_DASH_ADMIN_TEMPLATES_DIR . '/divi-dash-page';
    const RETRY_CONNECTION_PARAM = 'retry';
    const RETRY_CONNECTION_NONCE = 'divi_dash_retry_connection_nonce';
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
        add_action( 'network_admin_menu', array( $this, 'add_network_menu_item' ) );
    }

    public function add_menu_item()
    {
        add_submenu_page(
            'options-general.php',
            'Divi Dash',                                // Page title
            'Divi Dash',                                // Menu title
            'manage_options',                           // Capability required to access
            'et-divi-dash',                             // Menu slug
            array( $this, 'divi_dash_page_content' ),   // Callback function to display the page content
            99                                          // Low priority
        );
    }

    public function add_network_menu_item()
    {
        add_submenu_page(
            'settings.php',
            'Divi Dash',                                // Page title
            'Divi Dash',                                // Menu title
            'manage_network_options',                   // Capability required to access
            'et-divi-dash',                             // Menu slug
            array( $this, 'divi_dash_page_content' ),   // Callback function to display the page content
            99                                          // Low priority
        );
    }

    // Callback function to display the Divi Dash page content
    public function divi_dash_page_content()
    {
        $divi_dash_connection_details = Divi_Dash_Connection_Manager::get_divi_dash_connection_details();

        if ( $divi_dash_connection_details['divi_dash_connection_data']['status'] ) {
            include_once( self::ET_DIVI_DASH_PAGE_TEMPLATES . '/connected.php');
            return;
        }

        $divi_dash_health_checker = new Divi_Dash_Health_Checker;

        $retry = isset( $_GET[self::RETRY_CONNECTION_PARAM] ) && sanitize_text_field( $_GET[self::RETRY_CONNECTION_PARAM] ) === 'true';

        if ( ! $retry && $divi_dash_health_checker->should_display_troubleshoot_screen() ) {
            $divi_dash_connection_details['retry-url'] = wp_nonce_url(
                add_query_arg(
                    self::RETRY_CONNECTION_PARAM,
                    'true',
                    remove_query_arg( self::RETRY_CONNECTION_PARAM )
                ),
                self::RETRY_CONNECTION_NONCE
            );

            include_once( self::ET_DIVI_DASH_PAGE_TEMPLATES . '/troubleshoot.php');
            return;
        }

        if ( $retry && check_admin_referer( self::RETRY_CONNECTION_NONCE ) ) {
            $divi_dash_health_checker->delete_connection_attempted_option();
        }

        include_once( self::ET_DIVI_DASH_PAGE_TEMPLATES . '/not-connected.php');
    }
}

new Divi_Dash_Admin_Menu();
