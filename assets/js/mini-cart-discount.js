/**
 * Mini Cart Discount Handler
 * 
 * Handles automatic discount updates in the WooCommerce mini cart when cart contents change.
 * Compatible with both Classic Cart and Block-based Cart implementations.
 * 
 * @requires jQuery
 * @requires WooCommerce
 */

jQuery(function ($) {
    /**
     * Bind cart update event handlers
     * Listens for various cart modification events and triggers discount recalculation
     * Events handled:
     * - added_to_cart: When items are added
     * - removed_from_cart: When items are removed  
     * - updated_cart_totals: When cart totals are recalculated
     * - wc-blocks-cart-update-cart: When block cart is updated
     */
    $(document.body).on('added_to_cart removed_from_cart updated_cart_totals wc-blocks-cart-update-cart', function () {
        updateMiniCartDiscount();
    });

    /**
     * Initialize Block Cart listener
     * Sets up subscription to cart changes in WooCommerce Blocks implementation
     * Only runs if WC Blocks Registry is available
     */
    if (window.wc && window.wc.blocksRegistry) {
        window.wc.blocksRegistry.subscribe('cart', function() {
            updateMiniCartDiscount();
        });
    }

    /**
     * Update mini cart discount via AJAX
     * Makes POST request to recalculate discount based on current cart contents
     * Triggers cart fragment refresh on successful discount application
     * Also handles Block Cart store invalidation when needed
     * 
     * @fires wc_fragment_refresh On successful discount update
     * @async
     */
    function updateMiniCartDiscount() {
        $.ajax({
            url: b4gf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_mini_cart_discount',
                nonce: b4gf_ajax.nonce
            },
            success: function (response) {
                // Only refresh cart if discount was successfully applied
                if (response.success && response.data.discount > 0) {
                    // Refresh classic cart fragments
                    $(document.body).trigger('wc_fragment_refresh');
                    
                    // Invalidate and refresh block cart if available
                    if (window.wc && window.wc.store && window.wc.store.dispatch) {
                        window.wc.store.dispatch('wc/cart').invalidateResolutionForStore();
                    }
                }
            }
        });
    }
});