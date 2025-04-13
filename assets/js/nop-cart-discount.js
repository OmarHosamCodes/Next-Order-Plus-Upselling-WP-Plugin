/**
 * Next Order Plus - Cart Discount Handler
 * 
 * Manages automatic discount updates in the WooCommerce cart when cart contents change.
 * Provides compatibility with both Classic Cart and Block-based Cart implementations.
 * Includes debouncing to prevent API spam and tracks pending requests.
 * 
 * @requires jQuery
 * @requires WooCommerce
 */
(function ($) {
    'use strict';

    // Check if nop_ajax is available (properly localized)
    if (typeof nop_ajax === 'undefined') {
        console.error('Next Order Plus: AJAX configuration not found');
        return;
    }

    /**
     * Debounce function to limit API call frequency
     * 
     * Creates a debounced version of a function that delays execution until after
     * a specified wait time has elapsed since the last call.
     *
     * @param {Function} func The function to debounce
     * @param {number} wait Wait time in milliseconds
     * @return {Function} Debounced version of the input function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Track pending AJAX requests to prevent duplicates
    let isPending = false;

    /**
     * Update cart discount via AJAX
     * 
     * Makes POST request to recalculate discount based on current cart contents.
     * Handles both Classic Cart and Block Cart updates.
     * Updates discount display and triggers cart refresh when needed.
     * 
     * @fires updated_cart_totals On classic cart update
     */
    function updateCartDiscount() {
        if (isPending) {
            return;
        }

        isPending = true;

        $.ajax({
            url: nop_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_mini_cart_discount',
                nonce: nop_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Only update if discount amount changed
                    if (response.data.discount !== getCurrentDiscount()) {
                        // Update discount display
                        if (response.data.discount > 0) {
                            $('.cart-discount').show();
                            $('.cart-discount-amount').text(response.data.discount_formatted);
                        } else {
                            $('.cart-discount').hide();
                        }

                        // Handle cart updates based on cart type
                        if ($('.woocommerce-cart-form').length) {
                            // Classic Cart update
                            $(document.body).off('updated_cart_totals', debouncedUpdate);
                            $(document.body).trigger('updated_cart_totals');
                            $(document.body).on('updated_cart_totals', debouncedUpdate);
                        } else if (window.wc && window.wc.store && window.wc.store.dispatch) {
                            // Block Cart update
                            window.wc.store.dispatch('wc/cart').invalidateResolutionForStore();
                        }
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('Next Order Plus: Cart discount update failed:', {
                    status: xhr.status + ' ' + status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                // Hide discount display on error
                $('.cart-discount').hide();
            },
            complete: function () {
                isPending = false;
            }
        });
    }

    /**
     * Get current discount amount from DOM
     * 
     * Extracts numeric discount value from cart discount display element.
     * 
     * @return {number} Current discount amount or 0 if not found
     */
    function getCurrentDiscount() {
        const discountElement = $('.cart-discount-amount');
        return discountElement.length ?
            parseFloat(discountElement.text().replace(/[^0-9.-]+/g, '')) :
            0;
    }

    // Create debounced version of update function (500ms delay)
    const debouncedUpdate = debounce(updateCartDiscount, 500);

    /**
     * Initialize cart update listeners
     */
    function init() {
        // Bind cart update event handlers for various cart modification events
        const events = [
            'updated_cart_totals',
            'added_to_cart',
            'removed_from_cart',
            'wc-blocks-cart-update-cart'
        ];

        for (const event of events) {
            $(document.body).on(event, debouncedUpdate);
        }

        // Initialize Block Cart listener if available
        if (window.wc && window.wc.blocksRegistry) {
            window.wc.blocksRegistry.subscribe('cart', debouncedUpdate);
        }

        // Initial update if cart is present
        if ($('.woocommerce-cart-form').length || $('.wp-block-woocommerce-cart').length) {
            debouncedUpdate();
        }
    }

    // Initialize when DOM is ready
    $(document).ready(init);

})(jQuery);