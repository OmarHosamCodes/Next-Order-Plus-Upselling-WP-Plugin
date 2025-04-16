<?php
/**
 * Admin Service
 *
 * Handles admin interface, settings page, and customization options
 *
 * @package NextOrderPlus
 * @since 1.0.0
 */

namespace NOP\Services;

use NOP\Base\NOP_Base;
use NOP\Util\NOP_Logger;

/**
 * Admin Service class
 */
class NOP_Admin_Service extends NOP_Base
{
    /**
     * Settings page slug
     *
     * @var string
     */
    private $page_slug;

    /**
     * Settings group name
     *
     * @var string
     */
    private $settings_group;

    /**
     * Settings option name in database
     *
     * @var string
     */
    private $option_name;

    /**
     * Constructor
     *
     * Sets up admin properties
     *
     * @param NOP_Logger|null $logger Optional logger instance
     */
    public function __construct($logger = null)
    {
        parent::__construct($logger);
        $this->page_slug = $this->prefix . 'settings';
        $this->settings_group = $this->prefix . 'settings_group';
        $this->option_name = $this->prefix . 'options';
    }

    /**
     * Initialize the service
     *
     * Sets up hooks for admin menus and settings
     *
     * @return void
     */
    public function init(): void
    {
        // Only load admin functionality in admin area
        if (!is_admin()) {
            return;
        }

        // Add admin menus
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_menu', [$this, 'add_woocommerce_submenu'], 70);

        // Register settings
        add_action('admin_init', [$this, 'register_settings']);

        // Add settings link on plugins page
        add_filter('plugin_action_links_next-order-plus/next-order-plus.php', [$this, 'add_plugin_action_links']);

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        $this->log('Admin service initialized');
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix The current admin page
     * @return void
     */
    public function enqueue_admin_assets(string $hook_suffix): void
    {
        // Only load on our settings page
        if (strpos($hook_suffix, $this->page_slug) === false) {
            return;
        }

        // Enqueue admin stylesheet
        wp_enqueue_style(
            $this->prefix . 'admin_styles',
            $this->plugin_url . 'assets/css/nop-admin.css',
            [],
            defined('NOP_VERSION') ? NOP_VERSION : '1.0.0'
        );
    }

    /**
     * Add submenu under "SOM Plugins" if it exists
     *
     * @return void
     */
    public function add_admin_menu(): void
    {
        // Check if "SOM Plugins" menu exists
        global $menu;
        $som_menu_exists = false;

        foreach ($menu as $item) {
            if (isset($item[0]) && $item[0] === 'SOM Plugins') {
                $som_menu_exists = true;
                break;
            }
        }

        if ($som_menu_exists) {
            // Add as submenu to SOM Plugins
            add_submenu_page(
                'som-plugins',
                __('Next Order Plus', 'next-order-plus'),
                __('Next Order Plus', 'next-order-plus'),
                'manage_options',
                $this->page_slug,
                [$this, 'render_settings_page']
            );
        } else {
            // Load custom icon if available
            $menu_icon = 'dashicons-cart';

            // Create as standalone menu
            add_menu_page(
                __('Next Order Plus', 'next-order-plus'),
                __('Next Order Plus', 'next-order-plus'),
                'manage_options',
                $this->page_slug,
                [$this, 'render_settings_page'],
                $menu_icon,
                58
            );

            // Add settings submenu to match the parent menu name for better UX
            add_submenu_page(
                $this->page_slug,
                __('Settings', 'next-order-plus'),
                __('Settings', 'next-order-plus'),
                'manage_options',
                $this->page_slug,
                [$this, 'render_settings_page']
            );
        }
    }

    /**
     * Add submenu under WooCommerce menu
     *
     * @return void
     */
    public function add_woocommerce_submenu(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_submenu_page(
            'woocommerce',
            __('Next Order Plus', 'next-order-plus'),
            __('Next Order Plus', 'next-order-plus'),
            'manage_woocommerce',
            $this->page_slug,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     *
     * @return void
     */
    public function register_settings(): void
    {
        // Register setting
        register_setting(
            $this->settings_group,
            $this->option_name,
            [$this, 'sanitize_settings']
        );

        // Add settings section
        add_settings_section(
            $this->prefix . 'general_section',
            __('Coupon Restrictions', 'next-order-plus'),
            [$this, 'render_general_section'],
            $this->page_slug
        );

        // Add only excluded coupons field
        add_settings_field(
            $this->prefix . 'excluded_coupons',
            __('Excluded Coupon Codes', 'next-order-plus'),
            [$this, 'render_excluded_coupons_field'],
            $this->page_slug,
            $this->prefix . 'general_section'
        );
    }

    /**
     * Sanitize settings before saving
     *
     * @param array $input Raw settings input
     * @return array Sanitized settings
     */
    public function sanitize_settings(array $input): array
    {
        $sanitized = [];

        // Keep defaults for all other settings that were removed
        $options = $this->get_options();

        // Only sanitize excluded coupons
        if (isset($input['excluded_coupons'])) {
            $coupons = explode(',', $input['excluded_coupons']);
            $sanitized_coupons = [];

            foreach ($coupons as $coupon) {
                $trimmed = trim($coupon);
                if (!empty($trimmed)) {
                    $sanitized_coupons[] = sanitize_text_field($trimmed);
                }
            }

            $sanitized['excluded_coupons'] = implode(',', $sanitized_coupons);
        } else {
            $sanitized['excluded_coupons'] = '';
        }

        return $sanitized;
    }

    /**
     * Render general section description
     *
     * @return void
     */
    public function render_general_section(): void
    {
        echo '<p>' . esc_html__('Configure which coupon codes cannot be used with the promotion.', 'next-order-plus') . '</p>';
    }

    /**
     * Render excluded coupons field
     *
     * @return void
     */
    public function render_excluded_coupons_field(): void
    {
        $options = $this->get_options();
        $value = isset($options['excluded_coupons']) ? $options['excluded_coupons'] : 'gtre50,abon-150';

        echo '<textarea name="' . esc_attr($this->option_name) . '[excluded_coupons]" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Comma-separated list of coupon codes that cannot be used with this promotion.', 'next-order-plus') . '</p>';
    }

    /**
     * Render the settings page
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        $options = $this->get_options();
        $rules_page_url = admin_url('admin.php?page=' . $this->prefix . 'rules');

        // Admin page wrapper
        ?>
        <div class="wrap nop-settings-wrap">
            <div class="nop-header">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="nop-header-actions">
                    <a href="<?php echo esc_url($rules_page_url); ?>" class="button button-primary">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e('Manage Rules', 'next-order-plus'); ?>
                    </a>
                </div>
            </div>

            <?php if (!class_exists('WooCommerce')): ?>
                <div class="nop-notice nop-notice-error">
                    <span class="dashicons dashicons-warning"></span>
                    <p><?php esc_html_e('WooCommerce is not active. This promotion requires WooCommerce to function.', 'next-order-plus'); ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="nop-notice nop-notice-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php esc_html_e('The "Next Order Plus Upselling Features" promotion is active on your store.', 'next-order-plus'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="nop-container">
                <main class="nop-main">
                    <div class="nop-card nop-card-primary">
                        <div class="nop-card-header">
                            <h2><?php esc_html_e('Active Promotion Category', 'next-order-plus'); ?></h2>
                            <p><?php esc_html_e('The currently active promotion and its associated rules.', 'next-order-plus'); ?>
                            </p>
                        </div>
                        <div class="nop-card-content">
                            <?php $this->render_active_category_and_rules(); ?>
                        </div>
                    </div>

                    <div class="nop-card">
                        <div class="nop-card-header">
                            <h2><?php esc_html_e('Coupon Restrictions', 'next-order-plus'); ?></h2>
                            <p><?php esc_html_e('Configure which coupon codes cannot be used together with this promotion.', 'next-order-plus'); ?>
                            </p>
                        </div>
                        <div class="nop-card-content">
                            <form method="post" action="options.php">
                                <?php
                                // Output security fields
                                settings_fields($this->settings_group);

                                // Custom form field rendering
                                ?>
                                <div class="nop-field">
                                    <label
                                        for="excluded-coupons"><?php esc_html_e('Excluded Coupon Codes', 'next-order-plus'); ?></label>
                                    <textarea id="excluded-coupons"
                                        name="<?php echo esc_attr($this->option_name); ?>[excluded_coupons]" rows="3"
                                        placeholder="<?php esc_attr_e('Enter coupon codes separated by commas', 'next-order-plus'); ?>"><?php echo esc_textarea(isset($options['excluded_coupons']) ? $options['excluded_coupons'] : 'gtre50,abon-150'); ?></textarea>
                                    <p class="nop-field-description">
                                        <?php esc_html_e('Comma-separated list of coupon codes that cannot be used with this promotion.', 'next-order-plus'); ?>
                                    </p>
                                </div>

                                <div class="nop-form-actions">
                                    <?php submit_button(__('Save Settings', 'next-order-plus'), 'primary nop-button', 'submit', false); ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="nop-card">
                        <div class="nop-card-header">
                            <h2><?php esc_html_e('Current Settings', 'next-order-plus'); ?></h2>
                        </div>
                        <div class="nop-card-content">
                            <div class="nop-fields-grid">
                                <div class="nop-field nop-field-readonly">
                                    <label><?php esc_html_e('Debug Mode', 'next-order-plus'); ?></label>
                                    <div class="nop-toggle-readonly">
                                        <span
                                            class="nop-toggle-slider <?php echo $options['debug_mode'] ? 'active' : ''; ?>"></span>
                                        <span
                                            class="nop-toggle-text"><?php echo $options['debug_mode'] ? esc_html__('Enabled', 'next-order-plus') : esc_html__('Disabled', 'next-order-plus'); ?></span>
                                    </div>
                                    <p class="nop-field-description">
                                        <?php esc_html_e('Enable detailed logging for troubleshooting.', 'next-order-plus'); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="nop-action-note">
                                <span class="dashicons dashicons-info"></span>
                                <p><?php esc_html_e('These settings can be managed via upsell rules. Visit the Rules page to configure them.', 'next-order-plus'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </main>

                <aside class="nop-sidebar">
                    <div class="nop-card">
                        <div class="nop-card-header">
                            <h2><?php esc_html_e('About This Plugin', 'next-order-plus'); ?></h2>
                        </div>
                        <div class="nop-card-content">
                            <div class="nop-card-meta">
                                <div class="nop-meta-item">
                                    <span class="dashicons dashicons-tag"></span>
                                    <span><strong><?php esc_html_e('Version', 'next-order-plus'); ?>:</strong>
                                        <?php echo esc_html(NOP_VERSION); ?></span>
                                </div>
                                <div class="nop-meta-item">
                                    <span class="dashicons dashicons-admin-plugins"></span>
                                    <span><strong><?php esc_html_e('Status', 'next-order-plus'); ?>:</strong>
                                        <?php echo class_exists('WooCommerce') ? esc_html__('Active', 'next-order-plus') : esc_html__('Inactive', 'next-order-plus'); ?>
                                    </span>
                                </div>
                            </div>
                            <p><?php esc_html_e('Next Order Plus automatically applies a "Next Order Plus Upselling Features" promotional discount to your WooCommerce store.', 'next-order-plus'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="nop-card">
                        <div class="nop-card-header">
                            <h2><?php esc_html_e('How It Works', 'next-order-plus'); ?></h2>
                        </div>
                        <div class="nop-card-content">
                            <div class="nop-steps">
                                <div class="nop-step">
                                    <span class="nop-step-number">1</span>
                                    <p><?php esc_html_e('Customer adds 4 or more items to their cart', 'next-order-plus'); ?>
                                    </p>
                                </div>
                                <div class="nop-step">
                                    <span class="nop-step-number">2</span>
                                    <p><?php esc_html_e('The plugin identifies the cheapest item', 'next-order-plus'); ?></p>
                                </div>
                                <div class="nop-step">
                                    <span class="nop-step-number">3</span>
                                    <p><?php esc_html_e('A discount equal to the cheapest item\'s price is applied', 'next-order-plus'); ?>
                                    </p>
                                </div>
                                <div class="nop-step">
                                    <span class="nop-step-number">4</span>
                                    <p><?php esc_html_e('For every 4 items, an additional free item discount is applied', 'next-order-plus'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="nop-card">
                        <div class="nop-card-header">
                            <h2><?php esc_html_e('Quick Links', 'next-order-plus'); ?></h2>
                        </div>
                        <div class="nop-card-content">
                            <div class="nop-links">
                                <a href="<?php echo esc_url($rules_page_url); ?>" class="nop-link">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <?php esc_html_e('Manage Promotion Rules', 'next-order-plus'); ?>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings')); ?>" class="nop-link">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                    <?php esc_html_e('WooCommerce Settings', 'next-order-plus'); ?>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-reports&tab=orders')); ?>"
                                    class="nop-link">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <?php esc_html_e('WooCommerce Order Reports', 'next-order-plus'); ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="nop-card">
                        <div class="nop-card-header">
                            <h2><?php esc_html_e('Need Help?', 'next-order-plus'); ?></h2>
                        </div>
                        <div class="nop-card-content">
                            <p><?php esc_html_e('If you have any questions or need assistance with this plugin, please contact the developer.', 'next-order-plus'); ?>
                            </p>
                            <p><?php esc_html_e('For issues or feature requests, please include your WordPress and WooCommerce versions.', 'next-order-plus'); ?>
                            </p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
        <?php
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_plugin_action_links(array $links): array
    {
        $rules_link = '<a href="' . admin_url('admin.php?page=' . $this->prefix . 'rules') . '">' . __('Rules', 'next-order-plus') . '</a>';
        $settings_link = '<a href="' . admin_url('admin.php?page=' . $this->page_slug) . '">' . __('Settings', 'next-order-plus') . '</a>';

        // Add rules link first, then settings link
        array_unshift($links, $settings_link);
        array_unshift($links, $rules_link);

        return $links;
    }

    /**
     * Get plugin options with defaults
     *
     * @return array Plugin options
     */
    public function get_options(): array
    {
        $defaults = [
            'excluded_coupons' => 'gtre50,abon-150',
            'debug_mode' => false,
        ];

        $options = get_option($this->option_name, []);
        return wp_parse_args($options, $defaults);
    }

    /**
     * Render active category and its rules
     *
     * Displays information about the currently active promotion category and rules
     *
     * @since 1.1.0
     * @return void
     */
    public function render_active_category_and_rules(): void
    {
        // Attempt to get the rules manager instance from the main plugin class
        $plugin_instance = \NOP_Plugin::get_instance();
        $rules_manager = null;

        if (method_exists($plugin_instance, 'get_rules_manager')) {
            $rules_manager = $plugin_instance->get_rules_manager();
        }

        if (!$rules_manager) {
            ?>
            <div class="nop-notice nop-notice-warning">
                <span class="dashicons dashicons-warning"></span>
                <p><?php esc_html_e('Could not retrieve rules manager. Please ensure the plugin is correctly installed.', 'next-order-plus'); ?>
                </p>
            </div>
            <?php
            return;
        }

        // Get all rules
        $rules = $rules_manager->get_rules();

        // Find active category and rules
        $active_category = '';
        $active_rules = [];

        foreach ($rules as $rule) {
            if ($rule->is_active() && !empty($rule->get_category())) {
                $active_category = $rule->get_category();
                break;
            }
        }

        // Now get all rules in the active category
        if (!empty($active_category)) {
            foreach ($rules as $rule) {
                if ($rule->get_category() === $active_category) {
                    $active_rules[] = $rule;
                }
            }
        }

        if (empty($active_category)) {
            ?>
            <div class="nop-notice nop-notice-info">
                <span class="dashicons dashicons-info"></span>
                <p><?php esc_html_e('No active promotion category found. Visit the Rules page to set up and activate promotion rules.', 'next-order-plus'); ?>
                </p>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->prefix . 'rules')); ?>" class="button button-primary">
                <?php esc_html_e('Set Up Rules', 'next-order-plus'); ?>
            </a>
            <?php
            return;
        }
        ?>

        <div class="nop-active-category">
            <h3 class="nop-active-category-name">
                <span class="nop-badge"><?php esc_html_e('Active', 'next-order-plus'); ?></span>
                <?php echo esc_html(ucfirst($active_category)); ?>
            </h3>

            <div class="nop-active-rules">
                <h4><?php esc_html_e('Active Rules in this Category', 'next-order-plus'); ?>:</h4>

                <?php if (empty($active_rules)): ?>
                    <p><?php esc_html_e('No rules found in this category.', 'next-order-plus'); ?></p>
                <?php else: ?>
                    <ul class="nop-active-rules-list">
                        <?php foreach ($active_rules as $rule): ?>
                            <li>
                                <span class="nop-rule-name"><?php echo esc_html($rule->get_name()); ?></span>

                                <div class="nop-rule-details">
                                    <div class="nop-rule-detail">
                                        <strong><?php esc_html_e('Description', 'next-order-plus'); ?>:</strong>
                                        <?php echo esc_html($rule->get_description()); ?>
                                    </div>
                                    <div class="nop-rule-detail">
                                        <strong><?php esc_html_e('Condition', 'next-order-plus'); ?>:</strong>
                                        <?php
                                        if (method_exists($rules_manager, 'get_condition_label')) {
                                            echo esc_html($rules_manager->get_condition_label($rule->get_condition_type()));
                                        } else {
                                            echo esc_html($rule->get_condition_type());
                                        }
                                        ?>
                                    </div>
                                    <div class="nop-rule-detail">
                                        <strong><?php esc_html_e('Action', 'next-order-plus'); ?>:</strong>
                                        <?php
                                        if (method_exists($rules_manager, 'get_action_label')) {
                                            echo esc_html($rules_manager->get_action_label($rule->get_action_type()));
                                        } else {
                                            echo esc_html($rule->get_action_type());
                                        }
                                        ?>
                                    </div>
                                    <div class="nop-rule-detail">
                                        <strong><?php esc_html_e('Priority', 'next-order-plus'); ?>:</strong>
                                        <?php echo esc_html($rule->get_priority()); ?>
                                    </div>
                                </div>

                                <span class="nop-rule-status active"><?php esc_html_e('Active', 'next-order-plus'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="nop-category-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->prefix . 'rules')); ?>" class="button">
                        <?php esc_html_e('Manage All Rules', 'next-order-plus'); ?>
                    </a>
                </div>
            </div>
        </div>

        <?php
    }
}