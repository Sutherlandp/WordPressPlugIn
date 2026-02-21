<?php

namespace WDS;

if (! defined('ABSPATH')) {
    exit;
}

require_once WDS_PLUGIN_DIR . 'includes/class-wds-settings.php';
require_once WDS_PLUGIN_DIR . 'includes/class-wds-checkout.php';
require_once WDS_PLUGIN_DIR . 'includes/class-wds-calendar.php';
require_once WDS_PLUGIN_DIR . 'includes/class-wds-admin-calendar-page.php';

class Plugin
{
    private static ?Plugin $instance = null;

    private Settings $settings;

    private Checkout $checkout;

    private Calendar $calendar;

    private Admin_Calendar_Page $admin_calendar_page;

    public static function instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compat']);

        if (! class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_required_notice']);
            return;
        }

        $this->settings           = new Settings();
        $this->checkout           = new Checkout($this->settings);
        $this->calendar           = new Calendar($this->settings);
        $this->admin_calendar_page = new Admin_Calendar_Page();

        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_pickup_location_taxonomy']);
        add_action('init', [$this, 'register_order_delivery_statuses']);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('woo-delivery-scheduler', false, dirname(plugin_basename(WDS_PLUGIN_FILE)) . '/languages');
    }

    public function declare_hpos_compat(): void
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WDS_PLUGIN_FILE, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', WDS_PLUGIN_FILE, true);
        }
    }

    public function woocommerce_required_notice(): void
    {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__('Woo Delivery Scheduler Pro requires WooCommerce to be installed and active.', 'woo-delivery-scheduler')
        );
    }

    public function register_pickup_location_taxonomy(): void
    {
        register_taxonomy('wds_pickup_location', ['product'], [
            'labels' => [
                'name'          => __('Pickup Locations', 'woo-delivery-scheduler'),
                'singular_name' => __('Pickup Location', 'woo-delivery-scheduler'),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => false,
            'show_in_rest'      => true,
        ]);
    }

    public function register_order_delivery_statuses(): void
    {
        register_post_status('wc-delivery-booked', [
            'label'                     => _x('Delivery Booked', 'Order status', 'woo-delivery-scheduler'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Delivery Booked <span class="count">(%s)</span>', 'Delivery Booked <span class="count">(%s)</span>', 'woo-delivery-scheduler'),
        ]);

        add_filter('wc_order_statuses', static function ($statuses) {
            $statuses['wc-delivery-booked'] = __('Delivery Booked', 'woo-delivery-scheduler');
            return $statuses;
        });
    }
}
