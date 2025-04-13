<?php
/**
 * Assets Service
 *
 * Handles script and style loading for the plugin
 *
 * @package NextOrderPlus
 * @since 1.0.0
 */

namespace NOP\Services;

use NOP\Base\NOP_Base;
use NOP\Util\NOP_Logger;

/**
 * Assets Service class
 */
class NOP_Assets_Service extends NOP_Base
{
    /**
     * Asset version for cache busting
     *
     * @var string
     */
    private $version;

    /**
     * Constructor
     *
     * Sets up assets version
     *
     * @param NOP_Logger|null $logger Optional logger instance
     */
    public function __construct($logger = null)
    {
        parent::__construct($logger);
        $this->version = defined('NOP_VERSION') ? NOP_VERSION : '1.0.0';
    }

    /**
     * Initialize the service
     *
     * Sets up hooks for asset loading
     *
     * @return void
     */
    public function init(): void
    {
        $this->log('Assets service initialized');
    }

    /**
     * Enqueues all required JavaScript and CSS assets
     * 
     * Loads cart discount scripts, mini cart functionality, and localizes AJAX data.
     * Also adds custom styling for discount display in various cart contexts.
     *
     * @return void
     */
    public function enqueue_assets(): void
    {
        // Skip on admin pages
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        // Only load on frontend cart/checkout pages
        if (!is_cart() && !is_checkout() && !wp_doing_ajax()) {
            $load_assets = apply_filters($this->prefix . 'load_assets_globally', false);
            if (!$load_assets) {
                return;
            }
        }

        try {
            // Enqueue cart discount functionality
            wp_enqueue_script(
                $this->prefix . 'cart_discount',
                $this->plugin_url . 'assets/js/nop-cart-discount.js',
                ['jquery'],
                $this->version,
                true
            );

            // Enqueue mini cart discount functionality with cart fragments dependency
            wp_enqueue_script(
                $this->prefix . 'mini_cart',
                $this->plugin_url . 'assets/js/nop-mini-cart.js',
                ['jquery', 'wc-cart-fragments'],
                $this->version,
                true
            );

            // Localize AJAX data for cart discount script
            wp_localize_script($this->prefix . 'cart_discount', $this->prefix . 'ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce($this->prefix . 'nonce')
            ]);

            $this->add_custom_styles();
            $this->log('Assets enqueued successfully');
        } catch (\Exception $e) {
            $this->log('Error enqueueing assets: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Adds custom inline styles for discount display
     * 
     * Injects CSS rules for styling discount elements in both classic cart/checkout
     * and block-based cart/checkout interfaces.
     *
     * @return void
     */
    private function add_custom_styles(): void
    {
        wp_add_inline_style('woocommerce-general', '
            /* Checkout fee blocks styling */
            .wp-block-woocommerce-checkout-order-summary-fee-block, 
            .wc-block-components-totals-fees {
                color: #EF5A59 !important;
            }
            
            /* Mini cart discount styling */
            .mini-cart-discount {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-top: 1px solid #eee;
                color: #292929;
                font-weight: inherit;
                font-size: initial;
            }
            
            /* Discount amount styling */
            .mini-cart-discount-amount {
                color: #EF5A59;
            }
        ');
    }
}