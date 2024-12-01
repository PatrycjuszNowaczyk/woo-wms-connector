<?php

namespace WooWMS\Services;

use WooWMS\Admin\Settings;
use WooWMS\Utils\Logger;

class Logicas {
	private string $apiBaseUrl;
	
	private int $warehouseId;
	
	private string $apiStoreToken;
	
	private string $apiManagementToken;
	
	private Logger|null $logger = null;
	
	public function __construct( Settings $settings ) {
		$this->apiBaseUrl         = $settings->getApiBaseUrl();
		$this->warehouseId        = $settings->getWarehouseId();
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
	private function getRightToken( string $url ): string {
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
					'X-Auth-Token' => $this->getRightToken( $url )
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
			wp_die( $e->getMessage() );
		}
	}
	
	/**
	 * Create order in Logicas warehouse by order id
	 *
	 * @param int $orderId
	 *
	 * @return object
	 */
	public function createOrder( int $orderId ): object {
		try {
			
			$order = wc_get_order( $orderId );
			if ( ! $order ) {
				throw new \Exception( "Order nr $orderId not found" );
			}
			
			$items = $order->get_items();
			foreach ( $items as $item ) {
				$product    = $item->get_product();
				$bundledIds = $product->get_bundled_ids();
				if ( ! empty( $bundledIds ) ) {
					foreach ( $bundledIds as $bundledId ) {
						$bundledProduct = wc_get_product( $bundledId );
					}
				}
				$upsellsIds = $product->get_upsell_ids();
				if ( ! empty( $upsellsIds ) ) {
					foreach ( $upsellsIds as $upsellId ) {
						$upsellProduct = wc_get_product( $upsellId );
					}
				}
			}
			
			$orderData = [
				'warehouse_code'   => $this->warehouseId,
				'shipping_method'  => 'innoship.' . $order->get_shipping_method(),
				'shipping_address' => [
					'first_name' => $order->get_shipping_first_name(),
					'last_name'  => $order->get_shipping_last_name(),
					'line1'      => $order->get_shipping_address_1(),
					'line2'      => $order->get_shipping_address_2(),
					'city'       => $order->get_shipping_city(),
					'email'      => $order->get_billing_email(),
					'phone'      => $order->get_billing_phone(),
					'country'    => $order->get_shipping_country(),
					'box_name'   => $order->get_shipping_method() ?? null
				],
				'shipping_comment' => $order->get_customer_note(),
				'order_number'     => $order->get_order_number(),
				'items'            => []
			];
			
			foreach ( $order->get_items() as $item ) {
				$orderData['items'][] = [
					'sku' => $item->get_product()->get_sku(),
					'qty' => $item->get_quantity()
				];
			}
			
			$response = $this->request( $this->apiBaseUrl . '/store/v2/orders/' . $orderId, 'PATCH', $orderData );
			
			$this->logger->info( 'Order data: ' . json_encode( $response ) );
			
			return $response;
			
		} catch ( \Exception $e ) {
			wp_die( $e->getMessage() );
		}
	}
	
	public function getStocks( $data ) {
		echo $data;
		$stocks = $this->request( $this->apiBaseUrl . '/management/v2/warehouse/' . $this->warehouseId . '/stocks' );
		
		return $stocks;
	}
}
