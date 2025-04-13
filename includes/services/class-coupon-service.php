<?php
/**
 * Coupon Service
 *
 * Handles coupon validation and error message customization
 *
 * @package NextOrderPlus
 * @since 1.0.0
 */

namespace NOP\Services;

use NOP\Base\NOP_Base;
use NOP\Util\NOP_Logger;

/**
 * Coupon Service class
 */
class NOP_Coupon_Service extends NOP_Base
{
    /**
     * List of coupon codes that are not allowed to be used with the promotion
     *
     * @var array
     */
    private $excluded_coupons;

    /**
     * Admin service instance for settings
     *
     * @var NOP_Admin_Service|null
     */
    private $admin_service;

    /**
     * Constructor
     *
     * Sets up excluded coupons
     *
     * @param NOP_Logger|null $logger Optional logger instance
     * @param NOP_Admin_Service|null $admin_service Optional admin service for settings
     */
    public function __construct($logger = null, $admin_service = null)
    {
        parent::__construct($logger);
        $this->admin_service = $admin_service;

        // Default excluded coupons
        $this->excluded_coupons = ['gtre50', 'abon-150'];

        // If admin service is available, get settings from it
        if ($this->admin_service instanceof NOP_Admin_Service) {
            $options = $this->admin_service->get_options();

            if (isset($options['excluded_coupons']) && !empty($options['excluded_coupons'])) {
                $coupons = explode(',', $options['excluded_coupons']);
                $this->excluded_coupons = array_map('trim', $coupons);
            }
        }

        // Allow filtering excluded coupons
        $this->excluded_coupons = apply_filters(
            $this->prefix . 'excluded_coupons',
            $this->excluded_coupons
        );
    }

    /**
     * Initialize the service
     *
     * Sets up hooks for coupon validation
     *
     * @return void
     */
    public function init(): void
    {
        $this->log('Coupon service initialized with ' . count($this->excluded_coupons) . ' excluded coupons');
    }

    /**
     * Validates whether a coupon can be applied based on exclusion rules
     *
     * @param bool $valid Whether the coupon is valid
     * @param \WC_Coupon|null $coupon The coupon object being validated
     * @return bool False if coupon is excluded, original validity status otherwise
     */
    public function validate_coupon(bool $valid, $coupon): bool
    {
        if (!$coupon || !method_exists($coupon, 'get_code')) {
            return $valid;
        }

        $code = $coupon->get_code();

        if (in_array($code, $this->excluded_coupons, true)) {
            wc_add_notice(
                __('This coupon cannot be applied with the 2025 Promotion', 'next-order-plus'),
                'error'
            );
            $this->log("Blocked excluded coupon: {$code}");
            return false;
        }

        return $valid;
    }

    /**
     * Modifies the error message for excluded coupons
     * 
     * @param string $err The original error message
     * @param string $err_code The error code
     * @param \WC_Coupon|null $coupon The coupon object
     * @return string Modified error message if coupon is excluded, original message otherwise
     */
    public function modify_error_message(string $err, string $err_code, $coupon): string
    {
        if (!$coupon || !method_exists($coupon, 'get_code')) {
            return $err;
        }

        if (in_array($coupon->get_code(), $this->excluded_coupons, true)) {
            return __('This coupon cannot be applied with the 2025 Promotion', 'next-order-plus');
        }

        return $err;
    }
}