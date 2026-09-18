<?php
/**
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\Email_Extract_Settings_Helper_Trait;
use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\Email_Extract_Settings_Interface;

/**
 * Regexes for the Venmo "paid you" email format in use since 2026, which has no plain-text part and
 * no longer includes the customer's Venmo username.
 */
class Pattern_3 implements Email_Extract_Settings_Interface {
	use Email_Extract_Settings_Helper_Trait;

	/**
	 * The customer's email address is not in the Venmo email!
	 */
	public function get_customer_email_regex(): ?string {
		return null;
	}

	/**
	 * "<title>Brian Henry paid you $10</title>"
	 */
	public function get_customer_name_regex(): ?string {
		return '/title>(.*?)\s*paid you.*<\/title>/';
	}

	/**
	 * 'note' => The note the customer has written when sending payment, hopefully the order id.
	 *
	 * @return string[]
	 */
	public function get_notes_array_regex(): array {
		return array(
			'note' => '/class="transaction-note.*?>(.*?)<\/p>/',
		);
	}

	/**
	 * `Transaction ID</h3><p class="transaction-value" style="...">283540688087819403</p><h3 style="...">Sent to`
	 */
	public function get_transaction_id_regex(): ?string {
		return '/Transaction ID.*>(\d{10,})<\/p>.*Sent to/';
	}

	/**
	 * The "See transaction" button's link, e.g. `https://venmo.com/story/4673461555274381813?k=...`.
	 */
	public function get_transaction_url_regex(): ?string {
		return '/href="(https:\/\/venmo.com\/story\/.*?)" target="_blank"/';
	}

	/**
	 * This is including the currency symbol. Should it?
	 */
	public function get_amount_regex(): string {
		return '/title>.*?\s*paid you\s*\$(.*)<\/title>/';
	}

	/**
	 * The customer's Venmo username isn't in the email.
	 */
	public function get_customer_id_regex(): ?string {
		return null;
	}
}
