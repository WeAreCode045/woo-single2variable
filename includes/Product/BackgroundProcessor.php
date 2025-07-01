<?php
namespace WS2V\Product;

class BackgroundProcessor {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('ws2v_process_products', [$this, 'process_products']);
        add_action('ws2v_process_single_batch', [$this, 'process_single_batch']);
    }

    /**
     * Process products in batches
     *
     * @param array $product_ids
     */
    public function process_products($product_ids) {
        // Group products by category
        $grouped_products = $this->group_products_by_category($product_ids);
        
        foreach ($grouped_products as $category_id => $products) {
            // Schedule each group as a separate batch
            wp_schedule_single_event(
                time(),
                'ws2v_process_single_batch',
                [$products]
            );
        }
    }

    /**
     * Process a single batch of products
     *
     * @param array $product_ids
     */
    public function process_single_batch($product_ids) {
        $merger = new ProductMerger();
        $stats = get_option('ws2v_stats', [
            'processed' => 0,
            'created' => 0,
            'failed' => 0
        ]);
        
        try {
            // Check if processing should continue
            if (get_option('ws2v_process_status') !== 'running') {
                return;
            }

            // Validate products can be merged
            $can_merge = $merger->can_merge_products($product_ids);
            if (is_wp_error($can_merge)) {
                $this->log_error($can_merge->get_error_message());
                $stats['failed']++;
                update_option('ws2v_stats', $stats);
                return;
            }

            // Create variable product
            $variable_product_id = $merger->create_variable_product($product_ids);
            if (is_wp_error($variable_product_id)) {
                $this->log_error($variable_product_id->get_error_message());
                $stats['failed']++;
                update_option('ws2v_stats', $stats);
                return;
            }

            // Update stats
            $stats['processed'] += count($product_ids);
            $stats['created']++;
            update_option('ws2v_stats', $stats);

            // Log success
            $this->log_message(sprintf(
                __('Successfully created variable product #%d from %d products', 'woo-single2variable'),
                $variable_product_id,
                count($product_ids)
            ));

        } catch (\Exception $e) {
            $this->log_error($e->getMessage());
            $stats['failed']++;
            update_option('ws2v_stats', $stats);
        }
    }

    /**
     * Group products by category
     *
     * @param array $product_ids
     * @return array
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

    /**
     * Log a message
     *
     * @param string $message
     * @param string $type
     */
    private function log_message($message, $type = 'info') {
        $logs = get_option('ws2v_process_logs', []);
        
        $logs[] = [
            'time' => current_time('mysql'),
            'message' => $message,
            'type' => $type
        ];
        
        // Keep only last 1000 logs
        if (count($logs) > 1000) {
            array_shift($logs);
        }
        
        update_option('ws2v_process_logs', $logs);
    }

    /**
     * Log an error message
     *
     * @param string $message
     */
    private function log_error($message) {
        $this->log_message($message, 'error');
    }
}
