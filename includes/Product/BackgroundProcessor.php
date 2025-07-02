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

            // Group products by similarity
            $similar_groups = $merger->group_similar_products($product_ids);
            
            foreach ($similar_groups as $group) {
                if (count($group) < 2) {
                    continue; // Skip groups with single products
                }
                
                $result = $merger->merge_products($group);
                if (!is_wp_error($result)) {
                    $stats['created']++;
                } else {
                    $stats['failed']++;
                }
                $stats['processed'] += count($group);
            }

            // Group products by similarity
            $similar_groups = $merger->group_similar_products($product_ids);
            
            foreach ($similar_groups as $group) {
                if (count($group) < 2) {
                    continue; // Skip groups with single products
                }
                
                $result = $merger->create_variable_product($group);
                if (!is_wp_error($result)) {
                    $stats['created']++;
                    $stats['processed'] += count($group);
                    
                    // Log success
                    $this->log_message(sprintf(
                        __('Successfully created variable product #%d from %d products', 'woo-single2variable'),
                        $result,
                        count($group)
                    ));
                } else {
                    $stats['failed']++;
                    $this->log_error($result->get_error_message());
                }
            }
            
            update_option('ws2v_stats', $stats);
            
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
        $grouped_products = [];
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }
            
            $categories = $product->get_category_ids();
            if (empty($categories)) {
                // Products without categories go into a special group
                if (!isset($grouped_products['uncategorized'])) {
                    $grouped_products['uncategorized'] = [];
                }
                $grouped_products['uncategorized'][] = $product_id;
                continue;
            }
            
            // Group by the first category (main category)
            $main_category = $categories[0];
            if (!isset($grouped_products[$main_category])) {
                $grouped_products[$main_category] = [];
            }
            $grouped_products[$main_category][] = $product_id;
        }
        
        return $grouped_products;
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
