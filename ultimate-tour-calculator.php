<?php
/**
 * Plugin Name: Ultimate Tour Price Calculator
 * Description: A modular, AJAX-powered tour pricing calculator with dynamic hotel categories.
 * Version: 1.0.28
 * Author: Your Name
 */

if (!defined('ABSPATH')) { exit; }

// Define Constants
define('UTPC_PATH', plugin_dir_path(__FILE__));
define('UTPC_URL', plugin_dir_url(__FILE__));

// Require Core Files
require_once UTPC_PATH . 'includes/class-utpc-calculator.php';
require_once UTPC_PATH . 'includes/class-utpc-ajax.php';
require_once UTPC_PATH . 'includes/class-utpc-shortcode.php';

// Enqueue Assets
add_action('wp_enqueue_scripts', 'utpc_enqueue_scripts', 999);
function utpc_enqueue_scripts() {
    wp_enqueue_style('utpc-style', UTPC_URL . 'assets/css/style.css', [], '1.0.28');
    wp_enqueue_script('utpc-script', UTPC_URL . 'assets/js/script.js', ['jquery'], '1.0.28', true);
    
    // Localize AJAX URL and config for JS
    $settings = include(UTPC_PATH . 'config/settings.php');
    wp_localize_script('utpc-script', 'utpc_obj', [
        'ajax_url'        => admin_url('admin-ajax.php'),
        'nonce'           => wp_create_nonce('utpc_nonce'),
        'wa_number'       => $settings['whatsapp_number'] ?? '',
        'popup_title'     => $settings['popup_title'] ?? 'Tour Package',
        'popup_subtitle'  => $settings['popup_subtitle'] ?? '',
        'inclusions'      => $settings['inclusions'] ?? [],
        'exclusions_note' => $settings['exclusions_note'] ?? 'Anything Not mentioned is all exclusions'
    ]);
}

// Register Custom Post Type for Bookings
add_action('init', 'utpc_register_booking_cpt');
function utpc_register_booking_cpt() {
    register_post_type('utpc_booking', [
        'labels'      => ['name' => 'Tour Bookings', 'singular_name' => 'Booking'],
        'public'      => false, 
        'show_ui'     => true,
        'menu_icon'   => 'dashicons-clipboard',
        'supports'    => ['title', 'custom-fields'],
        'has_archive' => false,
    ]);
}

// Global Role Authentication Helper
function utpc_get_user_role_type() {
    if (!is_user_logged_in()) return 'guest';
    
    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    $cfg = include(UTPC_PATH . 'config/settings.php');
    
    $manager_roles  = $cfg['role_settings']['managers'] ?? ['administrator'];
    $employee_roles = $cfg['role_settings']['employees'] ?? ['editor', 'author'];
    
    if (count(array_intersect($manager_roles, $roles)) > 0) return 'manager';
    if (count(array_intersect($employee_roles, $roles)) > 0) return 'employee';
    
    return 'guest';
}