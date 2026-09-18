<?php
/**
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;

interface Settings_Interface extends Email_Reconcile_Settings_Interface {

	public function get_plugin_slug(): string;

	public function get_plugin_basename(): string;

	public function get_plugin_version(): string;

	/**
	 * TODO: Returns true if the user has entered all the appropriate settings and checked enable.
	 *
	 * @return bool
	 */
	public function is_imap_reconcile_enabled(): bool;

	/**
	 * Returns the ids for all instances of Venmo Gateway registered with WooCommerce.
	 *
	 * @return string[]
	 */
	public function get_payment_method_ids(): array;
}
