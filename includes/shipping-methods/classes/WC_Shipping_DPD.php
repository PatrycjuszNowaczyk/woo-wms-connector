<?php


class WC_Shipping_DPD extends WC_Shipping_Flat_Rate {
	
	/**
	 * DHL shipping method type for instance and used by WMS API
	 *
	 * @var string
	 */
	public string $dpd_type = '';

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
		$this->method_title         = __( 'DPD', WOO_WMS_TEXT_DOMAIN );
		$this->method_description   = __( 'Choose the DPD shipping method.', WOO_WMS_TEXT_DOMAIN );
		$this->id                   = 'dpd';
		$this->title                = $this->get_option( 'title' );
		$this->dpd_type             = $this->get_option( 'dpd_type' );
		$this->tax_status           = $this->get_option( 'tax_status' );
		$this->cost                 = $this->get_option( 'cost' );
		$this->type                 = $this->get_option( 'type', 'class' );
		
	}
	
	/**
	 * Prepare instance form fields for the InPost shipping method with customized order
	 *
	 * @return array[]
	 */
	private function init_instance_form_fields(): array {
		$instance_form_fields = include WC()->plugin_path() . '/includes/shipping/flat-rate/includes/settings-flat-rate.php';
		
		$title_field = [
			'title'       => __( 'Name', WOO_WMS_TEXT_DOMAIN ),
			'type'        => 'text',
			'description' => __( 'Your customers will see the name of this shipping method during checkout.', WOO_WMS_TEXT_DOMAIN ),
			'default'     => __( 'DPD', WOO_WMS_TEXT_DOMAIN ),
			'placeholder' => __( 'e.g. DPD courier', WOO_WMS_TEXT_DOMAIN ),
			'desc_tip'    => true,
		];
		
		$inpost_type_field = [
			'title'       => __( 'DPD type', WOO_WMS_TEXT_DOMAIN ),
			'type'        => 'select',
			'description' => __( 'Select the DHL type for this shipping method.', WOO_WMS_TEXT_DOMAIN ),
			'default'     => 'dpd-courier',
			'options'     => [
				'dpd-courier' => __( 'DPD courier', WOO_WMS_TEXT_DOMAIN ),
				'none'        => __( 'None', WOO_WMS_TEXT_DOMAIN )
			],
			'desc_tip'    => true,
		];
		
		$instance_form_fields['title'] = $title_field;
		$title_field_index             = array_search( 'title', array_keys( $instance_form_fields ) );
		
		$instance_form_fields = array_slice( $instance_form_fields, 0, $title_field_index + 1, true ) +
		                        [ 'dpd_type' => $inpost_type_field ] +
		                        array_slice( $instance_form_fields, $title_field_index + 1, null, true );
		
		return $instance_form_fields;
	}
	
	/**
	 * Returns the shipping method instance slug
	 *
	 * @return string
	 */
	public function get_dpd_type(): string {
		return $this->dpd_type;
	}
}