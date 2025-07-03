<?php
namespace WS2V\Admin;

class Dashboard {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['toplevel_page_woo-single2variable', 'single-to-variable_page_woo-single2variable'])) {
            return;
        }
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery']);
        wp_enqueue_style('ws2v-admin', WS2V_PLUGIN_URL . 'assets/css/admin.css', [], WS2V_VERSION);
        wp_enqueue_script('ws2v-admin', WS2V_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'select2'], WS2V_VERSION, true);
        wp_localize_script('ws2v-admin', 'ws2v_ajax', [
            'nonce' => wp_create_nonce('ws2v_ajax_nonce')
        ]);
    }

    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Single to Variable Dashboard', 'woo-single2variable'); ?></h1>        
	        <!-- Add explanation instead of product selection -->
	        <div class="notice notice-info">
	            <p><?php echo esc_html__('This process will automatically analyze and merge all simple products in your store based on AI-powered similarity analysis. Products will be grouped by category, brand, and title similarity.', 'woo-single2variable'); ?></p>
	        </div>
	        
            <div class="ws2v-stats-container">
                <div class="ws2v-stat-box">
                    <h3><?php echo esc_html__('Processed Products', 'woo-single2variable'); ?></h3>
                    <div class="ws2v-stat-value" id="ws2v-processed-count">0</div>
                </div>
                <div class="ws2v-stat-box">
                    <h3><?php echo esc_html__('Created Variables', 'woo-single2variable'); ?></h3>
                    <div class="ws2v-stat-value" id="ws2v-created-count">0</div>
                </div>
                <div class="ws2v-stat-box">
                    <h3><?php echo esc_html__('Failed', 'woo-single2variable'); ?></h3>
                    <div class="ws2v-stat-value" id="ws2v-failed-count">0</div>
                </div>
            </div>
            <div class="ws2v-queue-status">
                <h2><?php echo esc_html__('Queue Status', 'woo-single2variable'); ?></h2>
                <div class="ws2v-progress-bar-container">
                    <div class="ws2v-progress-bar" id="ws2v-progress-bar"></div>
                </div>
                <div class="ws2v-queue-stats">
                    <div class="ws2v-queue-stat">
                        <span class="ws2v-queue-label"><?php echo esc_html__('Pending', 'woo-single2variable'); ?></span>
                        <span class="ws2v-queue-value" id="ws2v-queue-pending">0</span>
                    </div>
                    <div class="ws2v-queue-stat">
                        <span class="ws2v-queue-label"><?php echo esc_html__('Processing', 'woo-single2variable'); ?></span>
                        <span class="ws2v-queue-value" id="ws2v-queue-processing">0</span>
                    </div>
                    <div class="ws2v-queue-stat">
                        <span class="ws2v-queue-label"><?php echo esc_html__('Completed', 'woo-single2variable'); ?></span>
                        <span class="ws2v-queue-value" id="ws2v-queue-completed">0</span>
                    </div>
                    <div class="ws2v-queue-stat">
                        <span class="ws2v-queue-label"><?php echo esc_html__('Failed', 'woo-single2variable'); ?></span>
                        <span class="ws2v-queue-value" id="ws2v-queue-failed">0</span>
                    </div>
                </div>
            </div>
            <div class="ws2v-controls">
                <button class="button button-primary" id="ws2v-start-process">
                    <?php echo esc_html__('Start Processing', 'woo-single2variable'); ?>
                </button>
                <button class="button" id="ws2v-stop-process" style="display: none;">
                    <?php echo esc_html__('Stop Processing', 'woo-single2variable'); ?>
                </button>
                <?php do_action('ws2v_after_dashboard_controls'); ?>
            </div>
            <div class="ws2v-log-container">
                <h3><?php echo esc_html__('Process Log', 'woo-single2variable'); ?></h3>
                <div id="ws2v-log"></div>
            </div>
        </div>
        <?php
    }
}
