<?php
/**
 * Plugin Name: WooCommerce Single to Variable Products
 * Plugin URI: https://github.com/WeAreCode045/woo-single2variable
 * Description: Generate WooCommerce variable products by merging single products using AI.
 * Version: 2.3.7
 * Author: WeAreCode045
 * Author URI: https://github.com/WeAreCode045
 * Text Domain: woo-single2variable
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WS2V_VERSION', '1.3.5');
define('WS2V_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WS2V_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WS2V_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Ensure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'WS2V\\';
    $base_dir = WS2V_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Debug function to log to wp-content/debug.log
if (!function_exists('ws2v_log')) {
    function ws2v_log($message, $data = null) {
        if (WP_DEBUG === true) {
            if (is_array($data) || is_object($data)) {
                $message .= ' ' . print_r($data, true);
            }
            error_log('WS2V_DEBUG: ' . $message);
        }
    }
}

/**
 * Initialize the plugin
 */
function ws2v_init() {
    ws2v_log('Plugin initialization started');
    
    // Check if WooCommerce is active
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        ws2v_log('WooCommerce is not active');
        add_action('admin_notices', function() {
            echo '<div class="error"><p>WooCommerce Single to Variable requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }
    
    // Log AJAX context
    $ajax_context = [
        'wp_doing_ajax' => wp_doing_ajax(),
        'is_admin' => is_admin(),
        'DOING_AJAX' => defined('DOING_AJAX') ? DOING_AJAX : 'not defined',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not set',
        'action' => $_REQUEST['action'] ?? 'not set'
    ];
    
    ws2v_log('AJAX context', $ajax_context);
    
    // Initialize core classes
    try {
        $plugin = new WS2V\Core\Plugin();
        
        // Initialize AJAX handlers
        if (wp_doing_ajax() || is_admin()) {
            new \WS2V\Admin\Ajax();
            ws2v_log('AJAX handlers registered in ws2v_init');
        }
        
        ws2v_log('Plugin class instantiated successfully');
    } catch (Exception $e) {
        ws2v_log('Error initializing plugin', $e->getMessage());
        if (is_admin()) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="error"><p>Error initializing WooCommerce Single to Variable: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
}
add_action('plugins_loaded', 'ws2v_init', 1);

// Direct AJAX hook to ensure our handlers are registered early enough
add_action('init', function() {
    if (wp_doing_ajax()) {
        ws2v_log('AJAX request detected in init hook');
        
        // Ensure our AJAX handlers are registered
        if (class_exists('WS2V\\Admin\\Ajax')) {
            try {
                $ajax = new \WS2V\Admin\Ajax();
                ws2v_log('AJAX handlers registered in init hook', [
                    'action' => $_REQUEST['action'] ?? 'none',
                    'nonce' => !empty($_REQUEST['nonce']) ? 'set' : 'not set'
                ]);
            } catch (Exception $e) {
                ws2v_log('Error initializing AJAX handlers', $e->getMessage());
            }
        }
    }
}, 1);
