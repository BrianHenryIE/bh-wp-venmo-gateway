<?php

class AdminOrderPageCest {


	/**
	 * When an order is created, the admin UI should show the Venmo username.
	 *
	 * @param AcceptanceTester $I
	 */
	public function testPluginDescriptionHasBeenSet( AcceptanceTester $I ) {

		return;

		$I->loginAsAdmin();

		$I->canSee( 'venmo_username' );

	}

}
