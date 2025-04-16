<?php
/**
 * Rules Manager Service
 *
 * Manages creation, validation and application of upsell rules
 *
 * @package NextOrderPlus
 * @since 1.1.0
 */

namespace NOP\Services;

use NOP\Base\NOP_Base;
use NOP\Models\NOP_Rule;
use NOP\Util\NOP_Logger;

/**
 * Rules Manager Service class
 */
class NOP_Rules_Manager extends NOP_Base
{
    /**
     * Available condition types
     *
     * @var array
     */
    private $condition_types;

    /**
     * Available action types
     *
     * @var array
     */
    private $action_types;

    /**
     * Current cart instance
     *
     * @var mixed
     */
    private $cart;

    /**
     * Applied rules for current session
     * 
     * @var array
     */
    private $applied_rules = [];

    /**
     * Constructor
     *
     * Sets up rules manager
     *
     * @param NOP_Logger|null $logger Optional logger instance
     */
    public function __construct($logger = null)
    {
        parent::__construct($logger);

        // Define available condition types
        $this->condition_types = [
            'cart_total' => __('Cart Total', 'next-order-plus'),
            'item_count' => __('Item Count', 'next-order-plus'),
            'specific_product' => __('Specific Product', 'next-order-plus'),
            'product_count' => __('Product Count', 'next-order-plus'),
        ];

        // Define available action types
        $this->action_types = [
            'percentage_discount' => __('Percentage Discount', 'next-order-plus'),
            'fixed_discount' => __('Fixed Discount', 'next-order-plus'),
            'free_shipping' => __('Free Shipping', 'next-order-plus'),
            'cheapest_free' => __('Cheapest Product Free', 'next-order-plus'),
            'most_expensive_free' => __('Most Expensive Product Free', 'next-order-plus'),
            'nth_cheapest_free' => __('Nth Cheapest Product Free', 'next-order-plus'),
            'nth_expensive_free' => __('Nth Most Expensive Product Free', 'next-order-plus'),
        ];

        // Apply filters to allow extending types
        $this->condition_types = apply_filters($this->prefix . 'condition_types', $this->condition_types);
        $this->action_types = apply_filters($this->prefix . 'action_types', $this->action_types);
    }

    /**
     * Initialize the service
     *
     * Sets up hooks for rule management
     *
     * @return void
     */
    public function init(): void
    {
        $this->log('Rules manager initialized');
    }

    /**
     * Get all rules from database
     *
     * @param bool $active_only Whether to return only active rules
     * @return array Rules array
     */
    public function get_rules(bool $active_only = false): array
    {
        $rules = get_option('nop_upsell_rules', []);
        $result = [];

        foreach ($rules as $rule_data) {
            if ($active_only && empty($rule_data['active'])) {
                continue;
            }
            $rule = new NOP_Rule($rule_data);
            $result[$rule->get_id()] = $rule;
        }

        // Sort by priority
        usort($result, function ($a, $b) {
            return $a->get_priority() - $b->get_priority();
        });

        return $result;
    }

    /**
     * Get a specific rule by ID
     *
     * @param int $id Rule ID
     * @return NOP_Rule|null Rule object or null if not found
     */
    public function get_rule(int $id): ?NOP_Rule
    {
        $rule = new NOP_Rule();
        return $rule->load($id) ? $rule : null;
    }

    /**
     * Save a rule to database
     *
     * @param NOP_Rule $rule Rule object
     * @return int Rule ID
     */
    public function save_rule(NOP_Rule $rule): int
    {
        // If the rule is active and has a category, deactivate other rules in different categories
        if ($rule->is_active() && !empty($rule->get_category())) {
            $this->deactivate_other_category_rules($rule->get_category(), $rule->get_id());
        }

        // If no category is set, use the condition type as the category
        if (empty($rule->get_category()) && !empty($rule->get_condition_type())) {
            $rule->set_category($rule->get_condition_type());
            $this->log('Auto-setting category to condition type: ' . $rule->get_condition_type());

            // If the rule is active, deactivate other rules in different categories
            if ($rule->is_active()) {
                $this->deactivate_other_category_rules($rule->get_category(), $rule->get_id());
            }
        }

        return $rule->save();
    }

    /**
     * Delete a rule by ID
     *
     * @param int $rule_id The ID of the rule to delete
     * @return bool True on success, false on failure
     */
    public function delete_rule(int $rule_id): bool
    {
        if ($rule_id <= 0) {
            return false;
        }

        try {
            global $wpdb;
            $table_name = $wpdb->prefix . $this->prefix . 'rules';
            $result = $wpdb->delete(
                $table_name,
                ['id' => $rule_id],
                ['%d']
            );

            return $result !== false;
        } catch (\Exception $e) {
            $this->log('Error deleting rule: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Deactivate rules from other categories
     *
     * @param string $active_category The category that should remain active
     * @param int $except_rule_id The rule ID to exclude from deactivation
     * @return void
     */
    private function deactivate_other_category_rules(string $active_category, int $except_rule_id = 0): void
    {
        $rules = $this->get_rules(false); // Get all rules including inactive ones

        foreach ($rules as $rule_id => $rule) {
            // Skip the rule that triggered this change and rules in the active category
            if ((int) $rule_id === $except_rule_id || $rule->get_category() === $active_category) {
                continue;
            }

            // Only update if the rule is active
            if ($rule->is_active()) {
                $rule->set_active(false);
                $rule->save();
                $this->log("Deactivated rule #{$rule_id} because rule #{$except_rule_id} in category '{$active_category}' was activated");
            }
        }
    }

    /**
     * Activate a rule and deactivate other category rules
     *
     * @param int $rule_id The rule ID to activate
     * @return bool Success
     */
    public function activate_rule(int $rule_id): bool
    {
        $rule = $this->get_rule($rule_id);

        if (!$rule) {
            return false;
        }

        // Set the rule to active
        $rule->set_active(true);

        // If the rule has a category, deactivate other categories
        if (!empty($rule->get_category())) {
            $this->deactivate_other_category_rules($rule->get_category(), $rule_id);
        }
        // If no category but has a condition type, use it as category
        else if (!empty($rule->get_condition_type())) {
            $rule->set_category($rule->get_condition_type());
            $this->deactivate_other_category_rules($rule->get_category(), $rule_id);
        }

        // Save the rule
        $rule->save();
        $this->log("Activated rule #{$rule_id}");

        return true;
    }

    /**
     * Toggle a rule's active state
     * 
     * @param int $rule_id The rule ID to toggle
     * @return bool New active state or false on failure
     */
    public function toggle_rule(int $rule_id): bool
    {
        $rule = $this->get_rule($rule_id);

        if (!$rule) {
            return false;
        }

        if ($rule->is_active()) {
            // If rule is currently active, just deactivate it
            $rule->set_active(false);
            $rule->save();
            $this->log("Deactivated rule #{$rule_id}");
            return false;
        } else {
            // If rule is being activated, use the activate_rule method
            return $this->activate_rule($rule_id);
        }
    }

    /**
     * Get all unique rule categories
     * 
     * @return array List of unique categories
     */
    public function get_categories(): array
    {
        $rules = $this->get_rules(false);
        $categories = [];

        foreach ($rules as $rule) {
            $category = $rule->get_category();
            if (!empty($category) && !in_array($category, $categories)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    /**
     * Get available condition types
     *
     * @return array Condition types
     */
    public function get_condition_types(): array
    {
        return $this->condition_types;
    }

    /**
     * Get available action types
     *
     * @return array Action types
     */
    public function get_action_types(): array
    {
        return $this->action_types;
    }

    /**
     * Set cart instance for rule evaluation
     *
     * @param mixed $cart Cart object
     * @return void
     */
    public function set_cart($cart): void
    {
        $this->cart = $cart;
    }

    /**
     * Calculate all applicable discounts for the cart
     *
     * @param mixed $cart WooCommerce cart object
     * @return array Array of discount data with amounts and rule information
     */
    public function calculate_discounts($cart): array
    {
        $this->cart = $cart;
        $discounts = [];
        $active_rules = $this->get_rules(true); // Only get active rules

        if (empty($active_rules)) {
            $this->log('No active rules found for discount calculation');
            return [];
        }

        // Get active category (if any)
        $active_category = '';

        foreach ($active_rules as $rule) {
            if ($rule->is_active() && !empty($rule->get_category())) {
                $active_category = $rule->get_category();
                break;
            }
        }

        // If we have an active category, only evaluate rules in that category
        if (!empty($active_category)) {
            $category_rules = [];

            foreach ($active_rules as $rule) {
                if ($rule->get_category() === $active_category) {
                    $category_rules[] = $rule;
                }
            }

            // Replace active rules with only those in the active category
            $active_rules = $category_rules;
        }

        // Track which rules have been applied
        $this->applied_rules = [];

        // Evaluate each rule
        foreach ($active_rules as $rule) {
            $discount_data = $this->evaluate_rule($rule);

            if (!empty($discount_data) && isset($discount_data['amount']) && $discount_data['amount'] > 0) {
                $discounts[] = $discount_data;
                $this->applied_rules[] = $rule->get_id();

            }
        }

        return $discounts;
    }

    /**
     * Evaluate a single rule and return applicable discount
     *
     * @param NOP_Rule $rule Rule to evaluate
     * @return array|null Discount data if rule conditions are met, null otherwise
     */
    private function evaluate_rule($rule): ?array
    {
        // Skip inactive rules
        if (!$rule->is_active()) {
            return null;
        }

        $condition_type = $rule->get_condition_type();
        $condition_value = $rule->get_condition_value();
        $condition_params = $rule->get_condition_params();

        // Check if condition is met
        $condition_met = $this->evaluate_condition($condition_type, $condition_value, $condition_params);

        if (!$condition_met) {
            return null;
        }

        // Condition is met, calculate applicable discount
        $action_type = $rule->get_action_type();
        $action_value = $rule->get_action_value();
        $action_params = $rule->get_action_params();

        return $this->calculate_action_discount($action_type, $action_value, $action_params, [
            'rule_id' => $rule->get_id(),
            'rule_name' => $rule->get_name(),
            'category' => $rule->get_category()
        ]);
    }

    /**
     * Evaluate condition to determine if it's met
     *
     * @param string $type Condition type
     * @param mixed $value Condition value
     * @param array $params Additional parameters
     * @return bool Whether condition is met
     */
    private function evaluate_condition($type, $value, $params = []): bool
    {
        if (empty($this->cart)) {
            return false;
        }

        switch ($type) {
            case 'cart_total':
                return $this->evaluate_cart_total_condition($value);

            case 'item_count':
                return $this->evaluate_item_count_condition($value);

            case 'specific_product':
                return $this->evaluate_specific_product_condition($value, $params);

            case 'product_count':
                return $this->evaluate_product_count_condition($value, $params);

            default:
                // Allow custom conditions via filter
                return apply_filters(
                    $this->prefix . 'evaluate_condition',
                    false,
                    $type,
                    $value,
                    $params,
                    $this->cart
                );
        }
    }

    /**
     * Calculate discount amount based on action type
     *
     * @param string $type Action type
     * @param mixed $value Action value
     * @param array $params Additional parameters
     * @param array $rule_data Rule metadata
     * @return array Discount data with amount and metadata
     */
    private function calculate_action_discount($type, $value, $params = [], $rule_data = []): array
    {
        if (empty($this->cart)) {
            return ['amount' => 0, 'rule_name' => $rule_data['rule_name'] ?? ''];
        }

        $discount = [
            'amount' => 0,
            'rule_id' => $rule_data['rule_id'] ?? 0,
            'rule_name' => $rule_data['rule_name'] ?? __('Promotion', 'next-order-plus'),
            'type' => $type,
            'free_shipping' => false,
            'conflict' => false,
        ];

        switch ($type) {
            case 'percentage_discount':
                $discount['amount'] = $this->calculate_percentage_discount($value);
                break;

            case 'fixed_discount':
                $discount['amount'] = min((float) $value, $this->get_cart_subtotal());
                break;

            case 'free_shipping':
                $discount['free_shipping'] = true;
                break;

            case 'cheapest_free':
                $discount['amount'] = $this->calculate_cheapest_free_discount();
                break;

            case 'most_expensive_free':
                $discount['amount'] = $this->calculate_most_expensive_free_discount();
                break;

            case 'nth_cheapest_free':
                $position = isset($params['position']) ? (int) $params['position'] : 1;
                $discount['amount'] = $this->calculate_nth_cheapest_free_discount($position);
                break;

            case 'nth_expensive_free':
                $position = isset($params['position']) ? (int) $params['position'] : 1;
                $discount['amount'] = $this->calculate_nth_expensive_free_discount($position);
                break;

            default:
                // Allow custom actions via filter
                $custom_discount = apply_filters(
                    $this->prefix . 'calculate_action_discount',
                    0,
                    $type,
                    $value,
                    $params,
                    $this->cart,
                    $rule_data
                );
                $discount['amount'] = is_numeric($custom_discount) ? (float) $custom_discount : 0;
        }

        return $discount;
    }

    /**
     * Evaluate cart total condition
     *
     * @param float $min_amount Minimum cart amount
     * @return bool Whether condition is met
     */
    private function evaluate_cart_total_condition($min_amount): bool
    {
        $cart_total = $this->get_cart_subtotal();
        return $cart_total >= (float) $min_amount;
    }

    /**
     * Evaluate item count condition
     *
     * @param int $min_items Minimum number of items
     * @return bool Whether condition is met
     */
    private function evaluate_item_count_condition($min_items): bool
    {
        $item_count = $this->get_cart_item_count();
        return $item_count >= (int) $min_items;
    }

    /**
     * Evaluate specific product condition
     *
     * @param int|string $product_id Product ID
     * @param array $params Additional parameters
     * @return bool Whether condition is met
     */
    private function evaluate_specific_product_condition($product_id, $params = []): bool
    {
        $min_quantity = isset($params['min_quantity']) ? (int) $params['min_quantity'] : 1;

        // Get cart contents
        $cart_items = $this->get_cart_items();
        $product_quantity = 0;

        foreach ($cart_items as $item) {
            $item_product_id = isset($item['product_id']) ? $item['product_id'] : 0;
            $item_variation_id = isset($item['variation_id']) ? $item['variation_id'] : 0;

            if ($item_product_id == $product_id || ($item_variation_id > 0 && $item_variation_id == $product_id)) {
                $product_quantity += $item['quantity'];
            }
        }

        return $product_quantity >= $min_quantity;
    }

    /**
     * Evaluate product count condition
     *
     * @param int $min_products Minimum unique products
     * @param array $params Additional parameters
     * @return bool Whether condition is met
     */
    private function evaluate_product_count_condition($min_products, $params = []): bool
    {
        // Count unique products in cart
        $cart_items = $this->get_cart_items();
        $unique_products = [];

        foreach ($cart_items as $item) {
            $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
            if ($product_id > 0 && !in_array($product_id, $unique_products)) {
                $unique_products[] = $product_id;
            }
        }

        return count($unique_products) >= (int) $min_products;
    }

    /**
     * Calculate percentage discount
     *
     * @param float $percentage Percentage value
     * @return float Discount amount
     */
    private function calculate_percentage_discount($percentage): float
    {
        $subtotal = $this->get_cart_subtotal();
        $percentage = min(100, max(0, (float) $percentage));
        return $subtotal * ($percentage / 100);
    }

    /**
     * Calculate discount for cheapest free item
     *
     * @return float Discount amount
     */
    private function calculate_cheapest_free_discount(): float
    {
        $cart_items = $this->get_cart_items();
        $cheapest_price = PHP_FLOAT_MAX;

        foreach ($cart_items as $item) {
            $price = isset($item['line_subtotal']) && isset($item['quantity']) && $item['quantity'] > 0
                ? $item['line_subtotal'] / $item['quantity']
                : PHP_FLOAT_MAX;

            if ($price < $cheapest_price) {
                $cheapest_price = $price;
            }
        }

        return $cheapest_price !== PHP_FLOAT_MAX ? $cheapest_price : 0;
    }

    /**
     * Calculate discount for most expensive free item
     *
     * @return float Discount amount
     */
    private function calculate_most_expensive_free_discount(): float
    {
        $cart_items = $this->get_cart_items();
        $most_expensive = 0;

        foreach ($cart_items as $item) {
            $price = isset($item['line_subtotal']) && isset($item['quantity']) && $item['quantity'] > 0
                ? $item['line_subtotal'] / $item['quantity']
                : 0;

            if ($price > $most_expensive) {
                $most_expensive = $price;
            }
        }

        return $most_expensive;
    }

    /**
     * Calculate discount for nth cheapest free item
     *
     * @param int $position Position (1-based)
     * @return float Discount amount
     */
    private function calculate_nth_cheapest_free_discount($position = 1): float
    {
        $cart_items = $this->get_cart_items();
        $prices = [];

        foreach ($cart_items as $item) {
            if (isset($item['line_subtotal']) && isset($item['quantity']) && $item['quantity'] > 0) {
                $unit_price = $item['line_subtotal'] / $item['quantity'];

                // Add one entry per quantity
                for ($i = 0; $i < $item['quantity']; $i++) {
                    $prices[] = $unit_price;
                }
            }
        }

        // Sort prices low to high
        sort($prices);

        // Adjust position to 0-based
        $index = $position - 1;

        // Return price at requested position if it exists
        return isset($prices[$index]) ? $prices[$index] : 0;
    }

    /**
     * Calculate discount for nth most expensive free item
     *
     * @param int $position Position (1-based)
     * @return float Discount amount
     */
    private function calculate_nth_expensive_free_discount($position = 1): float
    {
        $cart_items = $this->get_cart_items();
        $prices = [];

        foreach ($cart_items as $item) {
            if (isset($item['line_subtotal']) && isset($item['quantity']) && $item['quantity'] > 0) {
                $unit_price = $item['line_subtotal'] / $item['quantity'];

                // Add one entry per quantity
                for ($i = 0; $i < $item['quantity']; $i++) {
                    $prices[] = $unit_price;
                }
            }
        }

        // Sort prices high to low
        rsort($prices);

        // Adjust position to 0-based
        $index = $position - 1;

        // Return price at requested position if it exists
        return isset($prices[$index]) ? $prices[$index] : 0;
    }

    /**
     * Get cart subtotal
     *
     * @return float Cart subtotal
     */
    private function get_cart_subtotal(): float
    {
        if (!$this->cart) {
            return 0;
        }

        // Standard WooCommerce cart
        if (method_exists($this->cart, 'get_subtotal')) {
            return (float) $this->cart->get_subtotal();
        }

        // Store API cart
        if (method_exists($this->cart, 'get_subtotal')) {
            return (float) $this->cart->get_subtotal();
        }

        return 0;
    }

    /**
     * Get cart item count
     *
     * @return int Number of items in cart
     */
    private function get_cart_item_count(): int
    {
        if (!$this->cart) {
            return 0;
        }

        // Standard WooCommerce cart
        if (method_exists($this->cart, 'get_cart_contents_count')) {
            return (int) $this->cart->get_cart_contents_count();
        }

        // Store API cart
        if (method_exists($this->cart, 'get_items_count')) {
            return (int) $this->cart->get_items_count();
        }

        return 0;
    }

    /**
     * Get cart items standardized format
     *
     * @return array Cart items
     */
    private function get_cart_items(): array
    {
        if (!$this->cart) {
            return [];
        }

        // Standard WooCommerce cart
        if (method_exists($this->cart, 'get_cart')) {
            return $this->cart->get_cart();
        }

        // Store API cart
        if (method_exists($this->cart, 'get_items')) {
            $items = $this->cart->get_items();
            $formatted_items = [];

            foreach ($items as $item) {
                $formatted_items[] = [
                    'product_id' => $item->get_id(),
                    'variation_id' => $item->get_variation_id(),
                    'quantity' => $item->get_quantity(),
                    'line_subtotal' => $item->get_subtotal(),
                    'data' => $item
                ];
            }

            return $formatted_items;
        }

        return [];
    }

    /**
     * Get applied rules for current session
     * 
     * @return array IDs of applied rules
     */
    public function get_applied_rules(): array
    {
        return $this->applied_rules;
    }

    /**
     * Get condition label by type
     *
     * @param string $type Condition type
     * @return string Condition label
     */
    public function get_condition_label(string $type): string
    {
        return isset($this->condition_types[$type])
            ? $this->condition_types[$type]
            : ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Get action label by type
     *
     * @param string $type Action type
     * @return string Action label
     */
    public function get_action_label(string $type): string
    {
        return isset($this->action_types[$type])
            ? $this->action_types[$type]
            : ucfirst(str_replace('_', ' ', $type));
    }
}