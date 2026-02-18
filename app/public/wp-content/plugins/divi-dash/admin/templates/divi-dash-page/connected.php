<div class="divi-dash-page-wrapper">
    <div class="divi-dash-page-container" style="text-align: center;">
        <div class="header">
            <h1><?php esc_html_e( 'You’re Connected To Divi Dash!', 'divi-dash' ) ?></h1>
            <p>
                <?php echo esc_html( $divi_dash_connection_details['divi_dash_connection_data']['last_sync_text'] ); ?>
            </p>

            <div class='card-footer action' style='justify-content: center;'>
              <a href='#' class='btn primary-btn disconnect-from-divi-dash'><?php esc_html_e( 'Disconnect This Website', 'divi-dash' ); ?></a>
              <a
                href="<?php echo esc_url( Divi_Dash_Helper::et_get_divi_dash_site_url() . '/website/' . base64_encode( get_site_url() ) ); ?>"
                target='_blank'
                class='btn primary-btn'><?php esc_html_e( 'Open Divi Dash', 'divi-dash' ); ?></a>
            </div>
        </div>
    </div>
    <div class='divi-dash-promo-image'>
      <img width='980' height='577' alt="<?php esc_html_e( 'Divi Dash', 'divi-dash' ); ?>" src="<?php echo esc_url( ET_DIVI_DASH_PLUGIN_URI . '/admin/assets/images/divi-dash-promo.jpg' ); ?>">
    </div>
</div>
<script>
    document.addEventListener("et:divi-dash:reload-page", function(){
        location.reload();
    });
</script>
