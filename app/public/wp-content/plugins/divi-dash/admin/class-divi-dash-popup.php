<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Popup
{
    const ET_DIVI_DASH_POPUP_TEMPLATES = ET_DIVI_DASH_ADMIN_TEMPLATES_DIR . '/divi-dash-popup';

    const ET_DIVI_DASH_NONCE = 'divi_dash_nonce';

    public function __construct()
    {
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'wp_ajax_disconnect_website_from_divi_dash', array( $this, 'disconnect_website_from_divi_dash' ) );
        add_action( 'wp_ajax_delete_divi_dash_corrupted_files', array( $this, 'delete_old_divi_dash_plugin_zips' ) );
    }

    public function plugin_row_meta( $links, $file )
    {
        if ( $file !== ET_DIVI_DASH_PLUGIN_BASENAME ) {
            return $links;
        }

        $connection_failed_class = Divi_Dash_Connection_Manager::is_divi_dash_connection_failed() ? 'connection-failed' : '';

        $links['divi_dash_connection'] = '<a href="#" class="open-divi-dash-connection-details-popup">Divi Dash Connection</a>
            <div id="divi-dash-popup-container" class="divi-dash-popup-container ' . $connection_failed_class . '" style="display:none;"></div>';

        return $links;
    }

    public function admin_enqueue_scripts( $hook )
    {
        if ( $hook !== 'plugins.php' && $hook != 'settings_page_et-divi-dash' ) {
            return;
        }

        wp_enqueue_style( 'et_divi_dash_connection_style', ET_DIVI_DASH_ADMIN_ASSETS_URI . '/css/divi-dash-connection.css', array(), ET_DIVI_DASH_PLUGIN_VERSION );

        wp_enqueue_script( 'et_divi_dash_connection_js', ET_DIVI_DASH_ADMIN_ASSETS_URI . '/js/divi-dash-connection.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog' ), ET_DIVI_DASH_PLUGIN_VERSION, true );

        $divi_dash_connection_details = Divi_Dash_Connection_Manager::get_divi_dash_connection_details();

        $divi_dash_integration_url = add_query_arg(
            array(
                'is_popup'        => 1,
                'divi_service'    => 'divi-hub',
                'site_url'        => urlencode( site_url() ),
                'connection_key'  => sanitize_text_field( $divi_dash_connection_details['divi_dash_connection_key'] ),
            ),
            Divi_Dash_Helper::et_get_divi_dash_site_url() . '/integration-website/'
        );

        wp_localize_script( 'et_divi_dash_connection_js', 'et_divi_dash_connection_details', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( self::ET_DIVI_DASH_NONCE ),
            'divi_dash_connection_status' => $divi_dash_connection_details['divi_dash_connection_data']['status'],
            'is_divi_dash_connection_failed' => Divi_Dash_Connection_Manager::is_divi_dash_connection_failed(),
            'connection_failed_html' => $this->get_html_content( 'connection-failed', $divi_dash_connection_details ),
            'not_connected_html' => $this->get_html_content( 'not-connected', $divi_dash_connection_details ),
            'successfully_connected_html' => $this->get_html_content( 'successfully-connected', $divi_dash_connection_details ),
            'et_divi_dash_integration_url' => $divi_dash_integration_url,
        ) );
    }

    public function disconnect_website_from_divi_dash()
    {
        check_ajax_referer( self::ET_DIVI_DASH_NONCE, 'nonce' );

        Divi_Dash_Plugin_Settings::delete_divi_dash_config();

        $connection_key = Divi_Dash_Connection_Manager::generate_divi_dash_connection_key();

        echo wp_json_encode( array(
            'status' => 'success',
            'connection_key' => $connection_key,
        ) );

        exit();
    }

    public function delete_old_divi_dash_plugin_zips()
    {
        check_ajax_referer( self::ET_DIVI_DASH_NONCE, 'nonce' );

        $uploads_directory = Divi_Dash_Helper::et_divi_dash_get_uploads_directory();

        if ( ! is_dir( $uploads_directory ) || ! is_writable( $uploads_directory ) ) {
            echo wp_json_encode( array(
                'status' => 'error',
                'message' => __('Error: The uploads directory is not writable. Please check your server configuration.', 'divi-dash'),
            ) );
            exit();
        }

        Divi_Dash_Connection_Manager::delete_divi_dash_plugin_old_zip_files_from_uploads_directory();

        echo wp_json_encode( array( 'status' => 'success' ) );

        exit();
    }

    private function get_html_content( $template_name, $divi_dash_connection_details )
    {
        $template_name = sanitize_file_name( $template_name );

        $allowed_templates = array(
            'connection-failed',
            'not-connected',
            'successfully-connected',
        );

        if ( ! in_array( $template_name, $allowed_templates, true ) ) {
            divi_dash_kernel()->dh_log_error( 'incorrect template name' );
            return '';
        }

        $path = self::ET_DIVI_DASH_POPUP_TEMPLATES . '/' . $template_name . '.php';

        if ( ! file_exists( $path ) ) {
            divi_dash_kernel()->dh_log_error( 'template not found' );
            return '';
        }

        ob_start();
        include($path);
        $content = ob_get_clean();

        return $content;
    }
}

new Divi_Dash_Popup();
