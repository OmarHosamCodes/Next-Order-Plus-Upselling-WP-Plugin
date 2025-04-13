/**
 * Next Order Plus - Mini Cart Discount Handler
 * 
 * Handles automatic discount updates in the WooCommerce mini cart when cart contents change.
 * Compatible with both Classic Cart and Block-based Cart implementations.
 * 
 * @requires jQuery
 * @requires WooCommerce
 */
(function ($) {
    'use strict';

    // Check if nop_ajax is available (properly localized)
    if (typeof nop_ajax === 'undefined') {
        console.error('Next Order Plus: AJAX configuration not found for mini-cart');
        return;
    }

    /**
     * Debounce function to limit API call frequency
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

    /**
     * Track whether an AJAX request is in progress
     */
    let isUpdating = false;

    /**
     * Update mini cart discount via AJAX
     * 
     * Makes POST request to recalculate discount based on current cart contents
     * Triggers cart fragment refresh on successful discount application
     * Also handles Block Cart store invalidation when needed
     * 
     * @fires wc_fragment_refresh On successful discount update
     */
    function updateMiniCartDiscount() {
        if (isUpdating) {
            return;
        }

        isUpdating = true;

        $.ajax({
            url: nop_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_mini_cart_discount',
                nonce: nop_ajax.nonce
            },
            success: function (response) {
                // Only refresh cart if discount was successfully applied
                if (response.success) {
                    if (response.data.discount > 0) {
                        // Refresh classic cart fragments
                        $(document.body).trigger('wc_fragment_refresh');

                        // Invalidate and refresh block cart if available
                        if (window.wc && window.wc.store && window.wc.store.dispatch) {
                            window.wc.store.dispatch('wc/cart').invalidateResolutionForStore();
                        }
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('Next Order Plus: Mini cart discount update failed:', error);
            },
            complete: function () {
                isUpdating = false;
            }
        });
    }

    /**
     * Initialize mini cart event listeners
     */
    function init() {
        // Create debounced version to prevent rapid-fire requests
        const debouncedUpdate = debounce(updateMiniCartDiscount, 500);

        // Cart event handlers
        const events = [
            'added_to_cart',
            'removed_from_cart',
            'updated_cart_totals',
            'wc-blocks-cart-update-cart'
        ];

        // Attach event listeners
        events.forEach(function (event) {
            $(document.body).on(event, debouncedUpdate);
        });

        // Initialize Block Cart listener if available
        if (window.wc && window.wc.blocksRegistry) {
            window.wc.blocksRegistry.subscribe('cart', debouncedUpdate);
        }
    }

    // Initialize when DOM is ready
    $(document).ready(init);

})(jQuery);