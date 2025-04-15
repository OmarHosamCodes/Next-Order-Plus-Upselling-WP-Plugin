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
     * Calculate total discount from all applicable rules
     *
     * @param mixed $cart WooCommerce cart object
     * @return array Discounts with details
     */
    public function calculate_discounts($cart): array
    {
        $this->set_cart($cart);
        $rules = $this->get_rules(true);
        $discounts = [];
        $this->applied_rules = [];

        foreach ($rules as $rule) {
            if ($this->check_condition($rule)) {
                $discount = $this->apply_action($rule);

                if ($discount['amount'] > 0 || !empty($discount['free_shipping'])) {
                    $discounts[] = $discount;
                    $this->applied_rules[$rule->get_id()] = $rule;

                    // Check if rule should prevent others from applying
                    if (!empty($discount['exclusive'])) {
                        break;
                    }
                }
            }
        }

        // Check for conflicts
        $this->detect_conflicts($discounts);

        // Store applied rules in session for reference
        if (function_exists('WC') && WC()->session) {
            WC()->session->set($this->prefix . 'applied_rules', array_keys($this->applied_rules));
        }

        return $discounts;
    }

    /**
     * Detect conflicts between applied rules
     *
     * @param array $discounts Applied discounts
     * @return void
     */
    private function detect_conflicts(array &$discounts): void
    {
        $conflict_groups = [
            'percentage' => [],
            'fixed' => [],
            'free_product' => [],
            'shipping' => []
        ];

        // Group discounts by type
        foreach ($discounts as $index => $discount) {
            $action_type = $discount['action_type'];

            switch ($action_type) {
                case 'percentage_discount':
                    $conflict_groups['percentage'][] = $index;
                    break;

                case 'fixed_discount':
                    $conflict_groups['fixed'][] = $index;
                    break;

                case 'free_shipping':
                    $conflict_groups['shipping'][] = $index;
                    break;

                case 'cheapest_free':
                case 'most_expensive_free':
                case 'nth_cheapest_free':
                case 'nth_expensive_free':
                    $conflict_groups['free_product'][] = $index;
                    break;
            }
        }

        // Handle percentage conflicts - keep highest
        if (count($conflict_groups['percentage']) > 1) {
            $max_percentage = 0;
            $max_index = -1;

            foreach ($conflict_groups['percentage'] as $index) {
                $rule_id = $discounts[$index]['rule_id'];
                $rule = $this->applied_rules[$rule_id];
                $value = $rule->get_action_value();

                if ($value > $max_percentage) {
                    $max_percentage = $value;
                    $max_index = $index;
                }
            }

            // Keep only the highest percentage discount
            foreach ($conflict_groups['percentage'] as $index) {
                if ($index !== $max_index) {
                    $this->log("Conflict detected: Removing percentage discount #{$discounts[$index]['rule_id']} in favor of #{$discounts[$max_index]['rule_id']}");
                    $discounts[$index]['amount'] = 0;
                    $discounts[$index]['conflict'] = true;
                }
            }
        }

        // Handle free product conflicts - keep highest discount amount
        if (count($conflict_groups['free_product']) > 1) {
            $max_amount = 0;
            $max_index = -1;

            foreach ($conflict_groups['free_product'] as $index) {
                $amount = $discounts[$index]['amount'];

                if ($amount > $max_amount) {
                    $max_amount = $amount;
                    $max_index = $index;
                }
            }

            // Keep only the highest free product discount
            foreach ($conflict_groups['free_product'] as $index) {
                if ($index !== $max_index) {
                    $this->log("Conflict detected: Removing free product discount #{$discounts[$index]['rule_id']} in favor of #{$discounts[$max_index]['rule_id']}");
                    $discounts[$index]['amount'] = 0;
                    $discounts[$index]['conflict'] = true;
                }
            }
        }
    }

    /**
     * Get applied rules for current session
     *
     * @return array Applied rule IDs
     */
    public function get_applied_rules(): array
    {
        if (!empty($this->applied_rules)) {
            return array_keys($this->applied_rules);
        }

        // Try to get from session
        if (function_exists('WC') && WC()->session) {
            $applied_rules = WC()->session->get($this->prefix . 'applied_rules', []);
            return $applied_rules;
        }

        return [];
    }

    /**
     * Check if a rule condition is met
     *
     * @param NOP_Rule $rule Rule to check
     * @return bool Whether condition is met
     */
    private function check_condition(NOP_Rule $rule): bool
    {
        if (!$this->cart) {
            return false;
        }

        $condition_type = $rule->get_condition_type();
        $condition_value = $rule->get_condition_value();
        $condition_params = $rule->get_condition_params();

        switch ($condition_type) {
            case 'cart_total':
                return $this->check_cart_total_condition($condition_value);

            case 'item_count':
                return $this->check_item_count_condition($condition_value);

            case 'specific_product':
                return $this->check_specific_product_condition($condition_value, $condition_params);

            case 'product_count':
                return $this->check_product_count_condition($condition_value, $condition_params);
            default:
                // Allow custom conditions via filter
                return apply_filters(
                    $this->prefix . 'check_custom_condition',
                    false,
                    $condition_type,
                    $condition_value,
                    $condition_params,
                    $this->cart
                );
        }
    }

    /**
     * Check if cart total meets condition
     *
     * @param float $min_total Minimum cart total
     * @return bool Whether condition is met
     */
    private function check_cart_total_condition(float $min_total): bool
    {
        $cart_total = 0;

        if (method_exists($this->cart, 'get_cart_contents_total')) {
            // Classic Cart
            $cart_total = (float) $this->cart->get_cart_contents_total();
        } elseif (method_exists($this->cart, 'get_totals')) {
            // Block Cart
            $totals = $this->cart->get_totals();
            $cart_total = isset($totals['total']) ? (float) $totals['total'] : 0;
        }

        $this->log("Cart total condition: {$cart_total} >= {$min_total}");
        return $cart_total >= $min_total;
    }

    /**
     * Check if item count meets condition
     *
     * @param int $min_count Minimum item count
     * @return bool Whether condition is met
     */
    private function check_item_count_condition(int $min_count): bool
    {
        $item_count = 0;

        if (method_exists($this->cart, 'get_cart_contents_count')) {
            // Classic Cart
            $item_count = (int) $this->cart->get_cart_contents_count();
        } elseif (method_exists($this->cart, 'get_items_count')) {
            // Block Cart
            $item_count = (int) $this->cart->get_items_count();
        }

        $this->log("Item count condition: {$item_count} >= {$min_count}");
        return $item_count >= $min_count;
    }

    /**
     * Check if specific product is in cart
     *
     * @param string|int $product_id Product ID
     * @param array $params Additional parameters
     * @return bool Whether condition is met
     */
    private function check_specific_product_condition($product_id, array $params): bool
    {
        $cart_items = [];

        if (method_exists($this->cart, 'get_cart')) {
            // Classic Cart
            $cart_items = $this->cart->get_cart();
        } elseif (method_exists($this->cart, 'get_items')) {
            // Block Cart
            $cart_items = $this->cart->get_items();
        }

        foreach ($cart_items as $cart_item) {
            $item_product_id = 0;

            if (isset($cart_item['product_id'])) {
                // Classic Cart
                $item_product_id = $cart_item['product_id'];
            } elseif (is_object($cart_item) && method_exists($cart_item, 'get_id')) {
                // Block Cart
                $item_product_id = $cart_item->get_id();
            }

            if ($item_product_id == $product_id) {
                $this->log("Specific product condition met: Product {$product_id} found in cart");
                return true;
            }
        }

        return false;
    }

    /**
     * Check if specific product count meets condition
     *
     * @param int $min_count Minimum count
     * @param array $params Additional parameters with product_id
     * @return bool Whether condition is met
     */
    private function check_product_count_condition(int $min_count, array $params): bool
    {
        if (empty($params['product_id'])) {
            return false;
        }

        $product_id = $params['product_id'];
        $product_count = 0;
        $cart_items = [];

        if (method_exists($this->cart, 'get_cart')) {
            // Classic Cart
            $cart_items = $this->cart->get_cart();
        } elseif (method_exists($this->cart, 'get_items')) {
            // Block Cart
            $cart_items = $this->cart->get_items();
        }

        foreach ($cart_items as $cart_item) {
            $item_product_id = 0;
            $quantity = 0;

            if (isset($cart_item['product_id']) && isset($cart_item['quantity'])) {
                // Classic Cart
                $item_product_id = $cart_item['product_id'];
                $quantity = $cart_item['quantity'];
            } elseif (is_object($cart_item)) {
                // Block Cart
                if (method_exists($cart_item, 'get_id')) {
                    $item_product_id = $cart_item->get_id();
                }
                if (method_exists($cart_item, 'get_quantity')) {
                    $quantity = $cart_item->get_quantity();
                }
            }

            if ($item_product_id == $product_id) {
                $product_count += $quantity;
            }
        }

        $this->log("Product count condition: {$product_count} >= {$min_count} for product {$product_id}");
        return $product_count >= $min_count;
    }

    /**
     * Calculate percentage discount based on cart total
     *
     * @param float $percentage Percentage value (0-100)
     * @return float Discount amount
     */
    private function calculate_percentage_discount(float $percentage): float
    {
        $cart_total = 0;

        if (method_exists($this->cart, 'get_cart_contents_total')) {
            // Classic Cart
            $cart_total = (float) $this->cart->get_cart_contents_total();
        } elseif (method_exists($this->cart, 'get_totals')) {
            // Block Cart
            $totals = $this->cart->get_totals();
            $cart_total = isset($totals['total']) ? (float) $totals['total'] : 0;
        }

        $discount = ($cart_total * $percentage) / 100;
        $this->log("Percentage discount: {$percentage}% of {$cart_total} = {$discount}");

        return $discount;
    }

    /**
     * Calculate discount for cheapest item in cart
     *
     * @return float Discount amount
     */
    private function calculate_cheapest_item_discount(): float
    {
        $prices = $this->get_item_prices();

        if (empty($prices)) {
            return 0;
        }

        // Sort prices to get cheapest first
        sort($prices);
        $discount = $prices[0];

        $this->log("Cheapest item discount: {$discount}");
        return $discount;
    }

    /**
     * Calculate discount for most expensive item in cart
     *
     * @return float Discount amount
     */
    private function calculate_most_expensive_item_discount(): float
    {
        $prices = $this->get_item_prices();

        if (empty($prices)) {
            return 0;
        }

        // Sort prices to get most expensive last
        sort($prices);
        $discount = end($prices);

        $this->log("Most expensive item discount: {$discount}");
        return $discount;
    }

    /**
     * Calculate discount for nth cheapest item in cart
     *
     * @param int $n Position (1-based)
     * @return float Discount amount
     */
    private function calculate_nth_cheapest_item_discount(int $n): float
    {
        $prices = $this->get_item_prices();

        if (empty($prices) || count($prices) < $n) {
            return 0;
        }

        // Sort prices to get cheapest first
        sort($prices);
        $index = $n - 1; // Convert to 0-based index
        $discount = $prices[$index];

        $this->log("Nth cheapest item discount (n={$n}): {$discount}");
        return $discount;
    }

    /**
     * Calculate discount for nth most expensive item in cart
     *
     * @param int $n Position (1-based)
     * @return float Discount amount
     */
    private function calculate_nth_expensive_item_discount(int $n): float
    {
        $prices = $this->get_item_prices();

        if (empty($prices) || count($prices) < $n) {
            return 0;
        }

        // Sort prices to get most expensive first
        rsort($prices);
        $index = $n - 1; // Convert to 0-based index
        $discount = $prices[$index];

        $this->log("Nth most expensive item discount (n={$n}): {$discount}");
        return $discount;
    }

    /**
     * Get all item prices from cart
     *
     * @return array Array of individual item prices
     */
    private function get_item_prices(): array
    {
        $prices = [];
        $cart_items = [];

        if (method_exists($this->cart, 'get_cart')) {
            // Classic Cart
            $cart_items = $this->cart->get_cart();
        } elseif (method_exists($this->cart, 'get_items')) {
            // Block Cart
            $cart_items = $this->cart->get_items();
        }

        foreach ($cart_items as $cart_item) {
            if (!$cart_item) {
                continue;
            }

            // Skip bundled items
            if (isset($cart_item['bundled_by'])) {
                continue;
            }

            $product_price = 0;
            $quantity = 0;

            if (isset($cart_item['data']) && method_exists($cart_item['data'], 'get_price')) {
                // Classic Cart
                $product_price = (float) $cart_item['data']->get_price();
                $quantity = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 1;
            } elseif (is_object($cart_item)) {
                // Block Cart
                if (method_exists($cart_item, 'get_product') && $cart_item->get_product()) {
                    $product = $cart_item->get_product();
                    if (method_exists($product, 'get_price')) {
                        $product_price = (float) $product->get_price();
                    }
                }
                if (method_exists($cart_item, 'get_quantity')) {
                    $quantity = (int) $cart_item->get_quantity();
                }
            }

            if ($product_price > 0) {
                for ($i = 0; $i < $quantity; $i++) {
                    $prices[] = $product_price;
                }
            }
        }

        return $prices;
    }

    /**
     * Apply rule action and get discount amount
     *
     * @param NOP_Rule $rule Rule to apply
     * @return array Discount details
     */
    private function apply_action(NOP_Rule $rule): array
    {
        $action_type = $rule->get_action_type();
        $action_value = $rule->get_action_value();
        $action_params = $rule->get_action_params();
        $discount = 0;

        $result = [
            'rule_id' => $rule->get_id(),
            'rule_name' => $rule->get_name(),
            'action_type' => $action_type,
            'amount' => 0,
            'label' => $rule->get_name(),
            'exclusive' => !empty($action_params['exclusive'])
        ];

        switch ($action_type) {
            case 'percentage_discount':
                $discount = $this->calculate_percentage_discount($action_value);
                $result['amount'] = $discount;
                $result['label'] = sprintf(__('Discount: %s (%s%%)', 'next-order-plus'), $rule->get_name(), $action_value);
                break;

            case 'fixed_discount':
                $discount = (float) $action_value;
                $result['amount'] = $discount;
                $result['label'] = sprintf(__('Discount: %s', 'next-order-plus'), $rule->get_name());
                break;

            case 'free_shipping':
                $result['amount'] = 0;  // Handled separately
                $result['free_shipping'] = true;
                $result['label'] = sprintf(__('Free Shipping: %s', 'next-order-plus'), $rule->get_name());
                break;

            case 'cheapest_free':
                $discount = $this->calculate_cheapest_item_discount();
                $result['amount'] = $discount;
                $result['label'] = sprintf(__('Free Item: %s', 'next-order-plus'), $rule->get_name());
                break;

            case 'most_expensive_free':
                $discount = $this->calculate_most_expensive_item_discount();
                $result['amount'] = $discount;
                $result['label'] = sprintf(__('Free Item: %s', 'next-order-plus'), $rule->get_name());
                break;

            case 'nth_cheapest_free':
                $n = isset($action_params['n']) ? (int) $action_params['n'] : 1;
                $discount = $this->calculate_nth_cheapest_item_discount($n);
                $result['amount'] = $discount;
                $result['label'] = sprintf(__('Free Item: %s', 'next-order-plus'), $rule->get_name());
                break;

            case 'nth_expensive_free':
                $n = isset($action_params['n']) ? (int) $action_params['n'] : 1;
                $discount = $this->calculate_nth_expensive_item_discount($n);
                $result['amount'] = $discount;
                $result['label'] = sprintf(__('Free Item: %s', 'next-order-plus'), $rule->get_name());
                break;

            default:
                // Allow custom actions via filter
                $custom_result = apply_filters(
                    $this->prefix . 'apply_custom_action',
                    [
                        'amount' => 0,
                        'label' => $rule->get_name()
                    ],
                    $action_type,
                    $action_value,
                    $action_params,
                    $this->cart
                );

                if (is_array($custom_result)) {
                    $result = array_merge($result, $custom_result);
                }
        }

        $this->log("Rule '{$rule->get_name()}' applied with action '{$action_type}', discount: {$result['amount']}");
        return $result;
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