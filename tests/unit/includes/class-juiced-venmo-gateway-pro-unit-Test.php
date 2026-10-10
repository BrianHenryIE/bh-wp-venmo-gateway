<?php
/**
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Includes;

use JuicedPlugins\Venmo_Gateway_Pro\Admin\Capabilities;
use JuicedPlugins\Venmo_Gateway_Pro\Admin\Plugins_Page;
use JuicedPlugins\Venmo_Gateway_Pro\Admin\Unreconciled_Orders_Menu;
use JuicedPlugins\Venmo_Gateway_Pro\API\API_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\API\Settings_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\Unit_Testcase;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Orders_List_Filter;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Payment_Gateways;
use WP_Mock\Matcher\AnyInstance;

/**
 * @coversDefaultClass  \JuicedPlugins\Venmo_Gateway_Pro\Includes\Register_Hooks
 *
 * Class Juiced_Venmo_Gateway_Pro_Unit_Test
 * @package brianhenryie/juiced-venmo-gateway-pro
 */
class Juiced_Venmo_Gateway_Pro_Unit_Test extends Unit_Testcase {

	/**
	 * @covers \JuicedPlugins\Venmo_Gateway_Pro\Includes\Register_Hooks::set_locale
	 */
	public function test_set_locale_hooked(): void {

		\WP_Mock::expectActionAdded(
			'plugins_loaded',
			array( new AnyInstance( I18n::class ), 'load_plugin_textdomain' )
		);

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => 'juiced-venmo-gateway-pro/juiced-venmo-gateway-pro.php',
			)
		);

		new Register_Hooks( $api, $settings, $this->logger );
	}

	/**
	 * @covers \JuicedPlugins\Venmo_Gateway_Pro\Includes\Register_Hooks::define_admin_hooks
	 */
	public function test_admin_hooks(): void {

		\WP_Mock::expectFilterAdded(
			'plugin_action_links_juiced-venmo-gateway-pro/juiced-venmo-gateway-pro.php',
			array( new AnyInstance( Plugins_Page::class ), 'add_settings_action_link' )
		);

		\WP_Mock::expectFilterAdded(
			'plugin_action_links_juiced-venmo-gateway-pro/juiced-venmo-gateway-pro.php',
			array( new AnyInstance( Plugins_Page::class ), 'add_orders_action_link' )
		);

		\WP_Mock::expectFilterAdded(
			'plugin_action_links_juiced-venmo-gateway-pro/juiced-venmo-gateway-pro.php',
			array( new AnyInstance( Plugins_Page::class ), 'add_unreconciled_orders_action_link' )
		);

		\WP_Mock::expectActionAdded(
			'admin_menu',
			array( new AnyInstance( Unreconciled_Orders_Menu::class ), 'register_submenu' )
		);

		\WP_Mock::expectFilterAdded(
			'bh_wp_mailboxes_required_capability',
			array( new AnyInstance( Capabilities::class ), 'filter_required_capability' ),
			10,
			3
		);

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => 'juiced-venmo-gateway-pro/juiced-venmo-gateway-pro.php',
			)
		);

		new Register_Hooks( $api, $settings, $this->logger );
	}

	/**
	 * @covers \JuicedPlugins\Venmo_Gateway_Pro\Includes\Register_Hooks::define_woocommerce_hooks
	 */
	public function test_woocommerce_hooks(): void {

		\WP_Mock::expectFilterAdded(
			'woocommerce_order_get_payment_method_title',
			array( new AnyInstance( Payment_Gateways::class ), 'format_method_title' ),
			10,
			2
		);

		\WP_Mock::expectFilterAdded(
			'woocommerce_order_list_table_prepare_items_query_args',
			array( new AnyInstance( Orders_List_Filter::class ), 'filter_hpos_list_table_query_args' )
		);

		\WP_Mock::expectFilterAdded(
			'request',
			array( new AnyInstance( Orders_List_Filter::class ), 'filter_legacy_list_table_query_vars' )
		);

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => 'juiced-venmo-gateway-pro/juiced-venmo-gateway-pro.php',
			)
		);

		new Register_Hooks( $api, $settings, $this->logger );
	}
}
