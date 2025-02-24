<?php

use WooWMS\Services\Logicas;

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
	$wms_order_id = $order->get_meta( Logicas::$META_WMS_LOGICAS_ORDER_ID );
	$is_wms_order_cancelled = ! empty( $order->get_meta( Logicas::$META_WMS_LOGICAS_IS_ORDER_CANCELLED ) );
  $order_status = $order->get_status();
  
	// Check if the custom field has a value
	if (empty($wms_order_id)) {
		$wms_order_id = 'N/A';
	}
 
	?>
  <div
    style="
      border-bottom: 1px solid #dfdfdf;
      background: #f8f8f8;
      padding: 1.5em 1em 1.5em 2em;
    "
  >
  <div
    style="
      display: flex;
      justify-content: space-between;
      line-height: 2em;
    "
  >
    <div>
      <div>
        <strong>
          <?= __('WMS Order ID:', 'woo_wms_connector') ?>
        </strong>
        <code>
          <?= esc_html($wms_order_id) ?>
        </code>
      </div>
    </div>
	  <?php if ( 'N/A' === $wms_order_id ): ?>
      <?php
        $create_order_tip = __('To create an order in the WMS system, the current order status must be set to completed.', 'woo_wms_connector');
      ?>
      <div>
        <button
          type="button"
          id="woo_wms_create_order"
          class="button button-primary"
          <?= (
          ( ! empty( $_GET['action'] ) && 'new' === $_GET['action'] )
          || 'completed' !== $order_status
            ? 'disabled="true"' : null
          ) ?>
        >
          <?= __('Create Order', 'woo_wms_connector') ?>
        </button>
        <span
          class="woocommerce-help-tip"
          data-tip="<?= __($create_order_tip, 'woo_wms_connector') ?>"
          aria-label="<?= __($create_order_tip, 'woo_wms_connector') ?>"
        ></span>
      </div>
    <?php endif; ?>
  </div>
	<?php if ( true === $is_wms_order_cancelled ) : ?>
    <div>
			<?= __('This order is cancelled in WMS.') ?>
    </div>
	<?php endif ?>
  </div>
	<?php if ( 'N/A' === $wms_order_id ): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('woo_wms_create_order').addEventListener('click', function() {
        const orderId = <?= $order->get_id() ?>;
        const url = 'admin-ajax.php?action=woo_wms_create_order&order_id=' + orderId;
        const originalText = this.textContent;
        
        this.disabled = true;
        this.textContent = 'Creating...';
        
        fetch(url)
          .then(response => response.json())
          .then(res => {
            if (res.success) {
              alert('<?= __( 'Order created successfully!', 'woo_wms_connector' ) ?>');
              window.location.reload();
            } else {
              alert('<?= __( 'Error creating order.', 'woo_wms_connector' ) ?>:\n\n' + res.data.message);
              this.disabled = false;
              this.textContent = originalText;
            }
          });
      })
    })
  </script>
	<?php endif; ?>
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
      <strong><?php esc_html_e( 'Parcel machine ID', 'woo_wms_connector' ); ?>:</strong>
      <code><?= $parcel_machine_id ? esc_attr( $parcel_machine_id ) : 'N/A'; ?></code>
    </p>
  </div>
  <div class="edit_address">
    <p class="form-field">
      <label for="parcel_machine_id">
        <?php esc_html_e( 'Parcel machine ID', 'woo_wms_connector' ); ?>:
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
				<?= __('Please select a parcel locker location from the map.', 'woo_wms_connector') ?>
      </p>
    </div>
	  
	  <?php if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'development' === WP_ENVIRONMENT_TYPE ) : ?>
      <button type="button" id="inpost_geowidget_button">click me</button>
	  <?php endif; ?>
    
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
	      
	      <?php if (defined( 'WP_ENVIRONMENT_TYPE' ) && 'development' === WP_ENVIRONMENT_TYPE) : ?>
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
	      <?php endif; ?>

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
			wc_add_notice( __( 'To place an order, select the parcel locker location.', 'woo_wms_connector' ), 'error' );
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
		'label'       => __('Shipping Phone', 'woo_wms_connector'),
		'required'    => true,
		'class'       => array('form-row-wide'),
		'priority'    => 110,
	);

	return $fields;
}


add_action( 'admin_notices', 'update_shop_stocks_button', 20 );
/**
 * Add a button to update all stocks in the shop all products page
 *
 * @return void
 */
function update_shop_stocks_button() {
	// Get the current screen
	$screen = get_current_screen();
	
	// Check if we're on the WooCommerce products page
	if ( $screen && $screen->id === 'edit-product' ) {
		?>
    <div class="notice notice-info">
      <p
        style="
          display: flex;
          justify-content: space-between;
          align-items: center;
        "
      >
        <span>
          <?= __( 'To update all stocks in a shop click the button.', 'woo_wms_connector' ); ?>
        </span>
        <button type="button" id="woo_wms_update_shop_stocks" class="button button-primary">
					<?= __( 'Update all stocks', 'woo_wms_connector' ); ?>
        </button>
      </p>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('woo_wms_update_shop_stocks').addEventListener('click', function () {
                const isUpdating = confirm('<?= __( 'Are you sure you want to update all stocks? This may take a while.', 'woo_wms_connector' ); ?>');
                if (!isUpdating) {
                    return;
                }

                const url = 'admin-ajax.php?action=woo_wms_update_shop_stocks';
                const originalText = this.textContent;

                this.disabled = true;
                this.textContent = '<?= __( 'Updating...', 'woo_wms_connector' ); ?>';

                fetch(url)
                    .then(response => response.json())
                    .then(res => {
                        if (res.success) {
                            alert(res.data.message);
                            window.location.reload();
                        } else {
                            alert('<?= __( 'Error updating stocks.', 'woo_wms_connector' ); ?>:\n\n' + res.data.message);
                            this.disabled = false;
                            this.textContent = originalText;
                        }
                    });
            })
        })
    </script>
		<?php
	}
}

add_action('woocommerce_checkout_create_order', 'save_custom_order_number', 10, 1);
add_action('woocommerce_new_order', function($id) {
  $order = wc_get_order($id);
  save_custom_order_number($order);
}, 10, 1);
/**
 *
 * On checkout create a custom order number and save it as meta data in the order.
 *
 * @param $order
 *
 * @return void
 */
function save_custom_order_number( $order ): void {
	// Get the current time
	$current_time = current_time('timestamp');
	$month = date('m', $current_time); // Current month
	$year = date('y', $current_time); // Current year in two digits
	
	// Get all orders for the current month
	$args = [
		'date_created' => '>' . date('Y-m-01 00:00:00', $current_time),
		'return' => 'ids',
    'limit' => -1
	];
	
	$orders_this_month = wc_get_orders($args);
	
	// Count orders for this month, starting from 10
	$nn = count($orders_this_month) + 10;
	
	// Create the custom order number
	$custom_order_number = sprintf('%02d%02d%02d', $nn, $month, $year);
	
	// Save the custom order number as meta data
	$order->update_meta_data( Logicas::$META_WMS_SHOP_CUSTOM_ORDER_ID, $custom_order_number );
  $order->save();
}

add_filter('woocommerce_order_number', 'use_custom_order_number_on_frontend', 10, 2);
/**
 *
 * On frontend use the custom order number if it exists in the order meta,
 * otherwise use the default order number (ID) which is the order ID in WooCommerce by default.
 *
 * @param $order_id
 * @param $order
 *
 * @return int|string
 */
function use_custom_order_number_on_frontend($order_id, $order): int|string {
  
  if ( is_admin()
       && isset($_GET['action'])
       && 'new' === $_GET['action']
       && isset($_GET['page'])
       && 'wc-orders' === $_GET['page']
  ) {
    return __('NEW', 'woo_wms_connector');
  }
  
	$custom_order_number = $order->get_meta( Logicas::$META_WMS_SHOP_CUSTOM_ORDER_ID );
	return ! empty($custom_order_number) ? $custom_order_number : $order_id;
}

add_filter( 'woocommerce_shop_order_search_fields', 'add_custom_meta_fields_to_search_fields' );
add_filter( 'woocommerce_order_table_search_query_meta_keys', 'add_custom_meta_fields_to_search_fields' );
/**
 *
 * Add custom meta fields to the search fields in the WooCommerce orders table search form.
 * This allows to search orders by the custom meta fields.
 *
 * @param array $search_fields
 *
 * @return array
 */
function add_custom_meta_fields_to_search_fields( array $search_fields ): array {
	// Add the custom meta key to the search fields
	$search_fields[] = Logicas::$META_WMS_SHOP_CUSTOM_ORDER_ID;
	
	return $search_fields;
}

add_filter( 'manage_edit-shop_order_columns', 'add_wms_order_status_column', 10, 1 );
add_filter( 'woocommerce_shop_order_list_table_columns', 'add_wms_order_status_column', 10, 1 );
/**
 *
 * Add wms order status column to the orders table
 *
 * @param $columns
 *
 * @return array
 */
function add_wms_order_status_column( $columns ): array {
	$new_columns = array();
	foreach ( $columns as $key => $column ) {
		$new_columns[ $key ] = $column;
		if ( 'order_status' === $key ) {
			$new_columns['order_status']     = __( 'Payment status', 'woo_wms_connector' );
			$new_columns['wms_order_status'] = __( 'WMS status', 'woo_wms_connector' );
		}
	}
	
	return $new_columns;
}

add_action( 'manage_shop_order_posts_custom_column', 'populate_wms_order_status_column_with_initial_value', 10, 1 );
add_action( 'woocommerce_shop_order_list_table_custom_column', 'populate_wms_order_status_column_with_initial_value', 10, 1 );
/**
 *
 * Populate wms order status column
 *
 * @param string $column
 *
 * @return void
 */
function populate_wms_order_status_column_with_initial_value( string $column ): void {
	if ( 'wms_order_status' === $column ) {
		echo '<img src="' . esc_url( admin_url( 'images/loading.gif' ) ) . '" alt="loading">';
	}
}

add_action( 'admin_footer', 'update_wms_order_statuses' );
/**
 *
 * Add script to the footer to handle fetch request for getting order statuses and update each wms order status in the orders table
 *
 * @return void
 */
function update_wms_order_statuses(): void {
	if (
    false === is_admin()
    || ! isset( $_GET['page'] )
    && 'wc-orders' !== $_GET['page']
    || true === isset( $_GET['action'] )
  ) {
		return;
	}
	
	?>
  <script>
    document.addEventListener( 'DOMContentLoaded', function () {
      const orderStatuses = Array.from( document.querySelectorAll( '.column-wms_order_status' ) ).slice( 1, -1 );
      const orderIds = Array.from( orderStatuses ).map( function ( orderStatus ) {
        return orderStatus.closest( 'tr' ).id.replace( 'order-', '' );
      } );

      const url = 'admin-ajax.php?action=woo_wms_get_orders_statuses&orders_ids=' + orderIds.join( ',' );

      const controller = new AbortController();
      const signal = controller.signal;

      document.querySelectorAll( 'a' ).forEach( link => {
        link.addEventListener( 'click', () => controller.abort() );
      } );

      fetch( url, { signal } )
      .then( response => response.json() )
      .then( res => {
        if ( false === res.success ) {
          throw new Error( res.data.message );
        }

        const ordersStatuses = res.data.orders_statuses;

        Object.entries( ordersStatuses ).forEach( function ( [ orderId, status ] ) {
          const orderStatusElement = document
          .querySelector( '#order-' + orderId )
          .querySelector( '.column-wms_order_status' );
          orderStatusElement.innerHTML = status;
        } );
      } )
      .catch( function ( error ) {

        if (
          !( error instanceof SyntaxError )
          && !( error instanceof TypeError )
          && error.name !== 'AbortError'
        ) {
          let message = '<?= __( 'There was an error updating order statuses.', 'woo_wms_connector' ); ?>' + '\n\n' + error.message;
          alert( message );
        }
      } );
    } );
  </script>
	<?php
}

add_filter( 'woocommerce_product_class', 'override_default_product_classes', 10, 3 );
/**
 *
 * Override the default WooCommerce product class with custom classes
 *
 * @param string $classname
 * @param string $product_type
 * @param string $product_id
 *
 * @return string
 */
function override_default_product_classes( string $classname, string $product_type, string $product_id ): string {
	require_once __DIR__ . '/includes/products/classes/Woo_WMS_Product_Variation.php';
	require_once __DIR__ . '/includes/products/classes/Woo_WMS_Product_Simple.php';
	require_once __DIR__ . '/includes/products/classes/Woo_WMS_Product_Variable.php';
	
	if ( 'variation' === $product_type ) {
		return 'Woo_WMS_Product_Variation';
	}
	
	if ( 'simple' === $product_type ) {
		return 'Woo_WMS_Product_Simple';
	}
	
	if ( 'variable' === $product_type ) {
		return 'Woo_WMS_Product_Variable';
	}
	
	return $classname;
}

add_action('woo_wms_connector_render_manufacturer_field', 'render_manufacturer_field', 10, 2);
/**
 *
 * Render the manufacturer field in the product edit screen for simple product, variable product and it's variations
 *
 * @param int $loop If the loop is greater than -1, it is a variation product
 * @param int $product_id
 *
 * @return void
 * @throws DOMException
 */
function render_manufacturer_field( $loop, $product_id ): void {
  $product = wc_get_product( $product_id );
	$product_type = $product->get_type();
	$available_types = [ 'simple', 'variable', 'variation' ];
	$is_loop = -1 < $loop;
	
	if( false === in_array( $product_type, $available_types ) ) {
		return;
	}
  // declare variables used in render_manufacturer_field function
  $product_wms_id = $product->get_wms_id();
  
  ob_start();
	woocommerce_wp_text_input( [
		'id'            => $is_loop ? "variation_wms_id[$loop]" : 'wms_id',
		'name'          => $is_loop ? "variation_wms_id[$loop]" : 'wms_id',
		'label'         => __( 'WMS ID', 'woo_wms_connector' ),
		'wrapper_class' => $is_loop ? 'form-row' : '',
		'description'   => __( 'This is an id of a product stored in WMS API.', 'woo_wms_connector' ),
		'desc_tip'      => true,
		'placeholder'   => __( 'Product is not created in WMS yet.', 'woo_wms_connector' ),
		'value'         => $product->get_wms_id(),
    'custom_attributes' => [
	    'disabled' => 'disabled',
	    'readonly' => 'readonly'
    ]
	] );
	$html = ob_get_clean();
	
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	
	$wms_id_input       = $dom->getElementById( ( $is_loop ? "variation_wms_id[$loop]" : 'wms_id' ) );
	$wms_id_input_clone = $wms_id_input->cloneNode( true );
	
  $wms_id_input_wrapper_class = ( $is_loop ? "variation_wms_id_wrapper[$loop]" : 'wms_id__wrapper' );
	$wms_id_input_wrapper_style = <<<EOF
    position:relative;
    display: block;
  EOF;
	$wms_id_input_wrapper_style .= ( $is_loop ? '' : 'float: left;' ) . "\n";
  
  $wms_id_input_wrapper = $dom->createElement( 'span' );
	$wms_id_input_wrapper->setAttribute( 'class', $wms_id_input_wrapper_class );
  $wms_id_input_wrapper->setAttribute( 'style', $wms_id_input_wrapper_style );
	$wms_id_input_wrapper->appendChild( $wms_id_input_clone );

  if ( ! empty( $product->get_wms_id()) ) {
    $wms_id_delete_button_style = '
      position: absolute;
      top: 50%;
      right: 0.5em;
      translate: 0 -50%;
      padding: 0.25rem;
      min-height: unset;
      line-height: 1em;
    ';
    
    $wms_id_delete_button_id = ( $is_loop ? "variation_wms_delete_button[$loop]" : 'wms_delete_button' );
    $wms_id_delete_button = $dom->createElement( 'button' );
    $wms_id_delete_button->setAttribute( 'id', $wms_id_delete_button_id );
    $wms_id_delete_button->setAttribute( 'class', 'button button-link-delete' );
    $wms_id_delete_button->setAttribute( 'style', $wms_id_delete_button_style );
    $wms_id_delete_button->setAttribute( 'type', 'button' );
    $wms_id_delete_button->setAttribute( 'rel', $product->get_wms_id() );
    $wms_id_delete_button->nodeValue = __( 'delete', 'woo_wms_connector' );
    
    $wms_id_input_wrapper->appendChild( $wms_id_delete_button );
  }
  
	$parent = $wms_id_input->parentNode;
	$parent->appendChild( $wms_id_input_wrapper );
	$parent->removeChild( $wms_id_input );
	
	echo $dom->saveHTML();
  
  /**
   * This "if" block is for adding a delete button to allow user delete a product from WMS
   */
	if ( !empty( $product_wms_id ) ) : ?>
		<?php
		$has_child = $product->has_child() ? 'true' : 'false';
		?>
    <script>
      ( function () {
        const deleteButton = document.getElementById( '<?= $wms_id_delete_button_id ?>' );

        deleteButton.addEventListener( 'click', function () {
          const hasChild = <?= $has_child ?>;
          const info_1 = '<?= __( "This product has variations.\\nAre you sure you want to delete this product from WMS?", 'woo_wms_connector' ) ?>';
          const info_2 = '<?= __( 'Are you sure you want to delete this product from WMS?', 'woo_wms_connector' ) ?>';

          if ( confirm( hasChild ? info_1 : info_2 ) ) {
            const url = 'admin-ajax.php?action=woo_wms_delete_product&productId=<?= $product_id ?>&productWmsId=<?= $product_wms_id ?>';
            fetch( url )
            .then( res => res.json() )
            .then( response => {
              if ( true !== response.success ) {
                throw new Error( response.data )
              }

              alert( response.data );
              window.location.reload( true );
            } )
            .catch( e => {
              const message = "<?= __( "There was an error:\\n\\n", 'woo_wms_connector' ) ?>";
              alert( message + e.message );
            } )
          }
        } )
      } )()
    </script>
	<?php endif; ?>
  <style>
    .<?= $wms_id_input_wrapper_class ?> input {
      width: 100%;
    }

    .<?= $wms_id_input_wrapper_class ?> .woo-wms__delete-product-button {
      position: absolute;
      top: 50%;
      right: 0.5em;
      translate: 0 -50%;
      padding: 0.25rem;
      min-height: unset;
      line-height: 1em;
    }

    .<?= $wms_id_input_wrapper_class ?> {
      position: relative;
      display: block;
      width: 80%;
      <?= ( $is_loop ? '' : 'float: left;' ) ?>
    }

    .<?= $wms_id_input_wrapper_class ?> > #<?= ( $is_loop ? "variation_wms_id[$loop]" : 'wms_id' ) ?> {
      width: 100% !important;
    }

    @media (min-width: 1281px) {
      .<?= $wms_id_input_wrapper_class ?> {
        width: 50%;
      }
    }
  </style>
 <?php
	woocommerce_wp_text_input( [
		'id'            => $is_loop ? "variation_wms_name[$loop]" : 'wms_name',
		'name'          => $is_loop ? "variation_wms_name[$loop]" : 'wms_name',
		'label'         => __( 'WMS name', 'woo_wms_connector' ),
		'wrapper_class' => $is_loop ? 'form-row' : '',
		'description'   => __( 'Input product name which be stored in WMS. It\'s required during WMS product creation.', 'woo_wms_connector' ),
		'desc_tip'      => true,
		'placeholder'   => '',
		'value'         => $product->get_wms_name()
	] );
	
	woocommerce_wp_select( [
		'id'            => $is_loop ? "variation_manufacturer[$loop]" : 'manufacturer',
		'name'          => $is_loop ? "variation_manufacturer[$loop]" : 'manufacturer',
		'label'         => __( 'WMS manufacturer', 'woo_wms_connector' ),
		'wrapper_class' => $is_loop ? 'form-row' : '',
		'description'   => __( 'Select the manufacturer of this variation. It\'s required during WMS product creation.', 'woo_wms_connector' ),
		'desc_tip'      => true,
		'options'       => [
			'' => __( "Select product's manufacturer", 'woo_wms_connector' )
		], // Options will be populated by JavaScript
	] );
	?>
  <script>
    <?php
    /**
     *  Select2.js for manufacturer search input.
     */
    ?>
    ( function ( $ ) {
      const url = 'admin-ajax.php?action=woo_wms_get_all_manufacturers';

      const manufacturerSelect = document.getElementById( '<?= ( $is_loop ? "variation_manufacturer[$loop]" : 'manufacturer' ) ?>' );
      const selectedOption = '<?= $product->get_manufacturer() ?>';
      let allManufacturersData = JSON.parse( sessionStorage.getItem( 'woo_wms_all_manufacturers' ) );

      function addManufacturersToSelect( manufacturers ) {
        manufacturers.forEach( manufacturer => {
          const option = document.createElement( 'option' );
          option.value = manufacturer.id;
          option.text = manufacturer.name + ' ( ID: ' + manufacturer.id + ' )';

          if ( selectedOption.toString().toLowerCase() === manufacturer.id.toString().toLowerCase() ) {
            option.selected = true;
          }

          manufacturerSelect.appendChild( option );
        } );

        $( manufacturerSelect ).select2( {
          placeholder: "<?= __( "Select product's manufacturer", 'woo_wms_connector') ?>",
          allowClear: true
        } );
        $( manufacturerSelect ).prop( 'disabled', <?= ( ! empty($product->get_wms_id() ) ) ?> );
        // $( manufacturerSelect ).prop( 'disabled', false );
      }

      if ( allManufacturersData ) {
        addManufacturersToSelect( allManufacturersData );
        return;
      }

      fetch( url )
      .then( response => response.json() )
      .then( res => {
        if ( false === res.success ) {
          throw new Error( res.data.message );
        }
        allManufacturersData = res.data.manufacturers;
        sessionStorage.setItem( 'woo_wms_all_manufacturers', JSON.stringify( allManufacturersData ) );
        addManufacturersToSelect( allManufacturersData );
      } )
      .catch( error => {
        alert( '<?= __( 'Error fetching manufacturers list from WMS API', 'woo_wms_connector' ) ?>:\n\n' + error.message );
        console.error( error );
      } );
    } )( jQuery );
  </script>
  <script>
    <?php
    /**
     * Set all critical inputs for WMS to readonly to prevent overwrite data by user by accident
     */
    ?>
    ( function () {
      const product_data_wrapper = document.querySelector( '#woocommerce-product-data' );
      const product_wms_id_input = product_data_wrapper.querySelector( '[id^="<?= ('variation' === $product_type ? 'variation_wms_id[' . $loop . ']' : 'wms_id' ) ?>"]' );

      if ( product_wms_id_input.value ) {
        const product_sku_input = product_data_wrapper.querySelector( '[id^="<?= ('variation' === $product_type ? 'variable_sku' . $loop : '_sku' ) ?>"]' );
        const product_gtin_input = product_data_wrapper.querySelector( '[id^="<?= ('variation' === $product_type ? 'variable_global_unique_id' . $loop : '_global_unique_id' ) ?>"]' );
        product_sku_input.readOnly = 'readonly';
        product_gtin_input.readOnly = 'readonly';
      }
    } )()
  </script>
  <style>
    <?php
    /**
    * Styles for above actions
    */
     ?>
      .select2-selection__clear {
          font-size: 2em;
          margin-right: 0.5rem;
      }

      .manufacturer_field .select2 {
          min-width: 80%;
      }

      [class*="variation_manufacturer"] .select2 {
          min-width: 100%;
      }

      @media (min-width: 1281px) {
          .manufacturer_field .select2 {
              min-width: 50%;
          }
      }
      input[readonly] {
          background-color: #eee;
      }
  </style>
	<?php
}

/**
 * Add custom manufacturer field to the product edit screen (simple product, variable product)
 */
add_action('woocommerce_product_options_global_unique_id', function() {
  do_action('woo_wms_connector_render_manufacturer_field', null, get_the_ID());
});

/**
 * Add custom manufacturer field to the product variations edit screen
 */
add_action( 'woocommerce_variation_options',function ( $loop, $variation_data, $variation ) {
  do_action( 'woo_wms_connector_render_manufacturer_field', $loop, $variation->ID );
}, 10, 3 );
