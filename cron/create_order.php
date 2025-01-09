<?php


use WooWMS\Services\Logicas;
use WooWMS\Utils\Logger;

require_once __DIR__ . '/../../../../wp-load.php';

$logger = Logger::init();

$order_statuses = [ 'completed' ];

$orders = [];

foreach ( $order_statuses as $order_status ) {
	$args = [
		'status' => $order_status,
		'limit'  => - 1
	];
	
	$orders = array_merge( $orders, wc_get_orders( $args ) );
}

foreach ( $orders as $order ) {
	$wms_logicas_order_id = $order->get_meta( Logicas::$META_WMS_LOGICAS_ORDER_ID );
	
	if ( $wms_logicas_order_id ) {
		continue;
	}
	
	$order_id = (int) $order->get_id();
	echo $order_id;
	
	do_action( 'woo_wms_cron_create_order', $order_id );
	
	// Log the process for debugging.
	$logger->info( 'Cron triggered woo_wms_cron_create_order for order ID: ' . $order_id );
}
