jQuery(document).ready(function($) {
    jQuery(".update-status").click(function() {
        var id      = jQuery(this).data('request_id');
        var value   = jQuery("#status_"+ id).val();

        if ( value ) {
            var data = {"action": "warranty_update_request_fragment", "type": "change_status", "status": value, "request_id": id};
            $.ajax({
                type:"POST",
                url: ajaxurl,
                data : data,
                success : function(response){
                    if ( response ) {
                        window.location.href = response;
                    }
                }
            });
        }
    });

    jQuery("a.inline-edit").click(function(e) {
        e.preventDefault();

        var req_id = jQuery(this).data("request_id");
        var tr = jQuery(this).closest("tr");
        var cloned = jQuery("#inline-edit-"+ req_id).clone();

        jQuery("#the-list tr#inline-edit-"+ req_id).find(".close_tr").click();

        cloned
            .insertAfter(tr)
            .show();

        jQuery("<tr class='hidden'></tr>").insertBefore(cloned);

        jQuery("#the-list .tip").tipTip({
            maxWidth: "400px"
        });
    });

    jQuery("#the-list").on("click", ".close-form", function(e) {
        e.preventDefault();

        jQuery(this).parents("div.closeable").hide();
    });

    jQuery("#the-list").on("click", ".close_tr", function() {
        jQuery(this).parents("tr").remove();
        jQuery("#the-list").find("tr.hidden").remove();
    });

    // RMA Update
    jQuery("#the-list").on("click", ".rma-update", function() {
        var request = jQuery("#the-list")
        var inputs  = request.find("input,select,textarea");
        var data    = jQuery(inputs).serializeArray();

        data.push({
            name: "action",
            value: "warranty_update_inline"
        });
        data.push({
            name: "id",
            value: jQuery(this).data("id")
        });
        data.push({
            name: "_wpnonce",
            value: jQuery(this).data("security")
        });

        request.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        $.post(
            ajaxurl, data, function(resp) {
                if ( resp.status == 'OK' ) {
                    var status_block = jQuery(request).find(".warranty-update-message");
                    status_block.find("p").html( resp.message );
                    status_block.show();
                } else {
                    alert( resp.message );
                }
                request.unblock();
            }
        );

    });

    // Uploading files
    var file_frame;

    jQuery("#the-list").on("click", ".rma-upload-button", function( event ) {
        event.preventDefault();

        var btn = jQuery(this);

        // Create the media frame.
        file_frame = wp.media.frames.file_frame = wp.media({
            title: jQuery( this ).data( 'uploader_title' ),
            button: {
                text: jQuery( this ).data( 'uploader_button_text' ),
            },
            multiple: false  // Set to true to allow multiple files to be selected
        });

        // When an image is selected, run a callback.
        file_frame.on( 'select', function() {
            // We set multiple to false so only get one image from the uploader
            attachment = file_frame.state().get('selection').first().toJSON();

            var request_id = btn.data("id");
            jQuery("#shipping_label_"+ request_id).val( attachment.url );
            jQuery("#shipping_label_id_"+ request_id).val( attachment.id );
        });

        // Finally, open the modal
        file_frame.open();
    });

    jQuery("#the-list").on("click", "input.request-tracking", function() {
        var btn = this;
        var tr = jQuery(this).closest("tr");
        var td = jQuery(tr).find("td");
        jQuery( td ).block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        $.post(
            ajaxurl,
            {
                action: "warranty_request_tracking",
                id: jQuery(this).data("request")
            },
            function(resp) {
                jQuery(".wc-tracking-requested").show();
                jQuery("#the-list .request-tracking-div").remove();
                jQuery(td).unblock();
            }
        );
    });

    jQuery("#the-list").on("click", ".set-tracking", function() {
        var btn = this;
        var tr = jQuery(this).closest("tr");
        var td = jQuery(tr).find("td");
        jQuery( td ).block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
        var provider = '';

        if ( jQuery("#the-list select.return_tracking_provider").length > 0 ) {
            provider = jQuery("#the-list select.return_tracking_provider option:selected").val();
        }

        $.post(
            ajaxurl,
            {
                action: "warranty_set_tracking",
                tracking: jQuery("#the-list").find(".tracking_code").val(),
                id: jQuery(this).data("request"),
                provider: provider
            },
            function(resp) {
                jQuery(".wc-tracking-saved").show();
                jQuery(td).unblock();
            }
        );
    });

    jQuery("body").on("click", ".warranty-process-refund", function() {
        var id          = jQuery(this).data("id");
        var security    = jQuery(this).data("security");
        var table       = jQuery("table.toplevel_page_warranties");
        var tb_window   = jQuery(this).parents("#TB_window");
        var amount      = tb_window.find("input.amount").val();

        tb_remove();

        table.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        $.post(
            ajaxurl,
            {
                action: "warranty_refund_item",
                ajax: true,
                id: jQuery(this).data("id"),
                amount: amount,
                _wpnonce: security
            },
            function(resp) {
                if ( resp.status == 'OK' ) {
                    window.location.reload();
                } else {
                    alert( resp.message );
                    table.unblock();
                }

            }
        )
    });

    jQuery("body").on("click", ".warranty-process-coupon", function() {
        var id          = jQuery(this).data("id");
        var security    = jQuery(this).data("security");
        var table       = jQuery("table.toplevel_page_warranties");
        var tb_window   = jQuery(this).parents("#TB_window");
        var amount      = tb_window.find("input.amount").val();

        tb_remove();

        table.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        $.post(
            ajaxurl,
            {
                action: "warranty_send_coupon",
                ajax: true,
                id: jQuery(this).data("id"),
                amount: amount,
                _wpnonce: security
            },
            function(resp) {
                if ( resp.status == 'OK' ) {
                    window.location.reload();
                } else {
                    alert( resp.message );
                    table.unblock();
                }

            }
        )
    });

    jQuery("body").on("click", ".add_note", function(e) {
        e.preventDefault();
        var container   = jQuery(this).parents(".inline-edit-col");
        var request     = jQuery(this).data("request");
        var notes_list  = container.find( "ul.admin-notes" );
        var note        = jQuery("#admin_note_"+ request).val()

        if ( note.length == 0 ) {
            return;
        }

        container.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        var data = {
            action: 'warranty_add_note',
            request: request,
            note: note
        };

        $.post(ajaxurl, data, function(resp) {
            jQuery(notes_list).html(resp);
            container.unblock();
        });
    });

    jQuery("body").on("click", ".delete_note", function(e) {
        e.preventDefault();
        var container   = jQuery(this).parents(".inline-edit-col");
        var note        = jQuery(this).data("note_id");
        var request     = jQuery(this).data("request");
        var notes_list  = container.find( "ul.admin-notes" );

        container.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        var data = {
            action: 'warranty_delete_note',
            request: request,
            note_id: note
        };

        $.post(ajaxurl, data, function(resp) {
            jQuery(notes_list).html(resp);
            container.unblock();
        });
    });

    jQuery(".tip").tipTip({
        maxWidth: "400px"
    });
});
