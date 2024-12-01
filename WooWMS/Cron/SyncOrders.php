<?php

namespace WooWMS\Cron;

class SyncOrders {
	public static function init(): void {
		// Register cron job.
//        if (!wp_next_scheduled('woo_wms_sync_orders')) {
//            wp_schedule_event(time(), 'hourly', 'woo_wms_sync_orders');
//        }
	}
}