jQuery(document).ready(function($) {
    // Mode Toggles (Auto vs Custom)
    $('input[name="room_mode"]').change(function() {
        $('#div-r-a').toggleClass('hidden', this.value !== 'auto');
        $('#div-r-c').toggleClass('hidden', this.value !== 'custom');
    });
    
    $('input[name="vehicle_mode"]').change(function() {
        $('#div-v-a').toggleClass('hidden', this.value !== 'auto');
        $('#div-v-c').toggleClass('hidden', this.value !== 'custom');
    });

    // Dynamic Builders
    $('#add-r').click(function() {
        $('#list-r').append($('#tpl-room-row').html());
    });
    
    $('#add-v').click(function() {
        $('#list-v').append($('#tpl-veh-row').html());
    });
    
    // Remove Row Button
    $(document).on('click', '.btn-rem', function() { 
        $(this).parent().remove(); 
    });

    // AJAX Form Submit
    $('#utpc-form').submit(function(e) {
        e.preventDefault();
        
        let btn = $('#btn-submit');
        let txt = btn.find('.btn-text');
        let ldr = btn.find('.btn-loader');
        
        // UI Loading State
        txt.addClass('hidden'); 
        ldr.removeClass('hidden'); 
        btn.prop('disabled', true);

        // Make AJAX request
        $.post(utpc_obj.ajax_url, {
            action: 'utpc_calculate',
            nonce: utpc_obj.nonce,
            form_data: $(this).serialize()
        }, function(response) {
            if(response.success) {
                $('#utpc-results').html(response.data);
            } else {
                $('#utpc-results').html('<div style="padding:15px;color:red;text-align:center;">Failed to calculate or no results found.</div>');
            }
            
            // Restore UI State
            txt.removeClass('hidden'); 
            ldr.addClass('hidden'); 
            btn.prop('disabled', false);
        }).fail(function() {
            $('#utpc-results').html('<div style="padding:15px;color:red;text-align:center;">Server error occurred. Try again.</div>');
            txt.removeClass('hidden'); 
            ldr.addClass('hidden'); 
            btn.prop('disabled', false);
        });
    });

    // Modal Popup Logic
    $(document).on('click', '.utpc-row', function() {
        // Use .attr() for dynamic HTML safety
        let pax = $(this).attr('data-pax');
        let veh = $(this).attr('data-veh');
        let rms = $(this).attr('data-rms');
        let pp = $(this).attr('data-pp');
        let hotel = $(this).attr('data-hotel');
        
        let numPP = "₹" + Number(pp).toLocaleString('en-IN');
        
        let title = $('#utpcModal .modal-header h2').text().trim();
        let subtitle = $('#utpcModal .modal-header p').text().trim();
        
        // Populate Modal Data UI
        $('#m-pax').text(pax + " Persons"); 
        $('#m-hotel').text(hotel);
        $('#m-veh').text(veh);
        $('#m-rms').text(rms); 
        $('#m-pp').text(numPP);
        
        // Prepare Formatted WhatsApp Message
        let messageText = `*${title}*\n_${subtitle}_\n\n*Package Details:*\n• Hotel Category: ${hotel}\n• Pax: ${pax} Persons\n• Vehicle: ${veh}\n• Rooms: ${rms}\n\n*Price PP: ${numPP}*`;
        let message = encodeURIComponent(messageText);
        
        $('#m-wa-btn').attr('href', `https://wa.me/${utpc_obj.wa_number}?text=${message}`);
        
        // Show Modal
        $('#utpcModal').fadeIn('fast');
    });

    // Close Modal Logic
    $('#close-modal, .ktc-modal').click(function(e) {
        if(e.target === this) {
            $('#utpcModal').fadeOut('fast');
        }
    });
});