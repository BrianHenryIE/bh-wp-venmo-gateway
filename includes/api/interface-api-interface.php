<?php
/**
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\API;

interface API_Interface {

	public function check_for_payment_emails(): void;
}
