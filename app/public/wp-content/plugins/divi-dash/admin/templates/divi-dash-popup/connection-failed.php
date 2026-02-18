<h3>
    <span>
        <svg width="32" viewBox="0 0 32 30" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M12.739 1.96721C14.1883 -0.655737 17.8117 -0.655738 19.261 1.96721L31.4899 24.0984C32.9392 26.7213 31.1276 30 28.2289 30H3.77113C0.872439 30 -0.939246 26.7213 0.510101 24.0984L12.739 1.96721ZM14 10C14 8.89543 14.8954 8 16 8C17.1046 8 18 8.89543 18 10V18C18 19.1046 17.1046 20 16 20C14.8954 20 14 19.1046 14 18V10ZM16 22C14.8954 22 14 22.8954 14 24C14 25.1046 14.8954 26 16 26C17.1046 26 18 25.1046 18 24C18 22.8954 17.1046 22 16 22Z" fill="#FF4C00"/>
        </svg>
    </span>
    Uh Oh!
</h3>
<p>
    <?php esc_html_e( 'Something went wrong while trying to connect this website to your Divi Dash. Please delete Corrupted Files and retry connecting this site. If the issue persists, please contact our support team.', 'divi-dash' ) ?>
    <span class="upload-folder-error"></span>
</p>
<div class="action">
    <a href="<?php echo esc_url( $divi_dash_connection_details['contact_support_link'] ); ?>" target="_blank" class="btn primary-btn"><?php esc_html_e( 'Contact Support', 'divi-dash' ) ?></a>
    <a href="#" class="btn danger-btn delete-divi-dash-corrupted-files"><?php esc_html_e( 'Delete Corrupted Files', 'divi-dash' ) ?></a>
</div>
