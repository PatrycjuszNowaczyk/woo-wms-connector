<?php

namespace WooWMS\Utils;

use WooWMS\Enums\AdminNoticeType;
use WC_Product;

class Utils {
	/**
	 * Generate an array of product data to compare
	 *
	 * @param WC_Product $product
	 * @param string $state
	 *
	 * @return array
	 */
	public static function generate_required_compare_array( WC_Product $product, string $state = 'after' ): array {
		$data = $product->get_data();
		$changes = $product->get_changes();
		
		return [
			'id'           => 'before' !== $state && isset( $changes['id'] ) ? (int) $changes['id'] : (int) $data['id'],
			'wms_id'       => 'before' !== $state && isset( $changes['wms_id'] ) ? (int) $changes['wms_id'] : (int) $data['wms_id'],
			'manufacturer' => 'before' !== $state && isset( $changes['manufacturer'] ) ? (int) $changes['manufacturer'] : (int) $data['manufacturer'],
			'sku'          => 'before' !== $state && isset( $changes['sku'] ) ? $changes['sku'] : $data['sku'],
			'ean'          => 'before' !== $state && isset( $changes['global_unique_id'] ) ? $changes['global_unique_id'] : $data['global_unique_id'],
			'name'         => 'before' !== $state && isset( $changes['wms_name'] ) ? $changes['wms_name'] : $data['wms_name'],
			'weight'       => 'before' !== $state && isset( $changes['weight'] ) ? (int) ( floatval( $changes['weight'] ) * 1000 ) : (int) ( floatval( $data['weight'] ) * 1000 ),
			// TODO setting image to upload to WMS
//			'image'        => false === empty( $product->get_image_id() ) ? [
//				'format'  => explode( '/', get_post_mime_type( $product->get_image_id() ) )[1],
//				'md5'     => md5_file( get_attached_file( $product->get_image_id() ) ),
//				'content' => base64_encode( file_get_contents( wp_get_attachment_url( $product->get_image_id() ) ) )
//			] : null
		];
	}


	/**
	 * Set admin notices with WordPress transient functionality
	 *
	 * Default is info type notice
	 *
	 * (available types: ERROR, WARNING, INFO, SUCCESS)
	 *
	 * @param string $message
	 * @param AdminNoticeType $type = AdminNoticeType::INFO
	 *
	 *
	 * @return void
	 */
	public static function set_admin_notice( string $message, AdminNoticeType $type = AdminNoticeType::INFO ): void {
		$notices = get_transient( 'woo_wms_admin_notices' ) ?: [];
		$notices[] = [
			'message' => $message,
			'type'    => "notice-$type->value"
		];
		set_transient( 'woo_wms_admin_notices', $notices , 300 );
	}
	
	/**
	 * Echo the admin notices with WordPress transient functionality
	 *
	 * @return void
	 */
	public static function show_admin_notices(): void {
		$notices = get_transient( 'woo_wms_admin_notices' ) ?: [];
		delete_transient( 'woo_wms_admin_notices' );
		
		if ( empty($notices) ) {
			return;
		}
		
		foreach ( $notices as $notice ) {
			echo ("
				<div class=\"notice $notice[type] is-dismissible\">
	        <p><b>woo wms connector:</b> $notice[message]</p>
	      </div>
	      ");
		}
	}
}