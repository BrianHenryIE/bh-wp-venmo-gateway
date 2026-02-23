<?php

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

class Payment_Gateways_Unit_Test extends \Codeception\Test\Unit {

	protected function _before() {
		\WP_Mock::setUp();
	}
	public function _after() {
		parent::_after();

		\WP_Mock::tearDown();
	}

	/**
	 * All it needs to do is add the classname to WooCommerce's filter so it can be instantiated later.
	 */
	public function test_class_is_added_to_array() {

		$sut = new Payment_Gateways( '', '' );

		$result = $sut->add_to_woocommerce( array() );

		$this->assertContains( 'BrianHenryIE\WC_Venmo_Gateway\WooCommerce\Venmo_Gateway', $result );

	}

}
