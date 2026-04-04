<?php
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_utpc_calculate', 'utpc_handle_ajax_calculation');
add_action('wp_ajax_nopriv_utpc_calculate', 'utpc_handle_ajax_calculation');

function utpc_extract_keys($raw_string, $items_config) {
    $keys = [];
    $parts = explode(',', $raw_string);
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;
        if (isset($items_config[$part])) { $keys[] = $part; } 
        elseif (preg_match('/^(\d+)\s*x\s+(.+)$/i', $part, $matches)) {
            $qty = (int)$matches[1]; $name = trim($matches[2]);
            foreach ($items_config as $k => $v) {
                if (trim($v['name']) === $name) { for($i=0; $i<$qty; $i++) $keys[] = $k; break; }
            }
        } else {
            foreach ($items_config as $k => $v) {
                if (trim($v['name']) === $part) { $keys[] = $k; break; }
            }
        }
    }
    return $keys;
}
function utpc_get_cost_from_keys($keys_array, $items_config) {
    $cost = 0; if(empty($keys_array)) return 0;
    $counts = array_count_values((array)$keys_array);
    foreach ($counts as $k => $qty) { if (isset($items_config[$k])) { $cost += $qty * $items_config[$k]['price']; } }
    return $cost;
}
function utpc_get_cost_from_raw($raw_string, $items_config) {
    $keys = utpc_extract_keys($raw_string, $items_config);
    return utpc_get_cost_from_keys($keys, $items_config);
}
function utpc_get_display_from_keys($keys_array, $items_config) {
    if (empty($keys_array)) return '';
    $counts = array_count_values((array)$keys_array);
    $raw = [];
    foreach ($counts as $k => $qty) { if (isset($items_config[$k])) { $name = $items_config[$k]['name']; $raw[] = "{$qty}x {$name}"; } }
    return implode(', ', $raw);
}

add_action('wp_ajax_utpc_save_custom_booking', 'utpc_handle_save_custom_booking');
add_action('wp_ajax_utpc_get_tour_details', 'utpc_handle_get_tour_details');
add_action('wp_ajax_utpc_resend_email', 'utpc_handle_resend_email');
add_action('wp_ajax_utpc_update_booking', 'utpc_handle_update_booking');
add_action('wp_ajax_utpc_delete_booking', 'utpc_handle_delete_booking');
add_action('wp_ajax_utpc_add_payment', 'utpc_handle_add_payment');
add_action('wp_ajax_utpc_get_receipt', 'utpc_handle_get_receipt');

$company_footer = "<hr><div style='font-size:11px; color:#64748b; margin-top:15px; line-height:1.4;'><strong>Soulful Pathfinder</strong><br>GSTIN: 19AXIPD7432L1Z5</div>";

function utpc_handle_save_custom_booking() {
    check_ajax_referer('utpc_nonce', 'nonce');
    if (!in_array(utpc_get_user_role_type(), ['manager', 'employee'])) wp_send_json_error('Unauthorized');

    global $company_footer;

    $customer = sanitize_text_field($_POST['bk_name']);
    $pax      = intval($_POST['cb_pax']);
    $tot_with_gst = floatval($_POST['cb_tot']); 
    
    $base_price = round($tot_with_gst / 1.05);

    $hotel    = sanitize_text_field($_POST['cb_hotel']);
    $veh      = sanitize_text_field($_POST['cb_veh']);
    $rms      = sanitize_text_field($_POST['cb_rms']);
    $start    = sanitize_text_field($_POST['cb_start']);
    $end      = sanitize_text_field($_POST['cb_end']);
    $phone    = sanitize_text_field($_POST['bk_phone']);
    $email    = sanitize_email($_POST['bk_email']);
    $address  = sanitize_text_field($_POST['bk_address']);
    $child    = intval($_POST['bk_child']);
    
    $trip_days = intval($_POST['cb_days'] ?? 7);
    $pickup_loc = sanitize_text_field($_POST['cb_pickup'] ?? 'srinagar');
    $service_type = sanitize_text_field($_POST['cb_service'] ?? 'both');

    $discount_type = sanitize_text_field($_POST['bk_discount_type'] ?? 'flat');
    $discount_val  = floatval($_POST['bk_discount_val'] ?? 0);
    $discount_amount = 0;
    if ($discount_val > 0) {
        if ($discount_type === 'percent') { $discount_amount = round(($base_price * $discount_val) / 100); } 
        else { $discount_amount = $discount_val; }
    }
    
    $subtotal = max(0, $base_price - $discount_amount);
    $gst_amount = round($subtotal * 0.05);
    $final_tot = $subtotal + $gst_amount;

    $post_id = wp_insert_post([
        'post_title'  => "Custom Trip: $customer ($pax Pax)",
        'post_type'   => 'utpc_booking',
        'post_status' => 'publish',
        'meta_input'  => [
            'employee_id'     => get_current_user_id(),
            'customer'        => $customer,
            'phone'           => $phone,
            'email'           => $email,
            'address'         => $address,
            'pax'             => $pax,
            'child'           => $child,
            'tour_id'         => 'custom_trip',
            'hotel'           => $hotel,
            'vehicle'         => $veh,
            'rooms'           => $rms,
            'start_date'      => $start,
            'end_date'        => $end,
            'trip_days'       => $trip_days,
            'pickup_location' => $pickup_loc,
            'service_type'    => $service_type,
            'base_price'      => $base_price,
            'discount_type'   => $discount_type,
            'discount_val'    => $discount_val,
            'discount_amount' => $discount_amount,
            'subtotal'        => $subtotal,
            'gst_amount'      => $gst_amount,
            'total_price'     => $final_tot,
            'total_paid'      => 0 
        ]
    ]);

    if ($post_id) { 
        if (!empty($email)) {
            $headers     = ['Content-Type: text/html; charset=UTF-8'];
            $cust_subject = "Booking Confirmation: Custom Tour Package";
            $cust_msg = "<h3>Booking Confirmed!</h3><p>Dear <strong>{$customer}</strong>,</p><p>Your Custom Tour Package booking has been successfully confirmed.</p>
                         <ul>
                            <li><strong>Total Person:</strong> {$pax} Adults" . ($child > 0 ? ", {$child} Child" : "") . "</li>
                            <li><strong>Dates:</strong> {$start} to {$end}</li>
                            <li><strong>Hotel Category:</strong> {$hotel}</li>
                            <li><strong>Vehicle:</strong> {$veh}</li>
                            <li><strong>Rooms:</strong> {$rms}</li>
                         </ul>
                         <hr>
                         <ul>" . ($discount_amount > 0 ? "<li style='color:green;'><strong>Discount Applied:</strong> - ₹" . number_format($discount_amount) . "</li>" : "") . "
                            <li><strong>Total Amount (Inc GST):</strong> ₹" . number_format($final_tot) . "</li>
                         </ul>
                         <p>Thank you for choosing us!</p>{$company_footer}";
            wp_mail($email, $cust_subject, $cust_msg, $headers);
            
            $admin_email = get_option('admin_email');
            $admin_subject = "New Custom Booking Alert";
            $admin_msg = "<h3>New Custom Booking Received</h3><p>A new custom booking was made by an employee.</p>
                          <ul>
                              <li><strong>Customer:</strong> {$customer}</li>
                              <li><strong>Phone:</strong> {$phone}</li>
                              <li><strong>Email:</strong> {$email}</li>
                              <li><strong>Address:</strong> {$address}</li>
                              <li><strong>Total Person:</strong> {$pax} Adults" . ($child > 0 ? ", {$child} Child" : "") . "</li>
                              <li><strong>Total Price:</strong> ₹" . number_format($final_tot) . "</li>
                          </ul>{$company_footer}";
            wp_mail($admin_email, $admin_subject, $admin_msg, $headers);
        }

        wp_send_json_success('Custom Booking Saved & Emails Sent Successfully!'); 
    } 
    else { wp_send_json_error('Failed to save booking.'); }
}

function utpc_handle_get_tour_details() {
    check_ajax_referer('utpc_nonce', 'nonce');
    if (!in_array(utpc_get_user_role_type(), ['manager', 'employee'])) wp_send_json_error('Unauthorized');

    $tour_id = sanitize_text_field($_POST['tour_id'] ?? '');
    if (empty($tour_id)) wp_send_json_success('');

    $cfg = include(UTPC_PATH . 'config/settings.php');
    $is_custom = ($tour_id === 'custom_trip');

    if (!$is_custom && !isset($cfg['fixed_departures'][$tour_id])) wp_send_json_error('Invalid Tour Selection.');

    $bookings = get_posts([
        'post_type'      => 'utpc_booking',
        'meta_key'       => 'tour_id',
        'meta_value'     => $tour_id,
        'posts_per_page' => -1,
    ]);

    $total_booked = 0;
    $table_rows = '';

    foreach ($bookings as $b) {
        $pax      = (int) get_post_meta($b->ID, 'pax', true);
        $total_booked += $pax;
        
        $customer = get_post_meta($b->ID, 'customer', true);
        $phone    = get_post_meta($b->ID, 'phone', true);
        $email    = get_post_meta($b->ID, 'email', true);
        $address  = get_post_meta($b->ID, 'address', true);
        $child    = (int) get_post_meta($b->ID, 'child', true);
        $service  = get_post_meta($b->ID, 'service_type', true) ?: 'both';
        
        $price      = (float) get_post_meta($b->ID, 'total_price', true);
        $total_paid = (float) get_post_meta($b->ID, 'total_paid', true);
        $balance    = max(0, $price - $total_paid);
        $price_pp   = $pax > 0 ? ($price / $pax) : 0;
        
        $discount_type = get_post_meta($b->ID, 'discount_type', true) ?: 'flat';
        $discount_val  = (float) get_post_meta($b->ID, 'discount_val', true);

        if(empty($customer) || $customer === 'Unknown') $customer = "Booking #" . $b->ID;

        $rooms_raw = get_post_meta($b->ID, 'rooms', true);
        $room_config = $is_custom ? $cfg['rooms'] : array_merge($cfg['rooms'] ?? [], $cfg['fixed_sharing_rooms'] ?? []);
        $room_keys_arr = utpc_extract_keys($rooms_raw, $room_config);
        $room_keys = implode(',', $room_keys_arr);
        
        $veh_raw = ''; $veh_keys = '';

        if ($is_custom) {
            $hotel = get_post_meta($b->ID, 'hotel', true);
            $veh_raw = get_post_meta($b->ID, 'vehicle', true);
            $veh_keys_arr = utpc_extract_keys($veh_raw, $cfg['vehicles']);
            $veh_keys = implode(',', $veh_keys_arr);
            $start = get_post_meta($b->ID, 'start_date', true);
            $end   = get_post_meta($b->ID, 'end_date', true);
            $rooms_display = "<div style='font-size:10px; color:#0369a1;'><b>Dates:</b> {$start} to {$end}</div><div><b>Hotel:</b> {$hotel}</div><div><b>Veh:</b> {$veh_raw}</div><div><b>Rooms:</b> {$rooms_raw}</div>";
        } else {
            $room_counts = array_count_values($room_keys_arr);
            $room_text = [];
            foreach($room_counts as $rk => $rqty) {
                $rname = reset(explode(' ', $room_config[$rk]['name'] ?? $rk));
                $room_text[] = "<div style='display:inline-block; background:#f1f5f9; padding:2px 6px; border-radius:4px; margin:2px 0; border:1px solid #e2e8f0;'>{$rqty}x {$rname}</div>";
            }
            $rooms_display = implode('<br>', $room_text);
            if(empty($rooms_display)) $rooms_display = "<div style='color:#94a3b8;'>N/A</div>";
        }

        $table_rows .= "<tr style='background:#fff;'>";
        $table_rows .= "<td style='padding:10px; border-bottom:1px solid #eee;'><strong>" . esc_html($customer) . "</strong><br><div style='font-size:10px; color:#64748b; font-weight:700;'>📞 " . esc_html($phone) . "</div></td>";
        $table_rows .= "<td style='padding:10px; border-bottom:1px solid #eee; text-align:center;'><strong>{$pax}</strong></td>";
        $table_rows .= "<td style='padding:10px; border-bottom:1px solid #eee; font-size:11px; color:#333; line-height:1.6;'>" . $rooms_display . "</td>";
        
        $table_rows .= "<td style='padding:10px; border-bottom:1px solid #eee; font-size:11px; font-weight:600;'>
            <div style='display:flex; justify-content:space-between; margin-bottom:2px;'><div>Total:</div> <div style='color:#333;'>₹" . number_format($price) . " <div style='font-size:9px; color:#94a3b8; display:inline;'>(" . number_format($price_pp) . "PP)</div></div></div>
            <div style='display:flex; justify-content:space-between; margin-bottom:2px;'><div>Paid:</div> <div style='color:#16a34a;'>₹" . number_format($total_paid) . "</div></div>
            <div style='display:flex; justify-content:space-between; border-top:1px dashed #ccc; padding-top:2px;'><div>Bal:</div> <div style='color:#dc2626;'>₹" . number_format($balance) . "</div></div>
        </td>";
        
        $table_rows .= "<td style='padding:10px; border-bottom:1px solid #eee; text-align:center; min-width:80px;'>
            <button class='btn-view-receipt' style='background:#6366f1; color:#fff; border:none; padding:6px 10px; border-radius:4px; font-size:10px; font-weight:bold; cursor:pointer; margin-bottom:4px; width:100%;' data-id='{$b->ID}' data-tour='{$tour_id}'>RECEIPT</button>
            <button class='btn-pay-booking' style='background:#10b981; color:#fff; border:none; padding:6px 10px; border-radius:4px; font-size:10px; font-weight:bold; cursor:pointer; margin-bottom:4px; width:100%;' data-id='{$b->ID}' data-tot='{$price}' data-paid='{$total_paid}'>PAYMENT</button>
            <button class='btn-edit-booking' style='background:#f59e0b; color:#fff; border:none; padding:6px 10px; border-radius:4px; font-size:10px; font-weight:bold; cursor:pointer; margin-bottom:4px; width:100%;' data-id='{$b->ID}' data-tour='{$tour_id}' data-name='" . esc_attr($customer) . "' data-phone='" . esc_attr($phone) . "' data-email='" . esc_attr($email) . "' data-address='" . esc_attr($address) . "' data-pax='{$pax}' data-child='{$child}' data-rooms='" . esc_attr($rooms_raw) . "' data-roomkeys='" . esc_attr($room_keys) . "' data-vehkeys='" . esc_attr($veh_keys) . "' data-disctype='" . esc_attr($discount_type) . "' data-discval='{$discount_val}' data-service='{$service}'>EDIT</button>
            <button class='btn-resend-email' style='background:#3b82f6; color:#fff; border:none; padding:6px 10px; border-radius:4px; font-size:10px; font-weight:bold; cursor:pointer; margin-bottom:4px; width:100%;' data-id='{$b->ID}' data-tour='{$tour_id}'>EMAIL</button>
            <button class='btn-delete-booking' style='background:#ef4444; color:#fff; border:none; padding:6px 10px; border-radius:4px; font-size:10px; font-weight:bold; cursor:pointer; width:100%;' data-id='{$b->ID}'>DELETE</button>
        </td>";
        $table_rows .= "</tr>";
    }

    ob_start();
    
    if (!$is_custom) {
        $tour = $cfg['fixed_departures'][$tour_id];
        $max_seats = (int) $tour['total_seats'];
        $seats_left = max(0, $max_seats - $total_booked);
        ?>
        <div style="display:flex; gap:10px; margin-bottom: 15px;">
            <div style="flex:1; background:#f0f9ff; padding:15px; border-radius:8px; border:1px solid #bae6fd; text-align:center;">
                <div style="font-size:10px; color:#0369a1; font-weight:800; text-transform:uppercase;">Capacity</div>
                <div style="font-size:24px; font-weight:900; color:#0073aa;"><?php echo $max_seats; ?></div>
            </div>
            <div style="flex:1; background:#fef2f2; padding:15px; border-radius:8px; border:1px solid #fecaca; text-align:center;">
                <div style="font-size:10px; color:#b91c1c; font-weight:800; text-transform:uppercase;">Booked</div>
                <div style="font-size:24px; font-weight:900; color:#dc2626;"><?php echo $total_booked; ?></div>
            </div>
            <div style="flex:1; background:#f0fdf4; padding:15px; border-radius:8px; border:1px solid #bbf7d0; text-align:center;">
                <div style="font-size:10px; color:#15803d; font-weight:800; text-transform:uppercase;">Seats Left</div>
                <div style="font-size:24px; font-weight:900; color:#16a34a;"><?php echo $seats_left; ?></div>
            </div>
        </div>
        <?php
    }

    if (empty($bookings)): ?>
        <div style="padding:20px; background:#f8fafc; text-align:center; border:1px dashed #cbd5e1; border-radius:8px; font-weight:600; color:#64748b;">No bookings found yet.</div>
    <?php else: ?>
        <div class="res-container">
            <table class="tour-table" style="width:100%; font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:10px; background:#0073aa; color:#fff;">Customer Info</th>
                        <th style="padding:10px; background:#0073aa; color:#fff; text-align:center;">Pax</th>
                        <th style="padding:10px; background:#0073aa; color:#fff;">Trip Details</th>
                        <th style="padding:10px; background:#0073aa; color:#fff; min-width:110px;">Financials</th>
                        <th style="padding:10px; background:#0073aa; color:#fff; text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody><?php echo $table_rows; ?></tbody>
            </table>
        </div>
    <?php endif;
    
    wp_send_json_success(ob_get_clean());
}

function utpc_handle_get_receipt() {
    check_ajax_referer('utpc_nonce', 'nonce');
    if (!in_array(utpc_get_user_role_type(), ['manager', 'employee'])) wp_send_json_error('Unauthorized');

    $booking_id = intval($_POST['booking_id']);
    $tour_id = sanitize_text_field($_POST['tour_id']);
    $cfg = include(UTPC_PATH . 'config/settings.php');
    
    $customer    = get_post_meta($booking_id, 'customer', true);
    $phone       = get_post_meta($booking_id, 'phone', true);
    $pax         = (int) get_post_meta($booking_id, 'pax', true);
    $child       = (int) get_post_meta($booking_id, 'child', true);
    $total_paid  = (float) get_post_meta($booking_id, 'total_paid', true);
    $rooms       = get_post_meta($booking_id, 'rooms', true);
    
    $base_price      = (float) get_post_meta($booking_id, 'base_price', true);
    $discount_amount = (float) get_post_meta($booking_id, 'discount_amount', true);
    $subtotal        = (float) get_post_meta($booking_id, 'subtotal', true);
    $gst_amount      = (float) get_post_meta($booking_id, 'gst_amount', true);
    $total_price     = (float) get_post_meta($booking_id, 'total_price', true);
    
    if (!$base_price) {
        $base_price = round($total_price / 1.05);
        $subtotal = $base_price;
        $gst_amount = $total_price - $subtotal;
        $discount_amount = 0;
    }
    
    $balance = max(0, $total_price - $total_paid);
    
    $base_pp  = $pax > 0 ? ($base_price / $pax) : 0;
    $gst_pp   = $pax > 0 ? ($gst_amount / $pax) : 0;
    $price_pp = $pax > 0 ? ($total_price / $pax) : 0;
    
    $is_custom = ($tour_id === 'custom_trip');
    $tour_name = $is_custom ? "Custom Tour Package" : ($cfg['fixed_departures'][$tour_id]['name'] ?? 'Tour Package');
    
    $status_badge = ($balance <= 0) 
        ? "<div style='display:inline-block; background:#16a34a; color:#fff; padding:2px 6px; border-radius:3px; font-size:9px !important; font-weight:800; letter-spacing:0.5px;'>FULLY PAID</div>" 
        : "<div style='display:inline-block; background:#f59e0b; color:#fff; padding:2px 6px; border-radius:3px; font-size:9px !important; font-weight:800; letter-spacing:0.5px;'>BALANCE DUE</div>";

    ob_start();
    ?>
    <div style="background:#fff; border-radius:6px; padding:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); border:1px solid #e2e8f0; color:#1e293b; font-family:sans-serif; line-height:1.2;">
        
        <div style="text-align:center; border-bottom:1px dashed #cbd5e1; padding-bottom:6px; margin-bottom:6px;">
            <h2 style="margin:0; color:#0f172a; font-size:16px !important; font-weight:900; line-height:1.1;">SOULFUL PATHFINDER</h2>
            <div style="font-size:9px !important; color:#64748b; font-weight:700; margin-bottom:4px;">GSTIN: 19AXIPD7432L1Z5</div>
            <div style="font-size:12px !important; color:#0369a1; font-weight:800;"><?php echo esc_html($cfg['popup_title']); ?></div>
            <div style="font-size:10px !important; color:#475569; font-weight:600; margin-bottom:4px;"><?php echo esc_html($tour_name); ?></div>
            <div><?php echo $status_badge; ?></div>
        </div>

        <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:11px !important;">
            <div>
                <div style="color:#64748b; font-size:8px !important; text-transform:uppercase; font-weight:800;">Customer Info</div>
                <div style="font-weight:800; color:#0f172a; font-size:12px !important;"><?php echo esc_html($customer); ?></div>
            </div>
            <div style="text-align:right; font-weight:600; color:#475569; margin-top:8px;">
                📞 <?php echo esc_html($phone); ?>
            </div>
        </div>

        <table style="width:100%; border-collapse:collapse; background:#f8fafc; font-size:10px !important; color:#334155; margin-bottom:6px; border:1px solid #e2e8f0; border-radius:4px;">
            <tr>
                <td style="padding:4px; border-bottom:1px solid #e2e8f0; width:50%;"><b>Pax:</b> <?php echo $pax; ?> <?php echo $child > 0 ? " (+$child Ch)" : ""; ?></td>
                <?php if($is_custom): ?>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0; width:50%;"><b>Dates:</b> <?php echo esc_html(get_post_meta($booking_id, 'start_date', true)); ?></td>
                <?php else: ?>
                    <td style="padding:4px; border-bottom:1px solid #e2e8f0; width:50%;"></td>
                <?php endif; ?>
            </tr>
            <?php if($is_custom): ?>
            <tr>
                <td style="padding:4px; border-bottom:1px solid #e2e8f0;"><b>Hotel:</b> <?php echo esc_html(get_post_meta($booking_id, 'hotel', true)); ?></td>
                <td style="padding:4px; border-bottom:1px solid #e2e8f0;"><b>Veh:</b> <?php echo esc_html(get_post_meta($booking_id, 'vehicle', true)); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="2" style="padding:4px;"><b>Rooms:</b> <?php echo esc_html($rooms); ?></td>
            </tr>
        </table>

        <div style="font-size:11px !important; margin-bottom:6px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; font-weight:600; color:#475569;">
                <div>Base Price:</div>
                <div>₹<?php echo number_format($base_price); ?> <div style="display:inline; font-size:8px !important; color:#94a3b8;">(₹<?php echo number_format($base_pp); ?> PP)</div></div>
            </div>
            
            <?php if ($discount_amount > 0): ?>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px; font-weight:600; color:#059669;">
                    <div>Discount:</div>
                    <div>- ₹<?php echo number_format($discount_amount); ?></div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px; font-weight:600; color:#475569;">
                    <div>Subtotal:</div>
                    <div>₹<?php echo number_format($subtotal); ?></div>
                </div>
            <?php endif; ?>
            
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; font-weight:600; color:#64748b;">
                <div>GST (5%):</div>
                <div>+ ₹<?php echo number_format($gst_amount); ?> <div style="display:inline; font-size:8px !important; color:#cbd5e1;">(₹<?php echo number_format($gst_pp); ?> PP)</div></div>
            </div>
            
            <div style="display:flex; justify-content:space-between; margin-bottom:4px; padding-top:4px; border-top:1px solid #cbd5e1; font-weight:800; font-size:13px !important; color:#0f172a;">
                <div>Grand Total:</div>
                <div>₹<?php echo number_format($total_price); ?></div>
            </div>
            
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; font-weight:600; color:#16a34a;">
                <div>Amount Paid:</div>
                <div>₹<?php echo number_format($total_paid); ?></div>
            </div>
            <div style="display:flex; justify-content:space-between; padding-top:4px; border-top:1px dashed #cbd5e1; font-weight:800; font-size:13px !important; color:#dc2626;">
                <div>Balance:</div>
                <div>₹<?php echo number_format($balance); ?></div>
            </div>
        </div>

        <?php 
        $history = get_post_meta($booking_id, 'payment_history', true);
        if (is_array($history) && !empty($history)): 
        ?>
            <div style="background:#fdfde8; padding:6px; border:1px solid #fef08a; border-radius:4px;">
                <div style="margin:0 0 4px 0; font-size:8px !important; color:#b45309; text-transform:uppercase; font-weight:800;">Payment History</div>
                <?php foreach($history as $h): ?>
                    <div style="display:flex; justify-content:space-between; font-size:9px !important; margin-bottom:2px; padding-bottom:2px; border-bottom:1px solid #fef08a;">
                        <div style="color:#713f12;"><?php echo date('d M Y', strtotime($h['date'])); ?> <i>(<?php echo esc_html($h['note'] ?: 'Pay'); ?>)</i></div>
                        <div style="color:#16a34a; font-weight:bold;">+ ₹<?php echo number_format($h['amount']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
    <?php
    wp_send_json_success(ob_get_clean());
}

function utpc_handle_add_payment() {
    check_ajax_referer('utpc_nonce', 'nonce');
    if (!in_array(utpc_get_user_role_type(), ['manager', 'employee'])) wp_send_json_error('Unauthorized');

    global $company_footer;

    $booking_id = intval($_POST['booking_id']);
    $pay_amount = floatval($_POST['pay_amount']);
    $pay_note   = sanitize_text_field($_POST['pay_note']);

    $total_price = (float) get_post_meta($booking_id, 'total_price', true);
    $total_paid  = (float) get_post_meta($booking_id, 'total_paid', true);

    // --- STRICT BALANCE VALIDATION (BACKEND) ---
    $current_balance = max(0, $total_price - $total_paid);
    if ($current_balance <= 0) {
        wp_send_json_error("This booking is already fully paid.");
    }
    if ($pay_amount > $current_balance) {
        wp_send_json_error("Error: Payment amount (₹" . number_format($pay_amount) . ") exceeds the remaining balance (₹" . number_format($current_balance) . ").");
    }

    $new_paid = $total_paid + $pay_amount;
    $new_balance = max(0, $total_price - $new_paid);

    update_post_meta($booking_id, 'total_paid', $new_paid);

    $history = get_post_meta($booking_id, 'payment_history', true);
    if (!is_array($history)) $history = [];
    $history[] = ['date' => current_time('mysql'), 'amount' => $pay_amount, 'note' => $pay_note];
    update_post_meta($booking_id, 'payment_history', $history);

    $cfg = include(UTPC_PATH . 'config/settings.php');
    $customer = get_post_meta($booking_id, 'customer', true);
    $email    = get_post_meta($booking_id, 'email', true);
    $tour_id  = get_post_meta($booking_id, 'tour_id', true);
    $tour_name = ($tour_id === 'custom_trip') ? "Custom Tour Package" : ($cfg['fixed_departures'][$tour_id]['name'] ?? 'Tour Package');

    if (!empty($email)) {
        
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $base_price      = (float) get_post_meta($booking_id, 'base_price', true);
        $discount_amount = (float) get_post_meta($booking_id, 'discount_amount', true);
        $subtotal        = (float) get_post_meta($booking_id, 'subtotal', true);
        $gst_amount      = (float) get_post_meta($booking_id, 'gst_amount', true);
        if (!$base_price) {
            $base_price = round($total_price / 1.05); $subtotal = $base_price; $gst_amount = $total_price - $subtotal; $discount_amount = 0;
        }

        $email_financials = "
        <div style='background:#f9f9f9; padding:15px; border-radius:6px; border:1px solid #ddd; margin-top:15px;'>
            <ul style='list-style:none; padding:0; margin:0; line-height:1.6;'>
                <li><strong>Base Package Price:</strong> ₹" . number_format($base_price) . "</li>" . 
                ($discount_amount > 0 ? "<li style='color:green;'><strong>Discount:</strong> - ₹" . number_format($discount_amount) . "</li><li><strong>Subtotal:</strong> ₹" . number_format($subtotal) . "</li>" : "") . "
                <li><strong>GST (5%):</strong> + ₹" . number_format($gst_amount) . "</li>
                <li style='font-size:16px; margin-top:5px; border-top:1px solid #ccc; padding-top:5px;'><strong>Grand Total:</strong> ₹" . number_format($total_price) . "</li>
                <li style='color:green; margin-top:5px;'><strong>Amount Paid:</strong> ₹" . number_format($new_paid) . "</li>
                <li style='color:red;'><strong>Remaining Balance:</strong> ₹" . number_format($new_balance) . "</li>
            </ul>
        </div>";

        $num_amount = number_format($pay_amount);

        if ($new_balance <= 0) {
            $subject = "Full Payment Received! ($tour_name)";
            $msg = "<h3>Full Payment Confirmed!</h3><p>Dear <strong>{$customer}</strong>,</p><p>We have successfully received your payment of <strong>₹{$num_amount}</strong>.</p><p style='color:green;'><strong>Your trip is now fully paid!</strong> Thank you for completing your payments.</p>{$email_financials}{$company_footer}";
        } else {
            $subject = "Payment Received - Receipt ($tour_name)";
            $msg = "<h3>Payment Successfully Recorded</h3><p>Dear <strong>{$customer}</strong>,</p><p>We have successfully received your payment of <strong>₹{$num_amount}</strong>.</p>{$email_financials}<p>Thank you for your payment!</p>{$company_footer}";
        }
        wp_mail($email, $subject, $msg, $headers);
    }
    wp_send_json_success("Payment of ₹" . number_format($pay_amount) . " recorded successfully!");
}

function utpc_handle_resend_email() {
    check_ajax_referer('utpc_nonce', 'nonce');
    if (!in_array(utpc_get_user_role_type(), ['manager', 'employee'])) wp_send_json_error('Unauthorized');

    global $company_footer;

    $booking_id = intval($_POST['booking_id']);
    $tour_id = sanitize_text_field($_POST['tour_id']);
    $cfg = include(UTPC_PATH . 'config/settings.php');
    
    $customer    = get_post_meta($booking_id, 'customer', true);
    $email       = get_post_meta($booking_id, 'email', true);
    $pax         = (int) get_post_meta($booking_id, 'pax', true);
    $child       = get_post_meta($booking_id, 'child', true);
    $total_price = (float) get_post_meta($booking_id, 'total_price', true);
    $total_paid  = (float) get_post_meta($booking_id, 'total_paid', true);
    $balance     = max(0, $total_price - $total_paid);
    
    if (empty($email)) wp_send_json_error('No email address saved for this customer.');

    if ($tour_id === 'custom_trip') {
        $tour_name = "Custom Tour Package";
        $hotel = get_post_meta($booking_id, 'hotel', true); $veh = get_post_meta($booking_id, 'vehicle', true); $start = get_post_meta($booking_id, 'start_date', true); $end = get_post_meta($booking_id, 'end_date', true);
        $extra_details = "<li><strong>Dates:</strong> $start to $end</li><li><strong>Hotel:</strong> $hotel</li><li><strong>Vehicle:</strong> $veh</li>";
    } else {
        $tour_name = $cfg['fixed_departures'][$tour_id]['name'] ?? 'Fixed Departure'; $extra_details = "";
    }
    
    $base_price      = (float) get_post_meta($booking_id, 'base_price', true);
    $discount_amount = (float) get_post_meta($booking_id, 'discount_amount', true);
    $subtotal        = (float) get_post_meta($booking_id, 'subtotal', true);
    $gst_amount      = (float) get_post_meta($booking_id, 'gst_amount', true);
    if (!$base_price) {
        $base_price = round($total_price / 1.05); $subtotal = $base_price; $gst_amount = $total_price - $subtotal; $discount_amount = 0;
    }

    $email_financials = "
    <div style='background:#f9f9f9; padding:15px; border-radius:6px; border:1px solid #ddd; margin-top:15px;'>
        <ul style='list-style:none; padding:0; margin:0; line-height:1.6;'>
            <li><strong>Base Package Price:</strong> ₹" . number_format($base_price) . "</li>" . 
            ($discount_amount > 0 ? "<li style='color:green;'><strong>Discount:</strong> - ₹" . number_format($discount_amount) . "</li><li><strong>Subtotal:</strong> ₹" . number_format($subtotal) . "</li>" : "") . "
            <li><strong>GST (5%):</strong> + ₹" . number_format($gst_amount) . "</li>
            <li style='font-size:16px; margin-top:5px; border-top:1px solid #ccc; padding-top:5px;'><strong>Grand Total:</strong> ₹" . number_format($total_price) . "</li>
            <li style='color:green; margin-top:5px;'><strong>Amount Paid:</strong> ₹" . number_format($total_paid) . "</li>
            <li style='color:red;'><strong>Remaining Balance:</strong> ₹" . number_format($balance) . "</li>
        </ul>
    </div>";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    $cust_subject = "Booking Details: " . $tour_name;
    $cust_msg = "<h3>Your Booking Details</h3><p>Dear <strong>{$customer}</strong>,</p><p>Here are the details for your booking (<strong>{$tour_name}</strong>):</p>
                 <ul><li><strong>Total Person:</strong> {$pax} Adults" . ($child>0 ? ", {$child} Child" : "") . "</li>{$extra_details}</ul>
                 {$email_financials}
                 <p>Thank you for choosing us!</p>
                 {$company_footer}";
                 
    if(wp_mail($email, $cust_subject, $cust_msg, $headers)) wp_send_json_success('Details Email sent to customer successfully!');
    else wp_send_json_error('Failed to send email. Check server configuration.');
}

function utpc_handle_update_booking() {
    check_ajax_referer('utpc_nonce', 'nonce');
    if (!in_array(utpc_get_user_role_type(), ['manager', 'employee'])) wp_send_json_error('Unauthorized');

    $cfg = include(UTPC_PATH . 'config/settings.php');
    $booking_id = intval($_POST['booking_id']);
    $tour_id = sanitize_text_field($_POST['tour_id']);
    $new_pax = max(1, intval($_POST['bk_pax']));
    
    $service_type = get_post_meta($booking_id, 'service_type', true) ?: 'both';
    
    $new_rooms = $_POST['custom_rooms'] ?? [];
    if($service_type !== 'cab' && empty($new_rooms)) wp_send_json_error('Please select at least one room.');

    $room_config = ($tour_id === 'custom_trip') ? $cfg['rooms'] : ($cfg['fixed_sharing_rooms'] ?? []);
    if($service_type !== 'cab') {
        $total_room_capacity = 0;
        foreach ($new_rooms as $rk) { if (isset($room_config[$rk])) $total_room_capacity += $room_config[$rk]['capacity']; }
        if ($total_room_capacity < $new_pax) wp_send_json_error("Capacity Error: The selected rooms can only accommodate {$total_room_capacity} persons, but you requested {$new_pax} Pax.");
    }

    $old_pax = (int) get_post_meta($booking_id, 'pax', true);
    $old_base_price = (float) get_post_meta($booking_id, 'base_price', true);
    if (!$old_base_price) { $old_total = (float) get_post_meta($booking_id, 'total_price', true); $old_base_price = round($old_total / 1.05); }

    $new_base_price = 0;

    if ($tour_id === 'custom_trip') {
        $trip_days  = (int) get_post_meta($booking_id, 'trip_days', true) ?: 7;
        $pickup_loc = get_post_meta($booking_id, 'pickup_location', true) ?: 'srinagar';
        
        $mapped_vehicles = [];
        foreach ($cfg['vehicles'] as $k => $v) {
            $mapped_vehicles[$k] = $v;
            $daily = $v['price_per_day'][$pickup_loc] ?? ($v['price'] / 7);
            $mapped_vehicles[$k]['price'] = $daily * $trip_days;
        }

        $new_vehs = $_POST['custom_vehicles'] ?? [];
        if($service_type !== 'hotel' && empty($new_vehs)) wp_send_json_error('Please select at least one vehicle.');
        
        if($service_type !== 'hotel') {
            $total_veh_capacity = 0;
            foreach ($new_vehs as $vk) { if (isset($mapped_vehicles[$vk])) $total_veh_capacity += $mapped_vehicles[$vk]['capacity']; }
            if ($total_veh_capacity < $new_pax) wp_send_json_error("Capacity Error: The selected vehicles can only accommodate {$total_veh_capacity} persons, but you requested {$new_pax} Pax.");
        }

        if ($service_type === 'hotel') { $new_vehs = []; }
        if ($service_type === 'cab') { $new_rooms = []; }

        $old_rooms_raw = get_post_meta($booking_id, 'rooms', true); 
        $old_veh_raw = get_post_meta($booking_id, 'vehicle', true);
        
        $old_room_cost = ($service_type === 'cab') ? 0 : utpc_get_cost_from_raw($old_rooms_raw, $cfg['rooms']); 
        $old_veh_cost = ($service_type === 'hotel') ? 0 : utpc_get_cost_from_raw($old_veh_raw, $mapped_vehicles);
        
        $base_pax_cost = ($service_type === 'cab') ? 0 : $cfg['base_cost_per_pax'];
        $old_base_cost = $old_veh_cost + $old_room_cost + ($base_pax_cost * $old_pax);

        $implied_multiplier = 1.0;
        if ($old_base_cost > 0) { 
            $profit = ($service_type === 'cab' || $service_type === 'hotel') ? ($cfg['profit_margin_per_pax'] * 0.5) : $cfg['profit_margin_per_pax'];
            $agent_total = $old_base_price - ($profit * $old_pax); 
            $implied_multiplier = max(0, $agent_total / $old_base_cost); 
        }

        $new_room_cost = ($service_type === 'cab') ? 0 : utpc_get_cost_from_keys($new_rooms, $cfg['rooms']); 
        $new_veh_cost = ($service_type === 'hotel') ? 0 : utpc_get_cost_from_keys($new_vehs, $mapped_vehicles);
        $new_base_cost = $new_veh_cost + $new_room_cost + ($base_pax_cost * $new_pax);

        $profit = ($service_type === 'cab' || $service_type === 'hotel') ? ($cfg['profit_margin_per_pax'] * 0.5) : $cfg['profit_margin_per_pax'];
        $new_agent_price = $new_base_cost * $implied_multiplier;
        $pp_base = ceil(($new_agent_price / $new_pax + $profit) / 500) * 500;
        $new_base_price = $pp_base * $new_pax;

        $new_rooms_display = ($service_type === 'cab') ? 'N/A' : utpc_get_display_from_keys($new_rooms, $cfg['rooms']); 
        $new_vehs_display = ($service_type === 'hotel') ? 'N/A' : utpc_get_display_from_keys($new_vehs, $mapped_vehicles);
        
        update_post_meta($booking_id, 'rooms', $new_rooms_display); 
        update_post_meta($booking_id, 'vehicle', $new_vehs_display);

    } else {
        
        // FIXED DEPARTURES UPDATE LOGIC
        $pax_diff = $new_pax - $old_pax;
        if ($pax_diff > 0) {
            $booked_already = utpc_get_booked_seats($tour_id);
            $total_allowed = $cfg['fixed_departures'][$tour_id]['total_seats'];
            if (($booked_already + $pax_diff) > $total_allowed) {
                $left = $total_allowed - $booked_already;
                wp_send_json_error("Not enough seats left! You are trying to add {$pax_diff} extra people, but only {$left} seats are available.");
            }
        }

        $tour = $cfg['fixed_departures'][$tour_id];
        
        $total_cost = 0;
        foreach (array_count_values((array)$new_rooms) as $rk => $qty) {
            if(isset($cfg['fixed_sharing_rooms'][$rk])) {
                $cap = $cfg['fixed_sharing_rooms'][$rk]['capacity'];
                $pp_price = $tour['sharing_prices'][$rk] ?? 0;
                $total_cost += ($pp_price * $cap * $qty);
            }
        }

        $new_base_price = $total_cost;

        $new_rooms_display = utpc_get_display_from_keys($new_rooms, $cfg['fixed_sharing_rooms']);
        update_post_meta($booking_id, 'rooms', $new_rooms_display);
    }

    $discount_type = sanitize_text_field($_POST['bk_discount_type'] ?? 'flat');
    $discount_val  = floatval($_POST['bk_discount_val'] ?? 0);
    $discount_amount = 0;
    if ($discount_val > 0) {
        if ($discount_type === 'percent') { $discount_amount = round(($new_base_price * $discount_val) / 100); } 
        else { $discount_amount = $discount_val; }
    }
    
    $subtotal = max(0, $new_base_price - $discount_amount);
    $gst_amount = round($subtotal * 0.05);
    $new_total_price = $subtotal + $gst_amount;

    $customer_name = sanitize_text_field($_POST['bk_name']);
    update_post_meta($booking_id, 'customer', $customer_name); update_post_meta($booking_id, 'phone', sanitize_text_field($_POST['bk_phone'])); update_post_meta($booking_id, 'email', sanitize_email($_POST['bk_email'])); update_post_meta($booking_id, 'address', sanitize_text_field($_POST['bk_address'])); update_post_meta($booking_id, 'pax', $new_pax); update_post_meta($booking_id, 'child', intval($_POST['bk_child']));
    update_post_meta($booking_id, 'base_price', $new_base_price); update_post_meta($booking_id, 'discount_type', $discount_type); update_post_meta($booking_id, 'discount_val', $discount_val); update_post_meta($booking_id, 'discount_amount', $discount_amount); update_post_meta($booking_id, 'subtotal', $subtotal); update_post_meta($booking_id, 'gst_amount', $gst_amount); update_post_meta($booking_id, 'total_price', $new_total_price);
    
    wp_update_post(['ID' => $booking_id, 'post_title' => "Booking: {$customer_name} ({$new_pax} Pax) - {$tour_id}"]);
    wp_send_json_success("Booking updated! The new total price is ₹" . number_format($new_total_price));
}

function utpc_handle_delete_booking() {
    check_ajax_referer('utpc_nonce', 'nonce');
    if (!in_array(utpc_get_user_role_type(), ['manager', 'employee'])) wp_send_json_error('Unauthorized');
    $booking_id = intval($_POST['booking_id']);
    if (wp_delete_post($booking_id, true)) wp_send_json_success('Booking permanently deleted.');
    else wp_send_json_error('Failed to delete booking.');
}

// ==========================================
// INITIAL BOOKING & FRONTEND CALCULATOR HTML
// ==========================================
function utpc_handle_ajax_calculation() {
    check_ajax_referer('utpc_nonce', 'nonce');
    parse_str($_POST['form_data'], $data);
    $cfg = include(UTPC_PATH . 'config/settings.php');
    $mode = $data['calc_mode'] ?? 'custom';

    global $company_footer;
    
    $role_type = utpc_get_user_role_type();
    $is_manager = ($role_type === 'manager');
    $can_book   = in_array($role_type, ['manager', 'employee']);

    if (isset($_POST['is_booking']) && $_POST['is_booking'] == 'true' && $can_book) {
        $pax         = intval($data['tour_pax']);
        $tour_id     = sanitize_text_field($data['fixed_tour']);
        $tot_with_gst = floatval($_POST['final_price']); 
        
        $base_price = round($tot_with_gst / 1.05);
        
        $customer   = !empty($_POST['bk_customer_name']) ? sanitize_text_field($_POST['bk_customer_name']) : 'Unknown';
        $bk_phone   = sanitize_text_field($_POST['bk_phone'] ?? '');
        $bk_email   = sanitize_email($_POST['bk_email'] ?? '');
        $bk_address = sanitize_text_field($_POST['bk_address'] ?? '');
        $bk_child   = intval($_POST['bk_child'] ?? 0);

        if ($customer === 'Unknown' || empty($bk_phone) || empty($bk_email) || empty($bk_address)) { wp_send_json_error('<div style="color:red; font-weight:bold;">Error: Please provide all customer details before confirming.</div>'); }

        $booked_already = utpc_get_booked_seats($tour_id);
        $total_allowed = $cfg['fixed_departures'][$tour_id]['total_seats'];
        if (($booked_already + $pax) > $total_allowed) { wp_send_json_error('<div style="color:red; font-weight:bold;">Error: Not enough seats left!</div>'); }

        $discount_type = sanitize_text_field($_POST['bk_discount_type'] ?? 'flat');
        $discount_val  = floatval($_POST['bk_discount_val'] ?? 0);
        $discount_amount = 0;
        if ($discount_val > 0) {
            if ($discount_type === 'percent') { $discount_amount = round(($base_price * $discount_val) / 100); } 
            else { $discount_amount = $discount_val; }
        }
        
        $subtotal = max(0, $base_price - $discount_amount);
        $gst_amount = round($subtotal * 0.05);
        $final_tot = $subtotal + $gst_amount;

        $post_id = wp_insert_post([
            'post_title'  => "Booking: $customer ($pax Pax) - $tour_id",
            'post_type'   => 'utpc_booking',
            'post_status' => 'publish',
            'meta_input'  => [
                'employee_id'     => get_current_user_id(),
                'customer'        => $customer,
                'phone'           => $bk_phone,
                'email'           => $bk_email,
                'address'         => $bk_address,
                'pax'             => $pax,
                'child'           => $bk_child,
                'tour_id'         => $tour_id,
                'rooms'           => implode(', ', $data['custom_rooms'] ?? []),
                'base_price'      => $base_price,
                'discount_type'   => $discount_type,
                'discount_val'    => $discount_val,
                'discount_amount' => $discount_amount,
                'subtotal'        => $subtotal,
                'gst_amount'      => $gst_amount,
                'total_price'     => $final_tot,
                'total_paid'      => 0 
            ]
        ]);
        
        if ($post_id) { 
            $headers     = array('Content-Type: text/html; charset=UTF-8');
            $tour_name   = $cfg['fixed_departures'][$tour_id]['name'] ?? 'Fixed Departure';
            
            $cust_subject = "Booking Confirmation: " . $tour_name;
            $cust_msg = "<h3>Booking Confirmed!</h3><p>Dear <strong>{$customer}</strong>,</p><p>Your booking for <strong>{$tour_name}</strong> has been successfully confirmed.</p><ul><li><strong>Total Person:</strong> {$pax} Adults" . ($bk_child > 0 ? ", {$bk_child} Child" : "") . "</li></ul><hr><ul>" . ($discount_amount > 0 ? "<li style='color:green;'><strong>Discount Applied:</strong> - ₹" . number_format($discount_amount) . "</li>" : "") . "<li><strong>Total Amount (Inc GST):</strong> ₹" . number_format($final_tot) . "</li></ul><p>Thank you for choosing us!</p>{$company_footer}";
            wp_mail($bk_email, $cust_subject, $cust_msg, $headers);
            
            $admin_email = get_option('admin_email');
            $admin_subject = "New Booking Alert: " . $tour_name;
            $admin_msg = "<h3>New Booking Received</h3><p>A new fixed departure booking was made by an employee.</p><ul><li><strong>Customer:</strong> {$customer}</li><li><strong>Phone:</strong> {$bk_phone}</li><li><strong>Email:</strong> {$bk_email}</li><li><strong>Address:</strong> {$bk_address}</li><li><strong>Total Person:</strong> {$pax} Adults" . ($bk_child > 0 ? ", {$bk_child} Child" : "") . "</li><li><strong>Total Price (Inc GST):</strong> ₹" . number_format($final_tot) . "</li></ul>{$company_footer}";
            wp_mail($admin_email, $admin_subject, $admin_msg, $headers);

            wp_send_json_success('<div style="background:#dcfce7; color:#166534; padding:15px; border-radius:6px; font-weight:bold; text-align:center;">Booking Confirmed! (ID: #'.$post_id.')</div>'); 
        } 
        else { wp_send_json_error('Failed to save booking.'); }
    }

    $results = UTPC_Calculator::process_calculation($data);
    if (empty($results)) wp_send_json_success('<div style="padding:15px;text-align:center;color:red;">No packages matched your configuration.</div>');

    // DYNAMIC GST ADDITION FOR FRONTEND
    foreach ($results as &$r) {
        $base_pp = $r['pp'];
        $r['pp'] = round($base_pp * 1.05); 
        $r['gst_pp'] = $r['pp'] - $base_pp;
        $r['base_pp'] = $base_pp;
        $r['tot'] = $r['pp'] * $r['tp'];
    }
    unset($r);

    ob_start();

    if ($mode === 'fixed') {
        $row = $results[0]; 
        $base_tot = $row['base_pp'] * $row['tp'];
        $gst_tot = $row['gst_pp'] * $row['tp'];
        
        echo '<div class="res-container" style="padding:10px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; margin-top:15px; font-family:sans-serif; line-height:1.2;">';
        echo "<div style='text-align:center; border-bottom:1px dashed #cbd5e1; padding-bottom:6px; margin-bottom:6px;'><h3 style='margin:0; color:#0369a1; font-size:14px; font-weight:800;'>" . esc_html($row['tour_name']) . "</h3></div>";
        echo "<div style='text-align:center; font-size:11px; color:#64748b; margin-bottom:10px;'><b>Dates:</b> {$row['start_date']} ➔ {$row['end_date']} | <b>Season:</b> <span style='color:#ca8a04; font-weight:bold;'>{$row['season_name']}</span></div>";
        
        echo "<table style='width:100%; border-collapse:collapse; background:#f8fafc; font-size:10px !important; color:#334155; margin-bottom:6px; border:1px solid #e2e8f0; border-radius:4px;'>";
        echo "<tr><td style='padding:4px; border-bottom:1px solid #e2e8f0;'><b>Veh:</b> {$row['v_h']}</td></tr>";
        echo "<tr><td style='padding:4px;'><b>Rooms:</b> {$row['r_h']}</td></tr>";
        echo "</table>";
        
        if ($can_book) {
            echo "<div style='background:#f1f5f9; padding:8px; border-radius:6px; border:1px solid #cbd5e1; margin-bottom:10px;'>";
            echo "  <div style='font-size:9px; font-weight:800; color:#475569; margin-bottom:6px; text-transform:uppercase;'>Customer Details Required</div>";
            echo "  <div style='display:flex; flex-wrap:wrap; gap:6px; margin-bottom:6px;'>";
            echo "      <div style='flex:1; min-width:100px;'><input type='text' id='bk_customer_name' class='u-field' placeholder='Customer Name *' required style='height:28px; font-size:11px;'></div>";
            echo "      <div style='flex:1; min-width:100px;'><input type='text' id='bk_phone' class='u-field' placeholder='Phone Number *' required style='height:28px; font-size:11px;'></div>";
            echo "      <div style='flex:1; min-width:100px;'><input type='email' id='bk_email' class='u-field' placeholder='Email ID *' required style='height:28px; font-size:11px;'></div>";
            echo "  </div>";
            echo "  <div style='display:flex; flex-wrap:wrap; gap:6px; margin-bottom:6px;'>";
            echo "      <div style='flex:3; min-width:150px;'><input type='text' id='bk_address' class='u-field' placeholder='Full Address *' required style='height:28px; font-size:11px;'></div>";
            echo "      <div style='flex:1; min-width:70px;'><input type='number' id='bk_child' class='u-field' placeholder='Child' value='0' min='0' style='height:28px; font-size:11px;'></div>";
            echo "  </div>";
            echo "  <div style='display:flex; flex-wrap:wrap; gap:6px; border-top:1px dashed #cbd5e1; padding-top:6px;'>";
            echo "      <div style='flex:1; min-width:100px;'><select id='bk_discount_type' class='u-field' style='height:28px; font-size:11px;'><option value='flat'>Discount: Flat (₹)</option><option value='percent'>Discount: Percent (%)</option></select></div>";
            echo "      <div style='flex:1; min-width:100px;'><input type='number' id='bk_discount_val' class='u-field' placeholder='Discount Value' value='0' min='0' step='any' style='height:28px; font-size:11px;'></div>";
            echo "  </div>";
            echo "</div>";
        }
        
        echo "<div style='font-size:11px !important; margin-bottom:6px;'>";
        echo "<div style='display:flex; justify-content:space-between; margin-bottom:2px; color:#475569;'><div>Base:</div> <div>₹".number_format($base_tot)." <div style='display:inline; font-size:8px !important;'>(".number_format($row['base_pp'])." PP)</div></div></div>";
        echo "<div style='display:flex; justify-content:space-between; margin-bottom:4px; color:#475569; border-bottom:1px dashed #cbd5e1; padding-bottom:4px;'><div>GST:</div> <div>₹".number_format($gst_tot)." <div style='display:inline; font-size:8px !important;'>(".number_format($row['gst_pp'])." PP)</div></div></div>";
        echo "<div style='display:flex; justify-content:space-between; align-items:center; margin-top:4px;'>";
        echo "  <div style='font-size:14px !important; font-weight:800; color:#0f172a;'>Total: <strong style='color:#16a34a;'>₹".number_format($row['tot'])."</strong> <div style='display:inline; font-size:10px !important; color:#64748b;'>(₹".number_format($row['pp'])." PP)</div></div>";
        if ($can_book) {
            echo "  <button type='button' id='btn-confirm-book' data-price='{$row['tot']}' class='btn-main' style='border:none; cursor:pointer; padding:6px 12px !important; font-size:10px !important; width:auto !important; margin:0 !important;'>CONFIRM BOOKING</button>";
        }
        echo "</div></div></div>";
    } 
    else {
        $h_cat = sanitize_text_field($data['hotel_category'] ?? 'budget');
        $hotel_name = $cfg['hotel_categories'][$h_cat]['name'] ?? 'Budget';

        echo '<div class="res-container"><table class="tour-table"><thead><tr>';
        echo '<th>Rooms Breakdown</th><th>Vehicle Type</th><th style="min-width:90px;">Price PP</th><th>Total (Inc GST)</th>';
        if ($is_manager) echo '<th>Agent</th><th>Profit</th>';
        if ($can_book) echo '<th style="text-align:center;">Action</th>';
        echo '</tr></thead><tbody>';

        foreach ($results as $row) {
            $r_h = $row['r_h']; $v_h = $row['v_h'];
            $pp_f = number_format($row['pp']); $tot_f = number_format($row['tot']);
            
            $base_pp_f = number_format($row['base_pp']);
            $gst_pp_f  = number_format($row['gst_pp']);
            
            $safe_hotel   = esc_attr($hotel_name);
            $safe_pax     = esc_attr($row['tp']);
            $safe_veh     = esc_attr($row['v_r']);
            $safe_rms     = esc_attr($row['r_r']);
            $safe_pp      = esc_attr($row['pp']);
            $safe_start   = esc_attr($row['start_date']);
            $safe_end     = esc_attr($row['end_date']);
            $safe_season  = esc_attr($row['season_name']);
            $safe_surch   = esc_attr($row['surcharge_percent']);
            $safe_days    = esc_attr($row['trip_days'] ?? 7);
            $safe_pickup  = esc_attr($row['pickup_loc'] ?? 'srinagar');
            $safe_service = esc_attr($row['service_type'] ?? 'both');

            echo "<tr class='utpc-row' data-hotel='{$safe_hotel}' data-pax='{$safe_pax}' data-veh='{$safe_veh}' data-rms='{$safe_rms}' data-pp='{$safe_pp}' data-tot='{$row['tot']}' data-start='{$safe_start}' data-end='{$safe_end}' data-season-name='{$safe_season}' data-surcharge-percent='{$safe_surch}' data-basepp='{$row['base_pp']}' data-gstpp='{$row['gst_pp']}' data-days='{$safe_days}' data-pickup='{$safe_pickup}' data-service='{$safe_service}'>";
            echo "<td class='col-adaptive'>{$r_h}</td><td class='col-adaptive'>{$v_h}</td>";
            
            echo "<td class='price-col'>
                    <div style='font-weight:bold; color:#16a34a; font-size:14px;'>₹{$pp_f}</div>
                    <div style='font-size:10px; color:#64748b; line-height:1.3; margin-top:3px;'>Base: ₹{$base_pp_f}<br>GST: ₹{$gst_pp_f}</div>
                  </td>";
                  
            echo "<td class='price-col'>₹{$tot_f}</td>";
            
            if ($is_manager) {
                echo "<td class='col-nowrap' style='background:#fffbea;'>₹".number_format($row['at'])."</td><td class='col-nowrap' style='background:#f0fdf4; color:green; font-weight:700;'>₹".number_format($row['pt'],0)."</td>";
            }
            if ($can_book) {
                echo "<td style='text-align:center;'><button class='btn-book-custom' style='background:#0284c7; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:10px; font-weight:bold; cursor:pointer;' data-hotel='{$safe_hotel}' data-pax='{$safe_pax}' data-veh='{$safe_veh}' data-rms='{$safe_rms}' data-pp='{$safe_pp}' data-tot='{$row['tot']}' data-start='{$safe_start}' data-end='{$safe_end}' data-basepp='{$row['base_pp']}' data-gstpp='{$row['gst_pp']}'>BOOK</button></td>";
            }
            echo "</tr>";
        }
        echo '</tbody></table></div>';
    }
    wp_send_json_success(ob_get_clean());
}