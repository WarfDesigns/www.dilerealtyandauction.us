<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Divi_Dash_Deactivation_Popup' ) ) {
    class Divi_Dash_Deactivation_Popup {
        private $plugin;

        public function __construct() {
            $this->plugin = basename( ET_DIVI_DASH_PLUGIN_DIR );

            add_action( 'admin_print_scripts', array( $this, 'js' ), 99 );
            add_action( 'admin_print_scripts', array( $this, 'css' ) );
            add_action( 'admin_footer', array( $this, 'modal' ) );
        }

        public function is_plugin_page() {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
            if ( empty( $screen ) ) {
                return false;
            }

            return ( ! empty( $screen->id ) && in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) );
        }

        public function js() {
            if ( ! $this->is_plugin_page() ) {
                return;
            }

            ?>
            <script type="text/javascript">
                jQuery(function ($) {
                    var deactivateLink = $('#the-list').find('[data-slug="<?php echo $this->plugin?>"] span.deactivate a');
                    var popup = $('#divi-dash-plugin-deactivation-popup-<?php echo $this->plugin?>');

                    deactivateLink.on('click', function (event) {
                        event.preventDefault();
                        popup.css('display', 'grid');
                    });

                    $(document).on('click', '.divi-dash-plugin-deactivation-popup-cancel', function (event) {
                        event.preventDefault();
                        popup.hide();
                    });

                    $(document).on('click', '.divi-dash-plugin-deactivation-popup-deactivate', function (event) {
                        event.preventDefault();
                        location.href = deactivateLink.attr('href');
                    });
                });
            </script>
            <?php
        }

        public function css() {
            if ( ! $this->is_plugin_page() ) {
                return;
            }
            ?>
            <style type="text/css">
                .divi-dash-plugin-deactivation-popup-modal {
                    display: none;
                    max-width: 500px;
                    border-radius: 3px;
                    background: #FFF;
                    overflow: hidden;
                    box-shadow: 0px 5px 30px 0px rgba(43, 135, 218, 0.20);
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    position: fixed;
                    z-index: 9999;
                }

                .divi-dash-plugin-deactivation-popup {
                    padding: 30px;
                }

                .divi-dash-plugin-deactivation-popup p {
                    color: #4C5866;
                    font-size: 13px;
                    font-weight: 400;
                    line-height: 1.5em;
                }

                .divi-dash-plugin-deactivation-popup-title {
                    margin: 0;
                }

                .divi-dash-plugin-deactivation-popup-footer {
                    margin-top: 18px;
                    display: flex;
                    gap: 10px;
                }

                .divi-dash-plugin-deactivation-popup-deactivate,
                .divi-dash-plugin-deactivation-popup-cancel {
                    display: flex;
                    padding: 10px 12px;
                    justify-content: center;
                    align-items: center;
                    gap: 10px;
                    border-radius: 3px;
                    text-decoration: none;
                    color: #fff;
                    width: 100%;
                    font-weight: 500;
                }

                .divi-dash-plugin-deactivation-popup-deactivate, .divi-dash-plugin-deactivation-popup-deactivate:hover {
                    background-color: #2B87DA;
                    color: #fff;
                }

                .divi-dash-plugin-deactivation-popup-cancel, .divi-dash-plugin-deactivation-popup-cancel:hover {
                    background-color: #ccc;
                    color: #333;
                }
            </style>
            <?php
        }

        public function modal() {
            if ( ! $this->is_plugin_page() ) {
                return;
            }
            ?>
            <div class="divi-dash-plugin-deactivation-popup-modal" id="divi-dash-plugin-deactivation-popup-<?php echo $this->plugin; ?>">
                <div class="divi-dash-plugin-deactivation-popup">
                    <h3 class="divi-dash-plugin-deactivation-popup-title"><?php esc_html_e( 'Are you sure you want to deactivate?', 'divi-dash' ); ?></h3>
                    <p><?php esc_html_e( 'Deactivating this plugin will unsync your website from Divi Dash', 'divi-dash' ); ?></p>
                    <div class="divi-dash-plugin-deactivation-popup-footer">
                        <a href="#" class="divi-dash-plugin-deactivation-popup-cancel"><?php esc_html_e( 'Cancel', 'divi-dash' ); ?></a>
                        <a href="#" class="divi-dash-plugin-deactivation-popup-deactivate"><?php esc_html_e( 'Deactivate', 'divi-dash' ); ?></a>
                    </div>
                </div>
            </div>
            <?php
        }
    }
}

function divi_dash_init_deactivation_popup() {
    new Divi_Dash_Deactivation_Popup();
}

add_action( 'admin_init', 'divi_dash_init_deactivation_popup' );
