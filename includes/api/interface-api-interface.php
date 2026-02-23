<?php
/**
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\API;

interface API_Interface {

	public function check_for_payment_emails(): void;
}
