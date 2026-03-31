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
        // Grab values safely with fallbacks
        let pax = $(this).attr('data-pax') || 'N/A';
        let veh = $(this).attr('data-veh') || 'N/A';
        let rms = $(this).attr('data-rms') || 'N/A';
        let pp = $(this).attr('data-pp') || '0';
        let hotel = $(this).attr('data-hotel') || 'N/A';
        let start = $(this).attr('data-start') || 'N/A';
        let end = $(this).attr('data-end') || 'N/A';
        
        // ONLY GET THE SEASON NAME. Ignore the percentage.
        let seasonName = $(this).attr('data-season-name') || 'Normal Season';
        
        let numPP = "₹" + Number(pp).toLocaleString('en-IN');
        let title = $('#utpcModal .modal-header h2').text().trim();
        let subtitle = $('#utpcModal .modal-header p').text().trim();
        
        // Populate Modal Data UI
        $('#m-dates').text(`${start} ➔ ${end}`);
        $('#m-season').text(`${seasonName}`); 
        $('#m-pax').text(pax + " Persons"); 
        $('#m-hotel').text(hotel);
        $('#m-veh').text(veh);
        $('#m-rms').text(rms); 
        $('#m-pp').text(numPP);
        
        // Grab the current page URL
        let pageUrl = window.location.href;
        
        // Prepare Formatted WhatsApp Message (Including the Inclusions/Exclusions and URL)
        let messageText = `*${title}*\n_${subtitle}_\n\n*Trip Dates:* ${start} to ${end}\n*Season:* ${seasonName}\n\n*Package Details:*\n• Hotel Category: ${hotel}\n• Pax: ${pax} Persons\n• Vehicle: ${veh}\n• Rooms: ${rms}\n\n*Inclusions:*\n✓ Jammu / Srinagar Pickup and drop\n✓ Sightseeing & Transfers\n✓ All Accommodation\n✓ Breakfast & Dinner\n_Not mentioned is all EXCLUSION_\n\n*Price PP: ${numPP}*\n\n*Detailed Itinerary:* ${pageUrl}`;
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