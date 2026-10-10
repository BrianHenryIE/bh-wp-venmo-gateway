<?php

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce;

use JuicedPlugins\Venmo_Gateway_Pro\Unit_Testcase;

class Payment_Gateways_Unit_Test extends Unit_Testcase {

	/**
	 * All it needs to do is add the classname to WooCommerce's filter so it can be instantiated later.
	 */
	public function test_class_is_added_to_array(): void {

		$sut = new Payment_Gateways( '', '' );

		$result = $sut->add_to_woocommerce( array() );

		$this->assertContains( \JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Venmo_Gateway::class, $result );
	}
}
