<?php

namespace BrianHenryIE\WP_Venmo_Gateway\Includes;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

/**
 * @covers \BrianHenryIE\WP_Venmo_Gateway\Includes\I18n
 */
class I18n_Unit_Test extends Unit_Testcase {

	/**
	 * Verify load_plugin_textdomain is correctly called.
	 *
	 * @covers \BrianHenryIE\WP_Venmo_Gateway\Includes\I18n::load_plugin_textdomain
	 */
	public function test_load_plugin_textdomain() {

		global $plugin_root_dir;

		\WP_Mock::userFunction(
			'load_plugin_textdomain',
			array(
				'args' => array(
					'bh-wp-venmo-gateway',
					false,
					$plugin_root_dir . '/Languages/',
				),
			)
		);
	}
}
