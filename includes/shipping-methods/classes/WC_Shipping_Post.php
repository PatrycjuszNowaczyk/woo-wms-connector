<?php


class WC_Shipping_Post extends WC_Shipping_Flat_Rate {
	
	/**
	 * Poland national post shipping method type for instance and used by WMS API
	 *
	 * @var string
	 */
	public string $post_type = '';

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
		$this->method_title         = __( 'National Poland Post', 'woo_wms_connector' );
		$this->method_description   = __( 'Choose the National Poland Post shipping method.', 'woo_wms_connector' );
		$this->id                   = 'post';
		$this->title                = $this->get_option( 'title' );
		$this->post_type            = $this->get_option( 'post_type' );
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
			'title'       => __( 'Name', 'woo_wms_connector' ),
			'type'        => 'text',
			'description' => __( 'Your customers will see the name of this shipping method during checkout.', 'woo_wms_connector' ),
			'default'     => __( 'Post', 'woo_wms_connector' ),
			'placeholder' => __( 'e.g. Post postman', 'woo_wms_connector' ),
			'desc_tip'    => true,
		];
		
		$method_type_field = [
			'title'       => __( 'Post type', 'woo_wms_connector' ),
			'type'        => 'select',
			'description' => __( 'Select the Post type for this shipping method.', 'woo_wms_connector' ),
			'default'     => 'post-postman',
			'options'     => [
				'post-postman' => __( 'Postman', 'woo_wms_connector' ),
				'none'         => __( 'None', 'woo_wms_connector' )
			],
			'desc_tip'    => true,
		];
		
		$instance_form_fields['title'] = $title_field;
		$title_field_index             = array_search( 'title', array_keys( $instance_form_fields ) );
		
		$instance_form_fields = array_slice( $instance_form_fields, 0, $title_field_index + 1, true ) +
		                        [ 'post_type' => $method_type_field ] +
		                        array_slice( $instance_form_fields, $title_field_index + 1, null, true );
		
		return $instance_form_fields;
	}
	
	/**
	 * Returns the shipping method instance slug
	 *
	 * @return string
	 */
	public function get_post_type(): string {
		return $this->post_type;
	}
}
