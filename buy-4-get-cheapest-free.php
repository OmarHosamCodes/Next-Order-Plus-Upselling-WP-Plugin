<?php

/**
 * Plugin Name: Buy 4 Get Cheapest Free
 * Description: Automatically apply a discount to the cheapest product in the WooCommerce cart when 4 or more items are present. Compatible with both Classic and Block Cart/Checkout.
 * Version: 1.4.5
 * Author: SoM
 * Text Domain: buy-4-get-cheapest-free
 * 
 * @package Buy4GetCheapestFree
 */

/**
 * Prevent direct access to this file
 */
if (!defined('ABSPATH')) {
    exit;
}

// Include service classes
require_once plugin_dir_path(__FILE__) . 'services/DiscountService.php';
require_once plugin_dir_path(__FILE__) . 'services/AssetsService.php';
require_once plugin_dir_path(__FILE__) . 'services/CartService.php';
require_once plugin_dir_path(__FILE__) . 'services/CouponService.php';

use B4G1F\Services\DiscountService;
use B4G1F\Services\AssetsService;
use B4G1F\Services\CartService;
use B4G1F\Services\CouponService;

/**
 * Main plugin class implementing singleton pattern
 * 
 * Handles initialization of services and hooks for the Buy 4 Get Cheapest Free promotion.
 * Ensures compatibility with both Classic and Block Cart/Checkout interfaces.
 * 
 * @since 1.0.0
 */
class Plugin
{
    /**
     * Singleton instance of the plugin
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Service instances
     *
     * @var DiscountService Handles discount calculations
     * @var AssetsService Manages frontend assets
     * @var CartService Handles cart operations
     * @var CouponService Manages coupon validations
     */
    private $discount_service;
    private $assets_service;
    private $cart_service;
    private $coupon_service;

    /**
     * Get singleton instance of the plugin
     *
     * Creates new instance if one doesn't exist, otherwise returns existing instance
     *
     * @since 1.0.0
     * @return Plugin Singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Protected constructor to prevent direct instantiation
     *
     * Initializes services and hooks
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->init_services();
        $this->init_hooks();
    }

    /**
     * Initialize service classes
     *
     * Creates instances of all required services used by the plugin
     *
     * @since 1.0.0
     * @return void
     */
    private function init_services()
    {
        $this->discount_service = new DiscountService();
        $this->assets_service = new AssetsService();
        $this->cart_service = new CartService($this->discount_service);
        $this->coupon_service = new CouponService();
    }

    /**
     * Initialize WordPress hooks
     *
     * Sets up all action and filter hooks for:
     * - Asset loading
     * - Cart discount application
     * - Block editor compatibility
     * - Mini cart functionality
     * - Shipping and coupon handling
     *
     * @since 1.0.0
     * @return void
     */
    private function init_hooks()
    {
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this->assets_service, 'enqueue_assets']);

        // Cart hooks for both classic and block checkout
        add_action('woocommerce_cart_calculate_fees', [$this->cart_service, 'apply_cart_discount'], 20);
        add_action('woocommerce_checkout_create_order', [$this->cart_service, 'save_discount_to_order'], 20, 2);
        add_action('woocommerce_before_calculate_totals', [$this->cart_service, 'apply_discount_persistently'], 10);

        // Block Cart and Checkout compatibility hooks
        add_action('woocommerce_store_api_cart_update_customer_from_request', [$this->cart_service, 'apply_cart_discount']);
        add_action('woocommerce_store_api_cart_update_order_from_request', [$this->cart_service, 'apply_cart_discount']); // updated
        add_action('woocommerce_store_api_cart_items_updated', [$this->cart_service, 'apply_cart_discount']);
        add_action('woocommerce_store_api_checkout_update_order_meta', [$this->cart_service, 'apply_cart_discount']);

        // Mini cart hooks
        add_action('wp_ajax_update_mini_cart_discount', [$this->cart_service, 'update_mini_cart_discount']);
        add_action('wp_ajax_nopriv_update_mini_cart_discount', [$this->cart_service, 'update_mini_cart_discount']);
        add_filter('woocommerce_add_to_cart_fragments', [$this->cart_service, 'add_mini_cart_discount_fragment']);
        add_action('woocommerce_mini_cart_contents', [$this->cart_service, 'display_mini_cart_discount'], 99);

        // Shipping and coupon hooks
        add_filter('woocommerce_package_rates', [$this->cart_service, 'remove_free_shipping_when_discount_applied'], 10, 2);
        add_filter('woocommerce_coupon_is_valid', [$this->coupon_service, 'validate_coupon'], 10, 2);
        add_filter('woocommerce_coupon_error', [$this->coupon_service, 'modify_error_message'], 10, 3);
    }
}

/**
 * Initialize the plugin when WordPress loads
 *
 * Checks for WooCommerce dependency before initializing
 *
 * @since 1.0.0
 */
add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        Plugin::getInstance();
    }
});
