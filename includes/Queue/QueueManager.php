<?php
namespace WS2V\Queue;

class QueueManager {
    /**
     * @var string Queue table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ws2v_queue';
        
        add_action('init', [$this, 'schedule_processor']);
        add_action('ws2v_process_queue', [$this, 'process_queue']);
    }

    /**
     * Create queue table
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_ids longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) NOT NULL DEFAULT 0,
            attempts int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY priority (priority)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Schedule queue processor
     */
    public function schedule_processor() {
        if (!wp_next_scheduled('ws2v_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'ws2v_process_queue');
        }
    }

    /**
     * Add items to queue
     *
     * @param array $product_ids
     * @param int $priority
     * @return int|false Queue item ID or false on failure
     */
    public function add_to_queue($product_ids, $priority = 0) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_name,
            [
                'product_ids' => maybe_serialize($product_ids),
                'priority' => $priority,
                'status' => 'pending'
            ],
            ['%s', '%d', '%s']
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Process queue items
     */
    public function process_queue() {
        // Check if processing is enabled
        if (get_option('ws2v_process_status') !== 'running') {
            return;
        }

        // Get next items from queue
        $items = $this->get_next_items();
        
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $this->process_item($item);
        }
    }

    /**
     * Get next items from queue
     *
     * @param int $limit
     * @return array
     */
    private function get_next_items($limit = 5) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE status = 'pending'
                AND attempts < 3
                ORDER BY priority DESC, created_at ASC
                LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Process single queue item
     *
     * @param object $item
     */
    private function process_item($item) {
        global $wpdb;
        
        // Update status to processing
        $wpdb->update(
            $this->table_name,
            [
                'status' => 'processing',
                'started_at' => current_time('mysql'),
                'attempts' => $item->attempts + 1
            ],
            ['id' => $item->id],
            ['%s', '%s', '%d'],
            ['%d']
        );

        try {
            $product_ids = maybe_unserialize($item->product_ids);
            $merger = new \WS2V\Product\ProductMerger();
            
            // Check if products can be merged
            $can_merge = $merger->can_merge_products($product_ids);
            if (is_wp_error($can_merge)) {
                throw new \Exception($can_merge->get_error_message());
            }

            // Create variable product
            $variable_product_id = $merger->create_variable_product($product_ids);
            if (is_wp_error($variable_product_id)) {
                throw new \Exception($variable_product_id->get_error_message());
            }

            // Update stats
            $stats = get_option('ws2v_stats', [
                'processed' => 0,
                'created' => 0,
                'failed' => 0
            ]);
            $stats['processed'] += count($product_ids);
            $stats['created']++;
            update_option('ws2v_stats', $stats);

            // Mark as completed
            $wpdb->update(
                $this->table_name,
                [
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ],
                ['id' => $item->id],
                ['%s', '%s'],
                ['%d']
            );

            // Log success
            $this->log_message(sprintf(
                __('Queue item #%d: Successfully created variable product #%d from %d products', 'woo-single2variable'),
                $item->id,
                $variable_product_id,
                count($product_ids)
            ));

        } catch (\Exception $e) {
            // Update stats
            $stats = get_option('ws2v_stats', ['failed' => 0]);
            $stats['failed']++;
            update_option('ws2v_stats', $stats);

            // Mark as failed if max attempts reached
            $status = $item->attempts >= 3 ? 'failed' : 'pending';
            
            $wpdb->update(
                $this->table_name,
                [
                    'status' => $status,
                    'error_message' => $e->getMessage()
                ],
                ['id' => $item->id],
                ['%s', '%s'],
                ['%d']
            );

            // Log error
            $this->log_message(
                sprintf(
                    __('Queue item #%d failed: %s', 'woo-single2variable'),
                    $item->id,
                    $e->getMessage()
                ),
                'error'
            );
        }
    }

    /**
     * Get queue stats
     *
     * @return array
     */
    public function get_queue_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
            FROM {$this->table_name}
            GROUP BY status",
            ARRAY_A
        );
        
        $formatted = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];
        
        foreach ($stats as $stat) {
            $formatted[$stat['status']] = (int)$stat['count'];
        }
        
        return $formatted;
    }

    /**
     * Clear completed and failed items
     *
     * @param int $days_old Items older than this many days
     */
    public function cleanup_queue($days_old = 7) {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name}
                WHERE status IN ('completed', 'failed')
                AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_old
            )
        );
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
}
