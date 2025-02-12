<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product_Variation' ) && function_exists( 'WC' ) ) {
	require_once WC_ABSPATH . 'includes/class-wc-product-variation.php';
}

class Woo_WMS_Product_Variation extends WC_Product_Variation {
	public function __construct( $product ) {
		parent::__construct( $product );
		$this->data['manufacturer'] = $this->get_meta( 'manufacturer', true ) ?: '';
		$this->data['wms_name'] = $this->get_meta( 'wms_name', true ) ?: '';
		$this->data['wms_id'] = $this->get_meta( 'wms_id', true ) ?: '';
	}
	
	public function get_manufacturer(): string {
		return $this->data['manufacturer'];
	}
	
	public function set_manufacturer( $value ): void {
		$this->data['manufacturer'] = $value;
		update_post_meta( $this->get_id(), 'manufacturer', $value );
	}
	
	public function get_wms_name(): string {
		return $this->data['wms_name'];
	}
	
	public function set_wms_name( $value ): void {
		$this->data['wms_name'] = $value;
		update_post_meta( $this->get_id(), 'wms_name', $value );
	}
	
	public function get_wms_id(): string {
		return $this->data['wms_id'];
	}
	
	public function set_wms_id( $value ): void {
		$this->data['wms_id'] = $value;
		update_post_meta( $this->get_id(), 'wms_id', $value );
	}
}
