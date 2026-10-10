<?php
/**
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\API;

use JuicedPlugins\Venmo_Gateway_Pro\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;

interface API_Interface {

	public function check_for_payment_emails(): void;

	/**
	 * The aggregate provider of unpaid WooCommerce orders and GiveWP donations the reconciler is waiting to match.
	 *
	 * Used to render the "Unreconciled orders" admin page.
	 */
	public function get_unpaid_orders_provider(): Unpaid_Orders_Provider_Interface;

	/**
	 * Fetch a customer's public Venmo profile (their full name) from venmo.com.
	 *
	 * @param string $username The Venmo username entered at checkout, with or without a leading `@`.
	 *
	 * @return ?Venmo_Profile Null when the user does not exist or the page could not be fetched.
	 */
	public function get_venmo_profile( string $username ): ?Venmo_Profile;
}
