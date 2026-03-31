<?php
if (!defined('ABSPATH')) { exit; }

return [
    // ---------------------------------------------------------
    // 1. CORE COSTS & PROFIT
    // ---------------------------------------------------------
    'base_cost_per_pax'     => 5000, 
    'profit_margin_per_pax' => 5000, 

    // ---------------------------------------------------------
    // 2. TRIP DURATION SETTINGS
    // ---------------------------------------------------------
    'trip_duration' => [
        'nights' => 6,
        'days'   => 7,
        'label'  => '6 Night - 7 Days'
    ],

    // ---------------------------------------------------------
    // 3. SEASONAL SURCHARGES
    // ---------------------------------------------------------
    'seasonal_surcharges' => [
        [ 'name' => 'Winter (Peak)', 'start' => '12-01', 'end' => '02-28', 'surcharge_percent' => 0 ],
        [ 'name' => 'Spring/Summer', 'start' => '03-01', 'end' => '06-30', 'surcharge_percent' => 0 ],
        [ 'name' => 'Autumn', 'start' => '09-01', 'end' => '11-30', 'surcharge_percent' => 0 ],
        [ 'name' => 'Monsoon/Shoulder', 'start' => '07-01', 'end' => '08-31', 'surcharge_percent' => 0 ],
    ],
    
    // ---------------------------------------------------------
    // 4. ROOM SETTINGS
    // ---------------------------------------------------------
    'rooms' => [
        'standard' => [ 'name' => 'Deluxe Room 2/3P' , 'price' => 5000, 'capacity' => 3, 'max_qty' => 0 ],
        'family'   => [ 'name' => 'Family Deluxe Room 4P', 'price' => 7000, 'capacity' => 4, 'max_qty' => 2 ],
    ],
    
    // ---------------------------------------------------------
    // 5. VEHICLE SETTINGS
    // ---------------------------------------------------------
    'vehicles' => [
        'dzire'   => [ 'name' => 'Dzire 4P', 'price' => 18900, 'capacity' => 4 ],
        'innova'  => [ 'name' => 'Innova 7P',  'price' => 24500, 'capacity' => 7 ],
        'urbania' => [ 'name' => 'Urbania 16P', 'price' => 43000, 'capacity' => 16 ],
    ],

    // ---------------------------------------------------------
    // 6. HOTEL CATEGORIES
    // ---------------------------------------------------------
    'hotel_categories' => [
        'budget' => [ 'name' => 'Budget Hotel', 'multiplier' => 1.0 ],
        '3star'  => [ 'name' => '3 Star Hotel', 'multiplier' => 1.75 ],
        '5star'  => [ 'name' => '5 Star Hotel', 'multiplier' => 2.50 ],
    ],

    // ---------------------------------------------------------
    // 7. CONTACT, TEXT & INCLUSION SETTINGS
    // ---------------------------------------------------------
    'whatsapp_number' => '918100347776',
    'popup_title'     => 'Kashmir Tour Package',
    'popup_subtitle'  => 'Daily departures from Jammu & Srinagar',
    
    // New Inclusions & Exclusions
    'inclusions' => [
        'Jammu / Srinagar Pickup and drop',
        'Sightseeing & Transfers',
        'All Accommodation',
        'Breakfast & Dinner'
    ],
    'exclusions_note' => 'Anything Not mentioned is all exclusions',
    
    'popup_note'      => '<b>Note:</b> 5% GST extra. Child below 6 FREE.'
];