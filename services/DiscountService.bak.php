<?php
namespace B4G1F\Services;

class DiscountService
{
    const MIN_ITEMS_FOR_DISCOUNT = 4;
    const MAX_PRICE_FOR_CHEAPEST = 110;
    
    /**
     * Cache for cart calculations
     */
    private $cache = [
        'hash' => '',
        'discount' => 0,
        'prices' => [],
        'total_items' => 0
    ];

    /**
     * Calculates the total discount to apply based on cart contents
     *
     * @param mixed $cart WooCommerce cart object
     * @return float Total discount amount
     */
    public function calculate_discount($cart)
    {
        // Quick null/invalid checks
        if ($cart === null || !$this->is_valid_cart($cart)) {
            return 0;
        }

        // Generate cart hash for cache checking
        $cart_hash = $this->generate_cart_hash($cart);
        if ($cart_hash === $this->cache['hash']) {
            return $this->cache['discount'];
        }

        // Get total items (cached)
        $total_items = $this->get_cached_total_items($cart, $cart_hash);
        if ($total_items < self::MIN_ITEMS_FOR_DISCOUNT) {
            return $this->cache_result($cart_hash, 0);
        }

        // Get sorted prices (cached)
        $prices = $this->get_cached_sorted_prices($cart, $cart_hash);
        if (empty($prices)) {
            return $this->cache_result($cart_hash, 0);
        }

        // Calculate discount based on number of items
        return $this->calculate_discount_by_items($total_items, $prices, $cart_hash);
    }

    /**
     * Generates a unique hash for the cart state
     */
    private function generate_cart_hash($cart): string
    {
        $items = $this->get_cart_items($cart);
        if (!is_array($items)) {
            return '';
        }

        $hash_data = [];
        foreach ($items as $item) {
            $qty = $this->get_item_quantity($item);
            $price = $this->get_item_price($item);
            if ($qty > 0 && $price > 0) {
                $hash_data[] = "$qty:$price";
            }
        }

        return md5(implode('|', $hash_data));
    }

    /**
     * Gets cached total items or calculates if needed
     */
    private function get_cached_total_items($cart, $cart_hash): int
    {
        if ($cart_hash === $this->cache['hash']) {
            return $this->cache['total_items'];
        }

        $total_items = $this->count_eligible_items($cart);
        $this->cache['total_items'] = $total_items;
        return $total_items;
    }

    /**
     * Gets cached sorted prices or calculates if needed
     */
    private function get_cached_sorted_prices($cart, $cart_hash): array
    {
        if ($cart_hash === $this->cache['hash']) {
            return $this->cache['prices'];
        }

        $prices = $this->get_item_prices($cart);
        sort($prices);
        $this->cache['prices'] = $prices;
        return $prices;
    }

    /**
     * Calculates discount based on number of items
     */
    private function calculate_discount_by_items(int $total_items, array $prices, string $cart_hash): float
    {
        // Special case for 4-7 items
        if ($total_items >= self::MIN_ITEMS_FOR_DISCOUNT && $total_items < 2 * self::MIN_ITEMS_FOR_DISCOUNT) {
            if ($prices[0] >= self::MAX_PRICE_FOR_CHEAPEST) {
                return $this->cache_result($cart_hash, $prices[0]);
            }
            return $this->cache_result($cart_hash, isset($prices[1]) ? $prices[1] : 0);
        }

        // For multiples of MIN_ITEMS_FOR_DISCOUNT (8 items or more)
        if ($total_items >= 2 * self::MIN_ITEMS_FOR_DISCOUNT) {
            $complete_sets = floor($total_items / self::MIN_ITEMS_FOR_DISCOUNT);
            $total_discount = array_sum(array_slice($prices, 0, $complete_sets));
            return $this->cache_result($cart_hash, $total_discount);
        }

        return $this->cache_result($cart_hash, 0);
    }

    /**
     * Caches and returns the discount result
     */
    private function cache_result(string $cart_hash, float $discount): float
    {
        $this->cache['hash'] = $cart_hash;
        $this->cache['discount'] = $discount;
        return $discount;
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