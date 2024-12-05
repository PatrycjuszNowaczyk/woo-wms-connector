<?php
/**
* Plugin Name: Woo WMS Connector
* Description: Connect WooCommerce with a WMS API.
* Version: 1.0
* Author: Patrycjusz Nowaczyk
* Author URI: https://github.com/patrycjusznowaczyk
* Text Domain: woo-wms-connector
* Domain Path: /languages
* Requires Plugins: woocommerce
* WC requires at least: 3.0.0
*/

require_once plugin_dir_path(__FILE__) . 'functions.php';

defined('ABSPATH') || exit;

const WOO_WMS_NSP = 'WooWMS';
const WOO_WMS_TEXT_DOMAIN = 'woo-wms-connector';

// Autoload classes.
spl_autoload_register(function ($class_name) {
    if (false === str_starts_with($class_name, WOO_WMS_NSP . '\\')) {
        return;
    }

    $removedWooWMS = str_replace(WOO_WMS_NSP . '\\', '', $class_name);
    $file = plugin_dir_path(__FILE__) . WOO_WMS_NSP . '/' . str_replace('\\', '/', $removedWooWMS) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize the plugin.
function woo_wms_connector_init(): void {
    WooWMS\Plugin::init();
}
add_action('plugins_loaded', 'woo_wms_connector_init');