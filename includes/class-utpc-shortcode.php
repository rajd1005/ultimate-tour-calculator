<?php
if (!defined('ABSPATH')) { exit; }

add_shortcode('ultimate_tour_calculator', 'utpc_render_shortcode');

function utpc_render_shortcode() {
    $settings = include(UTPC_PATH . 'config/settings.php');
    ob_start();
    ?>
    <div id="utpc-app-wrapper" class="utpc-wrapper">
        <form id="utpc-form">
            <div class="input-master-table">
                <div class="compact-box">
                    <div class="compact-label">1. Basic Details & Rooms</div>
                    
                    <div style="display:flex; gap:10px; margin-bottom: 12px;">
                        <div style="flex:1;">
                            <label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Total Pax</label>
                            <input type="number" name="tour_pax" class="u-field" value="4" min="1">
                        </div>
                        <div style="flex:2;">
                            <label style="font-size:10px; font-weight:800; color:#555; text-transform:uppercase;">Hotel Category</label>
                            <select name="hotel_category" class="u-field" style="background:#f0f7ff; font-weight:600; border-color:#bae6fd; color:#0369a1;">
                                <?php 
                                foreach($settings['hotel_categories'] as $k => $c) {
                                    echo "<option value='{$k}'>{$c['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="mini-toggles">
                        <input type="radio" id="r_a" name="room_mode" value="auto" checked>
                        <label for="r_a">AUTO ROOMS</label>
                        <input type="radio" id="r_c" name="room_mode" value="custom">
                        <label for="r_c">CUSTOM ROOMS</label>
                    </div>
                    
                    <div id="div-r-a">
                        <select name="room_pref" class="u-field">
                            <option value="any">Any Room Combo</option>
                            <?php 
                            foreach($settings['rooms'] as $k => $r) {
                                echo "<option value='{$k}'>Prefer {$r['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div id="div-r-c" class="hidden">
                        <div id="list-r" class="builder-list"></div>
                        <button type="button" id="add-r" class="btn-add">+ Add Room</button>
                    </div>
                </div>

                <div class="compact-box">
                    <div class="compact-label">2. Transport</div>
                    <div class="mini-toggles" style="margin-top: 5px;">
                        <input type="radio" id="v_a" name="vehicle_mode" value="auto" checked>
                        <label for="v_a">AUTO VEHICLE</label>
                        <input type="radio" id="v_c" name="vehicle_mode" value="custom">
                        <label for="v_c">CUSTOM VEHICLE</label>
                    </div>
                    <div id="div-v-a" style="margin-bottom: 5px;">
                        <select name="cab_pref" class="u-field">
                            <option value="any">Any Cab Combo</option>
                            <?php 
                            foreach($settings['vehicles'] as $k => $v) {
                                echo "<option value='{$k}'>Prefer {$v['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div id="div-v-c" class="hidden" style="margin-bottom: 5px;">
                        <div id="list-v" class="builder-list"></div>
                        <button type="button" id="add-v" class="btn-add">+ Add Car</button>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-main" id="btn-submit">
                <span class="btn-text">GET PACKAGE PRICES</span>
                <span class="btn-loader hidden">Calculating...</span>
            </button>
        </form>

        <div id="utpc-results"></div>
    </div>

    <div id="tpl-room-row" class="hidden">
        <div class="build-row">
            <select name="custom_rooms[]" class="u-field" style="border:none; background:transparent;">
                <?php 
                foreach($settings['rooms'] as $k => $r) {
                    echo "<option value='{$k}'>{$r['name']} ({$r['capacity']} Pax)</option>";
                }
                ?>
            </select>
            <button type="button" class="btn-rem">&times;</button>
        </div>
    </div>
    
    <div id="tpl-veh-row" class="hidden">
        <div class="build-row">
            <select name="custom_vehicles[]" class="u-field" style="border:none; background:transparent;">
                <?php 
                foreach($settings['vehicles'] as $k => $v) {
                    echo "<option value='{$k}'>{$v['name']} ({$v['capacity']} Pax)</option>";
                }
                ?>
            </select>
            <button type="button" class="btn-rem">&times;</button>
        </div>
    </div>

    <div id="utpcModal" class="ktc-modal">
        <div class="modal-content">
            <span class="close-modal" id="close-modal">&times;</span>
            <div class="modal-header">
                <h2><?php echo esc_html($settings['popup_title']); ?></h2>
                <p><?php echo esc_html($settings['popup_subtitle']); ?></p>
            </div>
            <div class="modal-body">
                <div class="mini-grid">
                    <div class="mini-box"><label>Total Pax</label><span id="m-pax"></span></div>
                    <div class="mini-box"><label>Category</label><span id="m-hotel"></span></div>
                    <div class="mini-box" style="grid-column: 1 / 3;"><label>Vehicle</label><span id="m-veh"></span></div>
                    <div class="mini-box" style="grid-column: 1 / 3;"><label>Rooms Breakdown</label><span id="m-rms"></span></div>
                </div>
                <div class="price-highlight">
                    <label>Package Price PP</label>
                    <span id="m-pp"></span>
                </div>
                <a href="#" id="m-wa-btn" target="_blank" class="btn-whatsapp">ENQUIRE ON WHATSAPP</a>
                <div class="policy-compact">
                    <?php echo wp_kses_post($settings['popup_note']); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}