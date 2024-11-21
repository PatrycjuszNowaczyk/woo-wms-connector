<?php
/**
* Plugin Name: Woo WMS Connector
* Description: Connect WooCommerce with a WMS API.
* Version: 1.0
* Author: Patrycjusz Nowaczyk
* Author URI: https://github.com/patrycjusznowaczyk
* Text Domain: woo-wms-connector
* Domain Path: /languages
*/

defined('ABSPATH') || exit;

add_action('admin_notices', function () {
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p>Hello! This is my first plugin.</p>';
    echo '</div>';
});