<?php
if (!defined('ABSPATH')) { exit; }

add_shortcode('ultimate_tour_calculator', 'utpc_render_shortcode');

function utpc_get_booked_seats($tour_id) {
    global $wpdb;
    $bookings = get_posts(['post_type' => 'utpc_booking', 'meta_key' => 'tour_id', 'meta_value' => $tour_id, 'posts_per_page' => -1, 'fields' => 'ids']);
    $total = 0;
    foreach ($bookings as $id) { $total += (int) get_post_meta($id, 'pax', true); }
    return $total;
}

function utpc_render_shortcode() {
    $settings = include(UTPC_PATH . 'config/settings.php');
    
    $role_type = utpc_get_user_role_type();
    $can_book  = ($role_type === 'manager' || $role_type === 'employee');
    
    ob_start();
    ?>
    <div id="utpc-app-wrapper" class="utpc-wrapper">
        
        <?php if ($can_book): ?>
        <div style="display:flex; margin-bottom: 15px; border-radius: 6px; overflow: hidden; border: 1px solid #0073aa;">
            <button type="button" class="utpc-tab-btn active" data-target="utpc-custom-wrap" style="flex:1; padding:10px; border:none; background:#0073aa; color:#fff; font-weight:bold; cursor:pointer; font-size:12px;">Custom Calculator</button>
            <button type="button" class="utpc-tab-btn" data-target="utpc-fixed-wrap" style="flex:1; padding:10px; border:none; background:#fff; color:#0073aa; font-weight:bold; cursor:pointer; font-size:12px;">Fixed Departure Booking</button>
            <button type="button" class="utpc-tab-btn" data-target="utpc-view-wrap" style="flex:1; padding:10px; border:none; background:#fff; color:#0073aa; font-weight:bold; cursor:pointer; font-size:12px;">View Trip Bookings</button>
        </div>
        <?php endif; ?>

        <div id="utpc-custom-wrap" class="utpc-tab-content">
            <form id="utpc-form">
                <input type="hidden" name="calc_mode" value="custom"> 
                <div class="input-master-table">
                    
                    <div class="compact-box" style="grid-column: 1 / -1; background:#f8fafc; border-color:#cbd5e1;">
                        <div class="compact-label" style="color:#334155; border-bottom:1px dashed #cbd5e1;">1. Trip Parameters</div>
                        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom: 5px;">
                            <div style="flex:1; min-width:80px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Total Pax</label>
                                <input type="number" name="tour_pax" class="u-field" value="4" min="1" style="font-weight:bold;">
                            </div>
                            <div style="flex:1; min-width:110px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Start Date</label>
                                <input type="date" name="tour_date" class="u-field" value="<?php echo date('Y-m-d', strtotime('tomorrow')); ?>" required>
                            </div>
                            <div style="flex:1; min-width:80px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Total Days</label>
                                <input type="number" name="trip_days" class="u-field" value="7" min="1" style="font-weight:bold;">
                            </div>
                            <div style="flex:1.5; min-width:150px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Pickup Location</label>
                                <select name="pickup_location" class="u-field" style="font-weight:bold;">
                                    <?php foreach($settings['pickup_locations'] as $k => $v) { echo "<option value='{$k}'>{$v}</option>"; } ?>
                                </select>
                            </div>
                            <div style="flex:1.5; min-width:150px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Service Type</label>
                                <select name="service_type" id="ui_service_type" class="u-field" style="background:#fefce8; color:#b45309; font-weight:bold;">
                                    <?php foreach($settings['service_types'] as $k => $v) { echo "<option value='{$k}'>{$v}</option>"; } ?>
                                </select>
                            </div>
                            <div id="ui_hotel_cat_box" style="flex:1.5; min-width:120px;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Hotel Category</label>
                                <select name="hotel_category" class="u-field" style="background:#f0f7ff; font-weight:600; border-color:#bae6fd; color:#0369a1;">
                                    <?php foreach($settings['hotel_categories'] as $k => $c) { echo "<option value='{$k}'>{$c['name']}</option>"; } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="compact-box" id="box-rooms">
                        <div class="compact-label">2. Rooms & Accommodation</div>
                        <div class="mini-toggles" style="margin-top: 5px;">
                            <input type="radio" id="r_a" name="room_mode" value="auto" checked> <label for="r_a">AUTO ROOMS</label>
                            <input type="radio" id="r_c" name="room_mode" value="custom"> <label for="r_c">CUSTOM ROOMS</label>
                        </div>
                        <div id="div-r-a">
                            <select name="room_pref" class="u-field">
                                <option value="any">Any Room Combo</option>
                                <?php foreach($settings['rooms'] as $k => $r) { echo "<option value='{$k}'>Prefer {$r['name']}</option>"; } ?>
                            </select>
                        </div>
                        <div id="div-r-c" class="hidden"><div id="list-r" class="builder-list"></div><button type="button" id="add-r" class="btn-add">+ Add Room</button></div>
                    </div>

                    <div class="compact-box" id="box-transport">
                        <div class="compact-label">3. Transport & Vehicle</div>
                        <div class="mini-toggles" style="margin-top: 5px;">
                            <input type="radio" id="v_a" name="vehicle_mode" value="auto" checked> <label for="v_a">AUTO VEHICLE</label>
                            <input type="radio" id="v_c" name="vehicle_mode" value="custom"> <label for="v_c">CUSTOM VEHICLE</label>
                        </div>
                        <div id="div-v-a" style="margin-bottom: 5px;">
                            <select name="cab_pref" class="u-field">
                                <option value="any">Any Cab Combo</option>
                                <?php foreach($settings['vehicles'] as $k => $v) { echo "<option value='{$k}'>Prefer {$v['name']}</option>"; } ?>
                            </select>
                        </div>
                        <div id="div-v-c" class="hidden" style="margin-bottom: 5px;"><div id="list-v" class="builder-list"></div><button type="button" id="add-v" class="btn-add">+ Add Car</button></div>
                    </div>
                </div>
                <button type="submit" class="btn-main" id="btn-submit"><span class="btn-text">GET PACKAGE PRICES</span><span class="btn-loader hidden">Calculating...</span></button>
            </form>
            <div id="utpc-results"></div>
        </div>

        <?php if ($can_book): ?>
        <div id="utpc-fixed-wrap" class="utpc-tab-content hidden">
            <form id="utpc-fixed-form">
                <input type="hidden" name="calc_mode" value="fixed">
                <div class="input-master-table">
                    <div class="compact-box" style="grid-column: 1 / -1;">
                        <div class="compact-label">1. Select Fixed Departure</div>
                        <select name="fixed_tour" id="fixed_tour_select" class="u-field" style="background:#f0f7ff; font-weight:800; border-color:#bae6fd; color:#0369a1; height: 40px; font-size: 14px;">
                            <option value="">-- Choose a Tour --</option>
                            <?php 
                            if(isset($settings['fixed_departures'])) {
                                foreach($settings['fixed_departures'] as $k => $tour) {
                                    $booked = utpc_get_booked_seats($k);
                                    $left = max(0, $tour['total_seats'] - $booked);
                                    $status = ($left <= 0) ? " (SOLD OUT)" : " ({$left} Seats Left)";
                                    $disabled = ($left <= 0) ? "disabled" : "";
                                    echo "<option value='{$k}' data-left='{$left}' {$disabled}>{$tour['name']}{$status}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="compact-box" style="grid-column: 1 / -1;">
                        <div class="compact-label">2. Booking Details & Room Sharing</div>
                        <div style="display:flex; gap:10px; margin-bottom: 12px;">
                            <div style="flex:1;"><label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Total Pax (Max <span id="max-pax-label">--</span>)</label><input type="number" name="tour_pax" id="fixed_tour_pax" class="u-field" value="2" min="1" required></div>
                        </div>
                        <label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Select Sharing Rooms</label>
                        <div id="list-f-r" class="builder-list"></div><button type="button" id="add-f-r" class="btn-add">+ Add Room Requirement</button>
                    </div>
                </div>
                <button type="submit" class="btn-main" id="btn-submit-fixed"><span class="btn-text">PREVIEW PRICING</span><span class="btn-loader hidden">Calculating...</span></button>
            </form>
            <div id="utpc-fixed-results"></div>
        </div>

        <div id="utpc-view-wrap" class="utpc-tab-content hidden">
            <div class="input-master-table" style="grid-template-columns: 1fr;">
                <div class="compact-box">
                    <div class="compact-label">Select Tour to View Bookings</div>
                    <select id="view_tour_select" class="u-field" style="background:#f8fafc; font-weight:800; border-color:#cbd5e1; color:#0f172a; height: 40px; font-size: 14px;">
                        <option value="">-- Select a Trip --</option>
                        <option value="custom_trip" style="background:#fef3c7; color:#b45309;">🌟 ALL CUSTOM TRIPS 🌟</option>
                        <?php 
                        if(isset($settings['fixed_departures'])) {
                            foreach($settings['fixed_departures'] as $k => $tour) { echo "<option value='{$k}'>Fixed: {$tour['name']}</option>"; }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div id="utpc-view-results" style="margin-top: 15px;"></div>
        </div>
        <?php endif; ?>
    </div>

    <div id="tpl-room-row" class="hidden">
        <div class="build-row"><select name="custom_rooms[]" class="u-field" style="border:none; background:transparent;"><?php foreach($settings['rooms'] as $k => $r) { echo "<option value='{$k}'>{$r['name']} ({$r['capacity']} Pax)</option>"; } ?></select><button type="button" class="btn-rem">&times;</button></div>
    </div>
    
    <div id="tpl-fixed-room-row" class="hidden">
        <div class="build-row"><select name="custom_rooms[]" class="u-field" style="border:none; background:transparent;"><?php if(isset($settings['fixed_sharing_rooms'])) { foreach($settings['fixed_sharing_rooms'] as $k => $r) { echo "<option value='{$k}'>{$r['name']} ({$r['capacity']} Pax)</option>"; } } ?></select><button type="button" class="btn-rem">&times;</button></div>
    </div>

    <div id="tpl-veh-row" class="hidden">
        <div class="build-row"><select name="custom_vehicles[]" class="u-field" style="border:none; background:transparent;"><?php foreach($settings['vehicles'] as $k => $v) { echo "<option value='{$k}'>{$v['name']} ({$v['capacity']} Pax)</option>"; } ?></select><button type="button" class="btn-rem">&times;</button></div>
    </div>

    <div id="utpcModal" class="ktc-modal">
        <div class="modal-content" style="max-width: 420px; background:#f1f5f9; padding:0; overflow:hidden; border-radius:8px;">
            <div class="modal-header" style="background:#0f172a; padding:15px; position:relative; text-align:center;">
                <div class="close-modal" style="color:#fff; top:10px; right:15px; position:absolute; cursor:pointer; font-size:24px; font-weight:bold;">&times;</div>
                <div style="margin:0 0 2px 0; color:#fff; font-size:18px; font-weight:900; text-transform:uppercase; letter-spacing:0.5px;">SOULFUL PATHFINDER</div>
                <div style="font-size:10px; color:#94a3b8; font-weight:700;">GSTIN: 19AXIPD7432L1Z5</div>
            </div>
            <div class="modal-body" style="padding: 15px; background:#f1f5f9; line-height:1.2; font-family:sans-serif;">
                <div style="background:#fff; border-radius:6px; padding:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); border:1px solid #e2e8f0; color:#1e293b;">
                    <div style="text-align:center; border-bottom:1px dashed #cbd5e1; padding-bottom:10px; margin-bottom:10px;">
                        <div style="margin:0 0 4px 0; color:#0369a1; font-size:16px; font-weight:800;"><?php echo esc_html($settings['popup_title']); ?></div>
                        <div id="m-serv-days" style="font-size:11px; color:#475569; font-weight:600; margin-bottom:2px;"></div>
                    </div>
                    <div id="modal-dynamic-content"></div>
                    <a href="#" id="m-wa-btn" target="_blank" class="btn-whatsapp" style="display:block; text-align:center; background:#25D366; color:#fff; text-decoration:none; padding:12px; border-radius:4px; font-weight:800; font-size:13px; margin-top:12px; box-shadow:0 2px 4px rgba(37,211,102,0.3);">ENQUIRE ON WHATSAPP</a>
                    <div class="policy-compact" style="font-size:10px; color:#64748b; margin-top:10px; text-align:center; border-top:1px solid #e2e8f0; padding-top:8px;"><?php echo wp_kses_post($settings['popup_note']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($can_book): ?>
    <div id="utpcCustomBookModal" class="ktc-modal">
        <div class="modal-content" style="max-width: 480px;">
            <div class="close-cb-modal close-modal" style="font-size:24px; font-weight:bold; cursor:pointer;">&times;</div>
            <div class="modal-header"><h2>Confirm Custom Booking</h2></div>
            <div class="modal-body" style="background:#f8fafc;">
                <div id="cb_summary"></div>
                <form id="utpc-custom-book-form">
                    <input type="hidden" name="cb_hotel" id="cb_hotel"> <input type="hidden" name="cb_pax" id="cb_pax">
                    <input type="hidden" name="cb_veh" id="cb_veh"> <input type="hidden" name="cb_rms" id="cb_rms">
                    <input type="hidden" name="cb_pp" id="cb_pp"> <input type="hidden" name="cb_tot" id="cb_tot">
                    <input type="hidden" name="cb_start" id="cb_start"> <input type="hidden" name="cb_end" id="cb_end">
                    
                    <div style="background:#fff; border:1px solid #cbd5e1; padding:10px; border-radius:6px;">
                        <div style="font-size:10px; font-weight:800; color:#475569; margin-bottom:8px; text-transform:uppercase;">Customer Details</div>
                        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                            <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Name *</label><input type="text" name="bk_name" id="cb_name" class="u-field" required></div>
                            <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Phone *</label><input type="text" name="bk_phone" id="cb_phone" class="u-field" required></div>
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                            <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Email *</label><input type="email" name="bk_email" id="cb_email" class="u-field" required></div>
                            <div style="flex:1; min-width:80px;"><label style="font-size:10px; font-weight:700;">Child</label><input type="number" name="bk_child" id="cb_child" class="u-field" value="0" min="0"></div>
                        </div>
                        <div style="margin-bottom:10px;"><label style="font-size:10px; font-weight:700;">Full Address *</label><input type="text" name="bk_address" id="cb_address" class="u-field" required></div>
                        
                        <div style="display:flex; flex-wrap:wrap; gap:10px; border-top:1px dashed #cbd5e1; padding-top:10px;">
                            <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Discount Type</label>
                                <select name="bk_discount_type" id="cb_discount_type" class="u-field"><option value="flat">Flat (₹)</option><option value="percent">Percentage (%)</option></select>
                            </div>
                            <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Discount Value</label>
                                <input type="number" name="bk_discount_val" id="cb_discount_val" class="u-field" value="0" min="0" step="any">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-main" id="btn-save-cb" style="margin-top:15px;">SAVE CUSTOM BOOKING</button>
                </form>
            </div>
        </div>
    </div>

    <div id="utpcEditModal" class="ktc-modal">
        <div class="modal-content" style="max-width: 480px;">
            <div class="close-edit-modal close-modal" style="font-size:24px; font-weight:bold; cursor:pointer;">&times;</div>
            <div class="modal-header"><h2>Edit Customer Booking</h2></div>
            <div class="modal-body" style="background:#f8fafc;">
                <form id="utpc-edit-form">
                    <input type="hidden" name="booking_id" id="edit_booking_id"> <input type="hidden" name="tour_id" id="edit_tour_id">
                    
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                        <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Name</label><input type="text" name="bk_name" id="edit_name" class="u-field" required></div>
                        <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Phone</label><input type="text" name="bk_phone" id="edit_phone" class="u-field" required></div>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                        <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Email</label><input type="email" name="bk_email" id="edit_email" class="u-field" required></div>
                        <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Address</label><input type="text" name="bk_address" id="edit_address" class="u-field" required></div>
                    </div>
                    <hr style="border:none; border-top:1px dashed #cbd5e1; margin:15px 0;">
                    
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                        <div style="flex:1; min-width:80px;"><label style="font-size:10px; font-weight:700;">Total Pax</label><input type="number" name="bk_pax" id="edit_pax" class="u-field" required min="1"></div>
                        <div style="flex:1; min-width:80px;"><label style="font-size:10px; font-weight:700;">Total Child</label><input type="number" name="bk_child" id="edit_child" class="u-field" min="0"></div>
                    </div>

                    <div id="edit-room-section" style="margin-bottom:15px; background: #fff; padding:10px; border:1px solid #e2e8f0; border-radius:4px;">
                        <label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Edit Sharing Rooms</label>
                        <div id="edit-list-f-r" class="builder-list" style="margin-top:8px;"></div><button type="button" id="add-edit-f-r" class="btn-add" style="margin-top:8px;">+ Add Room</button>
                    </div>
                    <div id="edit-veh-section" style="margin-bottom:15px; background: #fff; padding:10px; border:1px solid #e2e8f0; border-radius:4px; display:none;">
                        <label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Edit Vehicles</label>
                        <div id="edit-list-f-v" class="builder-list" style="margin-top:8px;"></div><button type="button" id="add-edit-f-v" class="btn-add" style="margin-top:8px;">+ Add Vehicle</button>
                    </div>

                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px; border-top:1px dashed #cbd5e1; padding-top:10px;">
                        <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Discount Type</label>
                            <select name="bk_discount_type" id="edit_discount_type" class="u-field"><option value="flat">Flat (₹)</option><option value="percent">Percentage (%)</option></select>
                        </div>
                        <div style="flex:1; min-width:120px;"><label style="font-size:10px; font-weight:700;">Discount Value</label>
                            <input type="number" name="bk_discount_val" id="edit_discount_val" class="u-field" value="0" min="0" step="any">
                        </div>
                    </div>
                    <button type="submit" class="btn-main" id="btn-save-edit" style="margin-top:15px;">SAVE & RECALCULATE PRICE</button>
                </form>
            </div>
        </div>
    </div>

    <div id="utpcPaymentModal" class="ktc-modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="close-pay-modal close-modal" style="font-size:24px; font-weight:bold; cursor:pointer;">&times;</div>
            <div class="modal-header"><h2>Record Payment</h2></div>
            <div class="modal-body" style="background:#f8fafc;">
                <div style="background:#fff; padding:12px; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:15px; font-size:13px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;"><div>Total Price:</div> <div style="font-weight:bold;" id="pay_ui_total"></div></div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;"><div>Already Paid:</div> <div style="font-weight:bold; color:#16a34a;" id="pay_ui_paid"></div></div>
                    <div style="display:flex; justify-content:space-between; padding-top:6px; border-top:1px dashed #cbd5e1;"><div>Remaining Balance:</div> <div style="font-weight:bold; color:#dc2626;" id="pay_ui_bal"></div></div>
                </div>
                <form id="utpc-payment-form">
                    <input type="hidden" name="booking_id" id="pay_booking_id">
                    <div style="margin-bottom:12px;"><label style="font-size:10px; font-weight:700;">Payment Received (₹) *</label><input type="number" name="pay_amount" id="pay_amount" class="u-field" required min="1" step="any"></div>
                    <div style="margin-bottom:15px;"><label style="font-size:10px; font-weight:700;">Payment Method / Note</label><input type="text" name="pay_note" id="pay_note" class="u-field" placeholder="e.g., Cash, UPI, Bank Transfer"></div>
                    <button type="submit" class="btn-main" id="btn-save-payment">SAVE PAYMENT & SEND RECEIPT</button>
                </form>
            </div>
        </div>
    </div>

    <div id="utpcReceiptModal" class="ktc-modal">
        <div class="modal-content" style="max-width: 420px; background:#f1f5f9; padding:0; overflow:hidden; border-radius:8px;">
            <div class="modal-header" style="background:#0f172a; padding:15px; position:relative; text-align:center;">
                <div class="close-receipt-modal close-modal" style="color:#fff; top:10px; right:15px; position:absolute; cursor:pointer; font-size:24px; font-weight:bold;">&times;</div>
                <div style="margin:0; color:#fff; font-size:18px; font-weight:900; text-transform:uppercase;">Booking Receipt</div>
            </div>
            <div class="modal-body" id="receipt_body" style="padding: 20px; background:#f1f5f9;">
                </div>
            <div style="text-align:center; padding:10px; background:#e2e8f0; border-top:1px solid #cbd5e1;">
                <div style="font-size:10px; color:#475569; margin:0; font-weight:600;">Take a screenshot of this receipt to send to the customer via WhatsApp.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}