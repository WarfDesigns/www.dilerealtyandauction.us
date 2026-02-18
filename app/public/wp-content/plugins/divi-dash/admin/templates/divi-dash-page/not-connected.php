<div class="divi-dash-page-wrapper">
    <div class="divi-dash-page-container">
        <div class="header">
            <h1><?php esc_html_e( 'Connect This Site to Your Divi Dash', 'divi-dash' ) ?></h1>
            <p>
                <?php echo et_get_safe_localization( __( 'Sync this site to <a target="_blank" href="https://www.elegantthemes.com/members-area/dash/">Divi Dash</a> to unlock site management from right inside your Elegant Themes account. Efficiently monitor and manage site updates, plugins, themes, 1-click logins, and more all from one place.', 'divi-dash' ) ) ?>
                <a href="https://help.elegantthemes.com/en/articles/9571733" target="_blank"><?php esc_html_e( 'Learn More', 'divi-dash' ) ?></a>
            </p>
        </div>

        <div class="divi-dash-card">
            <h2 class="card-header"><?php esc_html_e( 'Method 1: One-Click Connection', 'divi-dash' ) ?></h2>
            <p class="card-content">
                <?php esc_html_e( 'Click the button below and simply provide your Elegant Themes account credentials to automatically add this site to your Divi Dash.', 'divi-dash' ) ?>
            </p>
            <div class="card-footer action">
                <a href="#" class="btn primary-btn add-site-to-divi-dash"><?php esc_html_e( 'Add This Site to My Divi Dash', 'divi-dash' ) ?></a>
            </div>
        </div>

        <div class="divi-dash-card">
            <h2 class="card-header"><?php esc_html_e( 'Method 2: Connect From Divi Dash With WordPress Credentials', 'divi-dash' ) ?></h2>
            <p class="card-content">
                <?php echo et_get_safe_localization( __( '<a target="_blank" href="https://www.elegantthemes.com/members-area/dash/websites/">Log in to your Elegant Themes account</a> and automatically connect your website to Divi Dash using the username and password for this website.', 'divi-dash' ) ) ?>
            </p>
            <div class="card-footer action">
                <a href="<?php echo esc_url(Divi_Dash_Helper::et_get_divi_dash_site_url())?>/websites/" target="_blank" class="btn primary-btn login-to-divi"><?php esc_html_e( 'Log In to Divi Dash', 'divi-dash' ) ?></a>
            </div>
        </div>

        <div class="divi-dash-card">
            <h2 class="card-header"><?php esc_html_e( 'Method 3: Connect With Connection Key', 'divi-dash' ) ?></h2>
            <p class="card-content">
                 <?php echo et_get_safe_localization( __( 'Connect this website by <a target="_blank" href="https://www.elegantthemes.com/members-area/dash/websites/">logging into</a> your Elegant Themes Account and pasting the connection key below into Divi Dash.', 'divi-dash' ) ) ?>
            </p>
            <div class="et_manage_input">
              <h4 style='margin-bottom:4px;margin-top:0;'><?php esc_html_e( 'Connection Key', 'divi-dash' ) ?></h4>
              <input readonly='readonly' class="widefat click-to-copy divi-dash-connection-key" value="<?php echo esc_attr( $divi_dash_connection_details['divi_dash_connection_key'] ); ?>"/>
            </div>
        </div>
    </div>
</div>
