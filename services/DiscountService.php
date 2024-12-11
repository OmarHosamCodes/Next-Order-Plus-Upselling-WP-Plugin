<?php
namespace BXG1F\Services;

class DiscountService {
    // Constant defining minimum items needed for discount to apply
    const MIN_ITEMS_FOR_DISCOUNT = 3;

    /**
     * Calculates the total discount to apply based on cart contents
     * Works with both Classic Cart/Checkout and Block Cart/Checkout
     *
     * @param WC_Cart|WC_Store_API_Cart|null $cart WooCommerce cart object (Classic or Block Cart)
     * @return float Total discount amount
     */
    public function calculate_discount($cart = null): float {
        if (!$cart) {
            return 0.0;
        }

        // Handle both Classic Cart and Block Cart with null coalescing
        $total_items = method_exists($cart, 'get_cart_contents_count')
        ? ($cart->get_cart_contents_count() ?? 0)
        : 0;

        // Early return if not enough items
        if ($total_items < self::MIN_ITEMS_FOR_DISCOUNT) {
            return 0.0;
        }

        $prices = $this->get_item_prices($cart);
        if (empty($prices)) {
            return 0.0;
        }

        // Sort prices ascending to get cheapest items first
        sort($prices);

        // Calculate how many complete groups of 4 items exist
        $groups = floor($total_items / self::MIN_ITEMS_FOR_DISCOUNT);
        $total_discount = 0.0;

        // Add price of cheapest item for each complete group
        // Ensure we don't exceed array bounds
        for ($i = 0; $i < $groups && $i < count($prices); $i++) {
            $total_discount += (float)($prices[$i] ?? 0.0);
        }

        return $total_discount;
    }

    /**
     * Extracts individual item prices from cart
     * Compatible with both Classic Cart and Block Cart
     *
     * @param WC_Cart|WC_Store_API_Cart|null $cart WooCommerce cart object
     * @return array Array of individual item prices
     */
    private function get_item_prices($cart = null): array {
        if (!$cart) {
            return [];
        }

        $prices = [];

        // Handle both Classic Cart and Block Cart get_cart methods
        try {
            $cart_items = method_exists($cart, 'get_cart')
            ? ($cart->get_cart() ?? [])
            : ($cart->get_items() ?? []);
        } catch (\Exception $e) {
            return [];
        }

        foreach ($cart_items as $cart_item) {
            if (!$cart_item) {
                continue;
            }

            try {
                // Handle price retrieval for both cart types with null checks
                $product_data = $cart_item['data'] ?? $cart_item;
                if (!$product_data) {
                    continue;
                }

                $product_price = 0.0;
                if (method_exists($product_data, 'get_price')) {
                    $product_price = floatval($product_data->get_price() ?? 0);
                } elseif (method_exists($cart_item, 'get_product')) {
                    $product = $cart_item->get_product();
                    if ($product && method_exists($product, 'get_price')) {
                        $product_price = floatval($product->get_price() ?? 0);
                    }
                }

                $quantity = intval($cart_item['quantity'] ?? $cart_item->get_quantity() ?? 1);

                if ($product_price > 0 && $quantity > 0) {
                    // Protect against unreasonably large quantities
                    $quantity = min($quantity, 1000);
                    for ($i = 0; $i < $quantity; $i++) {
                        $prices[] = $product_price;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $prices;
    }
}
