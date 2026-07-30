<?php
/**
 * Unit tests for Venmo_Gateway::createPayment() — the v3/v2 input precedence,
 * the "@"-stripping on store, and the empty-username guard that the browser's
 * client-side `required` attribute prevents E2E tests from reaching.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;
use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\PaymentPending;
use Mockery;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\Venmo_Gateway
 */
class Venmo_Gateway_Create_Payment_Test extends Unit_TestCase {

	/**
	 * Instantiate without the PaymentGateway constructor, which builds a Webhook
	 * and needs a fuller environment. createPayment() does not use constructor state.
	 */
	private function gateway(): Venmo_Gateway {
		return ( new \ReflectionClass( Venmo_Gateway::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * A Donation whose only accessed property is `id`.
	 *
	 * Give models resolve `$donation->id` through the magic __get, so stub that
	 * rather than assigning the property (which would route through Give's __set).
	 *
	 * @param int $id The donation id to expose as $donation->id.
	 * @return Donation&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function donation( int $id = 42 ) {
		$donation = $this->createMock( Donation::class );
		$donation->method( '__get' )->willReturn( $id );
		return $donation;
	}

	/**
	 * @covers ::id
	 */
	public function test_id_is_venmo(): void {
		$this->assertSame( 'venmo', Venmo_Gateway::id() );
	}

	/**
	 * A v3 form passes the donor username via $gatewayData; a leading "@" is
	 * stripped before storage and the donation is created pending.
	 *
	 * @covers ::createPayment
	 */
	public function test_v3_username_stored_bare_and_returns_pending(): void {
		\WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		\WP_Mock::userFunction( 'give_get_option' )->with( 'venmo_store_username', '' )->andReturn( 'storevendor' );

		\WP_Mock::userFunction( 'give_update_meta' )
			->once()
			->with( 42, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'donor' );
		\WP_Mock::userFunction( 'give_update_meta' )
			->once()
			->with( 42, Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'storevendor' );

		$result = $this->gateway()->createPayment( $this->donation(), array( 'venmoUsername' => '@donor' ) );

		$this->assertInstanceOf( PaymentPending::class, $result );
	}

	/**
	 * A v2 (legacy) form has no $gatewayData; the username comes from $_POST.
	 *
	 * @covers ::createPayment
	 */
	public function test_v2_username_read_from_post(): void {
		\WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		\WP_Mock::passthruFunction( 'wp_unslash' );
		\WP_Mock::userFunction( 'give_get_option' )->with( 'venmo_store_username', '' )->andReturn( 'storevendor' );

		\WP_Mock::userFunction( 'give_update_meta' )
			->once()
			->with( 42, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'frompost' );
		\WP_Mock::userFunction( 'give_update_meta' )
			->once()
			->with( 42, Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'storevendor' );

		$_POST['venmo_username'] = '@frompost';

		$this->gateway()->createPayment( $this->donation(), array() );

		unset( $_POST['venmo_username'] );
	}

	/**
	 * When both are present, $gatewayData (v3) wins over $_POST (v2).
	 *
	 * @covers ::createPayment
	 */
	public function test_gateway_data_takes_precedence_over_post(): void {
		\WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		\WP_Mock::passthruFunction( 'wp_unslash' );
		\WP_Mock::userFunction( 'give_get_option' )->with( 'venmo_store_username', '' )->andReturn( 'storevendor' );

		\WP_Mock::userFunction( 'give_update_meta' )
			->once()
			->with( 42, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'fromdata' );
		\WP_Mock::userFunction( 'give_update_meta' )
			->with( 42, Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'storevendor' );

		$_POST['venmo_username'] = '@frompost';

		$this->gateway()->createPayment( $this->donation(), array( 'venmoUsername' => '@fromdata' ) );

		unset( $_POST['venmo_username'] );
	}

	/**
	 * With no username from either source, the customer meta is not written but
	 * the donation is still created pending.
	 *
	 * @covers ::createPayment
	 */
	public function test_empty_username_does_not_write_customer_meta(): void {
		\WP_Mock::userFunction( 'give_get_option' )->with( 'venmo_store_username', '' )->andReturn( 'storevendor' );

		\WP_Mock::userFunction( 'give_update_meta' )
			->with( 42, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, Mockery::any() )
			->never();
		\WP_Mock::userFunction( 'give_update_meta' )
			->once()
			->with( 42, Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'storevendor' );

		$result = $this->gateway()->createPayment( $this->donation(), array() );

		$this->assertInstanceOf( PaymentPending::class, $result );
	}

	/**
	 * With no store username configured, the store meta is not written.
	 *
	 * @covers ::createPayment
	 */
	public function test_empty_store_username_does_not_write_store_meta(): void {
		\WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		\WP_Mock::userFunction( 'give_get_option' )->with( 'venmo_store_username', '' )->andReturn( '' );

		\WP_Mock::userFunction( 'give_update_meta' )
			->once()
			->with( 42, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'donor' );
		\WP_Mock::userFunction( 'give_update_meta' )
			->with( 42, Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, Mockery::any() )
			->never();

		$this->gateway()->createPayment( $this->donation(), array( 'venmoUsername' => 'donor' ) );
	}
}
