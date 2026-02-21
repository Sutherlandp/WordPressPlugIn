<?php
/**
 * Plugin Name: Woo Delivery Scheduler Pro
 * Description: Advanced delivery scheduling for WooCommerce: categories, shipping methods, pickup locations, slots, limits, and calendar exports.
 * Version: 0.1.0
 * Author: Codex
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * Text Domain: woo-delivery-scheduler
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WDS_VERSION', '0.1.0');
define('WDS_PLUGIN_FILE', __FILE__);
define('WDS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WDS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WDS_PLUGIN_DIR . 'includes/class-wds-plugin.php';

add_action('plugins_loaded', static function () {
    \WDS\Plugin::instance();
});
