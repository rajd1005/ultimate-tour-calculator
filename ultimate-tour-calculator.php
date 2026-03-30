<?php
/**
 * Plugin Name: Ultimate Tour Price Calculator
 * Description: A modular, AJAX-powered tour pricing calculator with dynamic hotel categories.
 * Version: 1.0.0
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
    wp_enqueue_style('utpc-style', UTPC_URL . 'assets/css/style.css', [], '1.0.0');
    wp_enqueue_script('utpc-script', UTPC_URL . 'assets/js/script.js', ['jquery'], '1.0.0', true);
    
    // Localize AJAX URL and config for JS
    $settings = include(UTPC_PATH . 'config/settings.php');
    wp_localize_script('utpc-script', 'utpc_obj', [
        'ajax_url'  => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('utpc_nonce'),
        'wa_number' => $settings['whatsapp_number']
    ]);
}