<?php

namespace BrianHenryIE\WC_Venmo_Gateway\API;

use Psr\Log\NullLogger;
use Codeception\Stub\Expected;

/**
 * @coversDefaultClass  \BrianHenryIE\WC_Venmo_Gateway\API
 */
class API_Unit_Test extends \Codeception\Test\Unit {

	protected function _before() {
		\WP_Mock::setUp();
	}
	public function _after() {
		parent::_after();

		\WP_Mock::tearDown();
	}

	public function test_happy_simple_api() {

		$this->markTestSkipped( 'IMAP reconcile has been updated' );

		$imap     = $this->make(
			IMAP_Reconcile::class,
			array(
				'check_for_payment_emails' => Expected::once(),
			)
		);
		$settings = $this->makeEmpty( Settings_Interface::class );
		$logger   = new NullLogger();

		$sut = new API( $imap, $settings, $logger );

		$sut->check_for_payment_emails();

	}
}
