<?php
/**
 * Tests for the root plugin file.
 *
 * @package BrianHenryIE\WC_Venmo_Gateway
 * @author  Brian Henry <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WC_Venmo_Gateway;

use BrianHenryIE\WC_Venmo_Gateway\Includes\BH_WC_Venmo_Gateway;

/**
 * Class Plugin_WP_Mock_Test
 */
class Plugin_WP_Mock_Test extends \Codeception\Test\Unit {

	protected function _before() {
		\WP_Mock::setUp();
	}
	public function _after() {
		parent::_after();

		\WP_Mock::tearDown();
	}
	/**
	 * Verifies the plugin initialization.
	 */
	public function test_plugin_include() {

		/**
		 * @runInSeparateProcess
		 * @see https://github.com/lucatume/wp-browser/issues/410
		 * @see https://github.com/Codeception/Codeception/issues/3568
		 */
		$this->markTestSkipped( 'Need to @runInSeparateProcess' );

		$plugin_root_dir = dirname( __DIR__, 2 ) . '/src';

		\WP_Mock::userFunction(
			'plugin_dir_path',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => $plugin_root_dir . '/',
			)
		);

		\WP_Mock::userFunction(
			'register_activation_hook'
		);

		\WP_Mock::userFunction(
			'register_deactivation_hook'
		);

		require_once $plugin_root_dir . '/bh-wc-venmo-gateway.php';

		$this->assertArrayHasKey( 'bh_wc_venmo_gateway', $GLOBALS );

		$this->assertInstanceOf( BrianHenryIE\WC_Venmo_Gateway::class, $GLOBALS['bh_wc_venmo_gateway'] );

	}


	/**
	 * Verifies the plugin does not output anything to screen.
	 */
	public function test_plugin_include_no_output() {
		/**
		 * @runInSeparateProcess
		 * @see https://github.com/lucatume/wp-browser/issues/410
		 * @see https://github.com/Codeception/Codeception/issues/3568
		 */
		$this->markTestSkipped( 'Need to @runInSeparateProcess' );
		$plugin_root_dir = dirname( __DIR__, 2 ) . '/src';

		\WP_Mock::userFunction(
			'plugin_dir_path',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => $plugin_root_dir . '/',
			)
		);

		\WP_Mock::userFunction(
			'register_activation_hook'
		);

		\WP_Mock::userFunction(
			'register_deactivation_hook'
		);

		ob_start();

		require_once $plugin_root_dir . '/bh-wc-venmo-gateway.php';

		$printed_output = ob_get_contents();

		ob_end_clean();

		$this->assertEmpty( $printed_output );

	}

}
