<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader-skin.php';

class Divi_Dash_WP_Upgrader_Skin extends WP_Upgrader_Skin
{

	public $feedback_messages = array();

	public function feedback( $feedback, ...$args )
	{
		if ( isset( $this->upgrader->strings[ $feedback ] ) ) {
			$feedback = $this->upgrader->strings[ $feedback ];
		}

		if ( strpos( $feedback, '%' ) !== false ) {
			if ( $args ) {
				$args     = array_map( 'strip_tags', $args );
				$args     = array_map( 'esc_html', $args );
				$feedback = vsprintf( $feedback, $args );
			}
		}

		if ( empty( $feedback ) ) {
			return;
		}

		$this->et_show_message( $feedback );
	}

	public function et_show_message( $message )
	{
		if ( is_wp_error( $message ) ) {
			if ( $message->get_error_data() && is_string( $message->get_error_data() ) ) {
				$message = $message->get_error_message() . ': ' . $message->get_error_data();
			} else {
				$message = $message->get_error_message();
			}
		}

		$this->feedback_messages[] = $message;
	}

	public function header()
	{
		if ( $this->done_header ) {
			return;
		}

		$this->done_header = true;
	}

	public function footer()
	{
		if ( $this->done_footer ) {
			return;
		}

		$this->done_footer = true;
	}

}
