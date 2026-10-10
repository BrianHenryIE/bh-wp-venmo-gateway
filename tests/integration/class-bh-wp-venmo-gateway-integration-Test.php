<?php
/**
 * Class Plugin_Test. Tests the root plugin setup.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro;

use JuicedPlugins\Venmo_Gateway_Pro\API\API;

/**
 * Verifies the plugin has been instantiated and added to PHP's $GLOBALS variable.
 */
class Juiced_Venmo_Gateway_Pro_Integration_Test extends WPUnit_Testcase {


	/**
	 * Test the main plugin object is added to PHP's GLOBALS and that it is the correct class.
	 */
	public function test_plugin_instantiated(): void {

		$this->assertArrayHasKey( 'juiced_venmo_gateway_pro', $GLOBALS );

		$this->assertInstanceOf( API::class, $GLOBALS['juiced_venmo_gateway_pro'] );
	}
}
