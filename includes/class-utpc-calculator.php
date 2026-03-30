<?php
if (!defined('ABSPATH')) { exit; }

class UTPC_Calculator {
    public static function process_calculation($data) {
        $cfg = include(UTPC_PATH . 'config/settings.php');
        
        // Strict global PAX definition based on input
        $pax       = max(1, intval($data['tour_pax'] ?? 4));
        $tp        = $pax; 
        
        $hotel_cat = sanitize_text_field($data['hotel_category'] ?? 'budget');
        $multiplier= $cfg['hotel_categories'][$hotel_cat]['multiplier'] ?? 1.0;

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
                
                // Base cost logic strictly uses actual persons ($tp)
                $base_cost = $vobj['cost'] + $robj['cost'] + ($tp * $cfg['base_cost_per_pax']);
                $agent_price = $base_cost * $multiplier; 
                
                // Profit margin added
                $pp = ceil(($agent_price / $tp + $cfg['profit_margin_per_pax']) / 500) * 500;
                
                $res[] = [
                    'v_h' => $vobj['html'], 
                    'v_r' => $vobj['raw'], 
                    'r_h' => $robj['html'], 
                    'r_r' => $robj['raw'], 
                    'at'  => $agent_price, 
                    'pt'  => ($pp * $tp) - $agent_price, 
                    'pp'  => $pp, 
                    'tot' => $pp * $tp, 
                    'tp'  => $tp
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
                    $solve($i, $current_cap + $items[$k]['capacity'], $new_combo);
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