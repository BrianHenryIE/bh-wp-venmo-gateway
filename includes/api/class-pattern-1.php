<?php
/**
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\API;

use BrianHenryIE\WC_Venmo_Gateway\WC_Order_Email_Reconcile\Email_Extract_Settings_Helper_Trait;
use BrianHenryIE\WC_Venmo_Gateway\WC_Order_Email_Reconcile\Email_Extract_Settings_Interface;

class Pattern_1 implements Email_Extract_Settings_Interface {
	use Email_Extract_Settings_Helper_Trait;

	/**
	 * The customer's email address is not in the Venmo email!
	 *
	 * @return ?string
	 */
	public function get_customer_email_regex(): ?string {
		return null;
	}

	public function get_customer_name_regex(): ?string {
		return '/Subject:\s(.*)\spaid you/';
	}

	public function get_notes_array_regex(): array {
		return array(
			'note' => '/paid You .*>\s*(.*)Transfer Date and Amount/',
		);
	}

	public function get_transaction_id_regex(): ?string {
		return '/Payment ID: (\d+)/';
	}

	/**
	 * Match the text after the "Comment <" prompt which starts with https and capture it all until the ? (in the URL).
	 *
	 * "Comment <https://venmo.com/story/123456789101112?login=1>"
	 * =>
	 * "https://venmo.com/story/123456789101112"
	 *
	 * @return string|null
	 */
	public function get_transaction_url_regex(): ?string {
		return '/Comment\s<(https.*)\?/U';
	}

	public function get_subject_regex(): ?string {
		return '/.*paid you.*/';
	}

	public function get_amount_regex(): string {
		return '/Subject:\s.*\spaid you\s\$(\d*\.\d{2})/';
	}

	// This is just the email.
	public function get_customer_id_regex(): ?string {
		return null;
	}
}
