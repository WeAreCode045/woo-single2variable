<?php
namespace WS2V\Core;

class Plugin {
    /**
     * @var Plugin Single instance of this class
     */
    private static $instance;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register activation and deactivation hooks
        register_activation_hook(WS2V_PLUGIN_BASENAME, [self::class, 'activate']);
        register_deactivation_hook(WS2V_PLUGIN_BASENAME, [self::class, 'deactivate']);

        // Add custom cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_interval']);
        
        // Schedule automatic product processing
        if (!wp_next_scheduled('ws2v_auto_process_products')) {
            wp_schedule_event(time(), 'hourly', 'ws2v_auto_process_products');
        }
        add_action('ws2v_auto_process_products', [$this, 'auto_process_products']);

        // Initialize admin
        if (is_admin()) {
            new \WS2V\Admin\Dashboard();
            new \WS2V\Admin\Settings();
            new \WS2V\Admin\Ajax();
            new \WS2V\Queue\QueueCleaner();
        }

        // Initialize frontend
        add_action('init', [$this, 'init_frontend']);
    }

    /**
     * Initialize admin functionality
     */
    public function init_admin() {
        // Load admin classes
    }

    /**
     * Initialize frontend functionality
     */
    public function init_frontend() {
        // Load frontend classes
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(WS2V_PLUGIN_FILE));
            wp_die(
                esc_html__('This plugin requires WooCommerce to be installed and activated.', 'woo-single2variable'),
                'Plugin dependency check',
                ['back_link' => true]
            );
        }

        // Initialize default settings if not exists
        if (!get_option('ws2v_settings')) {
            update_option('ws2v_settings', [
                'title_similarity_threshold' => 80,
                'ai_provider' => 'openai',
                'openai_api_key' => '',
                'openai_model' => 'gpt-3.5-turbo'
            ]);
        }

        // Initialize queue table
        $queue_manager = new \WS2V\Queue\QueueManager();
        $queue_manager->create_table();

        // Add custom cron schedule
        if (!wp_next_scheduled('ws2v_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'ws2v_process_queue');
        }
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Clear any scheduled hooks
        wp_clear_scheduled_hook('ws2v_process_queue');
        wp_clear_scheduled_hook('ws2v_auto_process_products');
    }

    /**
     * Add custom cron interval
     *
     * @param array $schedules
     * @return array
     */
    public function add_cron_interval($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute', 'woo-single2variable')
        );
        $schedules['hourly'] = array(
            'interval' => 3600,
            'display'  => __('Every Hour', 'woo-single2variable')
        );
        return $schedules;
    }

    /**
     * Auto process products
     */
    public function auto_process_products() {
        // If a manual process is running, skip the automatic run
        $status = get_option('ws2v_process_status', 'idle');
        if ($status === 'manual_running') {
            return;
        }

        // Set process status to running
        update_option('ws2v_process_status', 'running');

        // Get all simple products
        $args = array(
            'type' => 'simple',
            'limit' => -1,
            'status' => 'publish',
        );
        $products = wc_get_products($args);
        if (empty($products)) {
            update_option('ws2v_process_status', 'idle');
            return;
        }

        $product_ids = array_map(function($product) {
            return $product->get_id();
        }, $products);

        // Batch into chunks of 50
        $batches = array_chunk($product_ids, 50);

        $processor = new \WS2V\Product\BackgroundProcessor();
        foreach ($batches as $batch) {
            $processor->process_single_batch($batch);
        }

        // Set status to idle when done
        update_option('ws2v_process_status', 'idle');
    }

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
