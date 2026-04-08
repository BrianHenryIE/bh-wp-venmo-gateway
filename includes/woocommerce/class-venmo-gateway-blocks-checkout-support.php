<?php
/**
 * Make the Venmo payment gateway available to the WooCommerce Blocks checkout.
 *
 * Registers the gateway script and exposes settings data to the frontend.
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

/**
 * Blocks checkout support for the Venmo payment gateway.
 */
class Venmo_Gateway_Blocks_Checkout_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var Venmo_Gateway
	 */
	protected $gateway;

	/**
	 * Payment method name. Must match WC_Payment_Gateway::$id.
	 *
	 * @var string
	 */
	protected $name = 'venmo';

	/**
	 * Constructor.
	 *
	 * @param Venmo_Gateway $gateway The Venmo gateway instance.
	 */
	public function __construct( Venmo_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Initializes the payment method type.
	 *
	 * @used-by IntegrationRegistry::initialize()
	 */
	public function initialize(): void {
		$this->settings = $this->gateway->settings;
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @used-by PaymentMethodRegistry::get_all_active_registered()
	 */
	public function is_active(): bool {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of script handles to be registered for this payment method.
	 *
	 * @used-by PaymentMethodRegistry::get_all_active_payment_method_script_dependencies()
	 *
	 * @return array<string>
	 */
	public function get_payment_method_script_handles(): array {

		$handle = 'bh-wc-venmo-gateway-blocks-checkout';

		$script_asset_path = WP_PLUGIN_DIR . '/bh-wc-venmo-gateway/build/checkout/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
				'version'      => '1.0.0',
			);

		$script_url = plugins_url( 'build/checkout/index.js', WP_PLUGIN_DIR . '/bh-wc-venmo-gateway/bh-wc-venmo-gateway.php' );

		wp_register_script(
			$handle,
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations( $handle, 'bh-wc-venmo-gateway' );

		return array( $handle );
	}

	/**
	 * Returns data made available to the payment methods script via `getSetting('venmo_data')`.
	 *
	 * @used-by PaymentMethodRegistry::get_all_registered_script_data()
	 *
	 * @return array{title:string, description:string, supports:array<string>, venmo_icon_url:string, saved_venmo_username:string}
	 */
	public function get_payment_method_data(): array {
		// Get saved Venmo username for auto-fill.
		$customer_id          = get_current_user_id();
		$saved_venmo_username = $this->gateway->get_saved_venmo_username( $customer_id );

		return array(
			'title'                => $this->get_setting( 'title' ),
			'description'          => $this->get_setting( 'description' ),
			'supports'             => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'venmo_icon_url'       => $this->gateway->icon,
			'saved_venmo_username' => $saved_venmo_username,
		);
	}
}
