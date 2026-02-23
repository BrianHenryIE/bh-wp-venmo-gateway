<?php

namespace BrianHenryIE\WC_Venmo_Gateway\Includes;

/**
 * @covers \BrianHenryIE\WC_Venmo_Gateway\Includes\I18n
 */
class I18n_Unit_Test extends \Codeception\Test\Unit {

	protected function _before() {
		\WP_Mock::setUp();
	}

	// This is required for `'times' => 1` to be verified.
	protected function _tearDown() {
		parent::_tearDown();
		\WP_Mock::tearDown();
	}

	/**
	 * Verify load_plugin_textdomain is correctly called.
	 *
	 * @covers \BrianHenryIE\WC_Venmo_Gateway\Includes\I18n::load_plugin_textdomain
	 */
	public function test_load_plugin_textdomain() {

		global $plugin_root_dir;

		\WP_Mock::userFunction(
			'load_plugin_textdomain',
			array(
				'args' => array(
					'bh-wc-venmo-gateway',
					false,
					$plugin_root_dir . '/Languages/',
				),
			)
		);
	}
}
