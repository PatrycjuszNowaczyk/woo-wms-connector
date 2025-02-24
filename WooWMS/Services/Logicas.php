<?php

namespace WooWMS\Services;

use Exception;
use WooWMS\Admin\Settings;
use WooWMS\Enums\AdminNoticeType;
use WooWMS\Utils\Logger;
use WC_Product;
use WooWMS\Utils\Utils;

class Logicas {
	static string $META_WMS_LOGICAS_ORDER_ID = 'wms_logicas_order_id';
	
	static string $META_WMS_LOGICAS_IS_ORDER_CANCELLED = 'wms_logicas_is_order_cancelled';
	
	static string $META_WMS_SHOP_CUSTOM_ORDER_ID = 'wms_shop_custom_order_id';
	
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
	private function request( string $url, string $method = 'GET', array $data = [] ) {
		try {
			$method = strtoupper( $method );
			$args = [
				'method'  => $method,
				'headers' => [
					'X-Auth-Token' => $this->get_right_token( $url )
				],
				'timeout' => apply_filters( 'http_request_timeout', 15, $url )
			];
			
			if (
				false === in_array( $method, [ 'GET', 'HEAD', 'OPTIONS' ] )
				&& false === empty( $data )
			) {
				$args['body'] = json_encode( $data );
			}
			
			$response = wp_remote_request( $url, $args );
			$response_code = wp_remote_retrieve_response_code( $response );
			
			if ( is_wp_error( $response )) {
				throw new Exception( $response->get_error_message() );
			} else if ( 400 <= $response_code) {
				if ( 401 === $response_code ) {
					$message = __('Your request has not been properly authorized.');
				} else {
					$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
					$message = $response_body['message'] ?? (string) $response_body ?: __( 'There is no message from WMS api.' );
				}
				throw new Exception( $message );
			}
			
			return json_decode( wp_remote_retrieve_body( $response ) );
		} catch ( Exception $e ) {
			$error_message = __('Error from WMS API:', 'woo_wms_connector') . ' ' . $e->getMessage();
			throw new Exception( $error_message );
		}
	}
	
	private function get_shipping_method(object $order): object|null {
		$shipping_items = $order->get_items('shipping');
		$shipping_item = null;
		
		foreach ($shipping_items as $item) {
			$shipping_item = $item;
			break;
		}
		
		return $shipping_item;
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
			
			if ( empty( $shipping_method ) ) {
				throw new Exception( 'Shipping method not found in order with ID: ' . $orderId );
			}
			
			$shipping_method_id = $shipping_method->get_method_id();
			$parcel_machine_id = $order->get_meta( self::$META_PARCEL_MACHINE_ID ) ?: $_POST[ self::$META_PARCEL_MACHINE_ID ] ?? null;
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
			
			if ( empty( $items_to_send ) ) {
				throw new Exception( 'No products found in order with ID: ' . $orderId );
			}
			
			// prepare order data
			$orderData = [
				'warehouse_code'   => $this->warehouseCode,
				'shipping_method'  => 'innoship.' . strtolower( $shipping_method_id ),
				'shipping_address' => [
					'zip'        => trim($order->get_shipping_postcode()),
					'city'       => trim($order->get_shipping_city()),
					'email'      => trim($order->get_billing_email()),
					'line1'      => trim($order->get_shipping_address_1()),
					'line2'      => trim($order->get_shipping_address_2()),
					'phone'      => trim($order->get_shipping_phone()),
					'last_name'  => trim($order->get_shipping_last_name()),
					'first_name' => trim($order->get_shipping_first_name()),
					'country'    => trim($order->get_shipping_country())
				],
				'shipping_comment' => trim($order->get_customer_note()),
				'order_number'     => $order->get_order_number(),
				'items'            => $items_to_send
			];
			
			if ( 'inpost' === $shipping_method_id && ! empty($parcel_machine_id) ) {
				$orderData['shipping_address']['box_name'] = $parcel_machine_id;
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
			
			if ( defined('WOO_WMS_DOING_CRON') && WOO_WMS_DOING_CRON ) {
				echo 'Error: ' . $e->getMessage() . PHP_EOL;
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
						false !== $stock_index ? $stocks[ $stock_index ]->quantity : 0
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
			$is_wms_order_cancelled = $order->get_meta( self::$META_WMS_LOGICAS_IS_ORDER_CANCELLED );
			if ( ! empty($is_wms_order_cancelled) ) {
				return;
			}
			
			$order_id = $order->get_id();
			$wms_order_id = $order->get_meta( self::$META_WMS_LOGICAS_ORDER_ID );
			
			$orderResponse = $this->request( $this->apiBaseUrl . '/store/v2/orders/' . $wms_order_id . '/cancel', 'POST' );
			
			if ( false === is_array($orderResponse) && ! $orderResponse ) {
				throw new Exception( 'Order not canceled' );
			}
			
			$order->update_meta_data( self::$META_WMS_LOGICAS_IS_ORDER_CANCELLED, 1 );
			$order->save();
			
			$this->logger->info( 'Canceled shop order id: ' . $order_id . ' | ' . 'Canceled wms order id: ' . $wms_order_id );
			
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
		} finally {
			$this->update_shop_stocks();
		}
	}
	
	/**
	 * Get orders statuses from Logicas warehouse
	 *
	 * @param array $orders_ids
	 *
	 * @return void
	 */
	public function get_orders_statuses( array $orders_ids ): void {
		$orders_statuses = [];
		
		try {
			foreach ( $orders_ids as $order_id ) {
				$order                  = wc_get_order( $order_id );
				$wms_order_id           = $order->get_meta( self::$META_WMS_LOGICAS_ORDER_ID );
				$wms_order_is_cancelled = $order->get_meta( self::$META_WMS_LOGICAS_IS_ORDER_CANCELLED );
				
				if ( $wms_order_is_cancelled ) {
					$orders_statuses[ $order_id ] = 'cancelled';
					continue;
				}
				
				if ( empty( $wms_order_id ) ) {
					$orders_statuses[ $order_id ] = '<code>N/A</code>';
					continue;
				}
				
				$wms_order                    = $this->request( $this->apiBaseUrl . '/store/v2/order/' . $wms_order_id . '/details' );
				$orders_statuses[ $order_id ] = $wms_order->status;
			}
			
			$orders_statuses = array_map(function ($status) {
				return ucfirst(str_replace('-', ' ', $status));
			}, $orders_statuses);
			
			wp_send_json_success( [
				'orders_statuses' => $orders_statuses
			], 200 );
			
		} catch ( Exception $e ) {
			wp_send_json_error( [
				'message' => $e->getMessage()
			], 500 );
		}
	}
	
	/**
	 * Create product in Logicas warehouse
	 *
	 * @param array $product
	 *
	 * @return void
	 */
	public function create_product( array $product ): void {
		$required_fields = [
			'manufacturer' => '( ' . __( 'Manufacturer', 'woo_wms_connector' ) . ' )',
			'sku'=> '( ' . __( 'SKU', 'woo_wms_connector' ) . ')',
			'ean' => '( ' . __( 'GTIN, UPC, EAN, or ISBN', 'woo_wms_connector' ) . ' )',
			'name'=> '( ' . __( 'WMS name', 'woo_wms_connector' ) . ' )',
			'weight' => '( ' . __( 'Weight (kg)', 'woo_wms_connector' ) . ' )'
		];
		
		$missing_fields = [];
		
		try {
			foreach ( $required_fields as $key => $value ) {
				if ( empty( $product[ $key ] ) ) {
					$missing_fields[] = $value;
				}
			}
			
			if ( ! empty( $missing_fields ) ) {
				$message = sprintf(
					__("Product with SKU <code>%s</code> has missing required fields: %s.", 'woo_wms_connector'),
					$product['sku'],
					join(', ', $missing_fields)
				);
				Utils::set_admin_notice( $message, AdminNoticeType::WARNING );
				return;
			}
		
			$response = $this->request( $this->apiBaseUrl . '/management/v2/products', 'POST', $product );
			
			if ( false === empty( $response->id) ) {
				update_post_meta( $product['id'], 'wms_id', $response->id );
			}
			
			$message = sprintf( __('Product with SKU <code>%s</code> has been created.', 'woo_wms_connector'), $product['sku'] );
			Utils::set_admin_notice( $message, AdminNoticeType::SUCCESS );
		} catch ( Exception $e ) {
			if ( str_contains( $e->getMessage(), $product['sku'] ) || str_contains( $e->getMessage(), $product['ean'] ) ) {
				if ( $this->sync_wc_product_data_with_wms( $product, $this->get_list_of_products()) ) {
					$message = sprintf( __( "Product with SKU number <code>%s</code> already exists in the WMS database. All data stored in WMS was synchronized to match product data stored in WooCommerce with WMS data.", 'woo_wms_connector' ), $product['sku'] );
					Utils::set_admin_notice( $message, AdminNoticeType::INFO );
				}
				
				return;
			}
			Utils::set_admin_notice( $e->getMessage(), AdminNoticeType::ERROR );
		}
	}
	
	/**
	 * Update product in Logicas warehouse
	 *
	 * @param array $product
	 *
	 * @return void
	 */
	public function update_product( array $product ): void {
		try {
			$fields_to_update = [
				'name'   => $product['name'],
				'weight' => (int) $product['weight']
			];
			
			$response = $this->request( $this->apiBaseUrl . '/management/v2/product/' . $product['wms_id'], 'PATCH', $fields_to_update );
			
			$message = $product['sku'] ?
				sprintf( __( 'Product with SKU <code>%s</code> has been updated.', 'woo_wms_connector' ), $product['sku'] )
				: __( 'Product has been updated.', 'woo_wms_connector' );
			Utils::set_admin_notice( $message, AdminNoticeType::SUCCESS );
		
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
			Utils::set_admin_notice( $e->getMessage(), AdminNoticeType::ERROR );
		}
	}
	
	/**
	 * Delete product from WMS
	 *
	 * @param int $product_id
	 * @param int $product_wms_id
	 */
	public function delete_product( int $product_id, int $product_wms_id ): void {
		try {
			if ( empty( $product_id ) ) {
				throw new Exception( 'WooCommerce\'s product ID is required', 400 );
			}
			
			if ( empty( $product_wms_id ) ) {
				throw new Exception( 'WMS\'s product ID is required', 400 );
			}
			
			$product = wc_get_product(  $product_id );
			
			$response = $this->request( $this->apiBaseUrl . '/management/v2/product/' . $product_wms_id, 'DELETE' );
			
			delete_post_meta( $product_id, 'wms_id' );
			
			wp_send_json_success( sprintf( __( 'Product with WMS ID: %s and SKU: %s was successfully deleted.', 'woo_wms_connector' ), $product_wms_id, $product->get_sku() ) );
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
			wp_send_json_error( $e->getMessage() );
		}
	}
	
	/**
	 * Fetch all products from api
	 *
	 * @return array
	 */
	public function get_list_of_products(): array {
		try {
			$products = [];
			$page = 1;
			$response = $this->request( $this->apiBaseUrl . '/management/v2/products?page=' . $page, 'GET' );
			$total_pages = $response->meta->pagination->totalPages;
			$products = $response->items;
			
			for( $page = 2; $page <= $total_pages; $page++ ) {
				$response = $this->request( $this->apiBaseUrl . '/management/v2/products?page=' . $page, 'GET' );
				$products[] = $response->items;
			}
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
			$message = "An error occurred while retrieving products:\n\n" . $e->getMessage();
			Utils::set_admin_notice( $message, AdminNoticeType::ERROR );
		}
		
		return $products;
	}
	
	/**
	 * Assign a product data stored in WMS to a WooCommerce product meta
	 *
	 * @param array $product_data
	 * @param array $wms_list_of_products
	 *
	 * @return bool
	 */
	private function sync_wc_product_data_with_wms( array $product_data, array $wms_list_of_products ): bool {
		$product_data_fetched = false;
		
		try {
			$wms_product = null;
			
			foreach ( $wms_list_of_products as $wms_product_item ) {
				if ( trim( (string) $wms_product_item->sku ) === trim( (string) $product_data['sku'] ) ) {
					$wms_product = $wms_product_item;
					break;
				}
			}

			
			if ( empty( $wms_product ) ) {
				$message = "Failed to synchronize data. "
				           . "The product with SKU <code>%s</code> was not found in your WMS product list. "
				           . "Verify SKU field have valid value. If so, contact your WMS provider.";
				
				throw new Exception( sprintf( __( $message, 'woo_wms_connector' ), $product_data['sku'] ), '404' );
			}
			
			if ( $product_data['ean'] !== $wms_product->ean ) {
				throw new Exception(
					sprintf(
						__( "Failed to synchronize data. A product with SKU number <code>%s</code> has a different EAN code ( <code>%s</code> ) in WMS product list. A EAN you entered is <code>%s</code>.", 'woo_wms_connector' ),
						$product_data['sku'],
						$wms_product->ean,
						$product_data['ean']
					),
					'404' );
			}
			
			$product = wc_get_product( $product_data['id'] );
			
			$response = $this->request( $this->apiBaseUrl . '/management/v2/product/' . $wms_product->id, 'GET' );
			
			$product->set_wms_id( $response->id );
			$product->set_wms_name( $response->name );
			$product->set_manufacturer( $response->manufacturer->id );
			update_post_meta( $product->get_id(), '_weight', $response->weight / 1000 );
			
			$product_data_fetched = true;
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
			$message = $e->getMessage();
			Utils::set_admin_notice( $message, AdminNoticeType::ERROR );
		}
		
		return $product_data_fetched;
	}
	
	/**
	 * Get all manufacturers from Logicas warehouse API
	 *
	 * @return void
	 */
	public function get_all_manufacturers(): void {
		try {
			if (false === is_admin()) {
				throw new Exception('You are not allowed to access.', 403);
			}
			
			$manufacturers = null;
			$page = 1;
			$total_pages = 1;
			
			$response = $this->request( $this->apiBaseUrl . "/management/v2/manufacturers?page=$page" );
			
			if ( ! $response ) {
				throw new Exception( 'Manufacturers not found', 404 );
			}
			
			$total_pages = $response->meta->pagination->totalPages;
			$manufacturers = $response->items;
			
			if ( $total_pages > $page ) {
				for ( $page = 2; $page <= $total_pages; $page++ ) {
					$response = $this->request( $this->apiBaseUrl . "/management/v2/manufacturers?page=$page" );
					$manufacturers = array_merge( $manufacturers, $response->items );
				}
			}
			
			wp_send_json_success( [
				'manufacturers' => $manufacturers
			], 200 );
			
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
			
			wp_send_json_error( [
				'message' => $e->getMessage()
			], $e->getCode() );
		}
	}
}
