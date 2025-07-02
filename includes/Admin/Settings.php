<?php
namespace WS2V\Admin;

class Settings {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Single to Variable', 'woo-single2variable'),
            __('Single to Variable', 'woo-single2variable'),
            'manage_woocommerce',
            'woo-single2variable-settings',
            [$this, 'render_settings'],
            'dashicons-randomize',
            56
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
