<?php
/**
 * Plugin Name: WooCommerce Single to Variable Products
 * Plugin URI: https://github.com/WeAreCode045/woo-single2variable
 * Description: Generate WooCommerce variable products by merging single products using AI.
 * Version: 1.2.0
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

// Initialize the plugin
function ws2v_init() {
    // Initialize core classes
    new WS2V\Core\Plugin();
}
add_action('plugins_loaded', 'ws2v_init');
