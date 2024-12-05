<?php


add_action( 'woocommerce_product_options_general_product_data', 'bundle_product_custom_fields' );
/**
* Add bundle product custom fields to WooCommerce product options
*
* @return void
*/
function bundle_product_custom_fields(): void {
global $post;
echo '<div class="options_group">';
	
	woocommerce_wp_text_input( [
	'id'          => '_bundled_products',
	'label'       => __( 'Bundled Product IDs', 'woocommerce' ),
	'placeholder' => __( 'Comma-separated product IDs', 'woocommerce' ),
	'desc_tip'    => true,
	'description' => __( 'Enter the product IDs of the simple products to include in this bundle.', 'woocommerce' ),
	] );
	
	echo '</div>';
}

add_action('woocommerce_process_product_meta', 'save_bundle_product_custom_fields');
/**
* Save bundle product custom fields to WooCommerce product
* @param $post_id
*
* @return void
*/
function save_bundle_product_custom_fields($post_id): void {
if (isset($_POST['_bundled_products'])) {
$bundled_products = sanitize_text_field($_POST['_bundled_products']);
update_post_meta($post_id, '_bundled_products', $bundled_products);
} else {
delete_post_meta($post_id, '_bundled_products'); // Remove meta if the field is empty
}
}

trait WC_Product_Bundled {
/**
* Get bundled product IDs.
*
* @return array Bundled product IDs.
*/
public function get_bundled_ids() {
$bundled_ids = $this->get_meta('_bundled_products'); // Retrieve meta data for bundled products
if (!empty($bundled_ids)) {
return array_map('trim', explode(',', $bundled_ids)); // Convert to array
}
return [];
}
}


add_filter('woocommerce_product_class', 'add_bundled_method_to_all_products', 10, 2);
/**
* Add bundled method to all products
* @param $class_name
* @param $product_type
*
* @return string
*/
function add_bundled_method_to_all_products($class_name, $product_type) {
// Define a new class name for the extended product class
$new_class_name = 'WC_Product_' . ucfirst($product_type) . '_With_Bundled';

// Check if the class already exists to avoid re-declaration
if (!class_exists($new_class_name)) {
eval("
class $new_class_name extends $class_name {
use WC_Product_Bundled;
}
");
}

return $new_class_name;
}