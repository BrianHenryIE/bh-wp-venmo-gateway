<?php

namespace BrianHenryIE\WC_Venmo_Gateway\API;

use Codeception\Stub\Expected;

/**
 * @coversDefaultClass  \BrianHenryIE\WC_Venmo_Gateway\API
 */
class API_Unit_Test extends \Codeception\Test\Unit {

	public function test_happy_simple_api() {

		$this->markTestSkipped( 'IMAP reconcile has been updated' );

		$imap     = $this->make(
			IMAP_Reconcile::class,
			array(
				'check_for_payment_emails' => Expected::once(),
			)
		);
		$settings = $this->makeEmpty( Settings_Interface::class );

		$sut = new API( $imap, $settings, $this->logger );

		$sut->check_for_payment_emails();
	}
}
