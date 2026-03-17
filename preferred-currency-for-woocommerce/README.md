# Preferred Currency for WooCommerce

This plugin lets WooCommerce use a customer's preferred currency.

## Features

- Uses each logged-in user's saved preferred currency.
- Adds a preferred currency field to WordPress user profile screens.
- Includes a shortcode `[wc_currency_switcher]` customers can use to choose and save a currency from the front end.
- Stores a cookie so guest visitors can keep their selected currency during future visits.

## Installation

1. Copy the `preferred-currency-for-woocommerce` folder into `wp-content/plugins/`.
2. Activate **Preferred Currency for WooCommerce** from the WordPress admin plugin screen.
3. Add `[wc_currency_switcher]` to any page (for example, "My Account" or a custom account settings page).

## Notes

- WooCommerce must be active.
- This plugin changes display currency and does not handle currency exchange rates by itself.
