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
            // require_once $this->plugin_path . 'assets/images/icon.svg';
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
            __('General Settings', 'next-order-plus'),
            [$this, 'render_general_section'],
            $this->page_slug
        );

        // Add settings fields
        add_settings_field(
            $this->prefix . 'discount_label',
            __('Discount Label', 'next-order-plus'),
            [$this, 'render_discount_label_field'],
            $this->page_slug,
            $this->prefix . 'general_section'
        );

        add_settings_field(
            $this->prefix . 'min_items',
            __('Minimum Items for Discount', 'next-order-plus'),
            [$this, 'render_min_items_field'],
            $this->page_slug,
            $this->prefix . 'general_section'
        );

        add_settings_field(
            $this->prefix . 'excluded_coupons',
            __('Excluded Coupon Codes', 'next-order-plus'),
            [$this, 'render_excluded_coupons_field'],
            $this->page_slug,
            $this->prefix . 'general_section'
        );

        add_settings_field(
            $this->prefix . 'disable_free_shipping',
            __('Disable Free Shipping', 'next-order-plus'),
            [$this, 'render_disable_free_shipping_field'],
            $this->page_slug,
            $this->prefix . 'general_section'
        );

        add_settings_field(
            $this->prefix . 'debug_mode',
            __('Debug Mode', 'next-order-plus'),
            [$this, 'render_debug_mode_field'],
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

        // Sanitize discount label
        $sanitized['discount_label'] = isset($input['discount_label'])
            ? sanitize_text_field($input['discount_label'])
            : __('Discount: 2025 Promotion', 'next-order-plus');

        // Sanitize minimum items
        $sanitized['min_items'] = isset($input['min_items'])
            ? absint($input['min_items'])
            : 4;

        // Make sure min_items is at least 2
        $sanitized['min_items'] = max(2, $sanitized['min_items']);

        // Sanitize excluded coupons
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

        // Sanitize boolean options
        $sanitized['disable_free_shipping'] = !empty($input['disable_free_shipping']);
        $sanitized['debug_mode'] = !empty($input['debug_mode']);

        return $sanitized;
    }

    /**
     * Render general section description
     *
     * @return void
     */
    public function render_general_section(): void
    {
        echo '<p>' . esc_html__('Configure the "Next Order Plus Upselling Features" promotion settings.', 'next-order-plus') . '</p>';
    }

    /**
     * Render discount label field
     *
     * @return void
     */
    public function render_discount_label_field(): void
    {
        $options = $this->get_options();
        $value = isset($options['discount_label']) ? $options['discount_label'] : __('Discount: 2025 Promotion', 'next-order-plus');

        echo '<input type="text" name="' . esc_attr($this->option_name) . '[discount_label]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('This text will appear in the cart and checkout pages.', 'next-order-plus') . '</p>';
    }

    /**
     * Render minimum items field
     *
     * @return void
     */
    public function render_min_items_field(): void
    {
        $options = $this->get_options();
        $value = isset($options['min_items']) ? absint($options['min_items']) : 4;

        echo '<input type="number" name="' . esc_attr($this->option_name) . '[min_items]" value="' . esc_attr($value) . '" class="small-text" min="2">';
        echo '<p class="description">' . esc_html__('Minimum number of items required for the discount to apply.', 'next-order-plus') . '</p>';
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
     * Render disable free shipping field
     *
     * @return void
     */
    public function render_disable_free_shipping_field(): void
    {
        $options = $this->get_options();
        $checked = isset($options['disable_free_shipping']) && $options['disable_free_shipping'] ? 'checked' : '';

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($this->option_name) . '[disable_free_shipping]" value="1" ' . $checked . '>';
        echo ' ' . esc_html__('Disable free shipping when discount is applied', 'next-order-plus');
        echo '</label>';
    }

    /**
     * Render debug mode field
     *
     * @return void
     */
    public function render_debug_mode_field(): void
    {
        $options = $this->get_options();
        $checked = isset($options['debug_mode']) && $options['debug_mode'] ? 'checked' : '';

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($this->option_name) . '[debug_mode]" value="1" ' . $checked . '>';
        echo ' ' . esc_html__('Enable debug logging', 'next-order-plus');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Logs will be stored in wp-content/debug-logs/nop-debug.log', 'next-order-plus') . '</p>';
    }

    /**
     * Render the settings page
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get plugin options
        $options = $this->get_options();

        // Get statistics
        $stats = $this->get_discount_statistics();

        // Admin page wrapper
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="nop-admin-content">
                <div class="nop-admin-main">
                    <!-- Status overview -->
                    <div class="nop-admin-box">
                        <h3><?php esc_html_e('Promotion Status', 'next-order-plus'); ?></h3>
                        <p>
                            <?php if (class_exists('WooCommerce')): ?>
                                <span class="nop-status nop-status-active">
                                    <?php esc_html_e('Active', 'next-order-plus'); ?>
                                </span>
                                <?php esc_html_e('The "Next Order Plus Upselling Features" promotion is active on your store.', 'next-order-plus'); ?>
                            <?php else: ?>
                                <span class="nop-status nop-status-error">
                                    <?php esc_html_e('Inactive', 'next-order-plus'); ?>
                                </span>
                                <?php esc_html_e('WooCommerce is not active. This promotion requires WooCommerce to function.', 'next-order-plus'); ?>
                            <?php endif; ?>
                        </p>

                        <?php if (!empty($stats)): ?>
                            <div class="nop-stats-grid">
                                <div class="nop-stats-card">
                                    <div class="nop-stats-value"><?php echo esc_html($stats['total_discounts']); ?></div>
                                    <div class="nop-stats-label"><?php esc_html_e('Discounts Applied', 'next-order-plus'); ?></div>
                                </div>
                                <div class="nop-stats-card">
                                    <div class="nop-stats-value"><?php echo wc_price($stats['total_amount']); ?></div>
                                    <div class="nop-stats-label"><?php esc_html_e('Total Discount Amount', 'next-order-plus'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Settings form -->
                    <div class="nop-admin-box">
                        <h3><?php esc_html_e('Promotion Settings', 'next-order-plus'); ?></h3>
                        <form method="post" action="options.php">
                            <?php
                            // Output security fields
                            settings_fields($this->settings_group);

                            // Output setting sections and fields
                            do_settings_sections($this->page_slug);

                            // Output save settings button
                            submit_button(__('Save Settings', 'next-order-plus'));
                            ?>
                        </form>
                    </div>
                </div>

                <div class="nop-admin-sidebar">
                    <div class="nop-admin-box">
                        <h3><?php esc_html_e('About This Plugin', 'next-order-plus'); ?></h3>
                        <p><?php esc_html_e('Next Order Plus automatically applies a "Next Order Plus Upselling Features" promotional discount to your WooCommerce store.', 'next-order-plus'); ?>
                        </p>
                        <p><strong><?php esc_html_e('Version', 'next-order-plus'); ?>:</strong>
                            <?php echo esc_html(NOP_VERSION); ?></p>
                        <p><strong><?php esc_html_e('Status', 'next-order-plus'); ?>:</strong>
                            <?php if ($options['debug_mode']): ?>
                                <span
                                    class="nop-status nop-status-active"><?php esc_html_e('Debug Mode On', 'next-order-plus'); ?></span>
                            <?php else: ?>
                                <span
                                    class="nop-status nop-status-inactive"><?php esc_html_e('Production Mode', 'next-order-plus'); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="nop-admin-box">
                        <h3><?php esc_html_e('How It Works', 'next-order-plus'); ?></h3>
                        <ol>
                            <li><?php echo sprintf(esc_html__('Customer adds %d or more items to their cart', 'next-order-plus'), $options['min_items']); ?>
                            </li>
                            <li><?php esc_html_e('The plugin automatically identifies the cheapest item', 'next-order-plus'); ?>
                            </li>
                            <li><?php esc_html_e('A discount equal to the cheapest item\'s price is applied', 'next-order-plus'); ?>
                            </li>
                            <li><?php echo sprintf(esc_html__('For every %d items, another discount is applied (%d items = 2 free items)', 'next-order-plus'), $options['min_items'], $options['min_items'] * 2); ?>
                            </li>
                        </ol>
                    </div>

                    <div class="nop-admin-box">
                        <h3><?php esc_html_e('Need Help?', 'next-order-plus'); ?></h3>
                        <p><?php esc_html_e('If you have any questions or need assistance with this plugin, please contact the developer.', 'next-order-plus'); ?>
                        </p>
                        <p><?php esc_html_e('For issues or feature requests, please include your WordPress and WooCommerce versions.', 'next-order-plus'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get statistics about applied discounts
     *
     * @return array Discount statistics
     */
    private function get_discount_statistics(): array
    {
        if (!class_exists('WooCommerce')) {
            return [];
        }

        global $wpdb;

        // Get orders with our discount
        $meta_key = '_' . $this->prefix . 'discount_amount';

        // Get count and sum of discounts
        $query = $wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT post_id) as total_discounts,
                SUM(meta_value) as total_amount
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value > 0",
            $meta_key
        );

        $results = $wpdb->get_row($query, ARRAY_A);

        if (!$results) {
            return [
                'total_discounts' => 0,
                'total_amount' => 0
            ];
        }

        return [
            'total_discounts' => (int) $results['total_discounts'],
            'total_amount' => (float) $results['total_amount']
        ];
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_plugin_action_links(array $links): array
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=' . $this->page_slug) . '">' . __('Settings', 'next-order-plus') . '</a>';
        array_unshift($links, $settings_link);
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
            'discount_label' => __('Discount: 2025 Promotion', 'next-order-plus'),
            'min_items' => 4,
            'excluded_coupons' => 'gtre50,abon-150',
            'disable_free_shipping' => true,
            'debug_mode' => false,
        ];

        $options = get_option($this->option_name, []);
        return wp_parse_args($options, $defaults);
    }
}