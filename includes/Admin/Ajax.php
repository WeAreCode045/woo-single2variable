<?php
namespace WS2V\Admin;

class Ajax {
    /**
     * @var string Current process status
     */
    private $status = 'idle';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_ws2v_start_process', [$this, 'start_process']);
        add_action('wp_ajax_ws2v_stop_process', [$this, 'stop_process']);
        add_action('wp_ajax_ws2v_get_status', [$this, 'get_status']);
        add_action('wp_ajax_ws2v_get_products', [$this, 'get_products']);
    }

    /**
     * Start the product merging process
     */
    public function start_process() {
        check_ajax_referer('ws2v_ajax_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
        
        if (empty($product_ids)) {
            wp_send_json_error('No products selected');
        }

        // Initialize queue manager
        $queue_manager = new \WS2V\Queue\QueueManager();
        
        // Group products by category
        $grouped_products = $this->group_products_by_category($product_ids);
        
        // Add each group to queue
        $queued_items = [];
        foreach ($grouped_products as $category_id => $products) {
            $item_id = $queue_manager->add_to_queue($products);
            if ($item_id) {
                $queued_items[] = $item_id;
            }
        }

        if (empty($queued_items)) {
            wp_send_json_error('Failed to queue products');
        }

        // Update process status
        update_option('ws2v_process_status', 'running');
        
        // Initialize stats if not exists
        if (!get_option('ws2v_stats')) {
            update_option('ws2v_stats', [
                'processed' => 0,
                'created' => 0,
                'failed' => 0
            ]);
        }

        wp_send_json_success([
            'message' => 'Processing started',
            'queued_items' => count($queued_items),
            'product_count' => count($product_ids)
        ]);
    }

    /**
     * Stop the current process
     */
    public function stop_process() {
        check_ajax_referer('ws2v_ajax_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        update_option('ws2v_process_status', 'stopped');
        wp_clear_scheduled_hook('ws2v_process_products');

        wp_send_json_success([
            'message' => 'Processing stopped'
        ]);
    }

    /**
     * Get current process status and stats
     */
    /**
     * Group products by category
     */
    private function group_products_by_category($product_ids) {
        $grouped = [];
        
        foreach ($product_ids as $product_id) {
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            
            if (!empty($categories)) {
                $category_id = $categories[0]; // Use first category
                if (!isset($grouped[$category_id])) {
                    $grouped[$category_id] = [];
                }
                $grouped[$category_id][] = $product_id;
            }
        }
        
        return $grouped;
    }

    public function get_status() {
        check_ajax_referer('ws2v_ajax_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $queue_manager = new \WS2V\Queue\QueueManager();
        $queue_stats = $queue_manager->get_queue_stats();
        
        $status = get_option('ws2v_process_status', 'idle');
        $stats = get_option('ws2v_stats', [
            'processed' => 0,
            'created' => 0,
            'failed' => 0
        ]);
        
        $logs = get_option('ws2v_process_logs', []);

        wp_send_json_success([
            'status' => $status,
            'stats' => $stats,
            'queue' => $queue_stats,
            'logs' => array_slice($logs, -50) // Get last 50 log entries
        ]);
    }

    /**
     * Get products that can be merged
     */
    public function get_products() {
        check_ajax_referer('ws2v_ajax_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $category = isset($_GET['category']) ? intval($_GET['category']) : 0;
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => 'simple'
                ]
            ]
        ];

        if (!empty($search)) {
            $args['s'] = $search;
        }

        if ($category > 0) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $category
            ];
        }

        $products = [];
        $query = new \WP_Query($args);

        foreach ($query->posts as $post) {
            $product = wc_get_product($post);
            $products[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_price(),
                'stock' => $product->get_stock_quantity(),
                'categories' => wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names'])
            ];
        }

        wp_send_json_success([
            'products' => $products,
            'total' => $query->found_posts
        ]);
    }
}
