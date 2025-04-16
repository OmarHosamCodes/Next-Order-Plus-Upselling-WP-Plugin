<?php
/**
 * Rules Admin UI
 *
 * Handles rule management interface in admin
 *
 * @package NextOrderPlus
 * @since 1.1.0
 */

namespace NOP\Admin;

use NOP\Base\NOP_Base;
use NOP\Models\NOP_Rule;
use NOP\Services\NOP_Rules_Manager;
use NOP\Util\NOP_Logger;

/**
 * Rules Admin UI class
 */
class NOP_Rules_Admin extends NOP_Base
{
    /**
     * Rules manager service instance
     *
     * @var NOP_Rules_Manager
     */
    private $rules_manager;

    /**
     * Constructor
     *
     * Sets up rules admin UI
     *
     * @param NOP_Rules_Manager $rules_manager Rules manager service
     * @param NOP_Logger|null $logger Optional logger instance
     */
    public function __construct(NOP_Rules_Manager $rules_manager, $logger = null)
    {
        parent::__construct($logger);
        $this->rules_manager = $rules_manager;
    }

    /**
     * Initialize the admin interface
     *
     * Sets up hooks for rule management
     *
     * @return void
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'add_rules_submenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // AJAX handlers
        add_action('wp_ajax_' . $this->prefix . 'save_rule', [$this, 'ajax_save_rule']);
        add_action('wp_ajax_' . $this->prefix . 'delete_rule', [$this, 'ajax_delete_rule']);
        add_action('wp_ajax_' . $this->prefix . 'toggle_rule', [$this, 'ajax_toggle_rule']);

        $this->log('Rules admin initialized');
    }

    /**
     * Add rules submenu to Next Order Plus admin menu
     *
     * @return void
     */
    public function add_rules_submenu(): void
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

        // If SOM menu exists, add as submenu there
        if ($som_menu_exists) {
            add_submenu_page(
                'som-plugins',
                __('Promotion Rules', 'next-order-plus'),
                __('Promotion Rules', 'next-order-plus'),
                'manage_options',
                $this->prefix . 'rules',
                [$this, 'render_rules_page']
            );
            return;
        }

        // Check if our main plugin menu exists
        $main_menu_exists = false;
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === $this->prefix . 'settings') {
                $main_menu_exists = true;
                break;
            }
        }

        // If our main menu exists, add as submenu there
        if ($main_menu_exists) {
            // Add as submenu to our main plugin menu
            add_submenu_page(
                $this->prefix . 'settings',
                __('Promotion Rules', 'next-order-plus'),
                __('Promotion Rules', 'next-order-plus'),
                'manage_options',
                $this->prefix . 'rules',
                [$this, 'render_rules_page']
            );
            return;
        }
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page
     * @return void
     */
    public function enqueue_admin_scripts(string $hook): void
    {
        if (strpos($hook, $this->prefix . 'rules') === false) {
            return;
        }

        // Enqueue select2 if not already registered
        if (!wp_script_is('select2', 'registered')) {
            wp_register_script(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                ['jquery'],
                '4.1.0-rc.0',
                true
            );

            wp_register_style(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                [],
                '4.1.0-rc.0'
            );
        }

        // Enqueue admin scripts
        wp_enqueue_script(
            $this->prefix . 'rules_admin',
            $this->plugin_url . 'assets/js/nop-rules-admin.js',
            ['jquery', 'select2'],
            defined('NOP_VERSION') ? NOP_VERSION : '1.1.0',
            true
        );

        // Enqueue admin styles
        wp_enqueue_style(
            $this->prefix . 'rules_admin',
            $this->plugin_url . 'assets/css/nop-rules-admin.css',
            ['select2'],
            defined('NOP_VERSION') ? NOP_VERSION : '1.1.0'
        );

        // Get the correct prefix for AJAX actions
        $ajax_prefix = $this->prefix; // This is coming from NOP_Base class

        // Make sure we're logging the actual prefix
        error_log('Using AJAX prefix: ' . $ajax_prefix);

        // Localize script data - FIXING THE PREFIX ISSUE
        wp_localize_script($this->prefix . 'rules_admin', $this->prefix . 'rules_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($this->prefix . 'rules_nonce'),
            'prefix' => $ajax_prefix,
            'condition_types' => $this->rules_manager->get_condition_types(),
            'action_types' => $this->rules_manager->get_action_types(),
            'products' => $this->get_products_for_select(),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this rule?', 'next-order-plus'),
                'save_success' => __('Rule saved successfully.', 'next-order-plus'),
                'delete_success' => __('Rule deleted successfully.', 'next-order-plus'),
                'error' => __('An error occurred. Please try again.', 'next-order-plus'),
                'add_rule' => __('Add Rule', 'next-order-plus'),
                'edit_rule' => __('Edit Rule', 'next-order-plus'),
                'select_product' => __('Select a product', 'next-order-plus'),
                'min_amount' => __('Minimum Cart Amount', 'next-order-plus'),
                'min_amount_desc' => __('Minimum cart subtotal required', 'next-order-plus'),
                'min_items' => __('Minimum Items', 'next-order-plus'),
                'min_items_desc' => __('Minimum number of items required', 'next-order-plus'),
                'min_spend' => __('Minimum Spend', 'next-order-plus'),
                'product_total_desc' => __('Total spend on this product', 'next-order-plus'),
                'discount_percentage' => __('Discount Percentage', 'next-order-plus'),
                'percentage_discount_desc' => __('Percentage off cart total', 'next-order-plus'),
                'discount_amount' => __('Discount Amount', 'next-order-plus'),
                'fixed_discount_desc' => __('Fixed amount off cart total', 'next-order-plus'),
                'free_shipping_desc' => __('Enables free shipping for eligible methods', 'next-order-plus'),
                'cheapest_free_desc' => __('Makes the cheapest product in cart free', 'next-order-plus'),
                'most_expensive_free_desc' => __('Makes the most expensive product in cart free', 'next-order-plus'),
                'position' => __('Position', 'next-order-plus'),
                'nth_cheapest_free_desc' => __('Makes the Nth cheapest product in cart free', 'next-order-plus'),
                'nth_expensive_free_desc' => __('Makes the Nth most expensive product in cart free', 'next-order-plus'),
                'active' => __('Active', 'next-order-plus'),
                'inactive' => __('Inactive', 'next-order-plus'),
                'activate' => __('Activate', 'next-order-plus'),
                'deactivate' => __('Deactivate', 'next-order-plus'),
                'edit' => __('Edit', 'next-order-plus'),
                'delete' => __('Delete', 'next-order-plus'),
                'no_rules' => __('No rules found. Add your first rule above.', 'next-order-plus')
            ]
        ]);
        // Debug output to verify localization
        error_log('Script localization completed with prefix: ' . $ajax_prefix);
    }

    /**
     * Render rules management page
     *
     * @return void
     */
    public function render_rules_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'next-order-plus'));
        }

        $rules = $this->rules_manager->get_rules();
        
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

        ?>
        <div class="wrap nop-rules-wrap">
            <h1><?php echo esc_html__('Promotion Rules', 'next-order-plus'); ?></h1>

            <div class="nop-rules-header">
                <p><?php echo esc_html__('Create and manage upsell rules for your store. Rules are evaluated in priority order (lower number = higher priority).', 'next-order-plus'); ?>
                </p>

                <button type="button" class="button button-primary nop-add-rule">
                    <?php echo esc_html__('Add New Rule', 'next-order-plus'); ?>
                </button>
            </div>

            <?php if (!empty($active_category)): ?>
            <div class="nop-active-category-summary">
                <h2><?php echo esc_html__('Active Category', 'next-order-plus'); ?>: <?php echo esc_html(ucfirst($active_category)); ?></h2>
                <div class="nop-active-rules">
                    <h3><?php echo esc_html__('Rules in this category', 'next-order-plus'); ?>:</h3>
                    <ul class="nop-active-rules-list">
                        <?php foreach ($active_rules as $rule): ?>
                            <li>
                                <span class="nop-rule-name"><?php echo esc_html($rule->get_name()); ?></span>
                                <span class="nop-rule-description"><?php echo esc_html($rule->get_description()); ?></span>
                                <span class="nop-rule-status <?php echo $rule->is_active() ? 'active' : 'inactive'; ?>">
                                    <?php echo $rule->is_active() ? esc_html__('Active', 'next-order-plus') : esc_html__('Inactive', 'next-order-plus'); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <div class="nop-rules-notice hidden"></div>

            <div class="nop-rules-table-container">
                <table class="wp-list-table widefat fixed striped nop-rules-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Priority', 'next-order-plus'); ?></th>
                            <th><?php echo esc_html__('Name', 'next-order-plus'); ?></th>
                            <th><?php echo esc_html__('Description', 'next-order-plus'); ?></th>
                            <th><?php echo esc_html__('Condition', 'next-order-plus'); ?></th>
                            <th><?php echo esc_html__('Action', 'next-order-plus'); ?></th>
                            <th><?php echo esc_html__('Status', 'next-order-plus'); ?></th>
                            <th><?php echo esc_html__('Actions', 'next-order-plus'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rules)): ?>
                            <tr>
                                <td colspan="7">
                                    <?php echo esc_html__('No rules found. Add your first rule above.', 'next-order-plus'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rules as $rule): ?>
                                <tr data-rule-id="<?php echo esc_attr($rule->get_id()); ?>">
                                    <td><?php echo esc_html($rule->get_priority()); ?></td>
                                    <td><?php echo esc_html($rule->get_name()); ?></td>
                                    <td><?php echo esc_html($rule->get_description()); ?></td>
                                    <td><?php echo esc_html($this->rules_manager->get_condition_label($rule->get_condition_type())); ?>
                                    </td>
                                    <td><?php echo esc_html($this->rules_manager->get_action_label($rule->get_action_type())); ?></td>
                                    <td>
                                        <div class="nop-status-toggle">
                                            <label class="nop-switch">
                                                <input type="checkbox" class="nop-rule-status" <?php checked($rule->is_active(), true); ?>>
                                                <span class="nop-slider"></span>
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="hidden" class="nop-rule-data"
                                            value='<?php echo esc_attr(wp_json_encode($rule->get_data())); ?>'>
                                        <button type="button" class="button nop-edit-rule"
                                            data-rule-id="<?php echo esc_attr($rule->get_id()); ?>"><?php echo esc_html__('Edit', 'next-order-plus'); ?></button>
                                        <button type="button" class="button nop-delete-rule"
                                            data-rule-id="<?php echo esc_attr($rule->get_id()); ?>"><?php echo esc_html__('Delete', 'next-order-plus'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="nop-rule-modal" class="nop-modal" style="display: none;">
            <div class="nop-modal-content">
                <span class="nop-modal-close">&times;</span>
                <h2 id="nop-modal-title"><?php echo esc_html__('Add Rule', 'next-order-plus'); ?></h2>

                <form id="nop-rule-form">
                    <input type="hidden" id="rule_id" name="rule_id" value="">

                    <div class="nop-form-row">
                        <div class="nop-form-group">
                            <label for="rule_name"><?php echo esc_html__('Rule Name', 'next-order-plus'); ?></label>
                            <input type="text" id="rule_name" name="rule_name" required>
                        </div>

                        <div class="nop-form-group">
                            <label for="rule_priority"><?php echo esc_html__('Priority', 'next-order-plus'); ?></label>
                            <input type="number" id="rule_priority" name="rule_priority" value="10" min="1">
                        </div>
                    </div>

                    <div class="nop-form-row">
                        <div class="nop-form-group">
                            <label for="rule_category"><?php echo esc_html__('Rule Category', 'next-order-plus'); ?></label>
                            <select id="rule_category" name="rule_category" required>
                                <option value=""><?php echo esc_html__('Select a category', 'next-order-plus'); ?></option>
                                <option value="cart_total"><?php echo esc_html__('Cart Total', 'next-order-plus'); ?></option>
                                <option value="item_count"><?php echo esc_html__('Item Count', 'next-order-plus'); ?></option>
                                <option value="specific_product"><?php echo esc_html__('Specific Product', 'next-order-plus'); ?></option>
                                <option value="product_count"><?php echo esc_html__('Product Count', 'next-order-plus'); ?></option>
                            </select>
                        </div>

                        <div class="nop-form-group">
                            <label>
                                <input type="checkbox" id="rule_active" name="rule_active" checked>
                                <?php echo esc_html__('Active', 'next-order-plus'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Activating this rule will deactivate all rules in other categories.', 'next-order-plus'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="nop-form-group">
                        <label for="rule_description"><?php echo esc_html__('Description', 'next-order-plus'); ?></label>
                        <textarea id="rule_description" name="rule_description"></textarea>
                    </div>

                    <!-- Hide the condition type selection as we'll use category directly -->
                    <div class="nop-form-row" style="display: none;">
                        <div class="nop-form-group">
                            <label for="condition_type"><?php echo esc_html__('Condition', 'next-order-plus'); ?></label>
                            <select id="condition_type" name="condition_type">
                                <option value=""><?php echo esc_html__('Select a condition', 'next-order-plus'); ?></option>
                                <?php foreach ($this->rules_manager->get_condition_types() as $type => $label): ?>
                                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="condition_fields">
                        <!-- Dynamic fields will be added here -->
                    </div>

                    <div class="nop-form-row">
                        <div class="nop-form-group">
                            <label for="action_type"><?php echo esc_html__('Action', 'next-order-plus'); ?></label>
                            <select id="action_type" name="action_type" required>
                                <option value=""><?php echo esc_html__('Select an action', 'next-order-plus'); ?></option>
                                <?php foreach ($this->rules_manager->get_action_types() as $type => $label): ?>
                                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="action_fields">
                        <!-- Dynamic fields will be added here -->
                    </div>

                    <div class="nop-form-group">
                        <label>
                            <input type="checkbox" id="action_exclusive" name="action_exclusive">
                            <?php echo esc_html__('Exclusive (prevents other rules from applying)', 'next-order-plus'); ?>
                        </label>
                    </div>

                    <div class="nop-form-actions">
                        <button type="submit"
                            class="button button-primary"><?php echo esc_html__('Save Rule', 'next-order-plus'); ?></button>
                        <button type="button"
                            class="button nop-cancel-rule"><?php echo esc_html__('Cancel', 'next-order-plus'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Get products for select input
     *
     * @return array
     */
    private function get_products_for_select(): array
    {
        $products = [];
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            foreach ($query->posts as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    // Format data for Select2: id and text properties
                    $products[] = [
                        'id' => (string)$product_id, // Convert to string for Select2 compatibility
                        'text' => $product->get_name()
                    ];
                }
            }
        }

        return $products;
    }

    /**
     * AJAX handler for saving a rule with extensive debugging
     *
     * @return void
     */
    public function ajax_save_rule(): void
    {
        // Enable error reporting for debugging
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        // Log the start of the function
        $this->log('ajax_save_rule called. POST data: ' . print_r($_POST, true), 'debug');

        // Check nonce for security
        if (!check_ajax_referer($this->prefix . 'rules_nonce', 'nonce', false)) {
            $this->log('Nonce check failed', 'error');
            wp_send_json_error(['message' => __('Security check failed.', 'next-order-plus')]);
            return;
        }

        $this->log('Nonce check passed', 'debug');

        // Check permissions
        if (!current_user_can('manage_options')) {
            $this->log('Permissions check failed, user cannot manage_options', 'error');
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'next-order-plus')]);
            return;
        }

        $this->log('Permission check passed', 'debug');

        // Get rule data from request
        $rule_data_raw = isset($_POST['rule_data']) ? $_POST['rule_data'] : '';
        $this->log('Raw rule data received: ' . $rule_data_raw, 'debug');

        if (empty($rule_data_raw)) {
            $this->log('No rule data provided in request', 'error');
            wp_send_json_error(['message' => __('No rule data provided.', 'next-order-plus')]);
            return;
        }

        // Decode the JSON data
        $rule_data = json_decode(wp_unslash($rule_data_raw), true);

        // Check if JSON decoding failed
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('JSON decode error: ' . json_last_error_msg(), 'error');
            wp_send_json_error(['message' => __('Invalid JSON data: ', 'next-order-plus') . json_last_error_msg()]);
            return;
        }

        if (!is_array($rule_data)) {
            $this->log('Decoded rule data is not an array', 'error');
            wp_send_json_error(['message' => __('Invalid rule data format.', 'next-order-plus')]);
            return;
        }

        // Log the decoded data
        $this->log('Decoded rule data: ' . print_r($rule_data, true), 'debug');

        // Create or update rule
        try {
            $rule = new NOP_Rule();
            $this->log('New rule object created', 'debug');

            $rule_id = isset($rule_data['id']) && !empty($rule_data['id']) ? absint($rule_data['id']) : 0;
            $this->log('Rule ID from data: ' . $rule_id, 'debug');

            if ($rule_id > 0) {
                $this->log('Attempting to load existing rule with ID: ' . $rule_id, 'debug');
                $rule = $this->rules_manager->get_rule($rule_id);
                if (!$rule) {
                    $this->log('Rule not found with ID: ' . $rule_id, 'error');
                    throw new \Exception(__('Rule not found.', 'next-order-plus'));
                }
                $this->log('Existing rule loaded successfully', 'debug');
            }

            // Set rule properties
            $this->log('Setting rule properties', 'debug');

            // Set basic properties
            $rule->set_name(sanitize_text_field($rule_data['name']));
            $rule->set_description(sanitize_textarea_field($rule_data['description'] ?? ''));
            $rule->set_priority(absint($rule_data['priority'] ?? 10));
            $rule->set_active(isset($rule_data['active']) ? (bool) $rule_data['active'] : true);

            // Set condition properties
            $rule->set_condition_type(sanitize_text_field($rule_data['condition_type']));
            if (isset($rule_data['condition_value'])) {
                $rule->set_condition_value($rule_data['condition_value']);
            }
            if (isset($rule_data['condition_settings']) && is_array($rule_data['condition_settings'])) {
                $rule->set_condition_params($rule_data['condition_settings']);
            }

            // Set action properties
            $rule->set_action_type(sanitize_text_field($rule_data['action_type']));
            if (isset($rule_data['action_value'])) {
                $rule->set_action_value($rule_data['action_value']);
            }
            if (isset($rule_data['action_settings']) && is_array($rule_data['action_settings'])) {
                $rule->set_action_params($rule_data['action_settings']);
            }

            $this->log('Rule properties set, dumping rule object: ' . print_r($rule->get_data(), true), 'debug');

            // Save rule
            $this->log('Attempting to save rule', 'debug');
            $saved_id = $this->rules_manager->save_rule($rule);
            $this->log('Rule saved, returned ID: ' . $saved_id, 'debug');

            if (empty($saved_id) || $saved_id === 0) {
                $this->log('Save operation returned empty or zero ID', 'error');
                throw new \Exception(__('Failed to generate a valid rule ID.', 'next-order-plus'));
            }

            // Get fresh rule object
            $saved_rule = $this->rules_manager->get_rule($saved_id);

            if (!$saved_rule) {
                $this->log('Could not retrieve the saved rule with ID: ' . $saved_id, 'error');
                throw new \Exception(__('Rule was saved but could not be retrieved.', 'next-order-plus'));
            }

            $rule_data = $saved_rule->get_data();
            $this->log('Retrieved saved rule data: ' . print_r($rule_data, true), 'debug');

            wp_send_json_success([
                'message' => __('Rule saved successfully.', 'next-order-plus'),
                'rule' => $rule_data
            ]);
        } catch (\Exception $e) {
            $this->log('Exception thrown during rule save: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString(), 'error');
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for deleting a rule
     *
     * @return void
     */
    public function ajax_delete_rule(): void
    {
        // Enable error reporting for debugging
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        // Log the start of the function
        $this->log('ajax_delete_rule called. POST data: ' . print_r($_POST, true), 'debug');

        // Check nonce for security
        if (!check_ajax_referer($this->prefix . 'rules_nonce', 'nonce', false)) {
            $this->log('Nonce check failed for delete rule', 'error');
            wp_send_json_error(['message' => __('Security check failed.', 'next-order-plus')]);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            $this->log('Permissions check failed for delete rule', 'error');
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'next-order-plus')]);
            return;
        }

        // Get rule ID
        $rule_id = isset($_POST['rule_id']) ? absint($_POST['rule_id']) : 0;

        // Log the rule ID we're trying to delete
        $this->log('Attempting to delete rule with ID: ' . $rule_id, 'debug');

        if ($rule_id <= 0) {
            $this->log('Invalid rule ID for deletion: ' . $rule_id, 'error');
            wp_send_json_error(['message' => __('Invalid rule ID.', 'next-order-plus')]);
            return;
        }

        // Delete rule
        try {
            // Get the rule first to verify it exists
            $rule = $this->rules_manager->get_rule($rule_id);

            if (!$rule) {
                $this->log('Rule not found for deletion: ' . $rule_id, 'error');
                wp_send_json_error(['message' => __('Rule not found.', 'next-order-plus')]);
                return;
            }

            $result = $this->rules_manager->delete_rule($rule_id);

            if (!$result) {
                $this->log('Failed to delete rule: ' . $rule_id, 'error');
                wp_send_json_error(['message' => __('Failed to delete rule.', 'next-order-plus')]);
                return;
            }

            $this->log('Rule deleted successfully: ' . $rule_id, 'debug');

            wp_send_json_success([
                'message' => __('Rule deleted successfully.', 'next-order-plus')
            ]);
        } catch (\Exception $e) {
            $this->log('Error deleting rule: ' . $e->getMessage(), 'error');
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for toggling rule active status
     *
     * @return void
     */
    public function ajax_toggle_rule(): void
    {
        // Check nonce for security
        if (!check_ajax_referer($this->prefix . 'rules_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'next-order-plus')]);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'next-order-plus')]);
            return;
        }

        // Get rule ID and status
        $rule_id = isset($_POST['rule_id']) ? absint($_POST['rule_id']) : 0;
        $active = isset($_POST['active']) ? (bool) $_POST['active'] : false;

        if ($rule_id <= 0) {
            wp_send_json_error(['message' => __('Invalid rule ID.', 'next-order-plus')]);
            return;
        }

        // Toggle rule status
        try {
            $rule = $this->rules_manager->get_rule($rule_id);
            if (!$rule) {
                throw new \Exception(__('Rule not found.', 'next-order-plus'));
            }

            $rule->set_active($active);
            $this->rules_manager->save_rule($rule);

            $this->log('Rule status updated: ' . $rule_id . ' - ' . ($active ? 'active' : 'inactive'));

            wp_send_json_success([
                'message' => $active
                    ? __('Rule activated successfully.', 'next-order-plus')
                    : __('Rule deactivated successfully.', 'next-order-plus')
            ]);
        } catch (\Exception $e) {
            $this->log('Error toggling rule status: ' . $e->getMessage(), 'error');
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
}
