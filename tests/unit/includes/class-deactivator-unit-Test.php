<?php
/**
 * Cron job should be deleted when the plugin is deactivated.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Includes;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

/**
 *
 * @covers \BrianHenryIE\WP_Venmo_Gateway\Includes\Deactivator
 *
 * Class Deactivator_Unit_Test
 * @package brianhenryie/bh-wp-venmo-gateway
 */
class Deactivator_Unit_Test extends Unit_Testcase {

	/**
	 * @see wp_clear_scheduled_hook()
	 */
	public function test_check_cron_job_is_deleted() {

		\WP_Mock::userFunction(
			'wp_clear_scheduled_hook',
			array(
				'args'  => array( 'bh_wp_venmo_gateway_check_for_payment_emails' ),
				'times' => 1,
			)
		);

		Deactivator::deactivate();
	}
}
