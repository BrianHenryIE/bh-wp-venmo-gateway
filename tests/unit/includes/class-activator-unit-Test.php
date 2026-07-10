<?php
/**
 * Activation time should be recorded in an option when the plugin is deactivated.
 *
 * This is later used to display a "please configure" notice for a week.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Includes;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Includes\Activator
 */
class Activator_Unit_Test extends Unit_Testcase {

	/**
	 * Confirm the activation time is saved.
	 */
	public function test_update_option_is_called() {
		\WP_Mock::userFunction(
			'update_option',
			array(
				'args'  => array(
					'bh_wp_venmo_gateway_activated_time',
					\WP_Mock\Functions::type( 'int' ),
				),
				'times' => 1,
			)
		);

		Activator::activate();
	}
}
