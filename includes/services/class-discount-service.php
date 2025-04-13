<?php
/**
 * Discount Service
 *
 * Handles discount calculations for the buy 4 get cheapest free promotion
 *
 * @package NextOrderPlus
 * @since 1.0.0
 */

namespace NOP\Services;

use NOP\Base\NOP_Base;
use NOP\Util\NOP_Logger;

/**
 * Discount Service class
 */
class NOP_Discount_Service extends NOP_Base
{
    /**
     * Minimum number of items required for the discount to apply
     */
    const MIN_ITEMS_FOR_DISCOUNT = 4;

    /**
     * Initialize the service
     *
     * Sets up any necessary hooks and filters
     *
     * @return void
     */
    public function init(): void
    {
        // No direct hooks needed for this service
        $this->log('Discount service initialized');
    }

    /**
     * Calculates the total discount to apply based on cart contents
     * 
     * @param mixed $cart WooCommerce cart object
     * @return float Total discount amount
     */
    public function calculate_discount($cart): float
    {
        // Return 0 if cart is null
        if ($cart === null) {
            $this->log('Cart is null, no discount applied', 'info');
            return 0;
        }

        // Verify we have a valid cart object
        if (!$this->is_valid_cart($cart)) {
            $this->log('Invalid cart object, no discount applied', 'warning');
            return 0;
        }

        $total_items = $this->count_eligible_items($cart);
        $this->log("Total eligible items in cart: {$total_items}");

        // Early return if not enough items
        if ($total_items < self::MIN_ITEMS_FOR_DISCOUNT) {
            return 0;
        }

        $prices = $this->get_item_prices($cart);
        if (empty($prices)) {
            $this->log('No valid prices found in cart', 'warning');
            return 0;
        }

        // Sort prices to get cheapest items first
        sort($prices);
        $groups = floor($total_items / self::MIN_ITEMS_FOR_DISCOUNT);
        $total_discount = 0;

        // Calculate discount based on cheapest items
        for ($i = 0; $i < $groups; $i++) {
            $total_discount += $prices[$i];
            $this->log("Adding item #{$i} to discount: " . $prices[$i]);
        }

        $this->log("Total discount calculated: {$total_discount}");
        return (float) $total_discount;
    }

    /**
     * Validates if the provided cart object is usable
     * 
     * @param mixed $cart Cart object to validate
     * @return bool Whether cart is valid
     */
    private function is_valid_cart($cart): bool
    {
        if (!is_object($cart)) {
            return false;
        }

        // Check for WC_Cart
        if (method_exists($cart, 'get_cart')) {
            return true;
        }

        // Check for Store API Cart
        if (method_exists($cart, 'get_items')) {
            // Additional verification to ensure it's not a Customer object
            return method_exists($cart, 'get_cart_items') || method_exists($cart, 'get_totals');
        }

        return false;
    }

    /**
     * Counts eligible items in cart, properly handling product bundles
     * 
     * @param mixed $cart Cart object
     * @return int Number of eligible items
     */
    private function count_eligible_items($cart): int
    {
        $count = 0;
        $cart_items = $this->get_cart_items($cart);

        if (!is_array($cart_items)) {
            return 0;
        }

        foreach ($cart_items as $cart_item) {
            if (!$cart_item) {
                continue;
            }

            // Skip bundled items to prevent double counting
            if (isset($cart_item['bundled_by'])) {
                continue;
            }

            // For product bundles
            if (
                isset($cart_item['data']) &&
                is_object($cart_item['data']) &&
                method_exists($cart_item['data'], 'is_type') &&
                $cart_item['data']->is_type('bundle')
            ) {
                $count += $this->get_item_quantity($cart_item);
                continue;
            }

            // Regular products
            $count += $this->get_item_quantity($cart_item);
        }

        return $count;
    }

    /**
     * Safely gets cart items regardless of cart type
     * 
     * @param mixed $cart Cart object
     * @return array|null Cart items or null if invalid
     */
    private function get_cart_items($cart): ?array
    {
        if (!is_object($cart)) {
            return null;
        }

        if (method_exists($cart, 'get_cart')) {
            return $cart->get_cart();
        }

        if (method_exists($cart, 'get_items') && $this->is_valid_cart($cart)) {
            return $cart->get_items();
        }

        return null;
    }

    /**
     * Safely gets item quantity regardless of item type
     * 
     * @param mixed $cart_item Cart item
     * @return int Item quantity
     */
    private function get_item_quantity($cart_item): int
    {
        if (!$cart_item) {
            return 0;
        }

        if (isset($cart_item['quantity'])) {
            return (int) $cart_item['quantity'];
        }

        if (is_object($cart_item) && method_exists($cart_item, 'get_quantity')) {
            return (int) $cart_item->get_quantity();
        }

        return 0;
    }

    /**
     * Safely gets item price regardless of item type
     * 
     * @param mixed $cart_item Cart item
     * @return float Item price
     */
    private function get_item_price($cart_item): float
    {
        if (!$cart_item) {
            return 0;
        }

        // Classic cart item
        if (
            isset($cart_item['data']) &&
            is_object($cart_item['data']) &&
            method_exists($cart_item['data'], 'get_price')
        ) {
            return (float) $cart_item['data']->get_price();
        }

        // Store API cart item
        if (is_object($cart_item) && method_exists($cart_item, 'get_product')) {
            $product = $cart_item->get_product();
            if ($product && is_object($product) && method_exists($product, 'get_price')) {
                return (float) $product->get_price();
            }
        }

        return 0;
    }

    /**
     * Safely extracts individual item prices from cart
     * 
     * @param mixed $cart Cart object
     * @return array Array of item prices
     */
    private function get_item_prices($cart): array
    {
        $prices = [];
        $cart_items = $this->get_cart_items($cart);

        if (!is_array($cart_items)) {
            return [];
        }

        foreach ($cart_items as $cart_item) {
            if (!$cart_item) {
                continue;
            }

            // Skip bundled items
            if (isset($cart_item['bundled_by'])) {
                continue;
            }

            $product_price = $this->get_item_price($cart_item);
            $quantity = $this->get_item_quantity($cart_item);

            if ($product_price > 0) {
                for ($i = 0; $i < $quantity; $i++) {
                    $prices[] = $product_price;
                }
            }
        }

        return $prices;
    }
}