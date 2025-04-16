# Next Order Plus

A highly optimized WooCommerce plugin that automatically applies a "Next Order Plus Upselling Features" promotional discount.

## Features

- **Automatic Discount Application**: When a customer adds 4 or more items to their cart, the cheapest item becomes free.
- **Multiple Discounts**: For every 4 items, an additional free item is applied (e.g., 8 items = 2 free items).
- **Full Compatibility**: Works with both Classic and Block Cart/Checkout experiences.
- **Mini-Cart Support**: Displays the discount in the mini cart dropdown.
- **Shipping Rule Integration**: Optionally disables free shipping when discount is applied.
- **Coupon Restrictions**: Prevents specific coupons from being used together with this promotion.
- **Admin Settings**: Fully customizable through dedicated admin interface.
- **Order Statistics**: Track the total number and amount of discounts applied.

## Technical Architecture

### Core Components

1. **Base Class System**:
   - Abstract foundation class for all services
   - Type declarations and error handling

2. **Service Modules**:
   - `NOP_Discount_Service`: Core discount calculation logic
   - `NOP_Cart_Service`: Cart interaction and discount application
   - `NOP_Assets_Service`: JS/CSS resource management
   - `NOP_Coupon_Service`: Coupon validation and error handling
   - `NOP_Admin_Service`: Admin interface and settings management

3. **Utility Classes**:
   - `NOP_Logger`: Debug logging capabilities

### Design Principles

- **Modular Architecture**: Each service has a single responsibility
- **Performance Optimized**: Debounced AJAX requests prevent API spam
- **Maintainable Code**: Well-documented with full PHPDoc support
- **Extensible Design**: Filterable settings and hooks for customization
- **Robust Error Handling**: Graceful degradation on errors

## Installationreplace 

1. Upload the `next-order-plus` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and activated
4. Configure settings via WooCommerce > Next Order Plus or SOM Plugins > Next Order Plus

## Customization

The plugin provides several filters for programmatic customization:

- `nop_excluded_coupons`: Modify the list of disallowed coupons
- `nop_load_assets_globally`: Control whether assets are loaded on all pages
- `nop_discount_label`: Customize the discount label text
- `nop_min_items_for_discount`: Override the minimum items required for discount

## Compatibility

- WordPress 5.6+
- WooCommerce 6.0+
- PHP 7.4+
- Compatible with most WooCommerce themes and plugins

## Support

For issues or feature requests, please contact the plugin author.

## Credits

Originally based on the "Buy 4 Get Cheapest Free" promotion concept, rewritten and optimized for modern WordPress and WooCommerce.