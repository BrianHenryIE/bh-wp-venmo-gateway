<?php

namespace JuicedPlugins\Venmo_Gateway_Pro\API;

use JuicedPlugins\Venmo_Gateway_Pro\Unit_Testcase;
use Codeception\Stub\Expected;

/**
 * @coversDefaultClass  \JuicedPlugins\Venmo_Gateway_Pro\API
 */
class API_Unit_Test extends Unit_Testcase {

	public function test_happy_simple_api(): void {

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
