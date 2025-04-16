<?php
/**
 * Cart Service
 *
 * Handles applying discounts to the WooCommerce cart
 *
 * @package NextOrderPlus
 * @since 1.0.0
 */

namespace NOP\Services;

use NOP\Base\NOP_Base;
use NOP\Util\NOP_Logger;

/**
 * Cart Service class
 */
class NOP_Cart_Service extends NOP_Base
{
    /**
     * Rules manager service instance
     *
     * @var NOP_Rules_Manager
     */
    private $rules_manager;

    /**
     * Discount service instance (legacy support)
     *
     * @var NOP_Discount_Service
     */
    private $discount_service;

    /**
     * Discount label text
     *
     * @var string
     */
    private $discount_label;

    /**
     * Admin service instance for settings
     *
     * @var NOP_Admin_Service|null
     */
    private $admin_service;

    /**
     * Whether to disable free shipping when discount is applied
     *
     * @var bool
     */
    private $disable_free_shipping;

    /**
     * Constructor
     *
     * Sets up discount service dependency
     *
     * @param NOP_Rules_Manager $rules_manager Rules manager service
     * @param NOP_Discount_Service $discount_service Legacy discount calculation service
     * @param NOP_Logger|null $logger Optional logger instance
     * @param NOP_Admin_Service|null $admin_service Optional admin service for settings
     */
    public function __construct(NOP_Rules_Manager $rules_manager, NOP_Discount_Service $discount_service, $logger = null, $admin_service = null)
    {
        parent::__construct($logger);
        $this->rules_manager = $rules_manager;
        $this->discount_service = $discount_service;
        $this->admin_service = $admin_service;

        // Default values
        $this->discount_label = __('Discount: 2025 Promotion', 'next-order-plus');
        $this->disable_free_shipping = true;

        // If admin service is available, get settings from it
        if ($this->admin_service instanceof NOP_Admin_Service) {
            $options = $this->admin_service->get_options();

            if (isset($options['discount_label']) && !empty($options['discount_label'])) {
                $this->discount_label = $options['discount_label'];
            }

            if (isset($options['disable_free_shipping'])) {
                $this->disable_free_shipping = (bool) $options['disable_free_shipping'];
            }
        }

        // Allow filtering discount label
        $this->discount_label = apply_filters(
            $this->prefix . 'discount_label',
            $this->discount_label
        );
    }

    /**
     * Initialize the service
     *
     * Sets up hooks and filters for cart operations
     *
     * @return void
     */
    public function init(): void
    {
        $this->log('Cart service initialized with label: ' . $this->discount_label);
    }

    /**
     * Applies discount to the cart
     *
     * @param mixed $cart WooCommerce cart object
     * @return void
     */
    public function apply_cart_discount($cart): void
    {
        // Skip for admin areas (except AJAX) and direct REST requests
        if ((is_admin() && !wp_doing_ajax()) || (defined('REST_REQUEST') && REST_REQUEST && !$this->is_store_api_request())) {
            return;
        }

        try {
            // Store if we have any free shipping rules
            $has_free_shipping = false;
            $total_discounts = 0;
            $applied_rule_names = [];

            // Get all rules-based discounts
            $rules_discounts = $this->rules_manager->calculate_discounts($cart);

            // Apply each discount if amount > 0
            foreach ($rules_discounts as $discount) {
                if (!empty($discount['conflict'])) {
                    continue; // Skip discounts marked as conflicting
                }

                if ($discount['amount'] > 0) {
                    // Use rule name directly as the discount label
                    $this->add_discount_fee($cart, $discount['amount'], $discount['rule_name']);
                    $total_discounts += $discount['amount'];
                    $applied_rule_names[] = $discount['rule_name'];
                }

                // Check for free shipping
                if (!empty($discount['free_shipping'])) {
                    $has_free_shipping = true;
                }
            }

            // Calculate legacy discount if no rules-based discounts were applied
            if (empty($rules_discounts)) {
                $legacy_discount = $this->discount_service->calculate_discount($cart);

                if ($legacy_discount > 0) {
                    $this->add_discount_fee($cart, $legacy_discount, $this->discount_label);
                    $total_discounts += $legacy_discount;
                }
            }

            // Store the current total discount and applied rule names in session
            if (function_exists('WC') && WC()->session) {
                WC()->session->set($this->prefix . 'total_savings', $total_discounts);
                WC()->session->set($this->prefix . 'has_free_shipping', $has_free_shipping);

                // Store applied rule names for persistent display
                if (!empty($applied_rule_names)) {
                    WC()->session->set($this->prefix . 'applied_rule_names', $applied_rule_names);
                }

                $this->log("Stored discount in session: {$total_discounts}, free shipping: " . ($has_free_shipping ? 'yes' : 'no'));
            }
        } catch (\Exception $e) {
            $this->log("Error applying cart discount: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Check if the current request is from Store API
     *
     * @return bool Whether current request is from Store API
     */
    private function is_store_api_request(): bool
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        $rest_prefix = trailingslashit(rest_get_url_prefix());
        $request_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));

        return strpos($request_uri, $rest_prefix . 'wc/store/') !== false;
    }

    /**
     * Adds discount fee to cart
     *
     * @param mixed $cart WooCommerce cart object
     * @param float $discount Discount amount
     * @param string $label Discount label
     * @return void
     */
    private function add_discount_fee($cart, float $discount, string $label): void
    {
        if (method_exists($cart, 'add_fee')) {
            // Classic Cart
            $cart->add_fee($label, -$discount, false);
        } else {
            // Block Cart
            $cart->add_fee([
                'name' => $label,
                'amount' => -$discount,
                'taxable' => false,
            ]);
        }
    }

    /**
     * Makes sure discount persists during checkout
     *
     * @param mixed $cart WooCommerce cart object
     * @return void
     */
    public function apply_discount_persistently($cart): void
    {
        // Get stored discount
        $discount = 0;
        $has_free_shipping = false;
        $applied_rule_names = [];

        if (function_exists('WC') && WC()->session) {
            $discount = (float) WC()->session->get($this->prefix . 'total_savings', 0);
            $has_free_shipping = (bool) WC()->session->get($this->prefix . 'has_free_shipping', false);
            $applied_rule_names = WC()->session->get($this->prefix . 'applied_rule_names', []);
        }

        if ($discount > 0) {
            // If we have stored rule names, use the first one as the discount label
            $label = !empty($applied_rule_names) ? $applied_rule_names[0] : $this->discount_label;
            $this->add_discount_fee($cart, $discount, $label);
            $this->log("Persistence: Applied stored discount of {$discount} with label: {$label}");
        }

        // Store free shipping status in session for the shipping method filter
        if (function_exists('WC') && WC()->session) {
            WC()->session->set($this->prefix . 'has_free_shipping', $has_free_shipping);
        }
    }

    /**
     * Removes only free shipping when discount is applied
     * or keeps free shipping when a free shipping rule is applied
     *
     * @param array $rates Available shipping rates
     * @param array $package Shipping package
     * @return array Modified shipping rates
     */
    public function remove_free_shipping_when_discount_applied(array $rates, array $package): array
    {
        $has_free_shipping = false;
        $has_discount = false;

        if (function_exists('WC') && WC()->session) {
            $has_free_shipping = (bool) WC()->session->get($this->prefix . 'has_free_shipping', false);
            $has_discount = (float) WC()->session->get($this->prefix . 'total_savings', 0) > 0;
        }

        // Keep free shipping if we have a free shipping rule
        if ($has_free_shipping) {
            $this->log("Keeping free shipping due to free shipping rule");
            return $rates;
        }

        // Remove free shipping if we have a discount and the setting is enabled
        if ($has_discount && $this->disable_free_shipping) {
            foreach ($rates as $rate_id => $rate) {
                if ('free_shipping' === $rate->method_id) {
                    unset($rates[$rate_id]);
                    $this->log("Removed free shipping due to active discount");
                }
            }
        }

        return $rates;
    }

    /**
     * Saves discount to order
     *
     * @param \WC_Order $order WooCommerce order object
     * @param array $data Order data
     * @return void
     */
    public function save_discount_to_order($order, array $data): void
    {
        $discount_amount = 0;
        $applied_rules = [];

        if (function_exists('WC') && WC()->session) {
            $discount_amount = (float) WC()->session->get($this->prefix . 'total_savings', 0);
            $applied_rules = $this->rules_manager->get_applied_rules();
        }

        if ($discount_amount > 0) {
            // Create fee item
            $item = new \WC_Order_Item_Fee();
            $item->set_name($this->discount_label);
            $item->set_amount(-$discount_amount);
            $item->set_total(-$discount_amount);
            $order->add_item($item);

            // Store discount amount as order meta
            $order->update_meta_data('_' . $this->prefix . 'discount_amount', $discount_amount);

            // Store applied rules
            if (!empty($applied_rules)) {
                $order->update_meta_data('_' . $this->prefix . 'applied_rules', $applied_rules);
            }

            $this->log("Saved discount of {$discount_amount} to order #{$order->get_id()}");
        }
    }

    /**
     * AJAX handler for mini cart updates
     *
     * @return void
     */
    public function update_mini_cart_discount(): void
    {
        check_ajax_referer($this->prefix . 'nonce', 'nonce');

        try {
            $discount = 0;
            $has_free_shipping = false;
            $applied_rule_names = [];

            if (function_exists('WC') && WC()->cart) {
                // Get all rules-based discounts
                $rules_discounts = $this->rules_manager->calculate_discounts(WC()->cart);

                // Sum up all valid discounts
                foreach ($rules_discounts as $rule_discount) {
                    if (empty($rule_discount['conflict'])) {
                        $discount += $rule_discount['amount'];

                        if (!empty($rule_discount['rule_name'])) {
                            $applied_rule_names[] = $rule_discount['rule_name'];
                        }

                        if (!empty($rule_discount['free_shipping'])) {
                            $has_free_shipping = true;
                        }
                    }
                }

                // Apply legacy discount if no rules-based discounts
                if (empty($rules_discounts)) {
                    $discount = $this->discount_service->calculate_discount(WC()->cart);
                }

                // Store values in session
                WC()->session->set($this->prefix . 'total_savings', $discount);
                WC()->session->set($this->prefix . 'has_free_shipping', $has_free_shipping);

                // Store applied rule names for display
                if (!empty($applied_rule_names)) {
                    WC()->session->set($this->prefix . 'applied_rule_names', $applied_rule_names);
                }

                $this->log("AJAX: Updated discount to {$discount}, free shipping: " . ($has_free_shipping ? 'yes' : 'no'));
            }

            wp_send_json_success([
                'discount' => $discount,
                'has_free_shipping' => $has_free_shipping,
                'discount_formatted' => function_exists('wc_price') ? wc_price($discount) : '' . number_format($discount, 2),
                'rule_names' => $applied_rule_names
            ]);
        } catch (\Exception $e) {
            $this->log("AJAX Error: " . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Failed to update cart discount']);
        }
    }

    /**
     * Updates mini cart fragments
     *
     * @param array $fragments Cart fragments
     * @return array Updated fragments
     */
    public function add_mini_cart_discount_fragment(array $fragments): array
    {
        $discount = 0;
        $has_free_shipping = false;
        $applied_rule_names = [];

        if (function_exists('WC') && WC()->session) {
            $discount = (float) WC()->session->get($this->prefix . 'total_savings', 0);
            $has_free_shipping = (bool) WC()->session->get($this->prefix . 'has_free_shipping', false);
            $applied_rule_names = WC()->session->get($this->prefix . 'applied_rule_names', []);
        }

        ob_start();
        if ($discount > 0) {
            // Use the first rule name if available, otherwise fall back to the default label
            $label = !empty($applied_rule_names) ? $applied_rule_names[0] : $this->discount_label;
            ?>
            <div class="mini-cart-discount">
                <span class="mini-cart-discount-label"><?php echo esc_html($label); ?></span>
                <span
                    class="mini-cart-discount-amount">-<?php echo function_exists('wc_price') ? wc_price($discount) : '' . number_format($discount, 2); ?></span>
            </div>
            <?php
        }
        $fragments['.mini-cart-discount'] = ob_get_clean();

        // Add free shipping notice if applicable
        if ($has_free_shipping) {
            ob_start();
            ?>
            <div class="mini-cart-free-shipping">
                <span
                    class="mini-cart-free-shipping-label"><?php echo esc_html__('Free Shipping Eligible', 'next-order-plus'); ?></span>
            </div>
            <?php
            $fragments['.mini-cart-free-shipping'] = ob_get_clean();
        }

        return $fragments;
    }

    /**
     * Displays discount in mini cart
     *
     * @return void
     */
    public function display_mini_cart_discount(): void
    {
        $discount = 0;
        $has_free_shipping = false;
        $applied_rule_names = [];

        if (function_exists('WC') && WC()->session) {
            $discount = (float) WC()->session->get($this->prefix . 'total_savings', 0);
            $has_free_shipping = (bool) WC()->session->get($this->prefix . 'has_free_shipping', false);
            $applied_rule_names = WC()->session->get($this->prefix . 'applied_rule_names', []);
        }

        if ($discount > 0) {
            // Use the first rule name if available, otherwise fall back to the default label
            $label = !empty($applied_rule_names) ? $applied_rule_names[0] : $this->discount_label;
            ?>
            <div class="mini-cart-discount">
                <span class="mini-cart-discount-label"><?php echo esc_html($label); ?></span>
                <span
                    class="mini-cart-discount-amount">-<?php echo function_exists('wc_price') ? wc_price($discount) : '' . number_format($discount, 2); ?></span>
            </div>
            <?php
        }

        if ($has_free_shipping) {
            ?>
            <div class="mini-cart-free-shipping">
                <span
                    class="mini-cart-free-shipping-label"><?php echo esc_html__('Free Shipping Eligible', 'next-order-plus'); ?></span>
            </div>
            <?php
        }
    }
}