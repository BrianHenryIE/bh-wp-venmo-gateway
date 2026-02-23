<?php
/**
 * Class Plugin_Test. Tests the root plugin setup.
 *
 * @package BrianHenryIE\WC_Venmo_Gateway
 * @author     Brian Henry <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WC_Venmo_Gateway;

use BrianHenryIE\WC_Venmo_Gateway\API\API;
use BrianHenryIE\WC_Venmo_Gateway\Includes\Register_Hooks;

/**
 * Verifies the plugin has been instantiated and added to PHP's $GLOBALS variable.
 */
class BH_WC_Venmo_Gateway_Integration_Test extends \BrianHenryIE\WC_Venmo_Gateway\WPUnit_Testcase {

	/**
	 * Test the main plugin object is added to PHP's GLOBALS and that it is the correct class.
	 */
	public function test_plugin_instantiated() {

		$this->assertArrayHasKey( 'bh_wc_venmo_gateway', $GLOBALS );

		$this->assertInstanceOf( API::class, $GLOBALS['bh_wc_venmo_gateway'] );
	}
}
