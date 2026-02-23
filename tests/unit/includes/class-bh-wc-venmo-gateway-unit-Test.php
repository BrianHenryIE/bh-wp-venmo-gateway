<?php
/**
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\Includes;

use BrianHenryIE\WC_Venmo_Gateway\Admin\Plugins_Page;
use BrianHenryIE\WC_Venmo_Gateway\API\API_Interface;
use BrianHenryIE\WC_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\Unit_Testcase;
use BrianHenryIE\WC_Venmo_Gateway\WooCommerce\Payment_Gateways;
use WP_Mock\Matcher\AnyInstance;

/**
 * @covers \BrianHenryIE\WC_Venmo_Gateway\Includes\Register_Hooks
 *
 * Class BH_WC_Venmo_Gateway_Unit_Test
 * @package brianhenryie/bh-wc-venmo-gateway
 */
class BH_WC_Venmo_Gateway_Unit_Test extends Unit_Testcase {

	/**
	 * @covers \BrianHenryIE\WC_Venmo_Gateway\Includes\Register_Hooks::set_locale
	 */
	public function test_set_locale_hooked() {

		\WP_Mock::expectActionAdded(
			'plugins_loaded',
			array( new AnyInstance( I18n::class ), 'load_plugin_textdomain' )
		);

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php',
			)
		);

		new Register_Hooks( $api, $settings, $this->logger );
	}

	/**
	 * @covers \BrianHenryIE\WC_Venmo_Gateway\Includes\Register_Hooks::define_admin_hooks
	 */
	public function test_admin_hooks() {

		\WP_Mock::expectFilterAdded(
			'plugin_action_links_bh-wc-venmo-gateway/bh-wc-venmo-gateway.php',
			array( new AnyInstance( Plugins_Page::class ), 'add_settings_action_link' )
		);

		\WP_Mock::expectFilterAdded(
			'plugin_action_links_bh-wc-venmo-gateway/bh-wc-venmo-gateway.php',
			array( new AnyInstance( Plugins_Page::class ), 'add_orders_action_link' )
		);

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php',
			)
		);

		new Register_Hooks( $api, $settings, $this->logger );
	}

	/**
	 * @covers \BrianHenryIE\WC_Venmo_Gateway\Includes\Register_Hooks::define_woocommerce_hooks
	 */
	public function test_woocommerce_hooks() {

		\WP_Mock::expectFilterAdded(
			'woocommerce_order_get_payment_method_title',
			array( new AnyInstance( Payment_Gateways::class ), 'format_method_title' ),
			10,
			2
		);

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php',
			)
		);

		new Register_Hooks( $api, $settings, $this->logger );
	}
}
