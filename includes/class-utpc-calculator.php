<?php
if (!defined('ABSPATH')) { exit; }

class UTPC_Calculator {
    public static function process_calculation($data) {
        if (($data['calc_mode'] ?? 'custom') === 'fixed') {
            return self::process_fixed($data);
        } else {
            return self::process_custom($data);
        }
    }

    private static function get_season_info($date_str, $surcharges) {
        $surcharge_percent = 0;
        $season_name = 'Normal Season';
        if ($date_str) {
            $m_d = date('m-d', strtotime($date_str));
            foreach ($surcharges as $season) {
                $start = $season['start']; $end = $season['end'];
                if ($start > $end) {
                    if ($m_d >= $start || $m_d <= $end) { $surcharge_percent = $season['surcharge_percent']; $season_name = $season['name']; break; }
                } else {
                    if ($m_d >= $start && $m_d <= $end) { $surcharge_percent = $season['surcharge_percent']; $season_name = $season['name']; break; }
                }
            }
        }
        return [
            'percent' => $surcharge_percent,
            'name' => $season_name,
            'multiplier' => 1 + ($surcharge_percent / 100)
        ];
    }

    private static function process_fixed($data) {
        $cfg = include(UTPC_PATH . 'config/settings.php');
        $tp = max(1, intval($data['tour_pax'] ?? 2));
        $tour_id = sanitize_text_field($data['fixed_tour'] ?? '');
        
        if (!isset($cfg['fixed_departures'][$tour_id])) return [];
        $tour = $cfg['fixed_departures'][$tour_id];
        
        $tour_date = sanitize_text_field($tour['date'] ?? date('Y-m-d'));
        $trip_days = max(1, intval($tour['trip_days'] ?? 7));
        $pickup_loc = sanitize_text_field($tour['pickup_location'] ?? 'srinagar');
        
        $season_info = self::get_season_info($tour_date, $cfg['seasonal_surcharges']);

        $rooms = $data['custom_rooms'] ?? [];
        if (empty($rooms)) return [];

        $total_cost = 0; $room_html = ""; $room_raw = [];
        $counts = array_count_values((array)$rooms);
        
        // Calculate price strictly based on selected sharing options
        foreach ($counts as $k => $qty) {
            if (!isset($cfg['fixed_sharing_rooms'][$k])) continue;
            
            $cap = $cfg['fixed_sharing_rooms'][$k]['capacity'];
            $pp_price = $tour['sharing_prices'][$k] ?? 0;
            
            // Base Cost = Per Person Price * Capacity * Number of rooms selected
            $total_cost += ($pp_price * $cap * $qty);
            
            $name = $cfg['fixed_sharing_rooms'][$k]['name'];
            $room_html .= "<span class='u-badge badge-room-std'>{$qty}x {$name}</span> ";
            $room_raw[] = "{$qty}x {$name}";
        }

        $base_pp = $tp > 0 ? ($total_cost / $tp) : 0;
        
        $start_date_str = date('d M Y', strtotime($tour_date));
        $end_date_str = date('d M Y', strtotime($tour_date . " + " . ($trip_days - 1) . " days"));

        $vehicle_names = [];
        foreach ($tour['vehicles'] as $v_key => $vqty) {
            $vehicle_names[] = $vqty . "x " . ($cfg['vehicles'][$v_key]['name'] ?? $v_key);
        }

        return [[
            'tour_name' => $tour['name'],
            'v_h' => "<span class='u-badge badge-veh'>" . implode(', ', $vehicle_names) . "</span>",
            'v_r' => implode(', ', $vehicle_names),
            'r_h' => "<div class='badge-list'>$room_html</div>",
            'r_r' => implode(', ', $room_raw),
            'pp'  => $base_pp, 
            'tot' => $total_cost, 
            'tp'  => $tp,
            'start_date' => $start_date_str,
            'end_date'   => $end_date_str,
            'season_name'=> $season_info['name'],
            'surcharge_percent' => $season_info['percent'],
            'trip_days'  => $trip_days,
            'pickup_loc' => $pickup_loc,
            'service_type'=> 'both'
        ]];
    }

    private static function process_custom($data) {
        $cfg = include(UTPC_PATH . 'config/settings.php');
        
        $pax       = max(1, intval($data['tour_pax'] ?? 4));
        $tp        = $pax; 
        
        $hotel_cat = sanitize_text_field($data['hotel_category'] ?? 'budget');
        $multiplier= $cfg['hotel_categories'][$hotel_cat]['multiplier'] ?? 1.0;
        $tour_date = sanitize_text_field($data['tour_date'] ?? date('Y-m-d'));
        
        $season_info = self::get_season_info($tour_date, $cfg['seasonal_surcharges']);
        
        $trip_days = max(1, intval($data['trip_days'] ?? 7));
        $pickup_loc = sanitize_text_field($data['pickup_location'] ?? 'srinagar');
        $serv = sanitize_text_field($data['service_type'] ?? 'both');

        $mapped_vehicles = [];
        foreach ($cfg['vehicles'] as $k => $v) {
            $mapped_vehicles[$k] = $v;
            $daily = $v['price_per_day'][$pickup_loc] ?? ($v['price'] / 7);
            $mapped_vehicles[$k]['price'] = $daily * $trip_days;
        }

        $end_date_str = date('d M Y', strtotime($tour_date . " + " . ($trip_days - 1) . " days"));
        $start_date_str = date('d M Y', strtotime($tour_date));

        $vr = [];
        if ($serv === 'cab') {
            $vr[] = ['html' => '<span class="u-badge badge-room-std">N/A</span>', 'cost' => 0, 'raw' => 'N/A', 'total_cap' => 0];
        } else {
            if (($data['room_mode'] ?? 'auto') === 'custom') {
                $custom_room_result = self::process_custom_selection($data['custom_rooms'] ?? [], $cfg['rooms'], 'badge-room-std');
                if ($custom_room_result) $vr[] = $custom_room_result;
            } else {
                $pref = sanitize_text_field($data['room_pref'] ?? 'any');
                $vr   = self::auto_calculate($pax, $cfg['rooms'], $pref, 'badge-room-std', false);
            }
        }

        $vv = [];
        if ($serv === 'hotel') {
            $vv[] = ['html' => '<span class="u-badge badge-veh">N/A</span>', 'cost' => 0, 'raw' => 'N/A', 'total_cap' => 0];
        } else {
            if (($data['vehicle_mode'] ?? 'auto') === 'custom') {
                $custom_veh_result = self::process_custom_selection($data['custom_vehicles'] ?? [], $mapped_vehicles, 'badge-veh');
                if ($custom_veh_result) $vv[] = $custom_veh_result;
            } else {
                $pref = sanitize_text_field($data['cab_pref'] ?? 'any');
                $vv   = self::auto_calculate($pax, $mapped_vehicles, $pref, 'badge-veh', true);
            }
        }

        $res = [];
        foreach($vv as $vobj) { 
            foreach($vr as $robj) {
                if (!$vobj || !$robj) continue; 
                
                $base_pax_cost = ($serv === 'cab') ? 0 : $cfg['base_cost_per_pax'];
                $base_cost = $vobj['cost'] + $robj['cost'] + ($tp * $base_pax_cost);
                
                $active_multiplier = ($serv === 'cab') ? 1.0 : $multiplier;
                $agent_price = $base_cost * $active_multiplier * $season_info['multiplier']; 
                
                $profit = ($serv === 'cab' || $serv === 'hotel') ? ($cfg['profit_margin_per_pax'] * 0.5) : $cfg['profit_margin_per_pax'];
                $pp = ceil(($agent_price / $tp + $profit) / 500) * 500;
                
                $res[] = [
                    'v_h'        => $vobj['html'], 
                    'v_r'        => $vobj['raw'], 
                    'r_h'        => $robj['html'], 
                    'r_r'        => $robj['raw'], 
                    'at'         => $agent_price, 
                    'pt'         => ($pp * $tp) - $agent_price, 
                    'pp'         => $pp, 
                    'tot'        => $pp * $tp, 
                    'tp'         => $tp,
                    'start_date' => $start_date_str,
                    'end_date'   => $end_date_str,
                    'season_name'=> $season_info['name'],
                    'surcharge_percent' => $season_info['percent'],
                    'trip_days'  => $trip_days,
                    'pickup_loc' => $pickup_loc,
                    'service_type'=> $serv
                ];
            }
        }
        
        $res = array_map("unserialize", array_unique(array_map("serialize", $res)));
        usort($res, function($a,$b){ return $a['tot'] <=> $b['tot']; });
        return $res;
    }

    private static function process_custom_selection($submitted_keys, $settings_array, $badge_class) {
        if (empty($submitted_keys)) return null;
        $counts = array_count_values((array)$submitted_keys);
        
        $cost = 0; $cap = 0; $badges = ""; $raw = [];
        foreach ($counts as $k => $qty) {
            if (!isset($settings_array[$k])) continue;
            $limit = $settings_array[$k]['max_qty'] ?? 0;
            if ($limit > 0 && $qty > $limit) { $qty = $limit; }
            
            $cost += $qty * $settings_array[$k]['price'];
            $cap  += $qty * $settings_array[$k]['capacity'];
            $name  = $settings_array[$k]['name'];
            $badges .= "<span class='u-badge {$badge_class}'>{$qty}x {$name}</span>";
            $raw[] = "{$qty}x {$name}";
        }
        return [ 'html' => "<div class='badge-list'>$badges</div>", 'cost' => $cost, 'raw' => implode(', ', $raw), 'total_cap' => $cap ];
    }

    private static function auto_calculate($target_pax, $settings_array, $pref_key, $badge_class, $is_vehicle) {
        $items = []; $max_qtys = [];
        foreach ($settings_array as $k => $v) {
            if ($pref_key !== 'any' && $pref_key !== $k) continue;
            $items[$k] = $v; $max_qtys[$k] = $v['max_qty'] ?? 0;
        }

        $results = []; $keys = array_keys($items);
        if (empty($keys)) return [];
        
        $veh_tol = max(4, ceil($target_pax * 0.4));
        $room_tol = 2; 
        
        $solve = function($idx, $current_cap, $combo) use (&$solve, &$results, $target_pax, $items, $keys, $max_qtys, $is_vehicle, $veh_tol, $room_tol, $pref_key) {
            if ($current_cap >= $target_pax) {
                $empty_space = $current_cap - $target_pax;
                if ($is_vehicle) { if ($pref_key === 'any' && $empty_space > $veh_tol) return; } 
                else { if ($empty_space > $room_tol) return; }

                $is_minimal = true;
                foreach ($combo as $k => $qty) {
                    if ($qty > 0 && ($current_cap - $items[$k]['capacity'] >= $target_pax)) { $is_minimal = false; break; }
                }
                if ($is_minimal) { $results[] = $combo; }
                return;
            }
            
            for ($i = $idx; $i < count($keys); $i++) {
                $k = $keys[$i]; $current_qty = $combo[$k] ?? 0; $limit = ($max_qtys[$k] === 0) ? 999 : $max_qtys[$k];
                if ($current_qty < $limit) {
                    $new_combo = $combo; $new_combo[$k] = $current_qty + 1;
                    $solve($i, $current_cap + $items[$k]['capacity'] ?? 0, $new_combo);
                }
            }
        };
        $solve(0, 0, []);

        $formatted = [];
        foreach ($results as $combo) {
            $cost = 0; $cap = 0; $badges = ""; $raw = [];
            foreach ($combo as $k => $qty) {
                if ($qty <= 0) continue;
                $cost += $qty * $items[$k]['price']; $cap  += $qty * $items[$k]['capacity']; $name  = $items[$k]['name'];
                $badges .= "<span class='u-badge {$badge_class}'>{$qty}x {$name}</span>"; $raw[] = "{$qty}x {$name}";
            }
            $formatted[] = [ 'html' => "<div class='badge-list'>$badges</div>", 'cost' => $cost, 'raw' => implode(', ', $raw), 'total_cap' => $cap ];
        }
        return $formatted;
    }
}