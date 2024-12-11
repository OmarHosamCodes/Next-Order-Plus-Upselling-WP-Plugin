<?php
namespace BXG1F\Services;

class CartService {
    private $discount_service;
    
    public function __construct(DiscountService $discount_service) {
        $this->discount_service = $discount_service;
    }

    /**
     * Applies discount to the cart
     */
    public function apply_cart_discount($cart) {
        // Skip for admin areas (except AJAX) and direct REST requests
        if ((is_admin() && !wp_doing_ajax()) || (defined('REST_REQUEST') && REST_REQUEST && !$this->is_store_api_request())) {
            return;
        }

        // Calculate discount
        $discount = $this->discount_service->calculate_discount($cart);
        
        // Always store the current discount in session
        WC()->session->set('bxg1f_total_savings', $discount);
        
        if ($discount > 0) {
            $this->add_discount_fee($cart, $discount);
        }
    }

    /**
     * Check if the current request is from Store API
     */
    private function is_store_api_request() {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }
        
        $rest_prefix = trailingslashit(rest_get_url_prefix());
        $request_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
        
        return strpos($request_uri, $rest_prefix . 'wc/store/') !== false;
    }

    /**
     * Adds discount fee to cart
     */
    private function add_discount_fee($cart, $discount) {
        if (method_exists($cart, 'add_fee')) {
            // Classic Cart
            $cart->add_fee(__('خصم: البلاك فريداي', 'buy-x-get-cheapest-free'), -$discount, false);
        } else {
            // Block Cart
            $cart->add_fee([
                'name' => __('خصم: البلاك فريداي', 'buy-x-get-cheapest-free'),
                'amount' => -$discount,
                'taxable' => false,
            ]);
        }
    }

    /**
     * Makes sure discount persists during checkout
     */
    public function apply_discount_persistently($cart) {
        // Get stored discount
        $discount = WC()->session->get('bxg1f_total_savings', 0);
        
        if ($discount > 0) {
            $this->add_discount_fee($cart, $discount);
        }
    }

    /**
     * Removes only free shipping when discount is applied
     */
    public function remove_free_shipping_when_discount_applied($rates, $package) {
        $discount = WC()->session->get('bxg1f_total_savings', 0);
        
        if ($discount > 0) {
            foreach ($rates as $rate_id => $rate) {
                if ('free_shipping' === $rate->method_id) {
                    unset($rates[$rate_id]);
                }
            }
        }
        return $rates;
    }

    /**
     * Saves discount to order
     */
    public function save_discount_to_order($order, $data) {
        $discount_amount = WC()->session->get('bxg1f_total_savings', 0);

        if ($discount_amount > 0) {
            $item = new \WC_Order_Item_Fee();
            $item->set_name(__('خصم: البلاك فريداي', 'buy-x-get-cheapest-free'));
            $item->set_amount(-$discount_amount);
            $item->set_total(-$discount_amount);
            $order->add_item($item);
            
            // Store discount amount as order meta
            $order->update_meta_data('_bxg1f_discount_amount', $discount_amount);
        }
    }

    /**
     * AJAX handler for mini cart updates
     */
    public function update_mini_cart_discount() {
        check_ajax_referer('b4gf-nonce', 'nonce');

        $discount = $this->discount_service->calculate_discount(WC()->cart);
        WC()->session->set('bxg1f_total_savings', $discount);

        wp_send_json_success([
            'discount' => $discount,
            'discount_formatted' => wc_price($discount)
        ]);
    }

    /**
     * Updates mini cart fragments
     */
    public function add_mini_cart_discount_fragment($fragments) {
        $discount = WC()->session->get('bxg1f_total_savings', 0);

        ob_start();
        if ($discount > 0) {
            ?>
            <div class="mini-cart-discount">
                <span class="mini-cart-discount-label"><?php _e('خصم: البلاك فريداي', 'buy-x-get-cheapest-free'); ?></span>
                <span class="mini-cart-discount-amount">-<?php echo wc_price($discount); ?></span>
            </div>
            <?php
        }
        $fragments['.mini-cart-discount'] = ob_get_clean();

        return $fragments;
    }

    /**
     * Displays discount in mini cart
     */
    public function display_mini_cart_discount() {
        $discount = WC()->session->get('bxg1f_total_savings', 0);
        if ($discount > 0) {
            ?>
            <div class="mini-cart-discount">
                <span class="mini-cart-discount-label"><?php _e('خصم: البلاك فريداي', 'buy-x-get-cheapest-free'); ?></span>
                <span class="mini-cart-discount-amount">-<?php echo wc_price($discount); ?></span>
            </div>
            <?php
        }
    }
}