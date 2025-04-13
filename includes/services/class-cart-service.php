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
     * Discount service instance
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
     * @param NOP_Discount_Service $discount_service Discount calculation service
     * @param NOP_Logger|null $logger Optional logger instance
     * @param NOP_Admin_Service|null $admin_service Optional admin service for settings
     */
    public function __construct(NOP_Discount_Service $discount_service, $logger = null, $admin_service = null)
    {
        parent::__construct($logger);
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
            // Calculate discount
            $discount = $this->discount_service->calculate_discount($cart);

            // Always store the current discount in session
            if (function_exists('WC') && WC()->session) {
                WC()->session->set($this->prefix . 'total_savings', $discount);
                $this->log("Stored discount in session: {$discount}");
            }

            if ($discount > 0) {
                $this->add_discount_fee($cart, $discount);
                $this->log("Applied discount to cart: {$discount}");
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
     * @return void
     */
    private function add_discount_fee($cart, float $discount): void
    {
        if (method_exists($cart, 'add_fee')) {
            // Classic Cart
            $cart->add_fee($this->discount_label, -$discount, false);
        } else {
            // Block Cart
            $cart->add_fee([
                'name' => $this->discount_label,
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

        if (function_exists('WC') && WC()->session) {
            $discount = (float) WC()->session->get($this->prefix . 'total_savings', 0);
        }

        if ($discount > 0) {
            $this->add_discount_fee($cart, $discount);
            $this->log("Persistence: Applied stored discount of {$discount}");
        }
    }

    /**
     * Removes only free shipping when discount is applied
     *
     * @param array $rates Available shipping rates
     * @param array $package Shipping package
     * @return array Modified shipping rates
     */
    public function remove_free_shipping_when_discount_applied(array $rates, array $package): array
    {
        // Skip if free shipping shouldn't be disabled
        if (!$this->disable_free_shipping) {
            return $rates;
        }

        $discount = 0;

        if (function_exists('WC') && WC()->session) {
            $discount = (float) WC()->session->get($this->prefix . 'total_savings', 0);
        }

        if ($discount > 0) {
            foreach ($rates as $rate_id => $rate) {
                if ('free_shipping' === $rate->method_id) {
                    unset($rates[$rate_id]);
                }
            }
            $this->log("Removed free shipping due to active discount");
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

        if (function_exists('WC') && WC()->session) {
            $discount_amount = (float) WC()->session->get($this->prefix . 'total_savings', 0);
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

            if (function_exists('WC') && WC()->cart) {
                $discount = $this->discount_service->calculate_discount(WC()->cart);
                WC()->session->set($this->prefix . 'total_savings', $discount);
                $this->log("AJAX: Updated discount to {$discount}");
            }

            wp_send_json_success([
                'discount' => $discount,
                'discount_formatted' => function_exists('wc_price') ? wc_price($discount) : '' . number_format($discount, 2)
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

        if (function_exists('WC') && WC()->session) {
            $discount = (float) WC()->session->get($this->prefix . 'total_savings', 0);
        }

        ob_start();
        if ($discount > 0) {
            ?>
            <div class="mini-cart-discount">
                <span class="mini-cart-discount-label"><?php echo esc_html($this->discount_label); ?></span>
                <span
                    class="mini-cart-discount-amount">-<?php echo function_exists('wc_price') ? wc_price($discount) : '' . number_format($discount, 2); ?></span>
            </div>
            <?php
        }
        $fragments['.mini-cart-discount'] = ob_get_clean();

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

        if (function_exists('WC') && WC()->session) {
            $discount = (float) WC()->session->get($this->prefix . 'total_savings', 0);
        }

        if ($discount > 0) {
            ?>
            <div class="mini-cart-discount">
                <span class="mini-cart-discount-label"><?php echo esc_html($this->discount_label); ?></span>
                <span
                    class="mini-cart-discount-amount">-<?php echo function_exists('wc_price') ? wc_price($discount) : '' . number_format($discount, 2); ?></span>
            </div>
            <?php
        }
    }
}