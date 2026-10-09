<?php
/**
 * Unit tests for registering the reconcile library's unreconciled orders page under the WooCommerce menu.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Admin;

use JuicedPlugins\Venmo_Gateway_Pro\API\API_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\API\Settings_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\Unit_Testcase;
use JuicedPlugins\Venmo_Gateway_Pro\WP_Order_Email_Reconcile\Admin\Unreconciled_Orders_Page;
use JuicedPlugins\Venmo_Gateway_Pro\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;

/**
 * @coversDefaultClass \JuicedPlugins\Venmo_Gateway_Pro\Admin\Unreconciled_Orders_Menu
 */
class Unreconciled_Orders_Menu_Unit_Test extends Unit_Testcase {

	/**
	 * @covers ::register_submenu
	 */
	public function test_registers_hidden_page(): void {
		\WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		\WP_Mock::userFunction( 'add_submenu_page' )
			->once()
			->with( '', \Mockery::any(), \Mockery::any(), 'manage_woocommerce', Unreconciled_Orders_Page::PAGE_SLUG, \Mockery::type( 'array' ) );

		$api = $this->makeEmpty(
			API_Interface::class,
			array( 'get_unpaid_orders_provider' => $this->makeEmpty( Unpaid_Orders_Provider_Interface::class ) )
		);

		$capabilities = $this->makeEmpty( Capabilities::class, array( 'get_payment_emails_capability' => 'manage_woocommerce' ) );

		$sut = new Unreconciled_Orders_Menu( $api, $this->makeEmpty( Settings_Interface::class ), $capabilities, $this->logger );

		$sut->register_submenu();
	}

	/**
	 * @covers ::get_url
	 */
	public function test_get_url(): void {
		\WP_Mock::userFunction( 'admin_url' )->andReturnUsing( fn( string $path ) => 'https://example.org/wp-admin/' . $path );

		$api = $this->makeEmpty(
			API_Interface::class,
			array( 'get_unpaid_orders_provider' => $this->makeEmpty( Unpaid_Orders_Provider_Interface::class ) )
		);

		$capabilities = $this->makeEmpty( Capabilities::class, array( 'get_payment_emails_capability' => 'manage_woocommerce' ) );

		$sut = new Unreconciled_Orders_Menu( $api, $this->makeEmpty( Settings_Interface::class ), $capabilities, $this->logger );

		$this->assertSame( 'https://example.org/wp-admin/admin.php?page=bh-wp-oer-unreconciled-orders', $sut->get_url() );
	}
}
