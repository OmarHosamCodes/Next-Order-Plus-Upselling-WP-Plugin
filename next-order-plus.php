<?php
/**
 * Plugin Name: Next Order Plus
 * Description: Enhanced WooCommerce promotion system with advanced upselling rules. Configure customizable conditions and discounts based on cart contents.
 * Version: 1.1.0
 * Author:      Omar Hosam
 * Author URI:  school-of-markiting.com
 * Text Domain: next-order-plus
 * 
 * @package NextOrderPlus
 */

/**
 * Prevent direct access to this file
 */
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NOP_VERSION', '1.1.0');
define('NOP_PREFIX', 'nop_');
define('NOP_DIR', plugin_dir_path(__FILE__));
define('NOP_URL', plugin_dir_url(__FILE__));
define('NOP_DEBUG', false); // Set to true for development

// Load core files
require_once NOP_DIR . 'includes/base/class-base.php';
require_once NOP_DIR . 'includes/util/class-logger.php';

// Load models
require_once NOP_DIR . 'includes/models/class-rule.php';

// Load service classes
require_once NOP_DIR . 'includes/services/class-rules-manager-service.php';
require_once NOP_DIR . 'includes/services/class-discount-service.php';
require_once NOP_DIR . 'includes/services/class-assets-service.php';
require_once NOP_DIR . 'includes/services/class-cart-service.php';
require_once NOP_DIR . 'includes/services/class-coupon-service.php';
require_once NOP_DIR . 'includes/services/class-admin-service.php';

// Load admin UI
require_once NOP_DIR . 'includes/admin/class-rules-admin.php';

/**
 * Main plugin class implementing singleton pattern
 * 
 * Manages initialization of services and hooks for the Next Order Plus promotion system.
 * Ensures compatibility with both Classic and Block Cart/Checkout interfaces.
 * 
 * @since 1.0.0
 */
class NOP_Plugin
{
    /**
     * Singleton instance of the plugin
     *
     * @var NOP_Plugin|null
     */
    private static $instance = null;

    /**
     * Service instances
     *
     * @var NOP\Services\NOP_Rules_Manager Handles upsell rules management
     * @var NOP\Services\NOP_Discount_Service Handles legacy discount calculations
     * @var NOP\Services\NOP_Assets_Service Manages frontend assets
     * @var NOP\Services\NOP_Cart_Service Handles cart operations
     * @var NOP\Services\NOP_Coupon_Service Manages coupon validations
     * @var NOP\Services\NOP_Admin_Service Handles admin interface and settings
     * @var NOP\Admin\NOP_Rules_Admin Handles rules admin UI
     * @var NOP\Util\NOP_Logger Handles debugging and logging
     */
    private $rules_manager;
    private $discount_service;
    private $assets_service;
    private $cart_service;
    private $coupon_service;
    private $admin_service;
    private $rules_admin;
    private $logger;

    /**
     * Get singleton instance of the plugin
     *
     * Creates new instance if one doesn't exist, otherwise returns existing instance
     *
     * @since 1.0.0
     * @return NOP_Plugin Singleton instance
     */
    public static function get_instance(): self
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
     * Get access to the rules manager instance
     *
     * @since 1.1.0
     * @return NOP\Services\NOP_Rules_Manager|null Rules manager instance
     */
    public function get_rules_manager()
    {
        return $this->rules_manager;
    }

    /**
     * Initialize service classes
     *
     * Creates instances of all required services used by the plugin
     *
     * @since 1.0.0
     * @return void
     */
    private function init_services(): void
    {
        // Initialize logger first for debugging
        $this->logger = new NOP\Util\NOP_Logger();

        // Initialize admin service first to get settings
        $this->admin_service = new NOP\Services\NOP_Admin_Service($this->logger);

        // Get plugin options
        $options = $this->admin_service->get_options();

        // Set debug mode from settings if available
        if (isset($options['debug_mode'])) {
            define('NOP_DEBUG_RUNTIME', (bool) $options['debug_mode']);
        }

        // Core services - initialize rules manager first
        $this->rules_manager = new NOP\Services\NOP_Rules_Manager($this->logger);

        // Initialize other services
        $this->discount_service = new NOP\Services\NOP_Discount_Service($this->logger, $this->admin_service);
        $this->assets_service = new NOP\Services\NOP_Assets_Service($this->logger);
        $this->cart_service = new NOP\Services\NOP_Cart_Service(
            $this->rules_manager,
            $this->discount_service,
            $this->logger,
            $this->admin_service
        );
        $this->coupon_service = new NOP\Services\NOP_Coupon_Service($this->logger, $this->admin_service);

        // Admin UI
        $this->rules_admin = new NOP\Admin\NOP_Rules_Admin($this->rules_manager, $this->logger);

        // Initialize all services
        $this->logger->init();
        $this->admin_service->init();
        $this->rules_manager->init();
        $this->discount_service->init();
        $this->assets_service->init();
        $this->cart_service->init();
        $this->coupon_service->init();
        $this->rules_admin->init();
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
    private function init_hooks(): void
    {
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this->assets_service, 'enqueue_assets']);

        // Cart hooks for both classic and block checkout
        add_action('woocommerce_cart_calculate_fees', [$this->cart_service, 'apply_cart_discount'], 20);
        add_action('woocommerce_checkout_create_order', [$this->cart_service, 'save_discount_to_order'], 20, 2);
        add_action('woocommerce_before_calculate_totals', [$this->cart_service, 'apply_discount_persistently'], 10);

        // Block Cart and Checkout compatibility hooks
        add_action('woocommerce_store_api_cart_update_customer_from_request', [$this->cart_service, 'apply_cart_discount']);

        // Handle coupon validation and restrictions
        add_filter('woocommerce_coupon_is_valid', [$this->coupon_service, 'validate_coupon'], 10, 2);
        add_filter('woocommerce_coupon_error', [$this->coupon_service, 'modify_error_message'], 10, 3);

        // Handle mini cart and fragments
        add_filter('woocommerce_add_to_cart_fragments', [$this->cart_service, 'add_mini_cart_discount_fragment']);
        add_action('woocommerce_after_mini_cart', [$this->cart_service, 'display_mini_cart_discount']);
        add_action('woocommerce_review_order_after_cart_contents', [$this->cart_service, 'display_mini_cart_discount']);

        // AJAX handler for mini cart updates
        add_action('wp_ajax_update_mini_cart_discount', [$this->cart_service, 'update_mini_cart_discount']);
        add_action('wp_ajax_nopriv_update_mini_cart_discount', [$this->cart_service, 'update_mini_cart_discount']);

        // Handle shipping restrictions
        add_filter('woocommerce_package_rates', [$this->cart_service, 'remove_free_shipping_when_discount_applied'], 100, 2);
    }
}

// Initialize the plugin
function nop_plugin_init()
{
    // Make sure WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Start the plugin
    $plugin = NOP_Plugin::get_instance();
}

// Start plugin on woocommerce_init hook to ensure WC is loaded
add_action('woocommerce_init', 'nop_plugin_init');