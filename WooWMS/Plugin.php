<?php

namespace WooWMS;

use WooWMS\Admin\Settings;
use WooWMS\Services\Logicas;

class Plugin {
	private static ?Settings $settings = null;
	
	public static function init(): void {
		// Load text domain for translations.
		$dirname = dirname( plugin_basename( __FILE__ ) ) . '/../languages';
		load_plugin_textdomain( 'woo_wms_connector', false, $dirname );
		
		// Initialize components.
		self::$settings = Settings::init();
		
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
		
		add_action( 'save_post_order_shop', [ $logicas, 'update_order' ], 10, 3 );
		
		add_action( 'woocommerce_order_status_changed', function ( $order_id, $old_status, $new_status, $order ) use ( $logicas ) {
			if ( in_array( $new_status, [ 'cancelled', 'refunded', 'failed' ] ) ) {
				$logicas->cancel_order( $order );
			}
		}, 10, 4 );

//		add_action('woocommerce_before_product_object_save', [ $logicas, 'update_product' ], 10, 1);
		
		// AJAX actions
		add_action('wp_ajax_woo_wms_update_shop_stocks', [ $logicas, 'update_shop_stocks' ]);
		
		add_action('wp_ajax_woo_wms_create_order', function () use ( $logicas ) {
			$logicas->create_order( (int) $_GET['order_id'] );
		});
		
		// Custom CRON actions
		add_action( 'woo_wms_cron_create_order', [ $logicas, 'create_order' ] );
	}
}
