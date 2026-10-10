<?php
/**
 * Cron job should be deleted when the plugin is deactivated.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Includes;

use JuicedPlugins\Venmo_Gateway_Pro\Unit_Testcase;

/**
 *
 * @covers \JuicedPlugins\Venmo_Gateway_Pro\Includes\Deactivator
 *
 * Class Deactivator_Unit_Test
 * @package brianhenryie/juiced-venmo-gateway-pro
 */
class Deactivator_Unit_Test extends Unit_Testcase {

	/**
	 * @see wp_clear_scheduled_hook()
	 */
	public function test_check_cron_job_is_deleted(): void {

		\WP_Mock::userFunction(
			'wp_clear_scheduled_hook',
			array(
				'args'  => array( 'juiced_venmo_gateway_pro_check_for_payment_emails' ),
				'times' => 1,
			)
		);

		Deactivator::deactivate();
	}
}
