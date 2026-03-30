<?php
if (!defined('ABSPATH')) { exit; }

return [
    // ---------------------------------------------------------
    // 1. CORE COSTS & PROFIT
    // ---------------------------------------------------------
    'base_cost_per_pax'     => 5000, 
    'profit_margin_per_pax' => 5000, 
    
    // ---------------------------------------------------------
    // 2. ROOM SETTINGS (Fully Editable)
    // max_qty: Set to 0 for unlimited, or a number (e.g., 2) to limit availability.
    // ---------------------------------------------------------
    'rooms' => [
        'standard' => [ 'name' => 'Deluxe Room', 'price' => 5000, 'capacity' => 3, 'max_qty' => 0 ],
        'family'   => [ 'name' => 'Family Room', 'price' => 7000, 'capacity' => 4, 'max_qty' => 2 ],
        // To add a new room, just copy the line above! Example:
        // 'suite' => [ 'name' => 'Luxury Suite', 'price' => 12000, 'capacity' => 2, 'max_qty' => 1 ],
    ],
    
    // ---------------------------------------------------------
    // 3. VEHICLE SETTINGS (Fully Editable)
    // ---------------------------------------------------------
    'vehicles' => [
        'dzire'   => [ 'name' => 'Dzire (Sedan)', 'price' => 18900, 'capacity' => 4 ],
        'innova'  => [ 'name' => 'Innova (SUV)',  'price' => 24500, 'capacity' => 7 ],
        'urbania' => [ 'name' => 'Urbania (Bus)', 'price' => 43000, 'capacity' => 16 ],
        // To add a new vehicle, just copy the line above! Example:
        // 'tempo' => [ 'name' => 'Tempo Traveler', 'price' => 35000, 'capacity' => 12 ],
    ],

    // ---------------------------------------------------------
    // 4. HOTEL CATEGORIES
    // multiplier: 1.0 = Base Price, 1.75 = +75%, 2.50 = +150%
    // ---------------------------------------------------------
    'hotel_categories' => [
        'budget' => [ 'name' => 'Budget (Standard Price)', 'multiplier' => 1.0 ],
        '3star'  => [ 'name' => '3 Star (+75%)',           'multiplier' => 1.75 ],
        '5star'  => [ 'name' => '5 Star (+150%)',          'multiplier' => 2.50 ],
        // Add new categories below:
        // '4star' => [ 'name' => '4 Star (+110%)', 'multiplier' => 2.10 ],
    ],

    // ---------------------------------------------------------
    // 5. CONTACT & TEXT SETTINGS
    // ---------------------------------------------------------
    'whatsapp_number' => '918100347776',
    'popup_title'     => 'Ultimate Tour Package',
    'popup_subtitle'  => '6 Night - 7 Days | Custom Route',
    'popup_note'      => '<b>Note:</b> 5% GST extra. Child <6 FREE. Above 6 full charge.<br><b>For inclusions & exclusions check with our agent.</b>'
];