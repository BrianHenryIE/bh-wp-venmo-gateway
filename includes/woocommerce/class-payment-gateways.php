<?php
/**
 * Add the payment gateway to WooCommerce's list of gateways.
 *
 * @package brianhenryie/bh-wc-venmo-gateway
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

		$destination_account_username = $order->get_meta( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY );

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
