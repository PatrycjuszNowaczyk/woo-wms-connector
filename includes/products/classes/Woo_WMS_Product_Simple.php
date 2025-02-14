<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product_Simple' ) && function_exists( 'WC' ) ) {
	require_once WC_ABSPATH . 'includes/class-wc-product-simple.php';
}

class Woo_WMS_Product_Simple extends WC_Product_Simple {
	
	public function __construct( $product = 0 ) {
		parent::__construct( $product );
		$this->data = array_merge( $this->data, [
			'manufacturer' => get_post_meta( $this->get_id(), 'manufacturer', true ),
			'wms_name'     => get_post_meta( $this->get_id(), 'wms_name', true ),
			'wms_id'       => get_post_meta( $this->get_id(), 'wms_id', true )
		] );
	}
	
	public function get_manufacturer( $context = 'view' ): string {
		return $this->get_prop( 'manufacturer', $context );
	}
	
	public function set_manufacturer( $value ): void {
		update_post_meta( $this->get_id(), 'manufacturer', $value );
		$this->set_prop( 'manufacturer', $value );
	}
	
	public function get_wms_name( $context = 'view' ): string {
		return $this->get_prop( 'wms_name', $context );
	}
	
	public function set_wms_name( $value ): void {
		update_post_meta( $this->get_id(), 'wms_name', $value );
		$this->set_prop( 'wms_name', $value );
	}
	
	public function get_wms_id( $context = 'view' ): string {
		return $this->get_prop( 'wms_id', $context );
	}
	
	public function set_wms_id( $value ): void {
		update_post_meta( $this->get_id(), 'wms_id', $value );
		$this->set_prop( 'wms_id', $value );
	}
	
	public function save() {
		/**
		 * Update product's data object and database.
		 * This hook is set to be executed as last one to allow to get data state before product object save.
		 */
		add_action( 'woocommerce_before_product_object_save', function ( $product ) {
			if ( 'simple' !== $product->get_type() ) {
				return;
			}
			
			if ( isset( $_POST['manufacturer'] ) && $product->get_manufacturer() !== sanitize_text_field( $_POST['manufacturer'] ) ) {
				$product->set_manufacturer( (int) sanitize_text_field( $_POST['manufacturer'] ) );
			}
			
			if ( isset( $_POST['wms_name'] ) && $product->get_wms_name() !== sanitize_text_field( $_POST['wms_name'] ) ) {
				$product->set_wms_name( sanitize_text_field( $_POST['wms_name'] ) );
			}
			
			if ( empty( $product->changes ) ) {
				return;
			}
			
			foreach ( $product->changes as $key => $value ) {
				$available_keys = [
					'manufacturer',
					'wms_name',
					'wms_id'
				];
				
				if ( false === in_array( $key, $available_keys ) ) {
					continue;
				}
				
				$this->data[ $key ] = $value;
			}
		}, 9999, 1 );
		
		return parent::save();
	}
}
