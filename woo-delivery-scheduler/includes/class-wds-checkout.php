<?php

namespace WDS;

use WC_Order;

if (! defined('ABSPATH')) {
    exit;
}

class Checkout
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        add_action('woocommerce_after_order_notes', [$this, 'render_fields']);
        add_action('woocommerce_checkout_process', [$this, 'validate_fields']);
        add_action('woocommerce_checkout_create_order', [$this, 'save_order_meta'], 10, 2);
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_dynamic_fees']);

        add_action('woocommerce_blocks_loaded', [$this, 'register_checkout_block_integration']);

        add_filter('woocommerce_email_order_meta_fields', [$this, 'email_meta'], 10, 3);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'add_calendar_link_to_my_orders'], 10, 2);

        add_action('woocommerce_order_status_cancelled', [$this, 'release_slot']);
        add_action('woocommerce_order_status_refunded', [$this, 'release_slot']);
    }

    public function render_fields(): void
    {
        $slots = $this->available_slots();

        woocommerce_form_field('wds_delivery_type', [
            'type'     => 'select',
            'label'    => __('Delivery Type', 'woo-delivery-scheduler'),
            'required' => true,
            'options'  => [
                ''         => __('Select', 'woo-delivery-scheduler'),
                'shipping' => __('Shipping', 'woo-delivery-scheduler'),
                'pickup'   => __('Pickup', 'woo-delivery-scheduler'),
            ],
        ]);

        woocommerce_form_field('wds_pickup_location', [
            'type'     => 'select',
            'label'    => __('Pickup Location', 'woo-delivery-scheduler'),
            'required' => false,
            'options'  => $this->pickup_options(),
        ]);

        woocommerce_form_field('wds_delivery_date', [
            'type'     => 'date',
            'label'    => __('Delivery Date', 'woo-delivery-scheduler'),
            'required' => true,
        ]);

        woocommerce_form_field('wds_delivery_slot', [
            'type'     => 'select',
            'label'    => __('Delivery Time Slot', 'woo-delivery-scheduler'),
            'required' => true,
            'options'  => $slots,
        ]);
    }

    public function validate_fields(): void
    {
        $delivery_date = wc_clean(wp_unslash($_POST['wds_delivery_date'] ?? ''));
        $slot          = wc_clean(wp_unslash($_POST['wds_delivery_slot'] ?? ''));
        $type          = wc_clean(wp_unslash($_POST['wds_delivery_type'] ?? ''));

        if ('' === $type) {
            wc_add_notice(__('Please select a delivery type.', 'woo-delivery-scheduler'), 'error');
        }

        if (! $this->is_date_available($delivery_date, $slot)) {
            wc_add_notice(__('Selected date is not available (holiday, cut-off, or fully booked).', 'woo-delivery-scheduler'), 'error');
        }

        if ('pickup' === $type) {
            $pickup_location = wc_clean(wp_unslash($_POST['wds_pickup_location'] ?? ''));
            if ('' === $pickup_location) {
                wc_add_notice(__('Please select a pickup location for pickup delivery type.', 'woo-delivery-scheduler'), 'error');
            }
        }

        if (! $this->is_slot_available($delivery_date, $slot)) {
            wc_add_notice(__('Selected time slot is fully booked.', 'woo-delivery-scheduler'), 'error');
        }
    }

    public function save_order_meta(WC_Order $order, array $data): void
    {
        unset($data);
        $meta = [
            '_wds_delivery_type'     => wc_clean(wp_unslash($_POST['wds_delivery_type'] ?? '')),
            '_wds_pickup_location'   => wc_clean(wp_unslash($_POST['wds_pickup_location'] ?? '')),
            '_wds_delivery_date'     => wc_clean(wp_unslash($_POST['wds_delivery_date'] ?? '')),
            '_wds_delivery_slot'     => wc_clean(wp_unslash($_POST['wds_delivery_slot'] ?? '')),
        ];

        foreach ($meta as $key => $value) {
            $order->update_meta_data($key, $value);
        }

        $this->reserve_slot($meta['_wds_delivery_date'], $meta['_wds_delivery_slot']);
    }

    public function email_meta(array $fields, bool $sent_to_admin, WC_Order $order): array
    {
        $fields['wds_delivery_date'] = [
            'label' => __('Delivery Date', 'woo-delivery-scheduler'),
            'value' => $order->get_meta('_wds_delivery_date'),
        ];

        $fields['wds_delivery_slot'] = [
            'label' => __('Delivery Slot', 'woo-delivery-scheduler'),
            'value' => $order->get_meta('_wds_delivery_slot'),
        ];

        return $fields;
    }

    public function add_calendar_link_to_my_orders(array $actions, WC_Order $order): array
    {
        $date = $order->get_meta('_wds_delivery_date');
        $slot = $order->get_meta('_wds_delivery_slot');

        if ($date && $slot) {
            $url = add_query_arg([
                'wds_ics'   => $order->get_id(),
                'wds_token' => wp_create_nonce('wds_ics_' . $order->get_id()),
            ], home_url('/'));

            $actions['wds_add_calendar'] = [
                'url'  => $url,
                'name' => __('Add to Calendar', 'woo-delivery-scheduler'),
            ];
        }

        return $actions;
    }

    public function register_checkout_block_integration(): void
    {
        if (class_exists('\Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry')) {
            add_action('woocommerce_blocks_checkout_block_registration', function ($integration_registry) {
                if (method_exists($integration_registry, 'register')) {
                    // Placeholder to demonstrate blocks compatibility registration point.
                }
            });
        }
    }

    public function apply_dynamic_fees(): void
    {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        $delivery_date = wc_clean(wp_unslash($_POST['wds_delivery_date'] ?? ''));
        if (! $delivery_date) {
            return;
        }

        $today = current_time('Y-m-d');
        if ($delivery_date === $today && $this->is_before_cutoff((string) $this->settings->get('same_day_cutoff'))) {
            WC()->cart->add_fee(__('Same-day delivery charge', 'woo-delivery-scheduler'), (float) $this->settings->get('same_day_charge'));
        }

        if ($delivery_date === gmdate('Y-m-d', strtotime('+1 day', current_time('timestamp'))) && $this->is_before_cutoff((string) $this->settings->get('next_day_cutoff'))) {
            WC()->cart->add_fee(__('Next-day delivery charge', 'woo-delivery-scheduler'), (float) $this->settings->get('next_day_charge'));
        }

        $threshold = (float) $this->settings->get('charges_below_amount');
        if (WC()->cart->subtotal < $threshold) {
            WC()->cart->add_fee(__('Small order delivery charge', 'woo-delivery-scheduler'), (float) $this->settings->get('charges_below_amount_value'));
        }
    }

    private function available_slots(): array
    {
        $slots = json_decode((string) $this->settings->get('time_slots'), true);
        if (! is_array($slots)) {
            return ['' => __('No slots configured', 'woo-delivery-scheduler')];
        }

        $out = ['' => __('Select slot', 'woo-delivery-scheduler')];
        foreach ($slots as $slot) {
            if (! isset($slot['start'], $slot['end'])) {
                continue;
            }
            $label        = $slot['start'] . ' - ' . $slot['end'];
            $out[$label] = $label;
        }

        return $out;
    }

    private function pickup_options(): array
    {
        $terms = get_terms([
            'taxonomy'   => 'wds_pickup_location',
            'hide_empty' => false,
        ]);

        $options = ['' => __('Select pickup location', 'woo-delivery-scheduler')];

        if (! is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[$term->slug] = $term->name;
            }
        }

        return $options;
    }

    private function is_date_available(string $delivery_date, string $slot): bool
    {
        if (! $delivery_date) {
            return false;
        }

        $holidays = array_filter(array_map('trim', explode(',', (string) $this->settings->get('holiday_dates'))));
        if (in_array($delivery_date, $holidays, true)) {
            return false;
        }

        $min_hours         = (int) $this->settings->get('minimum_delivery_hours');
        $lead_cutoff       = strtotime('+' . $min_hours . ' hours', current_time('timestamp'));
        $slot_start_time   = $this->extract_slot_start($slot);
        $candidate_ts      = strtotime($delivery_date . ' ' . $slot_start_time);

        if (false === $candidate_ts || $candidate_ts < $lead_cutoff) {
            return false;
        }

        $daily_limit = (int) $this->settings->get('daily_order_limit');
        $count       = (int) get_option('wds_booked_date_' . $delivery_date, 0);

        return $count < $daily_limit;
    }

    private function extract_slot_start(string $slot): string
    {
        if (false === strpos($slot, '-')) {
            return '23:59:59';
        }

        [$start] = array_map('trim', explode('-', $slot));

        return $start ?: '23:59:59';
    }

    private function is_before_cutoff(string $cutoff): bool
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $cutoff)) {
            return true;
        }

        $today      = current_time('Y-m-d');
        $cutoff_ts  = strtotime($today . ' ' . $cutoff . ':00');
        $current_ts = current_time('timestamp');

        if (false === $cutoff_ts) {
            return true;
        }

        return $current_ts <= $cutoff_ts;
    }

    private function is_slot_available(string $delivery_date, string $slot): bool
    {
        if (! $delivery_date || ! $slot) {
            return false;
        }

        $slot_limit = (int) $this->settings->get('slot_order_limit');
        $count      = (int) get_option('wds_booked_slot_' . md5($delivery_date . $slot), 0);

        return $count < $slot_limit;
    }

    private function reserve_slot(string $date, string $slot): void
    {
        if (! $date || ! $slot) {
            return;
        }

        update_option('wds_booked_date_' . $date, (int) get_option('wds_booked_date_' . $date, 0) + 1, false);
        update_option('wds_booked_slot_' . md5($date . $slot), (int) get_option('wds_booked_slot_' . md5($date . $slot), 0) + 1, false);
    }

    public function release_slot(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $date = (string) $order->get_meta('_wds_delivery_date');
        $slot = (string) $order->get_meta('_wds_delivery_slot');

        if ($date) {
            update_option('wds_booked_date_' . $date, max(0, (int) get_option('wds_booked_date_' . $date, 0) - 1), false);
        }

        if ($slot && $date) {
            $k = 'wds_booked_slot_' . md5($date . $slot);
            update_option($k, max(0, (int) get_option($k, 0) - 1), false);
        }
    }
}
