<?php

namespace JuicedPlugins\Venmo_Gateway_Pro\Includes;

use JuicedPlugins\Venmo_Gateway_Pro\Unit_Testcase;

/**
 * @covers \JuicedPlugins\Venmo_Gateway_Pro\Includes\I18n
 */
class I18n_Unit_Test extends Unit_Testcase {

	/**
	 * Verify load_plugin_textdomain is correctly called.
	 *
	 * @covers \JuicedPlugins\Venmo_Gateway_Pro\Includes\I18n::load_plugin_textdomain
	 */
	public function test_load_plugin_textdomain(): void {

		global $plugin_root_dir;

		\WP_Mock::userFunction(
			'load_plugin_textdomain',
			array(
				'args' => array(
					'juiced-venmo-gateway-pro',
					false,
					$plugin_root_dir . '/Languages/',
				),
			)
		);
	}
}
