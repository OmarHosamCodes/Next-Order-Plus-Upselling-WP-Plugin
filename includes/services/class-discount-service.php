<?php
/**
 * Discount Service
 *
 * Legacy service maintained for backward compatibility
 * All discount functionality is now handled by the rules manager
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
     * Admin service instance for settings
     *
     * @var NOP_Admin_Service|null
     */
    private $admin_service;

    /**
     * Constructor
     *
     * @param NOP_Logger|null $logger Optional logger instance
     * @param NOP_Admin_Service|null $admin_service Optional admin service for settings
     */
    public function __construct($logger = null, $admin_service = null)
    {
        parent::__construct($logger);
        $this->admin_service = $admin_service;
    }

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
        $this->log('Legacy discount service initialized - all discounts handled by rules manager');
    }

    /**
     * Calculates the total discount to apply based on cart contents
     * 
     * This method now returns 0 as all discount logic has been moved to the rules manager
     * 
     * @param mixed $cart WooCommerce cart object
     * @return float Total discount amount
     */
    public function calculate_discount($cart): float
    {
        // Legacy function now returns 0 as discounts are managed by the rules manager
        $this->log('Legacy calculate_discount called - returning 0 as discount is handled by rules', 'info');
        return 0;
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
            return true;
        }

        return false;
    }
}