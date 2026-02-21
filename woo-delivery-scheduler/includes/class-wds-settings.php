<?php

namespace WDS;

if (! defined('ABSPATH')) {
    exit;
}

class Settings
{
    public const OPTION_KEY = 'wds_settings';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Delivery Scheduler', 'woo-delivery-scheduler'),
            __('Delivery Scheduler', 'woo-delivery-scheduler'),
            'manage_woocommerce',
            'wds-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('wds_settings_group', self::OPTION_KEY, [$this, 'sanitize']);

        add_settings_section('wds_general', __('General Delivery Rules', 'woo-delivery-scheduler'), '__return_false', 'wds-settings');

        $fields = [
            'enabled_categories'         => __('Enabled Product Categories (IDs comma-separated)', 'woo-delivery-scheduler'),
            'enabled_shipping_methods'   => __('Enabled Shipping Methods (IDs comma-separated)', 'woo-delivery-scheduler'),
            'minimum_delivery_hours'     => __('Minimum Delivery Lead Time (hours)', 'woo-delivery-scheduler'),
            'same_day_cutoff'            => __('Same-day Cutoff (HH:MM)', 'woo-delivery-scheduler'),
            'next_day_cutoff'            => __('Next-day Cutoff (HH:MM)', 'woo-delivery-scheduler'),
            'same_day_charge'            => __('Same-day Extra Charge', 'woo-delivery-scheduler'),
            'next_day_charge'            => __('Next-day Extra Charge', 'woo-delivery-scheduler'),
            'daily_order_limit'          => __('Store-wide Daily Order Limit', 'woo-delivery-scheduler'),
            'slot_order_limit'           => __('Default Time Slot Order Limit', 'woo-delivery-scheduler'),
            'holiday_dates'              => __('Holiday Dates (YYYY-MM-DD comma-separated)', 'woo-delivery-scheduler'),
            'special_dates'              => __('Special Dates JSON', 'woo-delivery-scheduler'),
            'time_slots'                 => __('Time Slots JSON', 'woo-delivery-scheduler'),
            'urgency_charge_threshold'   => __('Urgency Charge Threshold (hours)', 'woo-delivery-scheduler'),
            'charges_below_amount'       => __('Charge Applies Below Order Amount', 'woo-delivery-scheduler'),
            'charges_below_amount_value' => __('Charge Amount (Below Order Amount)', 'woo-delivery-scheduler'),
        ];

        foreach ($fields as $key => $label) {
            add_settings_field($key, $label, [$this, 'render_text_field'], 'wds-settings', 'wds_general', ['key' => $key]);
        }

        add_settings_field(
            'google_calendar_enabled',
            __('Enable Google Calendar sync', 'woo-delivery-scheduler'),
            [$this, 'render_checkbox_field'],
            'wds-settings',
            'wds_general',
            ['key' => 'google_calendar_enabled']
        );
    }

    public function sanitize(array $input): array
    {
        $defaults = $this->defaults();
        $result   = [];

        foreach ($defaults as $key => $default) {
            if (! isset($input[$key])) {
                $result[$key] = is_bool($default) ? false : $default;
                continue;
            }

            if (is_bool($default)) {
                $result[$key] = (bool) $input[$key];
            } elseif (is_numeric($default)) {
                $result[$key] = (float) $input[$key];
            } else {
                $result[$key] = sanitize_text_field((string) $input[$key]);
            }
        }

        return $result;
    }

    public function get(string $key)
    {
        $settings = wp_parse_args(get_option(self::OPTION_KEY, []), $this->defaults());
        return $settings[$key] ?? null;
    }

    public function defaults(): array
    {
        return [
            'enabled_categories'         => '',
            'enabled_shipping_methods'   => '',
            'minimum_delivery_hours'     => 4,
            'same_day_cutoff'            => '14:00',
            'next_day_cutoff'            => '20:00',
            'same_day_charge'            => 5,
            'next_day_charge'            => 2,
            'daily_order_limit'          => 100,
            'slot_order_limit'           => 10,
            'holiday_dates'              => '',
            'special_dates'              => '[]',
            'time_slots'                 => '[{"start":"09:00","end":"12:00"},{"start":"13:00","end":"16:00"}]',
            'google_calendar_enabled'    => false,
            'urgency_charge_threshold'   => 24,
            'charges_below_amount'       => 50,
            'charges_below_amount_value' => 4,
        ];
    }

    public function render_text_field(array $args): void
    {
        $key   = $args['key'];
        $value = $this->get($key);

        printf(
            '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s"/>',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_attr((string) $value)
        );
    }

    public function render_checkbox_field(array $args): void
    {
        $key   = $args['key'];
        $value = (bool) $this->get($key);

        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s/> %4$s</label>',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            checked(true, $value, false),
            esc_html__('Enabled', 'woo-delivery-scheduler')
        );
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Woo Delivery Scheduler', 'woo-delivery-scheduler') . '</h1>';
        echo '<p>' . esc_html__('Configure category/shipping/pickup/date/slot rules and limits.', 'woo-delivery-scheduler') . '</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('wds_settings_group');
        do_settings_sections('wds-settings');
        submit_button();
        echo '</form></div>';
    }
}
