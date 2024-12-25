<?php
namespace B4G1F\Services;

class DiscountService
{
    const MIN_ITEMS_FOR_DISCOUNT = 4;
    const MAX_PRICE_FOR_CHEAPEST = 110;
    
  /**
     * Calculates the total discount to apply based on cart contents
     * 
     * @param mixed $cart WooCommerce cart object
     * @return float Total discount amount
     */
    public function calculate_discount($cart)
    {
        // Return 0 if cart is null
        if ($cart === null) {
            return 0;
        }

        // Verify we have a valid cart object
        if (!$this->is_valid_cart($cart)) {
            return 0;
        }

        $total_items = $this->count_eligible_items($cart);
        
        // Early return if not enough items for even one discount
        if ($total_items < self::MIN_ITEMS_FOR_DISCOUNT) {
            return 0;
        }

        $prices = $this->get_item_prices($cart);
        if (empty($prices)) {
            return 0;
        }

        sort($prices); // Sort prices in ascending order

        // Get the number of complete sets and remainder
        $complete_sets = floor($total_items / self::MIN_ITEMS_FOR_DISCOUNT);
        $remainder = $total_items % self::MIN_ITEMS_FOR_DISCOUNT;

        // If it's exactly a multiple of MIN_ITEMS_FOR_DISCOUNT
        if ($remainder === 0) {
            return $complete_sets * $prices[0];
        }

        // If it's one more than a multiple of MIN_ITEMS_FOR_DISCOUNT through one less than the next multiple
        // Example: for MIN_ITEMS_FOR_DISCOUNT = 4
        // This handles items 5-7, 9-11, 13-15, etc.
        if ($remainder > 0 && $remainder < self::MIN_ITEMS_FOR_DISCOUNT - 1) {
            // Check if cheapest item is less than MAX_PRICE_FOR_CHEAPEST for the extra items case
            if ($prices[0] >= self::MAX_PRICE_FOR_CHEAPEST) {
                return $prices[0] + ($complete_sets * $prices[0]);
            }
            // Return second cheapest item's price as discount for the extra items,
            // plus the normal discount for complete sets
            return (isset($prices[1]) ? $prices[1] : 0) + ($complete_sets * $prices[0]);
        }

        // For any other number of items, apply the complete sets discount
        return $complete_sets * $prices[0];
    }


    /**
     * Validates if the provided cart object is usable
     * 
     * @param mixed $cart
     * @return bool
     */
    private function is_valid_cart($cart)
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
     * @param mixed $cart
     * @return int
     */
    private function count_eligible_items($cart)
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
            if (isset($cart_item['data']) && 
                is_object($cart_item['data']) && 
                method_exists($cart_item['data'], 'is_type') && 
                $cart_item['data']->is_type('bundle')) {
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
     * @param mixed $cart
     * @return array|null
     */
    private function get_cart_items($cart)
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
     * @param mixed $cart_item
     * @return int
     */
    private function get_item_quantity($cart_item)
    {
        if (!$cart_item) {
            return 0;
        }

        if (isset($cart_item['quantity'])) {
            return (int)$cart_item['quantity'];
        }

        if (is_object($cart_item) && method_exists($cart_item, 'get_quantity')) {
            return (int)$cart_item->get_quantity();
        }

        return 0;
    }

    /**
     * Safely gets item price regardless of item type
     * 
     * @param mixed $cart_item
     * @return float
     */
    private function get_item_price($cart_item)
    {
        if (!$cart_item) {
            return 0;
        }

        // Classic cart item
        if (isset($cart_item['data']) && 
            is_object($cart_item['data']) && 
            method_exists($cart_item['data'], 'get_price')) {
            return floatval($cart_item['data']->get_price());
        }

        // Store API cart item
        if (is_object($cart_item) && method_exists($cart_item, 'get_product')) {
            $product = $cart_item->get_product();
            if ($product && is_object($product) && method_exists($product, 'get_price')) {
                return floatval($product->get_price());
            }
        }

        return 0;
    }

    /**
     * Safely extracts individual item prices from cart
     * 
     * @param mixed $cart
     * @return array
     */
    private function get_item_prices($cart)
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