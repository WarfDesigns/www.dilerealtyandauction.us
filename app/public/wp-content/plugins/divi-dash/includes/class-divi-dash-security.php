<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Divi_Dash_Security' ) ) {
	class Divi_Dash_Security {

		public static function get_request_data( $key, $method = 'post', $nonce_action = '', $nonce_key = '' ): string {
			if ( ! empty( $nonce_action ) && ! empty( $nonce_key ) && ! self::verify_nonce( $nonce_action, $nonce_key ) ) {
				return '';
			}

			if ( 'post' === $method ) {
				return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			}

			return '';
		}

		public static function verify_nonce( $action, $key ): bool {
			if ( empty( $action ) || empty( $key ) ) {
				return false;
			}

			return isset( $_POST[ $key ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ), $action );
		}

		public static function request_has( $key, $method = 'post' ): bool {
			if ( 'post' === $method ) {
				return isset( $_POST[ $key ] );
			}

			return false;
		}
	}
}
