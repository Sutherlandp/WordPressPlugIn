<?php

namespace WDS;

if (! defined('ABSPATH')) {
    exit;
}

class Calendar
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        add_action('template_redirect', [$this, 'serve_ics']);
    }

    public function serve_ics(): void
    {
        $order_id = absint($_GET['wds_ics'] ?? 0);
        $token    = sanitize_text_field(wp_unslash($_GET['wds_token'] ?? ''));

        if (! $order_id || ! wp_verify_nonce($token, 'wds_ics_' . $order_id)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        if (is_user_logged_in() && (int) get_current_user_id() !== (int) $order->get_user_id()) {
            return;
        }

        $date = (string) $order->get_meta('_wds_delivery_date');
        $slot = (string) $order->get_meta('_wds_delivery_slot');
        if (! $date || ! $slot) {
            return;
        }

        $parts = array_map('trim', explode('-', $slot));
        if (2 !== count($parts)) {
            return;
        }

        [$start, $end] = $parts;

        $start_dt = gmdate('Ymd\THis\Z', strtotime($date . ' ' . $start));
        $end_dt   = gmdate('Ymd\THis\Z', strtotime($date . ' ' . $end));

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//WDS//EN\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= 'UID:wds-' . $order_id . '@' . wp_parse_url(home_url('/'), PHP_URL_HOST) . "\r\n";
        $ics .= 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n";
        $ics .= 'DTSTART:' . $start_dt . "\r\n";
        $ics .= 'DTEND:' . $end_dt . "\r\n";
        $ics .= 'SUMMARY:' . $this->ics_escape('Delivery Order #' . $order->get_order_number()) . "\r\n";
        $ics .= 'DESCRIPTION:' . $this->ics_escape('Delivery for customer ' . $order->get_formatted_billing_full_name()) . "\r\n";
        $ics .= 'LOCATION:' . $this->ics_escape((string) $order->get_meta('_wds_pickup_location')) . "\r\n";
        $ics .= "END:VEVENT\r\nEND:VCALENDAR\r\n";

        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="delivery-' . $order_id . '.ics"');
        echo $ics;
        exit;
    }

    private function ics_escape(string $string): string
    {
        return str_replace(["\\", ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $string);
    }
}
