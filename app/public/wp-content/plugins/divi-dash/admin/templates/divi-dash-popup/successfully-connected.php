<img width="440" height="192" alt="<?php esc_html_e( 'Connected to Divi Dash', 'divi-dash' ) ?>" src="<?php echo esc_url( $divi_dash_connection_details['successful_connection_image_link'] ); ?>"></img>
<p class="success"><?php echo esc_html( $divi_dash_connection_details['divi_dash_connection_data']['last_sync_text'] ); ?></p>
<div class="action">
    <a href="#" class="btn primary-btn disconnect-from-divi-dash"><?php esc_html_e( 'Disconnect This Website From Divi Dash', 'divi-dash' ) ?></a>
</div>
