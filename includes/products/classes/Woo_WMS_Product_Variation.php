<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product_Variation' ) && function_exists( 'WC' ) ) {
	require_once WC_ABSPATH . 'includes/class-wc-product-variation.php';
}

class Woo_WMS_Product_Variation extends WC_Product_Variation {
	
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
			if ( 'variation' !== $product->get_type() ) {
				return;
			}
			
			$parent         = wc_get_product( $product->get_parent_id() );
			$variations_ids = $parent->get_children();
			
			$variations_indexes = array_map( function ( $variation_id ) {
				return wc_get_product( $variation_id );
			}, $variations_ids );
			
			$index = array_search( $product->get_id(), array_column( $variations_indexes, 'id' ) );
			
			if ( false !== $index && isset( $_POST['variation_manufacturer'][ $index ] ) ) {
				$product->set_manufacturer( (int) sanitize_text_field( $_POST['variation_manufacturer'][ $index ] ) );
			}
			
			if ( false !== $index && isset( $_POST['variation_wms_name'][ $index ] ) ) {
				$product->set_wms_name( sanitize_text_field( $_POST['variation_wms_name'][ $index ] ) );
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
