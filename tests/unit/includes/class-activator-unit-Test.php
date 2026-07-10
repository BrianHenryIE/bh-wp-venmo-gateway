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
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Includes\Activator
 */
class Activator_Unit_Test extends Unit_Testcase {

	/**
	 * Confirm the activation time is saved.
	 */
	public function test_update_option_is_called() {
		WP_Mock::userFunction(
			'get_option'
		);

		WP_Mock::userFunction( 'wp_date' )->andReturn( '2026-07-09 21:55:00-08:00' );

		\Patchwork\redefine(
			'constant',
			function ( string $constant_name ) {
				return 'BH_WP_VENMO_GATEWAY_VERSION' === $constant_name
					? '1.2.3'
					: \Patchwork\relay( func_get_args() );
			}
		);

		WP_Mock::userFunction(
			'update_option',
			array(
				'args'  => array(
					'bh_wp_venmo_gateway_activated_time',
					\WP_Mock\Functions::type( 'array' ),
				),
				'times' => 1,
			)
		);

		Activator::activate();
	}
}
