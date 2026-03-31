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
    $hotel_name = $cfg['hotel_categories'][$h_cat]['name'] ?? 'Budget';

    if (empty($results)) {
        wp_send_json_success('<div style="padding:15px;text-align:center;color:red;">No packages matched your configuration.</div>');
    }

    ob_start();
    echo '<div class="res-container"><table class="tour-table"><thead><tr>';
    echo '<th>Rooms Breakdown</th><th>Vehicle Type</th><th>Price PP</th><th>Total</th>';
    if ($is_admin) echo '<th>Agent</th><th>Profit</th>';
    echo '</tr></thead><tbody>';

    foreach ($results as $row) {
        $r_h = $row['r_h']; 
        $v_h = $row['v_h'];
        $pp_f = number_format($row['pp']); 
        $tot_f = number_format($row['tot']);
        
        // Safely escape all data attributes to prevent HTML breakage
        $safe_hotel   = esc_attr($hotel_name);
        $safe_pax     = esc_attr($row['tp']);
        $safe_veh     = esc_attr($row['v_r']);
        $safe_rms     = esc_attr($row['r_r']);
        $safe_pp      = esc_attr($row['pp']);
        $safe_start   = esc_attr($row['start_date']);
        $safe_end     = esc_attr($row['end_date']);
        $safe_season  = esc_attr($row['season_name']);
        $safe_surch   = esc_attr($row['surcharge_percent']);

        echo "<tr class='utpc-row' data-hotel='{$safe_hotel}' data-pax='{$safe_pax}' data-veh='{$safe_veh}' data-rms='{$safe_rms}' data-pp='{$safe_pp}' data-start='{$safe_start}' data-end='{$safe_end}' data-season-name='{$safe_season}' data-surcharge-percent='{$safe_surch}'>";
        echo "<td class='col-adaptive'>{$r_h}</td><td class='col-adaptive'>{$v_h}</td><td class='price-col'>₹{$pp_f}</td><td class='price-col'>₹{$tot_f}</td>";
        if ($is_admin) {
            echo "<td class='col-nowrap' style='background:#fffbea;'>₹".number_format($row['at'])."</td><td class='col-nowrap' style='background:#f0fdf4; color:green; font-weight:700;'>₹".number_format($row['pt'],0)."</td>";
        }
        echo "</tr>";
    }
    echo '</tbody></table></div>';

    wp_send_json_success(ob_get_clean());
}