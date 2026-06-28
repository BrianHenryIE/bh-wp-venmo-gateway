<?php
/**
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\Email_Extract_Settings_Helper_Trait;
use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\Email_Extract_Settings_Interface;

class Pattern_2 implements Email_Extract_Settings_Interface {
	use Email_Extract_Settings_Helper_Trait;

	/**
	 * The customer's email address is not in the Venmo email!
	 *
	 * @return ?string
	 */
	public function get_customer_email_regex(): ?string {
		return null;
	}

	/**
	 * The Venmo HTML email uses HTML comments before the data:
	 *
	 * <!-- actor name -->
	 *
	 * @return string
	 */
	public function get_customer_name_regex(): ?string {
		return '/actor name -->.*?>\s*(.*?)<\/a> <!-- action -->/';
	}

	/**
	 * 'note' => The note the customer has written when sending payment.
	 *
	 * TODO: <p> seems to be the entirety of the notes... but could they multiline?
	 *
	 * @return string[]
	 */
	public function get_notes_array_regex(): array {
		return array(
			'note' => '/<!-- note -->.*?<p>(.*?)<\/p>/',
		);
	}

	public function get_transaction_id_regex(): ?string {
		return '/Payment ID: (\d+)/';
	}

	/**
	 * @return string
	 */
	public function get_transaction_url_regex(): ?string {
		return '/<a href="(https:\/\/venmo.com\/story\/\d+)\?login=1" /';
	}

	public function get_subject_regex(): ?string {
		return '/.*paid you.*/';
	}

	public function get_amount_regex(): string {
		return '#\$(\d+\.\d{2})(</div> <div>Fee|<\/span>)#';
	}

	/**
	 * The customer's Venmo username isn't in the email.
	 *
	 * @return null
	 */
	public function get_customer_id_regex(): ?string {
		return null;
	}
}
