<?php
namespace BXG1F\Services;

/**
 * Class CouponService
 * 
 * Handles coupon validation and error message customization for the Buy 4 Get 1 Free (BXG1F) promotion.
 * Ensures certain coupons cannot be used in combination with the Black Friday promotion.
 *
 * @package BXG1F\Services
 */
class CouponService {
    /**
     * List of coupon codes that are not allowed to be used with the Black Friday promotion
     *
     * @var array
     */
    private $excluded_coupons = ['gtre50', 'abon-150'];

    /**
     * Validates whether a coupon can be applied based on exclusion rules
     *
     * Checks if the provided coupon is in the excluded coupons list. If it is,
     * adds an error notice and prevents the coupon from being applied.
     *
     * @param bool $valid Whether the coupon is valid
     * @param WC_Coupon|null $coupon The coupon object being validated
     * @return bool False if coupon is excluded, original validity status otherwise
     */
    public function validate_coupon($valid, $coupon) {
        if ($coupon && in_array($coupon->get_code(), $this->excluded_coupons)) {
            wc_add_notice(__('الكوبون ده مش بيتطبق على عروض الـ بلاك فريداي', 'woocommerce'), 'error');
            return false;
        }
        return $valid;
    }

    /**
     * Modifies the error message for excluded coupons
     * 
     * Customizes the error message shown to users when they attempt to use
     * an excluded coupon during the Black Friday promotion.
     *
     * @param string $err The original error message
     * @param string $err_code The error code
     * @param WC_Coupon|null $coupon The coupon object
     * @return string Modified error message if coupon is excluded, original message otherwise
     */
    public function modify_error_message($err, $err_code, $coupon) {
        if ($coupon && in_array($coupon->get_code(), $this->excluded_coupons)) {
            return 'الكوبون ده مش بيتطبق على عروض الـ بلاك فريداي';
        }
        return $err;
    }
}