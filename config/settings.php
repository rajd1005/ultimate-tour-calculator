<?php
if (!defined('ABSPATH')) { exit; }

return [
    // ---------------------------------------------------------
    // 0. ROLE BASED ACCESS CONTROL (RBAC)
    // ---------------------------------------------------------
    'role_settings' => [
        // Managers see Agent/Profit columns AND the Booking Dashboard
        'managers'  => ['administrator'], 
        // Employees only see the Booking Dashboard (Agent/Profit is hidden)
        'employees' => ['editor', 'author', 'shop_manager'], 
    ],

    // ---------------------------------------------------------
    // 1. CORE COSTS & PROFIT
    // ---------------------------------------------------------
    'base_cost_per_pax'     => 6000, 
    'profit_margin_per_pax' => 6000, 

    // ---------------------------------------------------------
    // 2. LOCATIONS & SERVICE TYPES
    // ---------------------------------------------------------
    'pickup_locations' => [
        'srinagar' => 'Srinagar R.S / Airport',
        'jammu'    => 'Jammu R.S / Airport'
    ],
    
    'service_types' => [
        'both'  => 'Package (Hotel + MAP + Cab)',
        'hotel' => 'Hotel + MAP Only',
        'cab'   => 'Cab Only'
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
        'standard' => [ 'name' => 'Deluxe Room + MAP' , 'price' => 6000, 'capacity' => 3, 'max_qty' => 0 ],
        'family'   => [ 'name' => 'Family Deluxe + MAP', 'price' => 7000, 'capacity' => 4, 'max_qty' => 2 ],
    ],
    
    // ---------------------------------------------------------
    // 5. VEHICLE SETTINGS (With Per Day & Location Pricing)
    // ---------------------------------------------------------
    'vehicles' => [
        'dzire'   => [ 
            'name' => 'Dzire', 
            'capacity' => 4, 
            'price' => 0, 
            'price_per_day' => ['srinagar' => 1800, 'jammu' => 3200] 
        ],
        'innova'  => [ 
            'name' => 'Innova',  
            'capacity' => 7, 
            'price' => 0, 
            'price_per_day' => ['srinagar' => 2700, 'jammu' => 4000] 
        ],
        'urbania' => [ 
            'name' => 'Urbania', 
            'capacity' => 15, 
            'price' => 0, 
            'price_per_day' => ['srinagar' => 6000, 'jammu' => 7500] 
        ],
        'Crysta' => [ 
            'name' => 'Crysta', 
            'capacity' => 7, 
            'price' => 0, 
            'price_per_day' => ['srinagar' => 3500, 'jammu' => 5500] 
        ],
    ],

    // ---------------------------------------------------------
    // 6. HOTEL CATEGORIES
    // ---------------------------------------------------------
    'hotel_categories' => [
        'budget' => [ 'name' => 'Budget Hotel + MAP', 'multiplier' => 1.0 ],
        '3star'  => [ 'name' => '3 Star Hotel + MAP', 'multiplier' => 1.75 ],
        '5star'  => [ 'name' => '5 Star Hotel + MAP', 'multiplier' => 2.50 ],
    ],

    // ---------------------------------------------------------
    // 7. CONTACT, TEXT & INCLUSION SETTINGS
    // ---------------------------------------------------------
    'whatsapp_number' => '918100347776',
    'popup_title'     => 'Kashmir Tour Package',
    'popup_subtitle'  => 'Daily departures from Jammu & Srinagar',
    
    'inclusions' => [
        'Jammu / Srinagar Pickup and drop',
        'Sightseeing & Transfers',
        'Tripe to Gulmarg, Sonamarg, Srinagar Local sightseeing, Doodhpathri, Pahalgam, Vaishno Devi Darshan',
        'All Accommodation',
        'Breakfast & Dinner'
    ],
    'exclusions_note' => 'Anything Not mentioned is all exclusions',
    'popup_note'      => '<b>Note:</b> Child below 6 FREE., MAP = Daily Breakfast + Dinner',

    // ---------------------------------------------------------
    // 8. FIXED DEPARTURES
    // ---------------------------------------------------------
    'fixed_departures' => [
        'kashmir_aug' => [
            'name'            => 'Kashmir Valley with Vaishno Devi Darshan',
            'date'            => '2024-05-08',
            'hotel_category'  => 'budget',
            'pickup_location' => 'jammu',
            'trip_days'       => 8,
            'vehicles'        => [ 'urbania' => 1 ], 
            'total_seats'     => 15
        ],
    ]
];