<?php


use WooWMS\Services\Logicas;
use WooWMS\Utils\Logger;

require_once __DIR__ . '/../../../../wp-load.php';

define('WOO_WMS_DOING_CRON', true);

$logger = Logger::init();

echo 'Cron has started creating orders' . PHP_EOL;

try {
	$args = [
		'status'     => 'completed',
		'meta_query' => [
			[
				'key'     => Logicas::$META_WMS_LOGICAS_ORDER_ID,
				'compare' => 'NOT EXISTS'
			]
		],
		'date_query' => [
			[
				'column' => 'post_date_gmt',
				'after'  => '1 day ago'
			]
		],
		'limit'      => - 1
	];
	
	$query = new WC_Order_Query( $args );
	
	$orders = $query->get_orders();
	
	foreach ( $orders as $order ) {
		$wms_logicas_order_id = $order->get_meta( Logicas::$META_WMS_LOGICAS_ORDER_ID );
		
		if ( ! empty( $wms_logicas_order_id ) ) {
			continue;
		}
		
		$order_id = (int) $order->get_id();
		echo 'Current order ID: ' . $order_id . PHP_EOL;
		
		// Log the process for debugging.
		$logger->info( 'Cron triggered woo_wms_cron_create_order for order ID: ' . $order_id . PHP_EOL );
		
		do_action( 'woo_wms_cron_create_order', $order_id );
	}
} catch ( Exception $e ) {
	$logger->error( $e->getMessage() );
}

echo 'Cron has finished creating orders' . PHP_EOL;
