<?php

namespace BrianHenryIE\WP_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

class Payment_Gateways_Unit_Test extends Unit_Testcase {

	/**
	 * All it needs to do is add the classname to WooCommerce's filter so it can be instantiated later.
	 */
	public function test_class_is_added_to_array() {

		$sut = new Payment_Gateways( '', '' );

		$result = $sut->add_to_woocommerce( array() );

		$this->assertContains( \BrianHenryIE\WP_Venmo_Gateway\WooCommerce\Venmo_Gateway::class, $result );
	}
}
