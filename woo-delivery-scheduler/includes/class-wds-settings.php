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

        add_settings_field(
            'enabled_categories',
            __('Enabled Product Categories', 'woo-delivery-scheduler'),
            [$this, 'render_category_multiselect_field'],
            'wds-settings',
            'wds_general',
            ['key' => 'enabled_categories']
        );

        add_settings_field(
            'enabled_shipping_methods',
            __('Enabled Shipping Methods', 'woo-delivery-scheduler'),
            [$this, 'render_shipping_multiselect_field'],
            'wds-settings',
            'wds_general',
            ['key' => 'enabled_shipping_methods']
        );

        $fields = [
            'minimum_delivery_hours'     => __('Minimum Delivery Lead Time (hours)', 'woo-delivery-scheduler'),
            'same_day_cutoff'            => __('Same-day Cutoff (HH:MM)', 'woo-delivery-scheduler'),
            'next_day_cutoff'            => __('Next-day Cutoff (HH:MM)', 'woo-delivery-scheduler'),
            'same_day_charge'            => __('Same-day Extra Charge', 'woo-delivery-scheduler'),
            'next_day_charge'            => __('Next-day Extra Charge', 'woo-delivery-scheduler'),
            'daily_order_limit'          => __('Store-wide Daily Order Limit', 'woo-delivery-scheduler'),
            'slot_order_limit'           => __('Default Time Slot Order Limit', 'woo-delivery-scheduler'),
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
            'holiday_dates',
            __('Holiday Dates', 'woo-delivery-scheduler'),
            [$this, 'render_holiday_dates_field'],
            'wds-settings',
            'wds_general',
            ['key' => 'holiday_dates']
        );

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

            if (in_array($key, ['enabled_categories', 'enabled_shipping_methods'], true)) {
                $items = is_array($input[$key]) ? $input[$key] : explode(',', (string) $input[$key]);
                $clean = array_filter(array_map(static function ($v) {
                    return sanitize_text_field((string) $v);
                }, $items));
                $result[$key] = implode(',', $clean);
                continue;
            }

            if ('holiday_dates' === $key) {
                $items = is_array($input[$key]) ? $input[$key] : explode(',', (string) $input[$key]);
                $clean = [];
                foreach ($items as $date) {
                    $value = sanitize_text_field((string) $date);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                        $clean[] = $value;
                    }
                }
                $result[$key] = implode(',', array_unique($clean));
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

    public function render_category_multiselect_field(): void
    {
        $selected = array_filter(array_map('trim', explode(',', (string) $this->get('enabled_categories'))));
        $terms    = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);

        echo '<select multiple="multiple" size="8" style="min-width:340px;" name="' . esc_attr(self::OPTION_KEY) . '[enabled_categories][]">';

        if (! is_wp_error($terms)) {
            foreach ($terms as $term) {
                printf(
                    '<option value="%1$s" %2$s>%3$s</option>',
                    esc_attr((string) $term->term_id),
                    selected(in_array((string) $term->term_id, $selected, true), true, false),
                    esc_html($term->name)
                );
            }
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__('Hold Ctrl/Cmd to select multiple categories.', 'woo-delivery-scheduler') . '</p>';
    }

    public function render_shipping_multiselect_field(): void
    {
        $selected = array_filter(array_map('trim', explode(',', (string) $this->get('enabled_shipping_methods'))));
        $methods  = [];

        if (function_exists('WC') && WC()->shipping()) {
            $methods = WC()->shipping()->load_shipping_methods();
        }

        echo '<select multiple="multiple" size="8" style="min-width:340px;" name="' . esc_attr(self::OPTION_KEY) . '[enabled_shipping_methods][]">';

        foreach ($methods as $method) {
            $id = (string) $method->id;
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($id),
                selected(in_array($id, $selected, true), true, false),
                esc_html($method->method_title . ' (' . $id . ')')
            );
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__('Select one or multiple shipping methods for delivery scheduling.', 'woo-delivery-scheduler') . '</p>';
    }

    public function render_holiday_dates_field(): void
    {
        $dates = array_filter(array_map('trim', explode(',', (string) $this->get('holiday_dates'))));
        if (empty($dates)) {
            $dates = [''];
        }

        echo '<div id="wds-holiday-dates-wrapper">';
        foreach ($dates as $date) {
            echo '<p><input type="date" name="' . esc_attr(self::OPTION_KEY) . '[holiday_dates][]" value="' . esc_attr($date) . '" /> <button type="button" class="button wds-remove-holiday">' . esc_html__('Remove', 'woo-delivery-scheduler') . '</button></p>';
        }
        echo '</div>';
        echo '<p><button type="button" class="button" id="wds-add-holiday-date">' . esc_html__('Add Holiday Date', 'woo-delivery-scheduler') . '</button></p>';
        echo '<p class="description">' . esc_html__('Use date pickers to block holidays from delivery.', 'woo-delivery-scheduler') . '</p>';

        echo '<script>
        document.addEventListener("DOMContentLoaded", function () {
            const wrapper = document.getElementById("wds-holiday-dates-wrapper");
            const addBtn = document.getElementById("wds-add-holiday-date");
            if (!wrapper || !addBtn) { return; }

            addBtn.addEventListener("click", function () {
                const p = document.createElement("p");
                p.innerHTML = `<input type="date" name="' . esc_js(self::OPTION_KEY) . '[holiday_dates][]" value="" /> <button type="button" class="button wds-remove-holiday">' . esc_js(__('Remove', 'woo-delivery-scheduler')) . '</button>`;
                wrapper.appendChild(p);
            });

            wrapper.addEventListener("click", function (event) {
                if (event.target && event.target.classList.contains("wds-remove-holiday")) {
                    const row = event.target.closest("p");
                    if (row) { row.remove(); }
                }
            });
        });
        </script>';
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Woo Delivery Scheduler', 'woo-delivery-scheduler') . '</h1>';
        echo '<p>' . esc_html__('Configure category/shipping/pickup/date/slot rules and limits.', 'woo-delivery-scheduler') . '</p>';

        echo '<div class="notice notice-info inline"><p><strong>' . esc_html__('Shortcode help:', 'woo-delivery-scheduler') . '</strong> ' . esc_html__('Use [wds_delivery_availability] on any page to show delivery schedule guidance to customers.', 'woo-delivery-scheduler') . '</p></div>';

        echo '<form method="post" action="options.php">';
        settings_fields('wds_settings_group');
        do_settings_sections('wds-settings');
        submit_button();
        echo '</form></div>';
    }
}
