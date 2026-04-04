jQuery(document).ready(function($) {
    
    // TAB SWITCHING
    $('.utpc-tab-btn').click(function(){
        $('.utpc-tab-btn').removeClass('active').css({'background':'#fff', 'color':'#0073aa'});
        $(this).addClass('active').css({'background':'#0073aa', 'color':'#fff'});
        
        $('.utpc-tab-content').addClass('hidden');
        $('#' + $(this).data('target')).removeClass('hidden');
    });

    $('input[name="room_mode"]').change(function() {
        $('#div-r-a').toggleClass('hidden', this.value !== 'auto');
        $('#div-r-c').toggleClass('hidden', this.value !== 'custom');
    });
    $('input[name="vehicle_mode"]').change(function() {
        $('#div-v-a').toggleClass('hidden', this.value !== 'auto');
        $('#div-v-c').toggleClass('hidden', this.value !== 'custom');
    });

    // SERVICE TYPE TOGGLER
    $('#ui_service_type').change(function() {
        let s = $(this).val();
        if(s === 'hotel') { 
            $('#box-transport').hide(); 
            $('#box-rooms').show(); 
            $('#ui_hotel_cat_box').show();
        } else if(s === 'cab') { 
            $('#box-rooms').hide(); 
            $('#box-transport').show(); 
            $('#ui_hotel_cat_box').hide();
        } else { 
            $('#box-rooms, #box-transport').show(); 
            $('#ui_hotel_cat_box').show();
        }
    });

    $('#add-r').click(function() { $('#list-r').append($('#tpl-room-row').html()); });
    $('#add-v').click(function() { $('#list-v').append($('#tpl-veh-row').html()); });
    $(document).on('click', '.btn-rem', function() { $(this).parent().remove(); });

    $('#utpc-form').submit(function(e) {
        e.preventDefault();
        let btn = $('#btn-submit');
        btn.find('.btn-text').addClass('hidden'); btn.find('.btn-loader').removeClass('hidden'); btn.prop('disabled', true);

        $.post(utpc_obj.ajax_url, {
            action: 'utpc_calculate', nonce: utpc_obj.nonce, form_data: $(this).serialize()
        }, function(response) {
            if(response.success) { $('#utpc-results').html(response.data); } 
            else { $('#utpc-results').html('<div style="padding:15px;color:red;text-align:center;">Failed to calculate or no results found.</div>'); }
            btn.find('.btn-text').removeClass('hidden'); btn.find('.btn-loader').addClass('hidden'); btn.prop('disabled', false);
        }).fail(function() {
            $('#utpc-results').html('<div style="padding:15px;color:red;text-align:center;">Server error.</div>');
            btn.find('.btn-text').removeClass('hidden'); btn.find('.btn-loader').addClass('hidden'); btn.prop('disabled', false);
        });
    });

    // ==========================================
    // INJECT DATA INTO WHATSAPP CUSTOM MODAL
    // ==========================================
    $(document).on('click', '.utpc-row', function(e) {
        if($(e.target).hasClass('btn-book-custom')) return; 

        let pax = $(this).attr('data-pax') || 'N/A';
        let veh = $(this).attr('data-veh') || 'N/A';
        let rms = $(this).attr('data-rms') || 'N/A';
        let hotel = $(this).attr('data-hotel') || 'N/A';
        let start = $(this).attr('data-start') || 'N/A';
        let end = $(this).attr('data-end') || 'N/A';
        let seasonName = $(this).attr('data-season-name') || 'Normal Season';
        
        let days = $(this).attr('data-days') || '7';
        let serv = $(this).attr('data-service') || 'both';
        
        let pp = $(this).attr('data-pp') || '0';
        let base_pp = $(this).attr('data-basepp') || '0';
        let gst_pp = $(this).attr('data-gstpp') || '0';
        let tot = $(this).attr('data-tot') || '0';
        
        let numPP = "₹" + Number(pp).toLocaleString('en-IN');
        let numTot = "₹" + Number(tot).toLocaleString('en-IN');
        
        // Dynamically grab titles and inclusions from PHP settings
        let title = utpc_obj.popup_title;
        let subtitle = utpc_obj.popup_subtitle;
        let incHtml = utpc_obj.inclusions.map(i => `✓ ${i}<br>`).join('');
        let waIncText = utpc_obj.inclusions.map(i => `✓ ${i}`).join('\n');
        let excNote = utpc_obj.exclusions_note;
        
        let servText = (serv === 'cab') ? 'Cab Only' : ((serv === 'hotel') ? 'Hotel Only' : 'Package (Hotel + Cab)');
        
        let content = `
            <table style="width:100%; border-collapse:collapse; background:#f8fafc; font-size:10px !important; color:#334155; margin-bottom:6px; border:1px solid #e2e8f0; border-radius:4px;">
                <tr>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0; width:50%;"><b>Dates:</b> ${start} ➔ ${end}</td>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0; width:50%;"><b>Season:</b> <b style="color:#ca8a04;">${seasonName}</b></td>
                </tr>
                <tr>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0;"><b>Pax:</b> ${pax} Persons</td>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0;"><b>Duration:</b> ${days} Days</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:4px; border-bottom:1px solid #e2e8f0;"><b>Service:</b> <span style="color:#b45309; font-weight:800;">${servText}</span></td>
                </tr>
                ${serv !== 'cab' ? `<tr><td colspan="2" style="padding:4px; border-bottom:1px solid #e2e8f0;"><b>Hotel:</b> ${hotel}</td></tr><tr><td colspan="2" style="padding:4px; border-bottom:1px solid #e2e8f0;"><b>Rooms:</b> ${rms}</td></tr>` : ''}
                ${serv !== 'hotel' ? `<tr><td colspan="2" style="padding:4px;"><b>Vehicle:</b> ${veh}</td></tr>` : ''}
            </table>

            ${serv === 'both' ? `
            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:4px; padding:6px; margin-bottom:6px;">
                <div style="font-size:9px !important; font-weight:800; color:#b45309; margin-bottom:4px; letter-spacing:0.5px;">INCLUSIONS</div>
                <div style="font-size:9px !important; color:#451a03; line-height:1.4; font-weight:600;">
                    ${incHtml}
                </div>
                <div style="font-size:8px !important; color:#dc2626; margin-top:4px; font-weight:700; font-style:italic; border-top:1px dashed #fcd34d; padding-top:4px;">* ${excNote}</div>
            </div>` : ''}

            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; padding:8px; margin-bottom:6px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                    <div style="font-size:11px !important; font-weight:700; color:#15803d;">Base Price PP:</div>
                    <div style="font-size:11px !important; font-weight:700; color:#15803d;">₹${Number(base_pp).toLocaleString('en-IN')}</div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px; border-bottom:1px dashed #bbf7d0; padding-bottom:4px;">
                    <div style="font-size:11px !important; font-weight:700; color:#15803d;">GST (5%) PP:</div>
                    <div style="font-size:11px !important; font-weight:700; color:#15803d;">₹${Number(gst_pp).toLocaleString('en-IN')}</div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <div style="font-size:13px !important; font-weight:800; color:#16a34a;">Total Price PP:</div>
                    <div style="font-size:13px !important; font-weight:800; color:#16a34a;">₹${Number(pp).toLocaleString('en-IN')}</div>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px solid #bbf7d0; padding-top:4px;">
                    <div style="font-size:15px !important; font-weight:900; color:#14532d;">Total (Inc GST):</div>
                    <div style="font-size:15px !important; font-weight:900; color:#14532d;">₹${Number(tot).toLocaleString('en-IN')}</div>
                </div>
            </div>
        `;
        
        $('#m-serv-days').text(`${days} Days | ${servText}`);
        $('#modal-dynamic-content').empty().html(content);
        
        let pageUrl = window.location.href;
        
        let incWaBlock = serv === 'both' ? `\n*Inclusions:*\n${waIncText}\n_${excNote}_\n` : '';

        let messageText = `*Soulful Pathfinder*\n_GSTIN: 19AXIPD7432L1Z5_\n\n*${title}*\n_${subtitle}_\n\n*Trip Details:*\n• Service: ${servText}\n• Duration: ${days} Days\n• Dates: ${start} to ${end}\n• Season: ${seasonName}\n\n*Package Details:*\n• Hotel Category: ${hotel}\n• Pax: ${pax} Persons\n• Vehicle: ${veh}\n• Rooms: ${rms}\n${incWaBlock}\n*Pricing Details:*\n• Base Price PP: ₹${Number(base_pp).toLocaleString('en-IN')}\n• GST (5%) PP: ₹${Number(gst_pp).toLocaleString('en-IN')}\n• Total Price PP: ${numPP}\n• *Grand Total: ${numTot}*\n\n*Detailed Itinerary:* ${pageUrl}`;
        
        $('#m-wa-btn').attr('href', `https://wa.me/${utpc_obj.wa_number}?text=${encodeURIComponent(messageText)}`);
        $('#utpcModal').fadeIn('fast');
    });

    $('#close-modal, .ktc-modal').click(function(e) {
        if(e.target === this) { $('.ktc-modal').fadeOut('fast'); }
    });

    // ==========================================
    // BOOK CUSTOM TRIP BUTTON
    // ==========================================
    $(document).on('click', '.btn-book-custom', function(e) {
        e.preventDefault(); e.stopPropagation();
        let btn = $(this);
        $('#cb_hotel').val(btn.data('hotel')); $('#cb_pax').val(btn.data('pax'));
        $('#cb_veh').val(btn.data('veh')); $('#cb_rms').val(btn.data('rms'));
        $('#cb_pp').val(btn.data('pp')); $('#cb_tot').val(btn.data('tot'));
        $('#cb_start').val(btn.data('start')); $('#cb_end').val(btn.data('end'));
        
        // Populate new hidden fields
        if($('#cb_days').length === 0) {
            $('#utpc-custom-book-form').prepend(`<input type="hidden" name="cb_days" id="cb_days"><input type="hidden" name="cb_pickup" id="cb_pickup"><input type="hidden" name="cb_service" id="cb_service">`);
        }
        $('#cb_days').val(btn.data('days'));
        $('#cb_pickup').val(btn.data('pickup'));
        $('#cb_service').val(btn.data('service'));

        let paxNum = Number(btn.data('pax'));
        let base_tot = Number(btn.data('basepp')) * paxNum;
        let gst_tot = Number(btn.data('gstpp')) * paxNum;

        let summary = `
            <table style="width:100%; border-collapse:collapse; background:#f8fafc; font-size:10px !important; color:#334155; margin-bottom:6px; border:1px solid #e2e8f0; border-radius:4px;">
                <tr>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0; width:50%;"><b>Dates:</b> ${btn.data('start')} to ${btn.data('end')}</td>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0; width:50%;"><b>Pax:</b> ${btn.data('pax')}</td>
                </tr>
                <tr>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0;"><b>Hotel:</b> ${btn.data('hotel')}</td>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0;"><b>Veh:</b> ${btn.data('veh')}</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:4px;"><b>Rooms:</b> ${btn.data('rms')}</td>
                </tr>
            </table>
            
            <div style="font-size:11px !important; margin-top:6px; padding-top:6px; border-top:1px dashed #cbd5e1;">
                <div style="display:flex; justify-content:space-between; margin-bottom:2px; color:#475569;">
                    <div>Base Price:</div> <div>₹${base_tot.toLocaleString('en-IN')} <div style="display:inline; font-size:8px !important;">(₹${Number(btn.data('basepp')).toLocaleString('en-IN')} PP)</div></div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px; color:#475569; border-bottom:1px dashed #e2e8f0; padding-bottom:4px;">
                    <div>GST (5%):</div> <div>+ ₹${gst_tot.toLocaleString('en-IN')} <div style="display:inline; font-size:8px !important;">(₹${Number(btn.data('gstpp')).toLocaleString('en-IN')} PP)</div></div>
                </div>
                <div style="display:flex; justify-content:space-between; font-weight:800; font-size:13px !important; color:#0f172a;">
                    <div>Total (Inc GST):</div> <div>₹${Number(btn.data('tot')).toLocaleString('en-IN')} <div style="display:inline; font-size:9px !important; color:#16a34a;">(₹${Number(btn.data('pp')).toLocaleString('en-IN')} PP)</div></div>
                </div>
            </div>
        `;
        $('#cb_summary').html(summary).css({'padding':'8px', 'background':'#fff', 'border':'1px solid #cbd5e1', 'border-radius':'6px', 'margin-bottom':'10px'});
        $('#utpcCustomBookModal').fadeIn('fast');
    });

    $('.close-cb-modal').click(function() { $('#utpcCustomBookModal').fadeOut('fast'); });

    $('#utpc-custom-book-form').submit(function(e) {
        e.preventDefault();
        let btn = $('#btn-save-cb');
        btn.text('SAVING...').prop('disabled', true);
        
        $.post(utpc_obj.ajax_url, $(this).serialize() + '&action=utpc_save_custom_booking&nonce=' + utpc_obj.nonce, function(response) {
            if(response.success) {
                alert("Custom Trip Booked Successfully!");
                $('#utpcCustomBookModal').fadeOut('fast');
                $('#utpc-custom-book-form')[0].reset();
            } else { alert(response.data || "An error occurred."); }
            btn.text('SAVE CUSTOM BOOKING').prop('disabled', false);
        }).fail(function() {
            alert("A server error occurred.");
            btn.text('SAVE CUSTOM BOOKING').prop('disabled', false);
        });
    });

    // ==========================================
    // FIXED DEPARTURE BOOKING
    // ==========================================
    $('#add-f-r').click(function() { $('#list-f-r').append($('#tpl-fixed-room-row').html()); });
    $('#fixed_tour_select').change(function() {
        let maxLeft = $(this).find(':selected').data('left') || 0;
        $('#max-pax-label').text(maxLeft);
        $('#fixed_tour_pax').attr('max', maxLeft);
    });

    $('#utpc-fixed-form').submit(function(e) {
        e.preventDefault();
        let fixedSelect = $('#fixed_tour_select').find(':selected');
        if(!fixedSelect.val()) { alert("Please select a Tour Date."); return; }
        
        let maxSeats = parseInt(fixedSelect.data('left') || 0);
        let reqSeats = parseInt($('#fixed_tour_pax').val() || 0);
        if (reqSeats > maxSeats) { alert("Error: You requested " + reqSeats + " persons, but only " + maxSeats + " seats are left."); return; }

        let btn = $('#btn-submit-fixed');
        btn.find('.btn-text').addClass('hidden'); btn.find('.btn-loader').removeClass('hidden'); btn.prop('disabled', true);

        $.post(utpc_obj.ajax_url, {
            action: 'utpc_calculate', nonce: utpc_obj.nonce, form_data: $(this).serialize()
        }, function(response) {
            if(response.success) { $('#utpc-fixed-results').html(response.data); } 
            else { $('#utpc-fixed-results').html('<div style="padding:15px;color:red;text-align:center;">Failed.</div>'); }
            btn.find('.btn-text').removeClass('hidden'); btn.find('.btn-loader').addClass('hidden'); btn.prop('disabled', false);
        });
    });

    $(document).on('click', '#btn-confirm-book', function(e) {
        e.preventDefault();
        let bk_customer_name = $('#bk_customer_name').val(); if(bk_customer_name) bk_customer_name = bk_customer_name.trim();
        let bk_phone = $('#bk_phone').val(); if(bk_phone) bk_phone = bk_phone.trim();
        let bk_email = $('#bk_email').val(); if(bk_email) bk_email = bk_email.trim();
        let bk_address = $('#bk_address').val(); if(bk_address) bk_address = bk_address.trim();
        let bk_child = $('#bk_child').val();
        let bk_discount_type = $('#bk_discount_type').val();
        let bk_discount_val = $('#bk_discount_val').val();

        if(!bk_customer_name || !bk_phone || !bk_email || !bk_address) { alert("Error: Please fill in the Customer Name, Phone Number, Email ID, and Full Address to confirm booking."); return; }

        let btn = $(this);
        let selectedOption = $('#fixed_tour_select').find(':selected');
        let maxAllowed = parseInt(selectedOption.data('left') || 0);
        let reqPax = parseInt($('#fixed_tour_pax').val() || 0);

        if (reqPax > maxAllowed) { alert("Error: You requested " + reqPax + " pax, but only " + maxAllowed + " seats are left."); return; }

        btn.text('Saving...').css('opacity', '0.7').prop('disabled', true);

        $.post(utpc_obj.ajax_url, {
            action: 'utpc_calculate', nonce: utpc_obj.nonce, form_data: $('#utpc-fixed-form').serialize(),
            is_booking: 'true', final_price: btn.data('price'),
            bk_customer_name: bk_customer_name, bk_phone: bk_phone, bk_email: bk_email, bk_address: bk_address, bk_child: bk_child,
            bk_discount_type: bk_discount_type, bk_discount_val: bk_discount_val
        }, function(response) {
            if(response.success) {
                let currentLeft = parseInt(selectedOption.attr('data-left')) || 0;
                let newLeft = Math.max(0, currentLeft - reqPax);
                selectedOption.data('left', newLeft);
                selectedOption.attr('data-left', newLeft);
                
                let baseTourName = selectedOption.text().replace(/\s*\(\d+\sSeats\sLeft\)/, '');
                if (newLeft <= 0) { selectedOption.text(baseTourName + " (SOLD OUT)").prop('disabled', true); } 
                else { selectedOption.text(baseTourName + " (" + newLeft + " Seats Left)"); }

                $('#utpc-fixed-results').html(response.data);
                $('#utpc-fixed-form')[0].reset();
                $('#list-f-r').empty();
                $('#max-pax-label').text('--');
                $('#utpc-view-results').html('');
                $('#view_tour_select').val('');
            } else {
                alert(response.data || "Error saving booking.");
                btn.text('CONFIRM BOOKING').css('opacity', '1').prop('disabled', false);
            }
        });
    });

    // ==========================================
    // VIEW / EDIT / DELETE / PAYMENT BOOKINGS
    // ==========================================
    $('#view_tour_select').change(function() {
        let tour_id = $(this).val();
        let resDiv = $('#utpc-view-results');
        if(!tour_id) { resDiv.html(''); return; }
        
        resDiv.html('<div style="text-align:center; padding:20px; color:#0073aa; font-weight:bold; font-size:14px;">Fetching Booking Details...</div>');
        $.post(utpc_obj.ajax_url, { action: 'utpc_get_tour_details', nonce: utpc_obj.nonce, tour_id: tour_id }, function(response) {
            if(response.success) { resDiv.html(response.data); } 
            else { resDiv.html('<div style="color:red; text-align:center; padding:15px; border:1px solid red; border-radius:6px; background:#fef2f2;">' + response.data + '</div>'); }
        }).fail(function() { resDiv.html('<div style="color:red; text-align:center; padding:15px;">A server error occurred while fetching the details.</div>'); });
    });

    $('#add-edit-f-r').click(function() { 
    let tid = $('#edit_tour_id').val();
    if(tid === 'custom_trip') { $('#edit-list-f-r').append($('#tpl-room-row').html()); } 
    else { $('#edit-list-f-r').append($('#tpl-fixed-room-row').html()); }
    });
    $('#add-edit-f-v').click(function() { $('#edit-list-f-v').append($('#tpl-veh-row').html()); });

    // EDIT MODAL
    $(document).on('click', '.btn-edit-booking', function(e) {
        e.preventDefault();
        let tid = $(this).data('tour');
        let serv = $(this).data('service');
        
        $('#edit_booking_id').val($(this).data('id'));
        $('#edit_tour_id').val(tid);
        $('#edit_name').val($(this).data('name'));
        $('#edit_phone').val($(this).data('phone'));
        $('#edit_email').val($(this).data('email'));
        $('#edit_address').val($(this).data('address'));
        $('#edit_pax').val($(this).data('pax'));
        $('#edit_child').val($(this).data('child'));
        
        $('#edit_discount_type').val($(this).data('disctype') || 'flat');
        $('#edit_discount_val').val($(this).data('discval') || '0');

        $('#edit-list-f-r').empty();
        let rawRoomKeys = $(this).data('roomkeys') || '';
        if(rawRoomKeys && serv !== 'cab') {
            let roomArray = String(rawRoomKeys).split(',');
            roomArray.forEach(function(room) {
                room = room.trim();
                if(room) { 
                    let newRow = tid === 'custom_trip' ? $($('#tpl-room-row').html()) : $($('#tpl-fixed-room-row').html()); 
                    newRow.find('select').val(room); 
                    $('#edit-list-f-r').append(newRow); 
                }
            });
        }

        $('#edit-list-f-v').empty();
        if(tid === 'custom_trip') {
            if(serv === 'hotel') {
                $('#edit-veh-section').hide();
                $('#edit-room-section').show();
            } else if(serv === 'cab') {
                $('#edit-room-section').hide();
                $('#edit-veh-section').show();
            } else {
                $('#edit-veh-section').show();
                $('#edit-room-section').show();
            }
            
            let rawVehKeys = $(this).data('vehkeys') || '';
            if(rawVehKeys && serv !== 'hotel') {
                let vehArray = String(rawVehKeys).split(',');
                vehArray.forEach(function(veh) {
                    veh = veh.trim();
                    if(veh) { let newRow = $($('#tpl-veh-row').html()); newRow.find('select').val(veh); $('#edit-list-f-v').append(newRow); }
                });
            }
        } else { 
            $('#edit-veh-section').hide(); 
            $('#edit-room-section').show();
        }
        $('#utpcEditModal').fadeIn('fast');
    });

    $('.close-edit-modal').click(function() { $('#utpcEditModal').fadeOut('fast'); });

    $('#utpc-edit-form').submit(function(e) {
        e.preventDefault();
        
        let serv = $('#edit_tour_id').val() === 'custom_trip' ? 'both' : 'fixed'; // Validated securely on backend
        
        let btn = $('#btn-save-edit');
        btn.text('SAVING...').prop('disabled', true);
        
        $.post(utpc_obj.ajax_url, $(this).serialize() + '&action=utpc_update_booking&nonce=' + utpc_obj.nonce, function(response) {
            if(response.success) { alert(response.data); $('#utpcEditModal').fadeOut('fast'); $('#view_tour_select').change(); } 
            else { alert(response.data || "An error occurred."); }
            btn.text('SAVE & RECALCULATE PRICE').prop('disabled', false);
        }).fail(function() { alert("A server error occurred."); btn.text('SAVE & RECALCULATE PRICE').prop('disabled', false); });
    });

    // EMAIL BUTTON
    $(document).on('click', '.btn-resend-email', function(e) {
        e.preventDefault();
        let btn = $(this);
        btn.text('SENDING...').prop('disabled', true).css('opacity', '0.7');
        $.post(utpc_obj.ajax_url, { action: 'utpc_resend_email', nonce: utpc_obj.nonce, booking_id: btn.data('id'), tour_id: btn.data('tour') }, function(response) {
            alert(response.success ? response.data : "Error: " + response.data);
            btn.text('EMAIL').prop('disabled', false).css('opacity', '1');
        }).fail(function() { alert("A server error occurred."); btn.text('EMAIL').prop('disabled', false).css('opacity', '1'); });
    });

    // DELETE BUTTON
    $(document).on('click', '.btn-delete-booking', function(e) {
        e.preventDefault();
        let btn = $(this);
        if(confirm("Are you sure you want to permanently delete this booking? This action cannot be undone.")) {
            btn.text('DELETING...').prop('disabled', true).css('opacity', '0.5');
            $.post(utpc_obj.ajax_url, { action: 'utpc_delete_booking', nonce: utpc_obj.nonce, booking_id: btn.data('id') }, function(response) {
                if(response.success) { $('#view_tour_select').change(); } 
                else { alert("Error: " + response.data); btn.text('DELETE').prop('disabled', false).css('opacity', '1'); }
            }).fail(function() { alert("A server error occurred."); btn.text('DELETE').prop('disabled', false).css('opacity', '1'); });
        }
    });

    // PAYMENT LOGIC
    $(document).on('click', '.btn-pay-booking', function(e) {
        e.preventDefault();
        let btn = $(this);
        let tot = parseFloat(btn.data('tot')) || 0;
        let paid = parseFloat(btn.data('paid')) || 0;
        let bal = Math.max(0, tot - paid);

        $('#pay_booking_id').val(btn.data('id'));
        $('#pay_ui_total').text('₹' + tot.toLocaleString('en-IN'));
        $('#pay_ui_paid').text('₹' + paid.toLocaleString('en-IN'));
        $('#pay_ui_bal').text('₹' + bal.toLocaleString('en-IN'));
        $('#pay_amount').val(''); $('#pay_note').val('');
        $('#utpcPaymentModal').fadeIn('fast');
    });

    $('.close-pay-modal').click(function() { $('#utpcPaymentModal').fadeOut('fast'); });

    $('#utpc-payment-form').submit(function(e) {
        e.preventDefault();
        let btn = $('#btn-save-payment');
        btn.text('PROCESSING...').prop('disabled', true);
        
        $.post(utpc_obj.ajax_url, $(this).serialize() + '&action=utpc_add_payment&nonce=' + utpc_obj.nonce, function(response) {
            if(response.success) {
                alert(response.data);
                $('#utpcPaymentModal').fadeOut('fast');
                $('#view_tour_select').change(); 
            } else { alert(response.data || "An error occurred."); }
            btn.text('SAVE PAYMENT & SEND RECEIPT').prop('disabled', false);
        }).fail(function() { alert("A server error occurred."); btn.text('SAVE PAYMENT & SEND RECEIPT').prop('disabled', false); });
    });

    // ==========================================
    // VIEW RECEIPT MODAL (FOR SCREENSHOTS)
    // ==========================================
    $(document).on('click', '.btn-view-receipt', function(e) {
        e.preventDefault();
        let bid = $(this).data('id');
        let tid = $(this).data('tour');
        
        $('#receipt_body').html('<div style="text-align:center; padding:20px; font-weight:bold; color:#0073aa;">Generating Receipt...</div>');
        $('#utpcReceiptModal').fadeIn('fast');
        
        $.post(utpc_obj.ajax_url, { 
            action: 'utpc_get_receipt', 
            nonce: utpc_obj.nonce, 
            booking_id: bid, 
            tour_id: tid 
        }, function(response) {
            if(response.success) {
                $('#receipt_body').html(response.data);
            } else {
                $('#receipt_body').html('<div style="color:red; text-align:center;">Error loading receipt.</div>');
            }
        }).fail(function() {
            $('#receipt_body').html('<div style="color:red; text-align:center;">Server error.</div>');
        });
    });

    $('.close-receipt-modal').click(function() { $('#utpcReceiptModal').fadeOut('fast'); });

});