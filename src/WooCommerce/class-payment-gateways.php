<?php
/**
 * Add the payment gateway to WooCommerce's list of gateways.
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use WC_Order;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * Add the payment gateway's class name to WooCommerce's list of gateways it will
 * later instantiate.
 */
class Payment_Gateways {

	/**
	 * Add the Gateway to WooCommerce.
	 *
	 * @hooked woocommerce_payment_gateways
	 * @see WC_Payment_Gateways::init()
	 *
	 * @param string[] $gateways The payment gateways registered with WooCommerce.
	 *
	 * @return string[]
	 **/
	public function add_to_woocommerce( array $gateways ): array {

		$gateways[] = Venmo_Gateway::class;

		return $gateways;
	}

	/**
	 * Add the destination venmo username to the admin ui gateway title, for the case of multiple instances.
	 *
	 * TODO: The <i> are showing on the individual gateway settings page: /wp-admin/admin.php?page=wc-settings&tab=checkout&section=venmo
	 *
	 * @hooked woocommerce_gateway_method_title
	 *
	 * @param string             $method_title
	 * @param WC_Payment_Gateway $payment_gateway
	 *
	 * @return string
	 */
	public function format_admin_gateway_name( string $method_title, WC_Payment_Gateway $payment_gateway ): string {

		if ( ! ( $payment_gateway instanceof Venmo_Gateway ) ) {
			return $method_title;
		}

		$username = $payment_gateway->get_option( 'venmo_username' );

		if ( empty( $username ) ) {
			return $method_title;
		}

		// Don't format it on the gateway's page itself.
		if ( isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] && ! isset( $_GET['section'] ) ) {
			return "{$method_title} – <i>{$username}</i>";
		} else {
			return "{$method_title} – {$username}";
		}

	}

	/**
	 * On the admin side, add the destination Venmo username where appropriate.
	 *
	 * @hooked woocommerce_order_get_payment_method_title
	 *
	 * @see WC_Admin_List_Table_Orders::render_order_total_column()
	 *
	 * @param string   $value
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public function format_method_title( $value, WC_Order $order ) {

		if ( $value !== 'Venmo' || ! is_admin() ) {
			return $value;
		}

		$destination_account_username = $order->get_meta( Venmo_Gateway::DESTINATION_VENMO_USERNAME_META_KEY );

		if ( empty( $destination_account_username ) ) {
			return $value;
		}

		return "{$value}: {$destination_account_username}";
	}

	/**
	 * When linking to WooCommerce/Settings/Payments from plugins.php, filter to only instances of this gateway.
	 *
	 * The plugins.php code checks for multiple instances of the gateway, then uses the `class=bh-wc-venmo-gateway`
	 * parameter on the Settings link to invoke this function.
	 *
	 * i.e. `wp-admin/admin.php?page=wc-settings&tab=checkout&class=bh-wc-venmo-gateway`.
	 *
	 * @hooked woocommerce_payment_gateways
	 * @see WC_Payment_Gateways::init()
	 *
	 * @param array<string|WC_Payment_Gateway> $gateways WC_Payment_Gateway subclass instance or class names of payment gateways registered with WooCommerce.
	 *
	 * @return array<string|WC_Payment_Gateway>
	 */
	public function filter_to_only_venmo_gateways( array $gateways ): array {
		return $gateways;

	}


}
