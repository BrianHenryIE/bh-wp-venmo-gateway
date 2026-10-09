<?php
/**
 * Activation time should be recorded in an option when the plugin is deactivated.
 *
 * This is later used to display a "please configure" notice for a week.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Includes;

use JuicedPlugins\Venmo_Gateway_Pro\Unit_Testcase;
use WP_Mock;

/**
 * @coversDefaultClass \JuicedPlugins\Venmo_Gateway_Pro\Includes\Activator
 */
class Activator_Unit_Test extends Unit_Testcase {

	/**
	 * Confirm the activation time is saved.
	 */
	public function test_update_option_is_called(): void {
		// Already at 4.3.0, so the upgrade does not run.
		WP_Mock::userFunction( 'get_option' )->andReturn( array( '2026-01-01T00:00:00+00:00' => '4.3.0' ) );

		WP_Mock::userFunction( 'wp_date' )->andReturn( '2026-07-09 21:55:00-08:00' );

		\Patchwork\redefine(
			'constant',
			function ( string $constant_name ) {
				return 'JUICED_VENMO_GATEWAY_PRO_VERSION' === $constant_name
					? '1.2.3'
					: \Patchwork\relay( func_get_args() );
			}
		);

		WP_Mock::userFunction(
			'update_option',
			array(
				'args'  => array(
					'juiced_venmo_gateway_pro_activated_time',
					\WP_Mock\Functions::type( 'array' ),
				),
				'times' => 1,
			)
		);

		Activator::activate();
	}
}
