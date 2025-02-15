<?php

namespace WooWMS;

use WooWMS\Admin\Settings;
use WooWMS\Services\Logicas;
use WooWMS\Utils\Utils;
use WC_Product;

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
		/**
		 * Hook to create order in WMS when WooCommerce order is completed.
		 */
		add_action( 'woocommerce_payment_complete', [ $logicas, 'create_order' ] );
		
		/**
		 * Hook to update shop stocks in WMS when WooCommerce order is completed.
		 */
		add_action( 'woocommerce_payment_complete', [ $logicas, 'update_shop_stocks' ] );
		
		/**
		 * Hook to update order in WMS when WooCommerce order is updated.
		 */
		add_action( 'save_post_order_shop', [ $logicas, 'update_order' ], 10, 3 );
		
		/**
		 * Hook to cancel order in WMS when WooCommerce order is cancelled.
		 */
		add_action( 'woocommerce_order_status_changed', function ( $order_id, $old_status, $new_status, $order ) use ( $logicas ) {
			if ( in_array( $new_status, [ 'cancelled', 'refunded', 'failed' ] ) ) {
				$logicas->cancel_order( $order );
			}
		}, 10, 4 );
		
		/**
		 * Hook to get data of a product before save to create/update product in WMS when WooCommerce product is created/updated.
		 */
		add_action( 'woocommerce_before_product_object_save', function ( WC_Product $product ) use ( $logicas ) {
			$available_product_types = [ 'simple', 'variable', 'variation' ];
			$product_type = $product->get_type();
			
			if ( ! in_array( $product_type, $available_product_types ) ) {
				return;
			}
			
			$fields_to_compare_before = Utils::generate_required_compare_array( $product, 'before' );
			
			set_transient('product_before_save', $fields_to_compare_before, 300);
		}, 10, 1 );

		/**
		 * Hook to create/update product in WMS when WooCommerce product is created/updated.
		 */
		add_action( 'woocommerce_after_product_object_save', function ( WC_Product $product ) use ( $logicas ) {
			if (wp_is_post_autosave($product->get_id()) || wp_is_post_revision($product->get_id())) {
				return;
			}
			
			$available_product_types = [ 'simple', 'variable', 'variation' ];
			$product_type = $product->get_type();
			if ( ! in_array( $product_type, $available_product_types ) ) {
				return;
			}
   
			$fields_to_compare_before = get_transient( 'product_before_save' );
			delete_transient( 'product_before_save' );
			$fields_to_compare_after =  Utils::generate_required_compare_array( $product );
			
			if ( empty( $fields_to_compare_before ) || empty( $fields_to_compare_after ) ) {
				return;
			}
			
			if (
				( 0 === $fields_to_compare_before['id'] && 0 < $fields_to_compare_after['id'] )
				|| ( ( 0 !== $fields_to_compare_before['wms_id'] && 0 !== $fields_to_compare_after['wms_id'] )
				&& serialize( $fields_to_compare_before ) === serialize( $fields_to_compare_after ) )
			) {
				return;
			}
			
			if (
				empty( $fields_to_compare_before[ 'wms_id' ] )
			) {
				$logicas->create_product( $fields_to_compare_after );
			} elseif ( 0 !== $fields_to_compare_after['wms_id'] ) {
				$logicas->update_product( $fields_to_compare_after );
			}
		}, 10, 1 );
		
		/**
		 * Hook to add admin notice using WordPress transient.
		 */
    add_action( 'admin_notices', function() {
	    $screen = get_current_screen();
	    if ( 'product' !== $screen->id && 'post' !== $screen->base ) {
	      return;
	    }
	    
	    Utils::show_admin_notices();
    }, 100, 0 );
		
		// AJAX actions
		add_action( 'wp_ajax_woo_wms_update_shop_stocks', [ $logicas, 'update_shop_stocks' ] );
		
		add_action( 'wp_ajax_woo_wms_create_order', function () use ( $logicas ) {
			$logicas->create_order( (int) $_GET['order_id'] );
		} );
		
		add_action( 'wp_ajax_woo_wms_get_orders_statuses', function () use ( $logicas ) {
			$orders_ids = explode( ',', $_GET['orders_ids'] );
			$logicas->get_orders_statuses( $orders_ids );
		} );
		
		add_action( 'wp_ajax_woo_wms_delete_product', function() use ( $logicas ) {
			$product_id = $_GET['productId'];
			$product_wms_id = $_GET['productWmsId'];
			$logicas->delete_product( $product_id, $product_wms_id );
		} );
		
		add_action( 'wp_ajax_woo_wms_get_all_manufacturers', [ $logicas, 'get_all_manufacturers' ] );
		
		// Custom CRON actions
		add_action( 'woo_wms_cron_create_order', [ $logicas, 'create_order' ] );
	}
}
