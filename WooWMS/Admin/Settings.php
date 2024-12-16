<?php

namespace WooWMS\Admin;

class Settings {
	
	private static ?self $instance = null;
	
	private static array $settings = [
		'woo_wms_warehouse_id'         => null,
    'woo_wms_warehouse_code'       => null,
		'woo_wms_base_api_url'         => null,
		'woo_wms_store_api_token'      => null,
		'woo_wms_management_api_token' => null
	];
	
	private function __construct() {
		
		foreach ( self::$settings as $name => $value ) {
			self::$settings[$name] = esc_attr( get_option( $name ) );
		}
		
		$this->register_settings( self::$settings );
		$this->add_settings_page();
	}
	
	
	// Singleton pattern
	
	/**
   * This is a singleton design pattern.
   * Initialize class instance if not already initialized and return it.
	 * @return self
	 */
	public static function init(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	
	/**
	 * Register settings in wordPress database
	 * @param array $settings
	 * @return void
	 */
	private function register_settings( array $settings = [] ): void {
		foreach ( $settings as $name => $value ) {
			register_setting( 'woo_wms_connector_settings', $name );
		}
	}
	
	/**
   * Add settings page to menu in admin panel
	 * @return void
	 */
	private function add_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		add_options_page(
			'Woo WMS Connector Settings',
			'WMS Connector',
			'manage_options',
			'woo-wms-connector',
			[ __CLASS__, 'render_settings_page' ]
		);
	}
	
  // Getters START
	/*------------------------------------------------------------------------------------------------------------------*/
	/**
	 * Get settings array
   * @return array
	 */
  public function getSettings(): array {
    return self::$settings;
  }
  
  /**
   * Get base api url without trailing slash
	 * @return string|null
	 */
  public function getApiBaseUrl(): string|null {
		return self::$settings['woo_wms_base_api_url'];
	}
	
	/**
   * Get store token
	 * @return string|null
	 */
	public function getApiStoreToken(): string|null {
		return self::$settings['woo_wms_store_api_token'];
	}
	
	/**
   * Get management token
	 * @return string|null
	 */
	public function getApiManagementToken(): string|null {
		return self::$settings['woo_wms_management_api_token'];
	}
	
	/**
   * Get warehouse id
	 * @return int|null
	 */
	public function getWarehouseId(): int|null {
		return (int) self::$settings['woo_wms_warehouse_id'] ?? null;
	}
 
	/**
   * Get warehouse code
	 * @return string|null
	 */
	public function getWarehouseCode(): string|null {
		return self::$settings['woo_wms_warehouse_code'] ?? null;
	}
	/*------------------------------------------------------------------------------------------------------------------*/
	// Getters END
	
	/**
   * Render settings page in admin panel
	 * @return void
	 */
	public static function render_settings_page(): void {
		?>
    <div class="wrap">
      <h1>Woo WMS Connector Settings</h1>
      <form method="post" action="options.php">
				<?php
				settings_fields( 'woo_wms_connector_settings' );
				do_settings_sections( 'woo_wms_connector_settings' );
				?>
        <table class="form-table">
          <tr>
            <th scope="row">
              <label for="woo_wms_base_api_url">
                API BASE URL
              </label>
            </th>
            <td>
              <input
                id="woo_wms_base_api_url"
                type="text"
                name="woo_wms_base_api_url"
                value="<?php echo self::$settings['woo_wms_base_api_url']; ?>"
                class="large-text"
                required
              />
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="woo_wms_store_api_token">
                API STORE TOKEN
              </label>
            </th>
            <td>
              <input
                id="woo_wms_store_api_token"
                type="text"
                name="woo_wms_store_api_token"
                value="<?php echo self::$settings['woo_wms_store_api_token']; ?>"
                class="large-text"
                required
              />
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="woo_wms_management_api_token">
                API MANAGEMENT TOKEN
              </label>
            </th>
            <td>
              <input
                id="woo_wms_management_api_token"
                type="text"
                name="woo_wms_management_api_token"
                value="<?php echo self::$settings['woo_wms_management_api_token']; ?>"
                class="large-text"
                required
              />
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="woo_wms_warehouse_id">
                WAREHOUSE ID
              </label>
            </th>
            <td>
              <input
                id="woo_wms_warehouse_id"
                type="text"
                name="woo_wms_warehouse_id"
                value="<?php echo self::$settings['woo_wms_warehouse_id']; ?>"
                class=""
                required
              />
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="woo_wms_warehouse_code">
                WAREHOUSE CODE
              </label>
            </th>
            <td>
              <input
                id="woo_wms_warehouse_code"
                type="text"
                name="woo_wms_warehouse_code"
                value="<?php echo self::$settings['woo_wms_warehouse_code']; ?>"
                class=""
                required
              />
            </td>
          </tr>
        </table>
				<?php submit_button(); ?>
      </form>
    </div>
		<?php
	}
}
