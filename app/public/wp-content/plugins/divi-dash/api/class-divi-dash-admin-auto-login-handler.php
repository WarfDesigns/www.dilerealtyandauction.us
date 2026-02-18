<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Admin_Auto_Login_Handler
{
	private static $_this;

	const TRANSIENT_PREFIX = 'et_request_options';

	const TRANSIENT_EXPIRATION = 300;

	const REDIRECT_PATH = 'et-dash';

	const NONCE_ACTION = 'et-redirect-nonce';

	const QUERY_PARAM_NONCE = '_etwpnonce';

	const QUERY_PARAM_REQUEST_ID = '_etreqid';

	public function do_auto_login_admin_user( array $postData )
	{
		if ( ! divi_dash_kernel()->is_admin_auto_login_required( $postData ) ) {
			return;
		}

		if ( is_user_logged_in() || ! empty( $_REQUEST[self::QUERY_PARAM_REQUEST_ID] ) ) {
			return;
		}

		$user = $this->get_first_admin_user();

		if ( ! $user ) {
			return;
		}

		$request_uuid = wp_generate_uuid4();

		$this->auto_login_user( $user );

		$this->set_request_transient_data( $request_uuid );

		$this->redirect_to_et_dash( $request_uuid );
	}

	public function auto_login_user( $user )
	{
		$user_id = $user->ID;

		wp_set_current_user( $user_id, $user->user_login );

		wp_cookie_constants();

		wp_set_auth_cookie( $user_id );

		$this->set_wpe_auth_cookie();
	}

	private function set_request_transient_data( $request_uuid )
	{
		if ( ! Divi_Dash_Helper::is_wpe_site() ) {
			return;
		}

		set_transient( self::TRANSIENT_PREFIX . $request_uuid, $_POST, self::TRANSIENT_EXPIRATION );    //save for max 5 minute
	}

	private function redirect_to_et_dash( $request_uuid )
	{
		if ( ! Divi_Dash_Helper::is_wpe_site() ) {
			return;
		}

		$query_string = sanitize_text_field(wp_unslash( $_SERVER['QUERY_STRING']));

		$nonce = $this->create_nonce( $request_uuid, self::NONCE_ACTION );

		parse_str( $query_string, $existing_query_params );

		$new_query_params = [
			self::QUERY_PARAM_NONCE => $nonce,
			self::QUERY_PARAM_REQUEST_ID => $request_uuid,
		];

		$query_string = http_build_query( array_merge( $existing_query_params, $new_query_params ) );

		wp_safe_redirect( self::REDIRECT_PATH . "?" . $query_string );
		exit();
	}

	public function get_first_admin_user()
	{
		$users = get_users( ['role' => 'administrator'] );

		if ( ! empty( $users ) ) {
			return $users[0];
		}

		return null;
	}

	private function set_wpe_auth_cookie()
	{
		if ( ! Divi_Dash_Helper::is_wpe_site() ) {
			return;
		}

		include_once WP_CONTENT_DIR.'/mu-plugins/wpengine-common/plugin.php';

		$wpe_plugin = new WpeCommon();
		$wpe_plugin->set_wpe_auth_cookie();
	}

	public function recover_saved_request_data()
	{
		if ( empty( $_REQUEST[self::QUERY_PARAM_REQUEST_ID] ) ) {
			return;
		}

		$request_uuid = sanitize_text_field( wp_unslash( $_REQUEST[self::QUERY_PARAM_REQUEST_ID] ));

		$saved_request_data = get_transient( self::TRANSIENT_PREFIX . $request_uuid );

		if ( empty( $saved_request_data ) ) {
			return;
		}

		if ( is_array( $saved_request_data ) ) {
			foreach ( $saved_request_data as $key => $value ) {
				$_POST[$key] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		delete_transient( self::TRANSIENT_PREFIX . $request_uuid );
	}

	private function create_nonce( $token, $action = -1 )
	{
		$user = wp_get_current_user();
		$uid = (int) $user->ID;
		if (! $uid) {
			/** This filter is documented in wp-includes/pluggable.php */
			$uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
		}

		$nonce_tick = wp_nonce_tick( $action );

		return substr( wp_hash( "$nonce_tick|$action|$uid|$token", 'nonce' ), -12, 10 );
	}

	public function verify_nonce( $nonce, $action = -1 )
	{
		if ( empty( $nonce ) || empty( $_REQUEST[self::QUERY_PARAM_REQUEST_ID] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( $nonce );
		$token = sanitize_text_field( wp_unslash( $_REQUEST[self::QUERY_PARAM_REQUEST_ID] ));
		$user = wp_get_current_user();
		$uid = (int) $user->ID;
		$nonce_tick = wp_nonce_tick( $action );

		// Nonce generated 0-12 hours ago.
		$expected = substr( wp_hash( "$nonce_tick|$action|$uid|$token", 'nonce'), -12, 10 );
		if ( hash_equals( $expected, $nonce ) ) {
			return 1;
		}

		// Nonce generated 12-24 hours ago.
		$expected = substr( wp_hash( ( $nonce_tick - 1 ) . "|$action|$uid|$token", 'nonce' ), -12, 10 );
		if ( hash_equals( $expected, $nonce ) ) {
			return 2;
		}

		// Invalid nonce.
		return false;
	}

	public static function get_instance()
	{
		if ( self::$_this === null ) {
			self::$_this = new self();
		}

		return self::$_this;
	}
}

function divi_dash_admin_auto_login_handler()
{
	return Divi_Dash_Admin_Auto_Login_Handler::get_instance();
}
