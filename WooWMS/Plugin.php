<?php

namespace WooWMS;

use WooWMS\Admin\Settings;
//use WooWMS\Cron\SyncOrders;
use WooWMS\Services\Logicas;

class Plugin {
	private static ?Settings $settings = null;
	
	public static function init(): void {
		// Load text domain.
		load_plugin_textdomain( WOO_WMS_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		
		// Initialize components.
		self::$settings = Settings::init();
//		SyncOrders::init();
		
		// Hook events.
		self::hookEvents();
	}
	
	private static function checkSettingsAreSet(): bool {
		foreach ( self::$settings->getSettings() as $key => $value ) {
			if ( empty( $value ) ) {
				return false;
			}
		}
		
		return true;
	}
	
	private static function hookEvents(): void {
		if ( false === self::checkSettingsAreSet() ) {
			return;
		}
		
		$logicas = new Logicas( self::$settings );
		
		// Hook events.
		add_action( 'woocommerce_payment_complete', [ $logicas, 'create_order' ] );
		add_action('save_post_order_shop', [ $logicas, 'update_order' ], 10, 3);
	}
}
