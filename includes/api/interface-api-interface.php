<?php
/**
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;

interface API_Interface {

	public function check_for_payment_emails(): void;

	/**
	 * The aggregate provider of unpaid WooCommerce orders and GiveWP donations the reconciler is waiting to match.
	 *
	 * Used to render the "Unreconciled orders" admin page.
	 */
	public function get_unpaid_orders_provider(): Unpaid_Orders_Provider_Interface;
}
