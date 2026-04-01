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

    // --- NEW: FIXED DEPARTURE SYSTEM ---
    private static function process_fixed($data) {
        $cfg = include(UTPC_PATH . 'config/settings.php');
        $tp = max(1, intval($data['tour_pax'] ?? 2));
        $tour_id = sanitize_text_field($data['fixed_tour'] ?? '');
        
        if (!isset($cfg['fixed_departures'][$tour_id])) return [];
        $tour = $cfg['fixed_departures'][$tour_id];
        
        $total_vehicle_cost = 0; $vehicle_names = [];
        foreach ($tour['vehicles'] as $v_key => $qty) {
            $total_vehicle_cost += ($cfg['vehicles'][$v_key]['price'] * $qty);
            $vehicle_names[] = $qty . "x " . $cfg['vehicles'][$v_key]['name'];
        }
        $veh_cost_per_seat = $total_vehicle_cost / max(1, intval($tour['total_seats']));

        $rooms = $data['custom_rooms'] ?? [];
        if (empty($rooms)) return [];

        $room_cost = 0; $room_html = ""; $room_raw = [];
        $counts = array_count_values((array)$rooms);
        foreach ($counts as $k => $qty) {
            if (!isset($cfg['rooms'][$k])) continue;
            $room_cost += $qty * $cfg['rooms'][$k]['price'];
            $name = $cfg['rooms'][$k]['name'];
            $room_html .= "<span class='u-badge badge-room-std'>{$qty}x {$name}</span> ";
            $room_raw[] = "{$qty}x {$name}";
        }

        $base_cost = ($veh_cost_per_seat * $tp) + $room_cost + ($cfg['base_cost_per_pax'] * $tp);
        $hotel_multiplier = $cfg['hotel_categories'][$tour['hotel_category']]['multiplier'] ?? 1.0;
        $agent_price = $base_cost * $hotel_multiplier; 
        
        $pp = ceil(($agent_price / $tp + $cfg['profit_margin_per_pax']) / 500) * 500;
        return [[
            'tour_name' => $tour['name'],
            'v_h' => "<span class='u-badge badge-veh'>" . implode(', ', $vehicle_names) . "</span>",
            'v_r' => implode(', ', $vehicle_names),
            'r_h' => "<div class='badge-list'>$room_html</div>",
            'r_r' => implode(', ', $room_raw),
            'pp'  => $pp, 'tot' => $pp * $tp, 'tp'  => $tp
        ]];
    }

    // --- EXACT ORIGINAL CUSTOM CALCULATOR ---
    private static function process_custom($data) {
        $cfg = include(UTPC_PATH . 'config/settings.php');
        
        // Strict global PAX definition based on input
        $pax       = max(1, intval($data['tour_pax'] ?? 4));
        $tp        = $pax; 
        
        $hotel_cat = sanitize_text_field($data['hotel_category'] ?? 'budget');
        $multiplier= $cfg['hotel_categories'][$hotel_cat]['multiplier'] ?? 1.0;

        // --- DATE & SEASONAL CALCULATION ---
        $tour_date = sanitize_text_field($data['tour_date'] ?? date('Y-m-d'));
        
        $surcharge_percent = 0;
        $season_name = 'Normal Season';
        
        if ($tour_date) {
            $m_d = date('m-d', strtotime($tour_date));
            foreach ($cfg['seasonal_surcharges'] as $season) {
                $start = $season['start'];
                $end   = $season['end'];
                
                // If season crosses new year (e.g. Dec 15 to Jan 10)
                if ($start > $end) {
                    if ($m_d >= $start || $m_d <= $end) {
                        $surcharge_percent = $season['surcharge_percent'];
                        $season_name = $season['name'];
                        break;
                    }
                } else {
                    if ($m_d >= $start && $m_d <= $end) {
                        $surcharge_percent = $season['surcharge_percent'];
                        $season_name = $season['name'];
                        break;
                    }
                }
            }
        }
        
        $season_multiplier = 1 + ($surcharge_percent / 100);
        
        // Calculate End Date based on Trip Duration settings
        $days = $cfg['trip_duration']['days'] ?? 7;
        $start_date_str = date('d M Y', strtotime($tour_date));
        // End date is Start Date + (Days - 1)
        $end_date_str = date('d M Y', strtotime($tour_date . " + " . ($days - 1) . " days"));

        // Process Rooms
        $vr = [];
        if (($data['room_mode'] ?? 'auto') === 'custom') {
            $custom_room_result = self::process_custom_selection($data['custom_rooms'] ?? [], $cfg['rooms'], 'badge-room-std');
            if ($custom_room_result) $vr[] = $custom_room_result;
        } else {
            $pref = sanitize_text_field($data['room_pref'] ?? 'any');
            $vr   = self::auto_calculate($pax, $cfg['rooms'], $pref, 'badge-room-std', false);
        }

        // Process Vehicles
        $vv = [];
        if (($data['vehicle_mode'] ?? 'auto') === 'custom') {
            $custom_veh_result = self::process_custom_selection($data['custom_vehicles'] ?? [], $cfg['vehicles'], 'badge-veh');
            if ($custom_veh_result) $vv[] = $custom_veh_result;
        } else {
            $pref = sanitize_text_field($data['cab_pref'] ?? 'any');
            $vv   = self::auto_calculate($pax, $cfg['vehicles'], $pref, 'badge-veh', true);
        }

        // Combine Results & Calculate Final Prices
        $res = [];
        foreach($vv as $vobj) { 
            foreach($vr as $robj) {
                if (!$vobj || !$robj) continue; 
                
                // Base cost logic multiplied by hotel category AND season surcharge
                $base_cost = $vobj['cost'] + $robj['cost'] + ($tp * $cfg['base_cost_per_pax']);
                $agent_price = $base_cost * $multiplier * $season_multiplier; 
                
                // Profit margin added
                $pp = ceil(($agent_price / $tp + $cfg['profit_margin_per_pax']) / 500) * 500;
                
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
                    'season_name'=> $season_name,
                    'surcharge_percent' => $surcharge_percent,
                ];
            }
        }
        
        // Remove duplicates & sort by cheapest total package price
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
            if ($limit > 0 && $qty > $limit) {
                $qty = $limit;
            }
            
            $cost += $qty * $settings_array[$k]['price'];
            $cap  += $qty * $settings_array[$k]['capacity'];
            $name  = $settings_array[$k]['name'];
            
            $badges .= "<span class='u-badge {$badge_class}'>{$qty}x {$name}</span>";
            $raw[] = "{$qty}x {$name}";
        }
        return [
            'html' => "<div class='badge-list'>$badges</div>", 
            'cost' => $cost, 
            'raw' => implode(', ', $raw), 
            'total_cap' => $cap
        ];
    }

    // Advanced Auto-Calculation Engine (Applies Tolerance Rules)
    private static function auto_calculate($target_pax, $settings_array, $pref_key, $badge_class, $is_vehicle) {
        $items = []; 
        $max_qtys = [];
        
        foreach ($settings_array as $k => $v) {
            if ($pref_key !== 'any' && $pref_key !== $k) continue;
            $items[$k] = $v;
            $max_qtys[$k] = $v['max_qty'] ?? 0;
        }

        $results = [];
        $keys = array_keys($items);
        if (empty($keys)) return [];
        
        // Tolerance limits to prevent over-allocating space
        $veh_tol = max(4, ceil($target_pax * 0.4));
        $room_tol = 2; 
        
        $solve = function($idx, $current_cap, $combo) use (&$solve, &$results, $target_pax, $items, $keys, $max_qtys, $is_vehicle, $veh_tol, $room_tol, $pref_key) {
            if ($current_cap >= $target_pax) {
                
                // Check Tolerance: Discard if there are too many empty seats/beds
                $empty_space = $current_cap - $target_pax;
                if ($is_vehicle) {
                    if ($pref_key === 'any' && $empty_space > $veh_tol) return;
                } else {
                    if ($empty_space > $room_tol) return;
                }

                // Verify minimal condition (ensures we don't add an extra item if it already fits)
                $is_minimal = true;
                foreach ($combo as $k => $qty) {
                    if ($qty > 0 && ($current_cap - $items[$k]['capacity'] >= $target_pax)) {
                        $is_minimal = false; break;
                    }
                }
                
                if ($is_minimal) {
                    $results[] = $combo;
                }
                return;
            }
            
            for ($i = $idx; $i < count($keys); $i++) {
                $k = $keys[$i];
                $current_qty = $combo[$k] ?? 0;
                $limit = ($max_qtys[$k] === 0) ? 999 : $max_qtys[$k];
                
                if ($current_qty < $limit) {
                    $new_combo = $combo;
                    $new_combo[$k] = $current_qty + 1;
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
                $cost += $qty * $items[$k]['price'];
                $cap  += $qty * $items[$k]['capacity'];
                $name  = $items[$k]['name'];
                
                $badges .= "<span class='u-badge {$badge_class}'>{$qty}x {$name}</span>";
                $raw[] = "{$qty}x {$name}";
            }
            $formatted[] = [
                'html' => "<div class='badge-list'>$badges</div>", 
                'cost' => $cost, 
                'raw' => implode(', ', $raw), 
                'total_cap' => $cap
            ];
        }
        return $formatted;
    }
}