<p><?php echo et_get_safe_localization( __( 'You can <a target="_blank" href="https://www.elegantthemes.com/members-area/dash/websites/">Log in to your Elegant Themes account</a> and automatically connect this website to Divi Dash using your username and password. Alternatively, you can connect this website using the connection key below.', 'divi-dash' ) ) ?></p>
<p><?php echo et_get_safe_localization( __( 'For more information, read <a target="_blank" href="https://help.elegantthemes.com/en/articles/9571881">our guide</a> on how to add sites to Divi Dash.', 'divi-dash' ) ) ?></p>
<div class='et_manage_input'>
  <h4
    style='margin-bottom:4px;margin-top:0;'><?php esc_html_e( 'Connection Key', 'divi-dash' ) ?></h4>
  <input readonly='readonly'
         class="widefat click-to-copy divi-dash-connection-key"
         value="<?php echo esc_attr( $divi_dash_connection_details['divi_dash_connection_key'] ); ?>"/>
</div>
