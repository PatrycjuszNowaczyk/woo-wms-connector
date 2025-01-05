<?php

add_action('woocommerce_admin_order_item_headers', 'display_custom_field_in_admin_order');
/**
 * Display WMS Order ID and create order button in the admin order
 *
 * @param WC_Order $order
 *
 * @return void
 */
function display_custom_field_in_admin_order(WC_Order $order): void {
	// Retrieve the custom field value
	$custom_field_value = $order->get_meta('wms_logicas_order_id');
	
	// Check if the custom field has a value
	if (empty($custom_field_value)) {
		$custom_field_value = 'N/A';
	}
	?>
	<p class="text-center"><strong><?= __('WMS Order ID:', WOO_WMS_TEXT_DOMAIN) ?></strong><code><?= esc_html($custom_field_value) ?></code></p>
	<?php
}

add_action('woocommerce_shipping_init', 'inpost_shipping_method_init');
/**
 * Require the shipping method class if not already loaded
 *
 * @return void
 */
function inpost_shipping_method_init(): void {
  $files = glob(__DIR__ . '/includes/shipping-methods/classes/*.php');
  
  $shipping_classes = array_map(function($file) {
    return basename($file, '.php');
  }, $files);
  
  foreach ($shipping_classes as $shipping_class) {
    if (class_exists($shipping_class)) {
      return;
    }
  }
  
  foreach ($files as $file) {
    require_once $file;
  }
}


add_filter('woocommerce_shipping_methods', 'add_shipping_methods' );
/**
 * Register new shipping methods in WooCommerce
 *
 * @param array $methods
 *
 * @return array
 */
function add_shipping_methods(array $methods): array {
	$files = glob(__DIR__ . '/includes/shipping-methods/classes/*.php');
  
  foreach ($files as $file) {
    $basename = basename($file, '.php');
    $exploded = explode('_', $basename);
	  $index = array_pop( $exploded );
    $methods[strtolower($index)] = $basename;
  }
  
	return $methods;
}

add_action('woocommerce_admin_order_data_after_shipping_address', 'add_parcel_machine_id_editable_field', 10, 1);
/**
 * Add editable field for parcel machine ID in the order edit screen
 *
 * @param WC_Order $order
 *
 * @return void
 */
function add_parcel_machine_id_editable_field( WC_Order $order ): void {
//	$parcel_machine_id = get_post_meta( $order->get_id(), 'parcel_machine_id', true );
	$parcel_machine_id = $order->get_meta('parcel_machine_id');
	?>
  <div class="clear"></div>
  <div class="address">
    <p>
      <strong><?php esc_html_e( 'Parcel machine ID', 'text-domain' ); ?>:</strong>
      <code><?= $parcel_machine_id ? esc_attr( $parcel_machine_id ) : 'N/A'; ?></code>
    </p>
  </div>
  <div class="edit_address">
    <p class="form-field">
      <label for="parcel_machine_id">
        <?php esc_html_e( 'Parcel machine ID', 'text-domain' ); ?>:
      </label>
      <input
        type="text"
        id="parcel_machine_id"
        class="short"
        name="parcel_machine_id"
        value="<?php echo esc_attr( $parcel_machine_id ); ?>"
      >
    </p>
  </div>
	<?php
}

add_action( 'woocommerce_checkout_update_order_meta', 'custom_checkout_update_order_meta', 10, 1 );
add_action( 'woocommerce_process_shop_order_meta', 'custom_checkout_update_order_meta', 10, 1 );
/**
 * Save VAT Number in the order meta
 *
 * @param int $order_id
 *
 * @return void
 */
function custom_checkout_update_order_meta( int $order_id ): void {
	$order       = wc_get_order( $order_id );
	$isAddedMeta = false;
	
	if ( ! empty( $_POST['vat_number'] ) ) {
		$order->update_meta_data( 'vat_number', sanitize_text_field( $_POST['vat_number'] ) );
		$isAddedMeta = true;
	}
	
	if ( ! empty( $_POST['parcel_machine_id'] ) ) {
		$order->update_meta_data( 'parcel_machine_id', sanitize_text_field( $_POST['parcel_machine_id'] ) );
		$isAddedMeta = true;
	}
	
	if ( $isAddedMeta ) {
		$order->save();
	}
}


add_action('wp_head', 'add_custom_styles_to_header' );
/**
 * Add geowidget styles to header
 *
 * @return void
 */
function add_custom_styles_to_header(): void {
	?>
  <style>
      #geowidget {
          height: 580px;
          border-radius: 1rem;
          overflow: hidden;
      }

      .geowidgetParcelInfo {
          border: 1px solid #888;
          border-radius: 10px;
      }
  </style>
	<?php
}

add_action('wp_head', 'add_inpost_geowidget_script_to_header' );
/**
 * Add Inpost GeoWidget script to header
 *
 * @return void
 */
function add_inpost_geowidget_script_to_header(): void {
	?>
  <link rel="stylesheet" href="https://geowidget.inpost.pl/inpost-geowidget.css"/>
  <script src='https://geowidget.inpost.pl/inpost-geowidget.js' defer></script>
	<?php
}

add_action('woo_wms_connector_shipping_options', 'add_inpost_geowidget_map', 1, 0);
/**
 * Add inpost geowidget map
 *
 * @return void
 */
function add_inpost_geowidget_map(): void {
  ?>
  <div id="inpost_geowidget_wrapper" style="display: none;">
    <input type="hidden" name="parcel_machine_id" id="parcel_machine_id" value="<?php echo esc_attr( WC()->checkout()->get_value( 'parcel_machine_id' ) ); ?>">
    <div
      id="inpost_geowidget_info"
      class="mt-4 mb-2 custom-checkout-text text-center"
    >
      <p>
				<?= __('Please select a parcel locker location from the map.', WOO_WMS_TEXT_DOMAIN) ?>
      </p>
    </div>
    <button type="button" id="inpost_geowidget_button">click me</button>
    <inpost-geowidget
      id="geowidget"
      class="d-block"
      onpoint="onpointselect"
      token="eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJzQlpXVzFNZzVlQnpDYU1XU3JvTlBjRWFveFpXcW9Ua2FuZVB3X291LWxvIn0.eyJleHAiOjIwNDkyOTQzNjcsImlhdCI6MTczMzkzNDM2NywianRpIjoiMzUwMTIyZTctNDU5Ni00NzRhLWJiYmItOGE3MDBkMzQyZDYwIiwiaXNzIjoiaHR0cHM6Ly9sb2dpbi5pbnBvc3QucGwvYXV0aC9yZWFsbXMvZXh0ZXJuYWwiLCJzdWIiOiJmOjEyNDc1MDUxLTFjMDMtNGU1OS1iYTBjLTJiNDU2OTVlZjUzNTpja0JueDYxcUJkdGhYZlE1X092aUNaU1lfRjh6VG5qcHJqZy0zQmRsVDVFIiwidHlwIjoiQmVhcmVyIiwiYXpwIjoic2hpcHgiLCJzZXNzaW9uX3N0YXRlIjoiYTkxNzBlZGMtN2NhZi00OWUzLTlhMGMtNGQxYjcyZDRlNDFlIiwic2NvcGUiOiJvcGVuaWQgYXBpOmFwaXBvaW50cyIsInNpZCI6ImE5MTcwZWRjLTdjYWYtNDllMy05YTBjLTRkMWI3MmQ0ZTQxZSIsImFsbG93ZWRfcmVmZXJyZXJzIjoiZHJodW50Zm9ybXVsYS5jb20iLCJ1dWlkIjoiZGRlYTRlN2QtZDI0Ni00NzU5LThkMDktZWQ3YTk4NWZlZTlmIn0.Kq7trUW0JSioyE8BUJga_VHvZkvhbkbnQSqBFK-9NaJ0JIPUb2vPqK6ZhfMt4FmUdXO6IUqZXyh7DkHXR6L8RAcEWK9Xei7eiSJD4wNQcyrnICrj1TB0LiQjp06aE3iM6t8vJB4y1zTWpPYboGAHDN3hUtLo7F9sQR7uw3vN7SVFuSeXrXDbgn5Pdd1oXNo2R6w7kJMcLzUTTecE__fwHuIsD5DnK1fbn2NcnEJf4O6U2i9d3jWPz8iLeLCcqBeW6SBP8YdRtoO0kN3c8J7xdTbIS7ME39VsyK1D3uB8Ko4LOnIIbKjwBeWSZ7cq8vsIHBicnVumLI_JzZq0OUybLQ"
      language="en"
      config="parcelCollect"
    ></inpost-geowidget>
  </div>
  <script defer>
      (function () {
          const geoWidgetWrapper = document.getElementById('inpost_geowidget_wrapper');
          const geoWidgetInfo = document.getElementById('inpost_geowidget_info');
          const geoWidgetInfoInitialText = geoWidgetInfo.innerHTML;
          const shippingButtons = document.querySelectorAll('input[name^="shipping_method"]');
          const inpostButton = shippingButtons[0]; // inpost button is the first shipping button
          const inpostParcelId = document.getElementById('parcel_machine_id');

          function setActiveInpostParcelId() {
              if (!inpostParcelId.required) {
                  inpostParcelId.required = true;
              }
          }

          function unsetActiveInpostParcelId() {
              if (inpostParcelId.required) {
                  inpostParcelId.removeAttribute('required');
                  inpostParcelId.value = '';
                  geoWidgetInfo.innerHTML = geoWidgetInfoInitialText;
              }
          }

          shippingButtons.forEach(function (button) {
              if (inpostButton.checked) {
                  geoWidgetWrapper.style.display = 'block';
                  setActiveInpostParcelId();
              }

              button.addEventListener('click', function (e) {
                  if (e.target === inpostButton) {
                      geoWidgetWrapper.style.display = 'block';
                      setActiveInpostParcelId();
                      return null;
                  }
                  if ('block' === geoWidgetWrapper.style.display) {
                      unsetActiveInpostParcelId();
                      geoWidgetWrapper.style.display = 'none';
                  }
              });
          })

          function handlePointSelection(event) {
              const point = event.detail;
              let selected_point_data = '';
              let parcelMachineId = '';
              let parcelMachineAddressDesc = '';
              let address_line1 = '';
              let address_line2 = '';


              if ( typeof point.name !== 'undefined' && point.name !== null ) {
                  parcelMachineId = point.name;
              }
              if ( typeof point.location_description != 'undefined' && point.location_description !== null ) {
                  parcelMachineAddressDesc = point.location_description;
              }
              if ( typeof point.address.line1 !== 'undefined' && point.address.line1 !== null ) {
                  address_line1 = point.address.line1;
              }
              if ( typeof point.address.line2 !== 'undefined' && point.address.line2 !== null ) {
                  address_line2 = point.address.line2;
              }

              selected_point_data =
                  '<div class="p-2 geowidgetParcelInfo">' +
                  (parcelMachineId ? '<p class="mb-0 text-center"><b>' + parcelMachineId + '</b></p>' : '') +
                  (address_line1 ? '<p class="mb-0 text-center">' + address_line1 + '</p>' : '') +
                  (address_line2 ? '<p class="mb-0 text-center">' + address_line2 + '</p>' : '') +
                  (parcelMachineAddressDesc ? '<p class="mb-0 text-center">' + parcelMachineAddressDesc + '</p>' : '') +
                  '</div>';

              geoWidgetInfo.innerHTML = selected_point_data;
              inpostParcelId.value = point.name;
          }


          const inpostGeoWidgetButton = document.getElementById('inpost_geowidget_button');

          inpostGeoWidgetButton.onclick = () => handlePointSelection(
              {
                  detail: {
                      location_description: '(behind the building)',
                      address: {
                          line1: 'Addr. line 1',
                          line2: 'Addr. line 2'
                      },
                      name: 'POZ123'
                  }
              }
          );

          document.addEventListener('onpointselect', handlePointSelection);
      })();
  </script>
<?php
}

add_action('woocommerce_checkout_process', 'check_is_parcel_machine_id_required', 1, 0);
/**
 * On pay button click check if the selected shipping method is "inpost" and if the "parcel_machine_id" field is empty
 *
 * @return void
 */
function check_is_parcel_machine_id_required(): void {
	$shipping_method_id   = WC()->session->get( 'chosen_shipping_methods' )[0];
	$shipping_instance_id = (int) explode( ':', $shipping_method_id )[1];
	$shipping_method      = null;
	
	if ( ! empty( $shipping_instance_id ) ) {
		$shipping_method = WC_Shipping_Zones::get_shipping_method( $shipping_instance_id );
	}
	
	if (
		$shipping_method
		&& 'inpost' === $shipping_method->id
		&& 'inpost-locker-247' === $shipping_method->get_inpost_type()
	) {
		if ( empty( $_POST['parcel_machine_id'] ) ) {
			wc_add_notice( __( 'To place an order, select the parcel locker location.', WOO_WMS_TEXT_DOMAIN ), 'error' );
		}
	}
};

add_filter('woocommerce_shipping_fields', 'add_shipping_fields', 10, 1);
/**
 * Allow to copy the billing phone to shipping
 *
 * @param array $fields
 *
 * @return array
 */
function add_shipping_fields(array $fields): array {
	$fields['shipping_phone'] = array(
		'type'        => 'tel',
		'label'       => __('Shipping Phone', WOO_WMS_TEXT_DOMAIN),
		'required'    => true,
		'class'       => array('form-row-wide'),
		'priority'    => 110,
	);

	return $fields;
}
