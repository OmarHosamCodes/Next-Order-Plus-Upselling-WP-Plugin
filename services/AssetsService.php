<?php
namespace BXG1F\Services;

/**
 * Class AssetsService
 * 
 * Handles enqueueing and managing frontend assets (JS and CSS) for the Buy 4 Get 1 Free promotion.
 * Responsible for loading cart discount scripts, mini cart functionality, and custom styling.
 *
 * @package BXG1F\Services
 */
class AssetsService {
    /**
     * Enqueues all required JavaScript and CSS assets
     * 
     * Loads cart discount scripts, mini cart functionality, and localizes AJAX data.
     * Also adds custom styling for discount display in various cart contexts.
     *
     * @return void
     */
    public function enqueue_assets() {
        // Enqueue cart discount functionality
        wp_enqueue_script('b4gf-ajax', plugins_url('assets/js/cart-discount.js', dirname(__FILE__)), ['jquery'], '1.0', true);
        
        // Enqueue mini cart discount functionality with cart fragments dependency
        wp_enqueue_script('b4gf-mini-cart', plugins_url('assets/js/mini-cart-discount.js', dirname(__FILE__)), ['jquery', 'wc-cart-fragments'], '1.0', true);

        // Localize AJAX data for cart discount script
        wp_localize_script('b4gf-ajax', 'b4gf_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('b4gf-nonce')
        ]);

        $this->add_custom_styles();
    }

    /**
     * Adds custom inline styles for discount display
     * 
     * Injects CSS rules for styling discount elements in both classic cart/checkout
     * and block-based cart/checkout interfaces. Handles styling for:
     * - Checkout fee blocks
     * - Mini cart discount display
     * - Discount amount coloring
     *
     * @return void
     */
    private function add_custom_styles() {
        wp_add_inline_style('woocommerce-general', '
            .wp-block-woocommerce-checkout-order-summary-fee-block, 
            .wc-block-components-totals-fees {
                color: #EF5A59 !important;
            }
            .mini-cart-discount {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-top: 1px solid #eee;
                color: #292929;
                font-weight: inherit;
                font-size: initial;
            }
            .mini-cart-discount-amount {
                color: #EF5A59;
            }
        ');
    }
}