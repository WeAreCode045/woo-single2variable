<?php
namespace WS2V\Admin;

class Settings {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Single to Variable', 'woo-single2variable'),
            __('Single to Variable', 'woo-single2variable'),
            'manage_woocommerce',
            'woo-single2variable',
            [$this, 'render_dashboard'],
            'dashicons-randomize',
            56
        );

        add_submenu_page(
            'woo-single2variable',
            __('Dashboard', 'woo-single2variable'),
            __('Dashboard', 'woo-single2variable'),
            'manage_woocommerce',
            'woo-single2variable',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'woo-single2variable',
            __('Settings', 'woo-single2variable'),
            __('Settings', 'woo-single2variable'),
            'manage_woocommerce',
            'woo-single2variable-settings',
            [$this, 'render_settings']
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('ws2v_settings', 'ws2v_settings');

        // General Settings
        add_settings_section(
            'ws2v_general_settings',
            __('General Settings', 'woo-single2variable'),
            null,
            'ws2v_settings'
        );

        // Title Similarity Threshold
        add_settings_field(
            'ws2v_title_similarity',
            __('Title Similarity Threshold (%)', 'woo-single2variable'),
            [$this, 'render_number_field'],
            'ws2v_settings',
            'ws2v_general_settings',
            [
                'label_for' => 'ws2v_title_similarity',
                'min' => 0,
                'max' => 100,
                'default' => 80
            ]
        );

        // AI Provider Settings
        add_settings_section(
            'ws2v_ai_settings',
            __('AI Provider Settings', 'woo-single2variable'),
            null,
            'ws2v_settings'
        );

        // OpenAI API Key
        add_settings_field(
            'ws2v_openai_api_key',
            __('OpenAI API Key', 'woo-single2variable'),
            [$this, 'render_password_field'],
            'ws2v_settings',
            'ws2v_ai_settings',
            ['label_for' => 'ws2v_openai_api_key']
        );

        // Default AI Provider
        add_settings_field(
            'ws2v_default_ai_provider',
            __('Default AI Provider', 'woo-single2variable'),
            [$this, 'render_select_field'],
            'ws2v_settings',
            'ws2v_ai_settings',
            [
                'label_for' => 'ws2v_default_ai_provider',
                'options' => [
                    'openai' => 'OpenAI',
                    'claude' => 'Claude',
                    'gemini' => 'Gemini'
                ]
            ]
        );
    }

    /**
     * Render dashboard page
     */
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['toplevel_page_woo-single2variable', 'single-to-variable_page_woo-single2variable-settings'])) {
            return;
        }

        // Enqueue Select2
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery']);

        // Enqueue our scripts and styles
        wp_enqueue_style('ws2v-admin', WS2V_PLUGIN_URL . 'assets/css/admin.css', [], WS2V_VERSION);
        wp_enqueue_script('ws2v-admin', WS2V_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'select2'], WS2V_VERSION, true);

        // Localize script
        wp_localize_script('ws2v-admin', 'ws2v_ajax', [
            'nonce' => wp_create_nonce('ws2v_ajax_nonce')
        ]);
    }

    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Single to Variable Dashboard', 'woo-single2variable'); ?></h1>
            
            <div class="ws2v-product-selection">
                <h2><?php echo esc_html__('Select Products to Merge', 'woo-single2variable'); ?></h2>
                <select id="ws2v-product-select" style="width: 100%;" multiple="multiple"></select>
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

    /**
     * Render settings page
     */
    public function render_settings() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Single to Variable Settings', 'woo-single2variable'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('ws2v_settings');
                do_settings_sections('ws2v_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render number field
     */
    public function render_number_field($args) {
        $options = get_option('ws2v_settings');
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : $args['default'];
        ?>
        <input
            type="number"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="ws2v_settings[<?php echo esc_attr($args['label_for']); ?>]"
            value="<?php echo esc_attr($value); ?>"
            min="<?php echo esc_attr($args['min']); ?>"
            max="<?php echo esc_attr($args['max']); ?>"
        >
        <?php
    }

    /**
     * Render password field
     */
    public function render_password_field($args) {
        $options = get_option('ws2v_settings');
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        ?>
        <input
            type="password"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="ws2v_settings[<?php echo esc_attr($args['label_for']); ?>]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
        >
        <?php
    }

    /**
     * Render select field
     */
    public function render_select_field($args) {
        $options = get_option('ws2v_settings');
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        ?>
        <select
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="ws2v_settings[<?php echo esc_attr($args['label_for']); ?>]"
        >
            <?php foreach ($args['options'] as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
