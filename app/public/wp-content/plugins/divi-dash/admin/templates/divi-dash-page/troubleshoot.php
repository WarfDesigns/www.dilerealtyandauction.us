<div class="divi-dash-page-wrapper troubleshoot-page">
    <div class="divi-dash-page-container">
        <div class="header">
            <h1><?php esc_html_e( 'Divi Dash', 'divi-dash' ) ?></h1>
        </div>

        <div class="divi-dash-card">
            <h2 class="card-header"><?php esc_html_e( 'Oops! Something went wrong.', 'divi-dash' ) ?></h2>
            <p class="card-content">
                <?php esc_html_e( 'It looks like we hit a snag while trying to connect this site to your Divi Dash. Don\'t worry! Please reach out to our support team for help or retry connecting your site now.', 'divi-dash' ) ?>
            </p>
            <div class="card-footer action">
                <a href="<?php echo esc_url( $divi_dash_connection_details['contact_support_link'] ); ?>" target="_blank" class="btn primary-btn"><?php esc_html_e( 'Chat With an Expert', 'divi-dash' ) ?></a>
                <a href="<?php echo esc_url( $divi_dash_connection_details['retry-url'] ); ?>" class="btn primary-btn"><?php esc_html_e( 'Retry Site Connection', 'divi-dash' ) ?></a>
            </div>
        </div>
    </div>
</div>
