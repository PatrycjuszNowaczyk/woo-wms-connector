<?php

namespace WooWMS\Services;

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
			
			if ( is_wp_error( $response ) ) {
				throw new \Exception( $response->get_error_message() );
			}
			
			return json_decode( wp_remote_retrieve_body( $response ) );
		} catch ( \Exception $e ) {
			$this->logger->error( $e->getMessage() );
			wp_die( $e->getMessage() );
		}
	}
	
	private function get_shipping_method(object $order): object {
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
	 * @return object
	 */
	public function create_order( int $orderId ): object {
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
		
		try {
			// get order object by id
			$order = wc_get_order( $orderId );
			if ( ! $order ) {
				throw new \Exception( "Order nr $orderId not found" );
			}
			
			// declare variables
			$order_items   = $order->get_items();
			$shipping_method = $this->get_shipping_method($order);
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
				$orderData['shipping_address']['box_name'] = $_POST['parcel_machine_id'];
			}
			
			$orderResponse = $this->request( $this->apiBaseUrl . '/store/v2/orders', 'POST', $orderData );
			if ( ! $orderResponse ) {
				throw new \Exception( 'Order not created: ' . json_encode( $orderData ) );
			}
			
			$order->update_meta_data('wms_logicas_order_id', $orderResponse->id);
			$order->save();
			
			$this->logger->info( 'Order data: ' . json_encode( $orderResponse ) );
			
			$shipResponse = $this->request($this->apiBaseUrl . '/store/v2/orders/' . $orderResponse->id . '/ship', 'POST');
			
			$this->logger->info( 'Ship data: ' . json_encode( $shipResponse ) );
			
			return $orderResponse;
			
		} catch ( \Exception $e ) {
			$this->logger->error( $e->getMessage() );
			
			wp_die( $e->getMessage() );
		}
	}
	
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
				throw new \Exception( 'Order not updated' );
			}
			
			$this->logger->info( 'Order data: ' . json_encode( $orderResponse ) );
		} catch ( \Exception $e ) {
			$this->logger->error( $e->getMessage() );
		}
	}
	
	public function get_stocks( $data ) {
		echo $data;
		$stocks = $this->request( $this->apiBaseUrl . '/management/v2/warehouse/' . $this->warehouseId . '/stocks' );
		
		return $stocks;
	}
}
