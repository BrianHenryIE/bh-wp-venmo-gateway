<?php
/**
 * Tests for the root plugin file.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro;

/**
 * Class Plugin_WP_Mock_Test
 */
class Plugin_WP_Mock_Test extends Unit_Testcase {
	/**
	 * Verifies the plugin initialization.
	 */
	public function test_plugin_include(): void {

		/**
		 * @runInSeparateProcess
		 * @see https://github.com/lucatume/wp-browser/issues/410
		 * @see https://github.com/Codeception/Codeception/issues/3568
		 */
		$this->markTestSkipped( 'Need to @runInSeparateProcess' );

		$plugin_root_dir = dirname( __DIR__, 2 ) . '/includes';

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

		require_once $plugin_root_dir . '/juiced-venmo-gateway-pro.php';

		$this->assertArrayHasKey( 'juiced_venmo_gateway_pro', $GLOBALS );

		$this->assertInstanceOf( JuicedPlugins\Venmo_Gateway_Pro::class, $GLOBALS['juiced_venmo_gateway_pro'] );
	}


	/**
	 * Verifies the plugin does not output anything to screen.
	 */
	public function test_plugin_include_no_output(): void {
		/**
		 * @runInSeparateProcess
		 * @see https://github.com/lucatume/wp-browser/issues/410
		 * @see https://github.com/Codeception/Codeception/issues/3568
		 */
		$this->markTestSkipped( 'Need to @runInSeparateProcess' );
		$plugin_root_dir = dirname( __DIR__, 2 ) . '/includes';

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

		require_once $plugin_root_dir . '/juiced-venmo-gateway-pro.php';

		$printed_output = ob_get_contents();

		ob_end_clean();

		$this->assertEmpty( $printed_output );
	}
}
