<?php
/**
 * Class Plugin_Test. Tests the root plugin setup.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway;

use BrianHenryIE\WP_Venmo_Gateway\API\API;

/**
 * Verifies the plugin has been instantiated and added to PHP's $GLOBALS variable.
 */
class BH_WP_Venmo_Gateway_Integration_Test extends WPUnit_Testcase {


	/**
	 * Test the main plugin object is added to PHP's GLOBALS and that it is the correct class.
	 */
	public function test_plugin_instantiated() {

		$this->assertArrayHasKey( 'bh_wp_venmo_gateway', $GLOBALS );

		$this->assertInstanceOf( API::class, $GLOBALS['bh_wp_venmo_gateway'] );
	}
}
