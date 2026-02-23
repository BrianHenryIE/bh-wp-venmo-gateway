<?php
/**
 * Cron job should be deleted when the plugin is deactivated.
 */

namespace BrianHenryIE\WC_Venmo_Gateway\Includes;

use BrianHenryIE\WC_Venmo_Gateway\Unit_Testcase;

/**
 *
 * @covers \BrianHenryIE\WC_Venmo_Gateway\Includes\Deactivator
 *
 * Class Deactivator_Unit_Test
 * @package BrianHenryIE\WC_Venmo_Gateway\Includes
 */
class Deactivator_Unit_Test extends Unit_Testcase {

	/**
	 * @see wp_clear_scheduled_hook()
	 */
	public function test_check_cron_job_is_deleted() {

		\WP_Mock::userFunction(
			'wp_clear_scheduled_hook',
			array(
				'args'  => array( 'bh_wc_venmo_gateway_check_for_payment_emails' ),
				'times' => 1,
			)
		);

		Deactivator::deactivate();
	}

	public function _after() {
		parent::_after();

		\WP_Mock::tearDown();
	}
}
