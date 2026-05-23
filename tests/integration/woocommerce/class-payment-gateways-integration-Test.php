<?php

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Venmo_Gateway\WPUnit_Testcase;

class Payment_Gateways_Integration_Test extends WPUnit_Testcase {

	/**
	 * Let's run the function WooCommerce uses to poll for gateways and see is our gateway there.
	 *
	 * This is distinct to `WC()->payment_gateways()->get_available_payment_gateways()`.
	 */
	public function test_gateway_is_added() {

		$gateways = WC()->payment_gateways()->payment_gateways();

		$this->assertArrayHasKey( 'venmo', $gateways );

		$this->assertInstanceOf( Venmo_Gateway::class, $gateways['venmo'] );
	}
}
