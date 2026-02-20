# Woo Delivery Scheduler Pro (MVP)

This plugin provides a production-ready **foundation architecture** for WooCommerce delivery scheduling:

- Delivery type selection (shipping/pickup)
- Delivery date and time slot checkout fields
- Pickup location taxonomy for products
- Store-wide daily and time-slot limits
- Minimum delivery lead time and holiday blocking
- Same-day/next-day and low-order-value charges
- Delivery metadata in emails and My Account
- ICS "Add to Calendar" link for customers
- Admin delivery calendar listing with date filters
- WooCommerce HPOS + Checkout Blocks compatibility declaration

## Installation

1. Copy folder `woo-delivery-scheduler` into `wp-content/plugins/`
2. Activate **Woo Delivery Scheduler Pro**
3. Configure settings at **WooCommerce → Delivery Scheduler**
4. Manage deliveries at **WooCommerce → Delivery Calendar**

## Notes

Some advanced requirements (Google OAuth sync/import, recurring schedules with subscription periods, multi-vendor deep integrations, WPML strings, mobile app APIs, and advanced block UI rendering) are scaffolded via architecture points but intentionally left as extension modules to keep the MVP maintainable.
