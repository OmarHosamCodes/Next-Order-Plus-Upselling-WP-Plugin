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
        add_submenu_page(
            $this->prefix . 'settings',
            __('Upsell Rules', 'next-order-plus'),
            __('Upsell Rules', 'next-order-plus'),
            'manage_options',
            $this->prefix . 'rules',
            [$this, 'render_rules_page']
        );
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

        // Localize script data
        wp_localize_script($this->prefix . 'rules_admin', $this->prefix . 'rules_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($this->prefix . 'rules_nonce'),
            'condition_types' => $this->rules_manager->get_condition_types(),
            'action_types' => $this->rules_manager->get_action_types(),
            'products' => $this->get_products_for_select(),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this rule?', 'next-order-plus'),
                'save_success' => __('Rule saved successfully.', 'next-order-plus'),
                'delete_success' => __('Rule deleted successfully.', 'next-order-plus'),
                'error' => __('An error occurred. Please try again.', 'next-order-plus')
            ]
        ]);
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

        ?>
        <di class="wrap nop-rules-wrap">
            <h1><?php echo esc_html__('Upsell Rules', 'next-order-plus'); ?></h1>
            
            <div class="nop-rules-header">
                <p><?php echo esc_html__('Create and manage upsell rules for your store. Rules are evaluated in priority order (lower number = higher priority).', 'next-order-plus'); ?></p>
                
                <button type="button" class="button button-primary nop-add-rule">
                    <?php echo esc_html__('Add New Rule', 'next-order-plus'); ?>
                </button>
            </div>
            
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
                                <td colspan="7"><?php echo esc_html__('No rules found. Add your first rule above.', 'next-order-plus'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rules as $rule): ?>
                                <tr data-rule-id="<?php echo esc_attr($rule->get_id()); ?>">
                                    <td><?php echo esc_html($rule->get_priority()); ?></td>
                                    <td><?php echo esc_html($rule->get_name()); ?></td>
                                    <td><?php echo esc_html($rule->get_description()); ?></td>
                                    <td><?php echo esc_html($this->rules_manager->get_condition_label($rule->get_condition_type())); ?></td>
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
                                        <button type="button" class="button nop-edit-rule"><?php echo esc_html__('Edit', 'next-order-plus'); ?></button>
                                        <button type="button" class="button nop-delete-rule"><?php echo esc_html__('Delete', 'next-order-plus'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                    $products[$product_id] = $product->get_name();
                }
            }
        }

        return $products;
    }
    /**
     * AJAX handler for saving a rule
     *
     * @return void
     */
    public function ajax_save_rule(): void
    {
        // Check nonce for security
        if (!check_ajax_referer("{$this->prefix}rules_nonce", 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'next-order-plus')]);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'next-order-plus')]);
            return;
        }

        // Get rule data from request
        $rule_data = isset($_POST['rule_data']) ? wp_unslash($_POST['rule_data']) : [];
        if (empty($rule_data)) {
            wp_send_json_error(['message' => __('No rule data provided.', 'next-order-plus')]);
            return;
        }

        // Create or update rule
        try {
            $rule = new NOP_Rule();
            $rule_id = isset($rule_data['id']) ? absint($rule_data['id']) : 0;

            if ($rule_id > 0) {
                $rule = $this->rules_manager->get_rule($rule_id);
                if (!$rule) {
                    throw new \Exception(__('Rule not found.', 'next-order-plus'));
                }
            }

            // Set rule properties
            $rule->set_name(sanitize_text_field($rule_data['name']));
            $rule->set_description(sanitize_textarea_field($rule_data['description'] ?? ''));
            $rule->set_priority(absint($rule_data['priority'] ?? 10));
            $rule->set_condition_type(sanitize_text_field($rule_data['condition_type']));
            $rule->set_condition_data($rule_data['condition_settings'] ?? []);
            $rule->set_action_type(sanitize_text_field($rule_data['action_type']));
            $rule->set_action_data($rule_data['action_settings'] ?? []);
            $rule->set_active(isset($rule_data['active']) ? (bool)$rule_data['active'] : true);

            // Save rule
            $saved_id = $this->rules_manager->save_rule($rule);
            
            $this->log('Rule saved: ' . $saved_id);
            
            // Get fresh rule object
            $saved_rule = $this->rules_manager->get_rule($saved_id);
            
            wp_send_json_success([
                'message' => __('Rule saved successfully.', 'next-order-plus'),
                'rule' => $saved_rule ? $saved_rule->to_array() : ['id' => $saved_id]
            ]);
        } catch (\Exception $e) {
            $this->log('Error saving rule: ' . $e->getMessage(), 'error');
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

        // Get rule ID
        $rule_id = isset($_POST['rule_id']) ? absint($_POST['rule_id']) : 0;
        if ($rule_id <= 0) {
            wp_send_json_error(['message' => __('Invalid rule ID.', 'next-order-plus')]);
            return;
        }

        // Delete rule
        try {
            $this->rules_manager->delete_rule($rule_id);
            $this->log('Rule deleted: ' . $rule_id);
            
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
        $active = isset($_POST['active']) ? (bool)$_POST['active'] : false;

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
                                    