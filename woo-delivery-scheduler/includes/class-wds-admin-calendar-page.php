<?php

namespace WDS;

if (! defined('ABSPATH')) {
    exit;
}

class Admin_Calendar_Page
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_page']);
    }

    public function register_page(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Delivery Calendar', 'woo-delivery-scheduler'),
            __('Delivery Calendar', 'woo-delivery-scheduler'),
            'manage_woocommerce',
            'wds-delivery-calendar',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $from = sanitize_text_field(wp_unslash($_GET['from'] ?? current_time('Y-m-01')));
        $to   = sanitize_text_field(wp_unslash($_GET['to'] ?? current_time('Y-m-t')));

        $orders = wc_get_orders([
            'limit'      => 200,
            'meta_query' => [
                [
                    'key'     => '_wds_delivery_date',
                    'value'   => [$from, $to],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ],
            ],
        ]);

        echo '<div class="wrap"><h1>' . esc_html__('Delivery Calendar', 'woo-delivery-scheduler') . '</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="wds-delivery-calendar" />';
        echo '<label>From <input type="date" name="from" value="' . esc_attr($from) . '"/></label> ';
        echo '<label>To <input type="date" name="to" value="' . esc_attr($to) . '"/></label> ';
        submit_button(__('Filter', 'woo-delivery-scheduler'), 'secondary', '', false);
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Order', 'woo-delivery-scheduler') . '</th>';
        echo '<th>' . esc_html__('Date', 'woo-delivery-scheduler') . '</th>';
        echo '<th>' . esc_html__('Slot', 'woo-delivery-scheduler') . '</th>';
        echo '<th>' . esc_html__('Type', 'woo-delivery-scheduler') . '</th>';
        echo '<th>' . esc_html__('Pickup Location', 'woo-delivery-scheduler') . '</th>';
        echo '<th>' . esc_html__('Status', 'woo-delivery-scheduler') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($orders as $order) {
            echo '<tr>';
            echo '<td><a href="' . esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')) . '">#' . esc_html($order->get_order_number()) . '</a></td>';
            echo '<td>' . esc_html((string) $order->get_meta('_wds_delivery_date')) . '</td>';
            echo '<td>' . esc_html((string) $order->get_meta('_wds_delivery_slot')) . '</td>';
            echo '<td>' . esc_html((string) $order->get_meta('_wds_delivery_type')) . '</td>';
            echo '<td>' . esc_html((string) $order->get_meta('_wds_pickup_location')) . '</td>';
            echo '<td>' . esc_html(wc_get_order_status_name($order->get_status())) . '</td>';
            echo '</tr>';
        }

        if (empty($orders)) {
            echo '<tr><td colspan="6">' . esc_html__('No deliveries found for the selected period.', 'woo-delivery-scheduler') . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }
}
