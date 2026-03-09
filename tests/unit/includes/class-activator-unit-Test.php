<?php
/**
 * Activation time should be recorded in an option when the plugin is deactivated.
 *
 * This is later used to display a "please configure" notice for a week.
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\Includes;

use BrianHenryIE\WC_Venmo_Gateway\Unit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WC_Venmo_Gateway\Includes\Activator
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
					'bh-wc-venmo-gateway-last-activated-time',
					\WP_Mock\Functions::type( 'int' ),
				),
				'times' => 1,
			)
		);

		Activator::activate();
	}
}
