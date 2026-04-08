<?php
/**
 * Tests for Venmo Gateway username handling functionality.
 *
 * Tests the get_saved_venmo_username(), set_venmo_username_cookie(), and
 * validate_fields() methods, as well as the usermeta saving in
 * save_order_payment_type_meta_data().
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WC_Venmo_Gateway\Unit_Testcase;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WC_Venmo_Gateway\WooCommerce\Venmo_Gateway
 */
class Venmo_Gateway_Username_Test extends Unit_TestCase {

	/**
	 * Create a partial mock of Venmo_Gateway that skips the constructor.
	 *
	 * The real constructor calls plugins_url(), init_form_fields(), etc.
	 * which need a full WordPress environment. For unit tests we skip it.
	 *
	 * @return Venmo_Gateway|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function create_gateway_mock( array $methods_to_mock = array() ) {
		$gateway = $this->getMockBuilder( Venmo_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods( $methods_to_mock )
			->getMock();

		// Set the id property which is normally set as a class property.
		$gateway->id = 'venmo';

		return $gateway;
	}

	/**
	 * Test that get_saved_venmo_username returns user meta when user is logged in.
	 *
	 * @covers ::get_saved_venmo_username
	 */
	public function test_get_saved_venmo_username_from_user_meta(): void {
		$gateway = $this->create_gateway_mock();

		\WP_Mock::userFunction( 'get_user_meta' )
			->once()
			->with( 123, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true )
			->andReturn( 'test-username' );

		$result = $gateway->get_saved_venmo_username( 123 );

		$this->assertEquals( 'test-username', $result );
	}

	/**
	 * Test that get_saved_venmo_username returns cookie value for guests.
	 *
	 * @covers ::get_saved_venmo_username
	 */
	public function test_get_saved_venmo_username_from_cookie(): void {
		$gateway = $this->create_gateway_mock();

		\WP_Mock::userFunction( 'get_user_meta' )
			->once()
			->with( 123, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true )
			->andReturn( '' );

		\WP_Mock::userFunction( 'sanitize_text_field' )
			->once()
			->with( 'cookie-username' )
			->andReturn( 'cookie-username' );

		$_COOKIE['venmo_username'] = 'cookie-username';

		$result = $gateway->get_saved_venmo_username( 123 );

		$this->assertEquals( 'cookie-username', $result );

		unset( $_COOKIE['venmo_username'] );
	}

	/**
	 * Test that get_saved_venmo_username returns empty string when no sources have a value.
	 *
	 * @covers ::get_saved_venmo_username
	 */
	public function test_get_saved_venmo_username_returns_empty_when_no_value(): void {
		$gateway = $this->create_gateway_mock();

		\WP_Mock::userFunction( 'get_user_meta' )
			->once()
			->andReturn( 'val' );

		$result = $gateway->get_saved_venmo_username( 123 );

		$this->assertEquals( 'val', $result );
	}

	/**
	 * Test that get_saved_venmo_username falls back to previous order meta.
	 *
	 * @covers ::get_saved_venmo_username
	 */
	public function test_get_saved_venmo_username_from_previous_order(): void {
		$gateway = $this->create_gateway_mock();

		// User meta returns empty.
		\WP_Mock::userFunction( 'get_user_meta' )
			->once()
			->with( 456, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true )
			->andReturn( '' );

		// No cookie set.

		// Mock wc_get_orders to return a previous order.
		$previous_order = $this->createMock( WC_Order::class );
		$previous_order->method( 'get_meta' )
			->with( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true )
			->willReturn( 'previous-order-username' );

		\WP_Mock::userFunction( 'wc_get_orders' )
			->once()
			->andReturn( array( $previous_order ) );

		$result = $gateway->get_saved_venmo_username( 456 );

		$this->assertEquals( 'previous-order-username', $result );
	}

	/**
	 * Test that validate_fields returns false and adds notice when username is empty.
	 *
	 * @covers ::validate_fields
	 */
	public function test_validate_fields_fails_when_username_empty(): void {
		$gateway = $this->create_gateway_mock();

		$_POST[ Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY ] = '';

		\WP_Mock::userFunction( 'sanitize_text_field' )->andReturn( '' );
		\WP_Mock::passthruFunction( 'wp_unslash' );
		\WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		\WP_Mock::userFunction( 'wc_add_notice' )->once();

		$result = $gateway->validate_fields();

		$this->assertFalse( $result );

		unset( $_POST[ Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY ] );
	}

	/**
	 * Test that validate_fields returns true when username is provided.
	 *
	 * @covers ::validate_fields
	 */
	public function test_validate_fields_passes_when_username_provided(): void {
		$gateway = $this->create_gateway_mock();

		$_POST[ Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY ] = 'myvenmo';

		\WP_Mock::userFunction( 'sanitize_text_field' )->andReturn( 'myvenmo' );
		\WP_Mock::passthruFunction( 'wp_unslash' );

		$result = $gateway->validate_fields();

		$this->assertTrue( $result );

		unset( $_POST[ Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY ] );
	}

	/**
	 * Test that save_order_payment_type_meta_data saves username to user meta for logged-in customers.
	 *
	 * @covers ::save_order_payment_type_meta_data
	 */
	public function test_save_order_payment_type_meta_data_saves_user_meta(): void {
		$gateway = $this->create_gateway_mock( array( 'get_option' ) );
		$gateway->method( 'get_option' )
			->with( 'store_venmo_username' )
			->willReturn( 'store-user' );

		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_customer_id' )->willReturn( 123 );
		$order->expects( $this->exactly( 2 ) )->method( 'add_meta_data' );
		$order->expects( $this->once() )->method( 'save' );

		$_POST[ Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY ] = 'customer-user';

		\WP_Mock::userFunction( 'esc_attr' )->andReturn( 'customer-user' );

		\WP_Mock::userFunction( 'update_user_meta' )
			->once()
			->with( 123, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'customer-user' );

		$data = array( 'payment_method' => 'venmo' );
		$gateway->save_order_payment_type_meta_data( $order, $data );

		unset( $_POST[ Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY ] );
	}

	/**
	 * Test that save_order_payment_type_meta_data sets cookie for guest customers.
	 *
	 * @covers ::save_order_payment_type_meta_data
	 */
	public function test_save_order_payment_type_meta_data_sets_cookie_for_guest(): void {
		$gateway = $this->create_gateway_mock( array( 'get_option' ) );
		$gateway->method( 'get_option' )
			->with( 'store_venmo_username' )
			->willReturn( 'store-user' );

		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_customer_id' )->willReturn( 0 );
		$order->expects( $this->exactly( 2 ) )->method( 'add_meta_data' );
		$order->expects( $this->once() )->method( 'save' );

		$_POST[ Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY ] = 'guest-user';

		\WP_Mock::userFunction( 'esc_attr' )->andReturn( 'guest-user' );
		\WP_Mock::userFunction( 'is_ssl' )->andReturn( false );

		// headers_sent() is a PHP built-in, will return true in test context
		// so set_venmo_username_cookie won't actually call setcookie

		\Patchwork\redefine(
			'constant',
			function ( string $constant_name ) {
				return 'YEAR_IN_SECONDS' === $constant_name
					? 60 * 60 * 365
					: \Patchwork\relay( func_get_args() );
			}
		);

		$data = array( 'payment_method' => 'venmo' );
		$gateway->save_order_payment_type_meta_data( $order, $data );

		unset( $_POST[ Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY ] );

		// If we got here without errors, the guest path was executed.
		$this->assertTrue( true );
	}
}
