<?php
/**
 * Unit tests for the "orders awaiting Venmo payment" list filter.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Orders_List_Filter
 */
class Orders_List_Filter_Unit_Test extends Unit_Testcase {

	protected function setup(): void {
		parent::setup();

		\WP_Mock::passthruFunction( 'wp_unslash' );
		\WP_Mock::passthruFunction( 'sanitize_text_field' );
		\WP_Mock::userFunction( 'admin_url' )->andReturnUsing( fn( string $path ) => 'https://example.org/wp-admin/' . $path );
		\WP_Mock::userFunction( 'add_query_arg' )->andReturnUsing( fn( string $key, string $value, string $url ) => $url . '&' . $key . '=' . $value );
	}

	protected function tearDown(): void {
		unset( $_GET[ Orders_List_Filter::QUERY_ARG ] );
		parent::tearDown();
	}

	/**
	 * @covers ::get_awaiting_payment_orders_url
	 */
	public function test_url_uses_hpos_orders_page(): void {
		$sut = new class() extends Orders_List_Filter {
			protected function is_hpos_enabled(): bool {
				return true;
			}
		};

		$this->assertSame(
			'https://example.org/wp-admin/admin.php?page=wc-orders&bh_wp_venmo_gateway_awaiting_payment=1',
			$sut->get_awaiting_payment_orders_url()
		);
	}

	/**
	 * @covers ::get_awaiting_payment_orders_url
	 */
	public function test_url_uses_legacy_posts_list_without_hpos(): void {
		$sut = new class() extends Orders_List_Filter {
			protected function is_hpos_enabled(): bool {
				return false;
			}
		};

		$this->assertSame(
			'https://example.org/wp-admin/edit.php?post_type=shop_order&bh_wp_venmo_gateway_awaiting_payment=1',
			$sut->get_awaiting_payment_orders_url()
		);
	}

	/**
	 * @covers ::filter_hpos_list_table_query_args
	 */
	public function test_hpos_query_args_are_untouched_without_the_query_arg(): void {
		$args = array( 'limit' => 20 );

		$this->assertSame( $args, ( new Orders_List_Filter() )->filter_hpos_list_table_query_args( $args ) );
	}

	/**
	 * @covers ::filter_hpos_list_table_query_args
	 */
	public function test_hpos_query_args_filter_to_venmo_awaiting_payment(): void {
		$_GET[ Orders_List_Filter::QUERY_ARG ] = '1';

		$result = ( new Orders_List_Filter() )->filter_hpos_list_table_query_args( array( 'limit' => 20 ) );

		$this->assertSame( 20, $result['limit'] );
		$this->assertSame( 'venmo', $result['payment_method'] );
		$this->assertSame( array( 'wc-pending', 'wc-on-hold' ), $result['status'] );
	}

	/**
	 * @covers ::filter_legacy_list_table_query_vars
	 */
	public function test_legacy_query_vars_are_untouched_for_other_post_types(): void {
		$_GET[ Orders_List_Filter::QUERY_ARG ] = '1';
		\WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		$vars = array( 'post_type' => 'page' );

		$this->assertSame( $vars, ( new Orders_List_Filter() )->filter_legacy_list_table_query_vars( $vars ) );
	}

	/**
	 * @covers ::filter_legacy_list_table_query_vars
	 */
	public function test_legacy_query_vars_filter_to_venmo_awaiting_payment(): void {
		$_GET[ Orders_List_Filter::QUERY_ARG ] = '1';
		\WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		$result = ( new Orders_List_Filter() )->filter_legacy_list_table_query_vars( array( 'post_type' => 'shop_order' ) );

		$this->assertSame( array( 'wc-pending', 'wc-on-hold' ), $result['post_status'] );
		$this->assertSame( '_payment_method', $result['meta_query'][0]['key'] );
		$this->assertSame( 'venmo', $result['meta_query'][0]['value'] );
	}
}
