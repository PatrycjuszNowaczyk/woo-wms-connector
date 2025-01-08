<?php


class WC_Shipping_InPost extends WC_Shipping_Flat_Rate {
	
	/**
	 * InPost shipping method type for instance and used by WMS API
	 *
	 * @var string
	 */
	public string $inpost_type = '';

//	public string $name = '';
	
	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
	}
	
	public function init(): void {
		$this->instance_form_fields = $this->init_instance_form_fields();
		$this->method_title       = __( 'InPost', 'woo_wms_connector' );
		$this->method_description = __( 'Choose the InPost shipping method.', 'woo_wms_connector' );
		$this->id                 = 'inpost';
		$this->title              = $this->get_option( 'title' );
		$this->inpost_type        = $this->get_option( 'inpost_type' );
		$this->tax_status         = $this->get_option( 'tax_status' );
		$this->cost               = $this->get_option( 'cost' );
		$this->type               = $this->get_option( 'type', 'class' );
		
	}
	
	/**
	 * Prepare instance form fields for the InPost shipping method with customized order
	 *
	 * @return array[]
	 */
	private function init_instance_form_fields(): array {
		$instance_form_fields = include WC()->plugin_path() . '/includes/shipping/flat-rate/includes/settings-flat-rate.php';
		
		$title_field = [
			'title'       => __( 'Name', 'woo_wms_connector' ),
			'type'        => 'text',
			'description' => __( 'Your customers will see the name of this shipping method during checkout.', 'woo_wms_connector' ),
			'default'     => __( 'InPost', 'woo_wms_connector' ),
			'placeholder' => __( 'e.g. Inpost locker 24/7', 'woo_wms_connector' ),
			'desc_tip'    => true,
		];
		
		$method_type_field = [
			'title'       => __( 'InPost type', 'woo_wms_connector' ),
			'type'        => 'select',
			'description' => __( 'Select the InPost type for this shipping method.', 'woo_wms_connector' ),
			'default'     => 'inpost-locker-247',
			'options'     => [
				'inpost-locker-247' => __( 'Parcel locker 24/7', 'woo_wms_connector' ),
				'none'              => __( 'None', 'woo_wms_connector' )
			],
			'desc_tip'    => true,
		];
		
		$instance_form_fields['title'] = $title_field;
		$title_field_index             = array_search( 'title', array_keys( $instance_form_fields ) );
		
		$instance_form_fields = array_slice( $instance_form_fields, 0, $title_field_index + 1, true ) +
		                        [ 'inpost_type' => $method_type_field ] +
		                        array_slice( $instance_form_fields, $title_field_index + 1, null, true );
		
		return $instance_form_fields;
	}
	
	/**
	 * Returns the shipping method instance slug
	 *
	 * @return string
	 */
	public function get_inpost_type(): string {
		return $this->inpost_type;
	}
}
