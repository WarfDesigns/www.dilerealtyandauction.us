<?php

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Include necessary files
require_once __DIR__ . '/divi-dash.php';

// Option keys
$option_keys_to_delete = array(
    Divi_Dash_Plugin_Settings::DIVI_DASH_UNAUTH_REQUEST_OPTION_KEY,
    Divi_Dash_Plugin_Settings::DIVI_DASH_CONFIG_OPTION,
    Divi_Dash_Health_Checker::DIVI_DASH_CONNECTION_ATTEMPTED_OPTION,
);

// Delete options from main site/regular site
foreach ( $option_keys_to_delete as $option_key ) {
    delete_site_option( $option_key );
}

$transient_keys_to_delete = array(
    Divi_Dash_Plugin_Settings::DIVI_DASH_CONNECTION_KEY_TRANSIENT,
    Divi_Dash_Connection_Manager::DIVI_DASH_ZIP_FILE_EXISTS_TRANSIENT,
);

// Delete the transient
foreach ( $transient_keys_to_delete as $transient_key ) {
    delete_site_transient( $transient_key );
}

// Clear any cached data that has been removed.
wp_cache_flush();

// Unschedule the cleanup cron event
wp_clear_scheduled_hook( Divi_Dash_WordPress_Login_Helper::CLEANUP_EXPIRED_TOKENS_EVENT_NAME );

// Log uninstallation
if ( function_exists( 'divi_dash_kernel' ) ) {
    divi_dash_kernel()->dh_log_error('plugin data cleared successfully on uninstall');
}
