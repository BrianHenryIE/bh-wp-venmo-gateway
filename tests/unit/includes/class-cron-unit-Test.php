<?php

namespace JuicedPlugins\Venmo_Gateway_Pro\Includes;

use JuicedPlugins\Venmo_Gateway_Pro\API\API_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\API\Settings_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\Unit_Testcase;

/**
 * @covers \JuicedPlugins\Venmo_Gateway_Pro\Includes\Cron
 *
 * Class Cron_Unit_Test
 * @package brianhenryie/juiced-venmo-gateway-pro
 */
class Cron_Unit_Test extends Unit_Testcase {

	/**
	 * Check when the cron's check_for_payment_emails function is called, i.e.
	 * by Cron, that it calls API's check_for_payment_emails function.
	 */
	public function test_check_for_payment_emails_calls_api(): void {

		$settings_mock = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_slug'    => '',
				'get_plugin_version' => 'a',
			)
		);

		$api_mock = $this->makeEmpty(
			API_Interface::class,
			array( 'check_for_payment_emails' => \Codeception\Stub\Expected::once() )
		);

		$cron = new Cron( $api_mock, $settings_mock, $this->logger );

		$cron->check_for_payment_emails();
	}
}
