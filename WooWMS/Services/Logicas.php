<?php

namespace WooWMS\Services;

use Exception;
use WC_Shipping_Zones;
use WooWMS\Admin\Settings;
use WooWMS\Utils\Logger;

class Logicas {
	static string $META_WMS_LOGICAS_ORDER_ID = 'wms_logicas_order_id';
	
	static string $META_PARCEL_MACHINE_ID = 'parcel_machine_id';
	
	private string $apiBaseUrl;
	
	private int $warehouseId;
	
	private string $warehouseCode;
	
	private string $apiStoreToken;
	
	private string $apiManagementToken;
	
	private Logger|null $logger = null;
	
	public function __construct( Settings $settings ) {
		$this->apiBaseUrl         = $settings->getApiBaseUrl();
		$this->warehouseId        = $settings->getWarehouseId();
		$this->warehouseCode      = $settings->getWarehouseCode();
		$this->apiStoreToken      = $settings->getApiStoreToken();
		$this->apiManagementToken = $settings->getApiManagementToken();
		
		$this->logger = Logger::init();
	}
	
	/**
	 * Get right token for request based on url
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private function get_right_token( string $url ): string {
		return str_contains( $url, '/management' ) ? $this->apiManagementToken : $this->apiStoreToken;
	}
	
	/**
	 * Send a request to Logicas API
	 *
	 * @param string $url
	 * @param string $method
	 * @param array|null $data
	 *
	 * @return mixed|void
	 *
	 * @throws Exception
	 */
	private function request( string $url, string $method = 'GET', array $data = null ) {
		try {
			$args = [
				'method'  => strtoupper( $method ),
				'headers' => [
					'X-Auth-Token' => $this->get_right_token( $url )
				]
			];
			
			if ( false === in_array( $args['method'], [ 'GET', 'HEAD', 'OPTIONS' ] ) ) {
				$args['body'] = json_encode( $data );
			}
			
			$response = wp_remote_request( $url, $args );
			$response_code = wp_remote_retrieve_response_code( $response );
			
			if ( is_wp_error( $response )) {
				throw new Exception( $response->get_error_message() );
			} else if ( 400 <= $response_code) {
				throw new Exception( wp_remote_retrieve_body( $response ) );
			}
			
			return json_decode( wp_remote_retrieve_body( $response ) );
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
			throw new Exception( $e->getMessage() );
		}
	}
	
	private function get_shipping_method(object $order): object|bool {
		$shipping_method_name = $order->get_shipping_method();
		$shipping_methods     = $order->get_shipping_methods();
		$shipping_instance_id = null;
		
		// get selected shipping method instance id
		foreach ( $shipping_methods as $method ) {
			if ( str_contains( $method->get_name(), $shipping_method_name ) ) {
				$shipping_instance_id   = $method->get_instance_id();
			}
		}
		
		return WC_Shipping_Zones::get_shipping_method($shipping_instance_id);
	}
	
	/**
	 * Create order in Logicas warehouse by order id
	 *
	 * @param int $orderId
	 *
	 * @return void
	 */
	public function create_order( int $orderId ): void {
		if ( ! function_exists( 'WooWMS\Services\create_products_order_array' ) ) {
			function create_products_order_array( array $items_to_send, string $sku, int $item_quantity ): array {
				$key = array_search( $sku, array_column( $items_to_send, 'sku' ) );
				if ( $key !== false ) {
					$items_to_send[ $key ]['qty'] = $items_to_send[ $key ]['qty'] + $item_quantity;
				} else {
					$items_to_send[] = [
						'sku' => $sku,
						'qty' => $item_quantity
					];
				}
				
				return $items_to_send;
			}
		}
		
		try {
			// get order object by id
			$order = wc_get_order( $orderId );
			if ( ! $order ) {
				throw new Exception( "Order nr $orderId not found" );
			}
			
			$wms_logicas_order_id = $order->get_meta( self::$META_WMS_LOGICAS_ORDER_ID );
			if ( ! empty( $wms_logicas_order_id ) ) {
				throw new Exception( "Order nr $orderId already created in Logicas with nr: " . $wms_logicas_order_id );
			}
			
			// declare variables
			$order_items   = $order->get_items();
			$shipping_method = $this->get_shipping_method($order);
			$parcel_machine_id = $order->get_meta( self::$META_PARCEL_MACHINE_ID );
			$items_to_send = [];
			
			// add products to items_to_send
			foreach ( $order_items as $item ) {
				$item_quantity = $item->get_quantity();
				$product       = $item->get_product();
				$product_sku   = $product->get_sku();
				
				if ( str_contains( $product_sku, '|' ) ) {
					$product_sku = explode( '|', $product_sku );
				} else {
					$product_sku = trim($product_sku);
				}
				
				if ( is_array( $product_sku ) ) {
					foreach ( $product_sku as $sku ) {
						$sku = trim($sku);
						$items_to_send = create_products_order_array( $items_to_send, $sku, $item_quantity );
					}
				} else {
					$items_to_send = create_products_order_array( $items_to_send, $product_sku, $item_quantity );
				}
			}
			
			// prepare order data
			$orderData = [
				'warehouse_code'   => $this->warehouseCode,
				'shipping_method'  => 'innoship.' . strtolower( $shipping_method->id ),
				'shipping_address' => [
					'zip'        => trim($order->get_shipping_postcode()),
					'city'       => trim($order->get_shipping_city()),
					'email'      => trim($order->get_billing_email()),
					'line1'      => trim($order->get_shipping_address_1()),
					'line2'      => trim($order->get_shipping_address_2()),
					'phone'      => trim($order->get_billing_phone()),
					'last_name'  => trim($order->get_shipping_last_name()),
					'first_name' => trim($order->get_shipping_first_name()),
					'country'    => trim($order->get_shipping_country())
				],
				'shipping_comment' => trim($order->get_customer_note()),
				'order_number'     => $order->get_order_number(),
				'items'            => $items_to_send
			];
			
			if ( 'inpost' === $shipping_method->id && 'inpost-locker-247' === $shipping_method->get_inpost_type() ) {
				$orderData['shipping_address']['box_name'] = $parcel_machine_id ?: $_POST[ self::$META_PARCEL_MACHINE_ID ];
			}
			
			$orderResponse = $this->request( $this->apiBaseUrl . '/store/v2/orders', 'POST', $orderData );
			if ( ! $orderResponse ) {
				throw new Exception( 'Order not created: ' . json_encode( $orderData ) );
			}
			
			$order->update_meta_data( self::$META_WMS_LOGICAS_ORDER_ID, $orderResponse->id );
			$order->save();
			
			$this->logger->info( 'Order data send to WMS: ' . json_encode( $orderData ) );
			$this->logger->info( 'Order data in WMS: ' . json_encode( $orderResponse ) );
			
			$shipResponse = $this->request($this->apiBaseUrl . '/store/v2/orders/' . $orderResponse->id . '/ship', 'POST');
			
			$this->logger->info( 'Ship data in WMS: ' . json_encode( $shipResponse ) );
			
			if (
				defined( 'DOING_AJAX' )
				&& DOING_AJAX
				&& isset( $_GET['action'] )
				&& 'woo_wms_create_order' === $_GET['action']
			) {
				wp_send_json_success([
					'message' => __('Order created', 'woo_wms_connector')
				], 200);
			}
			
			$this->update_shop_stocks();
			
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
			
			if (
				defined( 'DOING_AJAX' ) && DOING_AJAX
				&& isset( $_GET['action'] ) && 'woo_wms_create_order' === $_GET['action']
			) {
				wp_send_json_error([
					'message' => $e->getMessage()
				], 500);
			}
		}
	}
	
	
	/**
	 * Update order in Logicas warehouse
	 *
	 * @param object $order
	 *
	 * @return void
	 */
	public function update_order( object $order ): void {
		try {
			if (
				false === is_admin() ||
				(isset($_GET['page']) && 'wc-orders' !== $_GET['page'] && isset($_GET['action']) && 'edit' !== $_GET['action'])
			) {
				return;
			}
			
			if (0 === count($order->get_changes())) {
				return;
			}
			
			$orderData = [
				'warehouse_code' => $this->warehouseCode,
				'items'          => $order['changes']
			];
			
			$orderResponse = $this->request( $this->apiBaseUrl . '/store/v2/orders/' . $order['id'], 'PUT', $orderData );
			if ( ! $orderResponse ) {
				throw new Exception( 'Order not updated' );
			}
			
			$this->logger->info( 'Order data: ' . json_encode( $orderResponse ) );
			
			$this->update_shop_stocks();
			
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
		}
	}
	
	/**
	 * Update woocommerce stocks in a database
	 *
	 * @return void
	 */
	public function update_shop_stocks(): void {
		try {
			$stocks = $this->request( $this->apiBaseUrl . '/management/v2/warehouse/' . $this->warehouseId . '/stocks/sellable' );
			if ( ! $stocks ) {
				throw new Exception( 'Stocks not found' );
			}
			$stocks = $stocks->items;
			
			$all_products = wc_get_products([
				'status' => 'publish',
				'limit'  => -1
			]);
			
			foreach ($all_products as $product) {
				if ($product->get_children()) {
					foreach ($product->get_children() as $child) {
						$all_products[] = wc_get_product( $child );
					}
				}
			}
			
			foreach ($all_products as $product) {
				if ( str_contains($product->get_sku(), '|') ) {
					$skus = array_fill_keys(explode('|', $product->get_sku()), 0);
					$skus = array_map('trim', $skus);
					
					foreach ( $skus as $sku => $qty ) {
						$skus[$sku] = $stocks[
							array_search($sku, array_column($stocks, 'sku') )
						]->quantity;
					}
					
					$minimal_quantity = min($skus);
					$product->set_stock_quantity((int) $minimal_quantity);
					$product->save();
					
					continue;
				}
				
				$stock_index = array_search($product->get_sku(), array_column($stocks, 'sku') );
				
				if ( $product->get_sku()) {
					$product->set_stock_quantity(
						$stock_index ? $stocks[ $stock_index ]->quantity : 0
					);
					$product->save();
				}
			}

			
			if (
				defined( 'DOING_AJAX' )
				&& DOING_AJAX
				&& isset( $_GET['action'] )
				&& 'woo_wms_update_shop_stocks' === $_GET['action']
			) {
				wp_send_json_success([
					'message' => __('Stocks updated successfully!', 'woo_wms_connector')
				], 200);
			}
			
		} catch ( Exception $e ) {
			$this->logger->error( 'Stocks not updated: ' . $e->getMessage() );
			
			if (
				defined( 'DOING_AJAX' )
				&& DOING_AJAX
				&& isset( $_GET['action'] )
				&& 'woo_wms_update_shop_stocks' === $_GET['action']
			) {
				wp_send_json_error([
					'message' => $e->getMessage()
				], 500);
			}
		}
	}
	
	/**
	 * Cancel order in Logicas warehouse
	 *
	 * @param object $order
	 *
	 * @return void
	 */
	public function cancel_order( object $order ): void {
		try {
			$is_wms_order = $order->get_meta( self::$META_WMS_LOGICAS_ORDER_ID );
			if ( ! $is_wms_order ) {
				return;
			}
			
			$order_id = $order->get_id();
			$wms_order_id = $order->get_meta( self::$META_WMS_LOGICAS_ORDER_ID );
			
			$orderResponse = $this->request( $this->apiBaseUrl . '/store/v2/orders/' . $wms_order_id . '/cancel', 'POST' );
			
			if ( false === is_array($orderResponse) && ! $orderResponse ) {
				throw new Exception( 'Order not canceled' );
			}
			
			$this->logger->info( 'Canceled shop order id: ' . $order_id . ' | ' . 'Canceled wms order id: ' . $wms_order_id );
			
			$this->update_shop_stocks();
			
			add_action('admin_notices', function () use ($order_id) {
				echo '<div class="notice notice-success is-dismissible">
					<p>' . sprintf(__('Order nr %s was successfully canceled in WMS', 'woo_wms_connector'), $order_id) . '</p>
				</div>';
			}, 0, 0);
			
			
		} catch ( Exception $e ) {
			
			add_action('admin_notices', function () use ($order_id) {
				echo '<div class="notice notice-warning is-dismissible">
					<p>' . sprintf(__('Order nr %s was not canceled in Logicas warehouse', 'woo_wms_connector'), $order_id) . '</p>
				</div>';
			}, 0, 0);
			
			$this->logger->error( $e->getMessage() );
		}
	}
}
