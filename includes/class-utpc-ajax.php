<?php
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_utpc_calculate', 'utpc_handle_ajax_calculation');
add_action('wp_ajax_nopriv_utpc_calculate', 'utpc_handle_ajax_calculation');

function utpc_handle_ajax_calculation() {
    check_ajax_referer('utpc_nonce', 'nonce');

    parse_str($_POST['form_data'], $data);
    $results = UTPC_Calculator::process_calculation($data);
    $is_admin = is_user_logged_in();

    // Get the dynamic hotel category name from settings
    $cfg = include(UTPC_PATH . 'config/settings.php');
    $h_cat = sanitize_text_field($data['hotel_category'] ?? 'budget');
    $hotel_name = esc_attr($cfg['hotel_categories'][$h_cat]['name'] ?? 'Budget');

    if (empty($results)) {
        wp_send_json_success('<div style="padding:15px;text-align:center;color:red;">No packages matched your configuration.</div>');
    }

    ob_start();
    echo '<div class="res-container"><table class="tour-table"><thead><tr>';
    echo '<th>Rooms Breakdown</th><th>Vehicle Type</th><th>Price PP</th><th>Total</th>';
    if ($is_admin) echo '<th>Agent</th><th>Profit</th>';
    echo '</tr></thead><tbody>';

    foreach ($results as $row) {
        $r_h = $row['r_h']; $v_h = $row['v_h'];
        $pp_f = number_format($row['pp']); $tot_f = number_format($row['tot']);
        
        // Passing the $hotel_name into the data-hotel attribute here
        echo "<tr class='utpc-row' data-hotel='{$hotel_name}' data-pax='{$row['tp']}' data-veh='{$row['v_r']}' data-rms='{$row['r_r']}' data-pp='{$row['pp']}'>";
        echo "<td class='col-adaptive'>{$r_h}</td><td class='col-adaptive'>{$v_h}</td><td class='price-col'>₹{$pp_f}</td><td class='price-col'>₹{$tot_f}</td>";
        if ($is_admin) {
            echo "<td class='col-nowrap' style='background:#fffbea;'>₹".number_format($row['at'])."</td><td class='col-nowrap' style='background:#f0fdf4; color:green; font-weight:700;'>₹".number_format($row['pt'],0)."</td>";
        }
        echo "</tr>";
    }
    echo '</tbody></table></div>';

    wp_send_json_success(ob_get_clean());
}