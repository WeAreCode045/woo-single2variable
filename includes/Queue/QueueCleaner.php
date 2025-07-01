<?php
namespace WS2V\Queue;

class QueueCleaner {
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
        
        // Schedule daily cleanup
        add_action('init', [$this, 'schedule_cleanup']);
        add_action('ws2v_daily_cleanup', [$this, 'auto_cleanup']);
        
        // Add cleanup button to admin
        add_action('ws2v_after_dashboard_controls', [$this, 'render_cleanup_button']);
        add_action('wp_ajax_ws2v_cleanup_queue', [$this, 'handle_manual_cleanup']);
    }

    /**
     * Schedule daily cleanup
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('ws2v_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ws2v_daily_cleanup');
        }
    }

    /**
     * Automatic daily cleanup
     */
    public function auto_cleanup() {
        $this->cleanup_completed(7); // Clean completed items older than 7 days
        $this->cleanup_failed(30);   // Clean failed items older than 30 days
        $this->cleanup_stuck(24);    // Clean stuck items older than 24 hours
    }

    /**
     * Clean completed items
     *
     * @param int $days_old
     * @return int Number of items cleaned
     */
    public function cleanup_completed($days_old) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "DELETE FROM {$this->table_name}
            WHERE status = 'completed'
            AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_old
        );
        
        return $wpdb->query($query);
    }

    /**
     * Clean failed items
     *
     * @param int $days_old
     * @return int Number of items cleaned
     */
    public function cleanup_failed($days_old) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "DELETE FROM {$this->table_name}
            WHERE status = 'failed'
            AND attempts >= 3
            AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_old
        );
        
        return $wpdb->query($query);
    }

    /**
     * Clean stuck items
     *
     * @param int $hours_old
     * @return int Number of items cleaned
     */
    public function cleanup_stuck($hours_old) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "UPDATE {$this->table_name}
            SET status = 'failed',
                error_message = %s
            WHERE status = 'processing'
            AND started_at < DATE_SUB(NOW(), INTERVAL %d HOUR)",
            __('Processing timed out', 'woo-single2variable'),
            $hours_old
        );
        
        return $wpdb->query($query);
    }

    /**
     * Handle manual cleanup request
     */
    public function handle_manual_cleanup() {
        check_ajax_referer('ws2v_ajax_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $type = isset($_POST['cleanup_type']) ? sanitize_text_field($_POST['cleanup_type']) : 'all';
        $count = 0;

        switch ($type) {
            case 'completed':
                $count = $this->cleanup_completed(0); // Clean all completed items
                break;
            case 'failed':
                $count = $this->cleanup_failed(0); // Clean all failed items
                break;
            case 'stuck':
                $count = $this->cleanup_stuck(24); // Clean stuck items older than 24 hours
                break;
            case 'all':
                $count += $this->cleanup_completed(0);
                $count += $this->cleanup_failed(0);
                $count += $this->cleanup_stuck(24);
                break;
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Cleaned up %d queue items', 'woo-single2variable'),
                $count
            )
        ]);
    }

    /**
     * Render cleanup button
     */
    public function render_cleanup_button() {
        ?>
        <div class="ws2v-cleanup-controls">
            <select id="ws2v-cleanup-type">
                <option value="all"><?php echo esc_html__('All Items', 'woo-single2variable'); ?></option>
                <option value="completed"><?php echo esc_html__('Completed Items', 'woo-single2variable'); ?></option>
                <option value="failed"><?php echo esc_html__('Failed Items', 'woo-single2variable'); ?></option>
                <option value="stuck"><?php echo esc_html__('Stuck Items', 'woo-single2variable'); ?></option>
            </select>
            <button class="button" id="ws2v-cleanup-queue">
                <?php echo esc_html__('Clean Queue', 'woo-single2variable'); ?>
            </button>
        </div>
        <?php
    }
}
