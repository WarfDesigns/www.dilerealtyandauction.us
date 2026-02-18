jQuery(document).ready(function ($) {
    var divi_dash_connection_status = et_divi_dash_connection_details.divi_dash_connection_status;
    var is_divi_dash_connection_failed = et_divi_dash_connection_details.is_divi_dash_connection_failed;

    $('#divi-dash-popup-container').dialog({
        autoOpen: false,
        modal: true,
        title: 'Divi Dash',
        width: 500,
        resizable: false,
        dialogClass: 'divi-dash-connection-details-dialog',
        open: function (event, ui) {
            var dialog = $(this);
            html = prepareDiviDashConnectionHTML();
            dialog.html(html);
        },
        close: function () {
            $(this).dialog('close');
        }
    });

    // Show Divi Dash connection details on Divi Dash page
    function showDiviDashConnectionDetailsOnDiviDashPage() {
        if ($('.divi-dash-page .divi-dash-popup-container').length) {
            $('.divi-dash-page .divi-dash-popup-container').html(prepareDiviDashConnectionHTML());
        }
    }

    function removeConnectionFailedClassOnDiviDashPage() {
        if ($('.divi-dash-page .divi-dash-popup-container').length) {
            $('.divi-dash-page .divi-dash-popup-container').removeClass('connection-failed');
        }
    }

    function updateDiviDashConnectionKey(connection_key) {
        $(document).find('.divi-dash-connection-key').val(connection_key);
    }

    showDiviDashConnectionDetailsOnDiviDashPage();

    $('.open-divi-dash-connection-details-popup').on('click', function (e) {
        e.preventDefault();
        $('#divi-dash-popup-container').dialog('open');
    });

    $(document).on('click', '.disconnect-from-divi-dash', function (e) {
        e.preventDefault();

        $(this).addClass('disabled');

        $.post(et_divi_dash_connection_details.ajax_url, {
            action: 'disconnect_website_from_divi_dash',
            nonce: et_divi_dash_connection_details.nonce
        }, function (response) {
            response = jQuery.parseJSON(response);

            if (response.status === 'success') {
                $('#divi-dash-popup-container').dialog('close');

                divi_dash_connection_status = false;

                $('#divi-dash-popup-container').dialog('open');

                showDiviDashConnectionDetailsOnDiviDashPage();

                updateDiviDashConnectionKey(response.connection_key);
            }

            $(this).removeClass('disabled');

            document.dispatchEvent(new Event("et:divi-dash:reload-page"));
        });
    });

    $(document).on('click', '.delete-divi-dash-corrupted-files', function (e) {
        e.preventDefault();

        $(this).addClass('disabled');

        $.post(et_divi_dash_connection_details.ajax_url, {
            action: 'delete_divi_dash_corrupted_files',
            nonce: et_divi_dash_connection_details.nonce
        }, function (response) {
            response = jQuery.parseJSON(response);

            if (response.status === 'success') {
                $('#divi-dash-popup-container').dialog('close');
                is_divi_dash_connection_failed = false;
                $('#divi-dash-popup-container').removeClass('connection-failed');
                $('#divi-dash-popup-container').dialog('open');

                removeConnectionFailedClassOnDiviDashPage();
                showDiviDashConnectionDetailsOnDiviDashPage();
            }

            if (response.status === 'error') {
                html = `<p style="color:#ff0000; margin-top:24px;">` + response.message + `</p>`;
                $('.upload-folder-error').html(html);
            }

            $(this).removeClass('disabled');
        });
    });

    $( 'body' ).on( 'click', '.click-to-copy', function(){
        $(this).select();
        document.execCommand("copy");
        $(this).parent().find( ".copy-status" ).addClass("copy-success").text("Copied!");
    }).on( 'mouseenter', '.click-to-copy', function() {
        $( this ).parent().append( '<span class="copy-status elevation-1">Click To Copy</span>' );
    } ).on( 'mouseleave', '.click-to-copy', function() {
        $( this ).parent().find( ".copy-status" ).remove();
    } );

    function prepareDiviDashConnectionHTML() {
        if (is_divi_dash_connection_failed) {
            return et_divi_dash_connection_details.connection_failed_html;
        }

        if (divi_dash_connection_status) {
            return et_divi_dash_connection_details.successfully_connected_html;
        }

        return et_divi_dash_connection_details.not_connected_html;
    }

    /** Divi Dash page actions */
    $(document).on('click', '.add-site-to-divi-dash', function (e) {
        e.preventDefault();
        var left = ($(window).width() / 2) - 200,
            top = ($(window).height() / 2) - 300,
            et_add_website_popup = window.open(et_divi_dash_connection_details.et_divi_dash_integration_url, 'et-login-window', "target=_blank, width=400, height=600, top=" + top + ", left=" + left);

        var et_popup_timer = setInterval(function () {
            if (et_add_website_popup.closed) {
                console.log("et popup closed");
                clearInterval(et_popup_timer);
                location.reload();
            }
        }, 500);
    });

});
