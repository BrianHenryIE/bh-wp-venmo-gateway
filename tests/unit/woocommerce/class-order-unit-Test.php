<?php
/**
 * Scheduling and running the background fetch of the customer's Venmo profile name.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Venmo_Gateway\API\API_Interface;
use BrianHenryIE\WP_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\API\Venmo_Profile;
use BrianHenryIE\WP_Venmo_Gateway\Includes\Cron;
use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;
use Mockery;
use Mockery\MockInterface;
use WC_Order;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Order
 */
class Order_Unit_Test extends Unit_Testcase {

	/**
	 * A Venmo order whose customer entered `$username` at checkout.
	 *
	 * @param string $username     The customer's Venmo username meta.
	 * @param string $display_name The already-recorded display name meta, if any.
	 *
	 * @return WC_Order&MockInterface
	 */
	protected function mock_venmo_order( string $username, string $display_name = '' ): WC_Order {
		/** @var WC_Order&MockInterface $order */
		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'venmo' );
		$order->shouldReceive( 'get_meta' )->with( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true )->andReturn( $username );
		$order->shouldReceive( 'get_meta' )->with( Venmo_Gateway::CUSTOMER_VENMO_DISPLAY_NAME_META_KEY, true )->andReturn( $display_name );
		return $order;
	}

	protected function get_settings(): Settings_Interface {
		/** @var Settings_Interface&MockInterface $settings */
		$settings = Mockery::mock( Settings_Interface::class );
		$settings->shouldReceive( 'get_payment_method_ids' )->andReturn( array( 'venmo' ) );
		return $settings;
	}

	/**
	 * @covers ::schedule_fetch_customer_venmo_profile
	 * @covers ::__construct
	 */
	public function test_schedules_single_event_when_venmo_order_goes_on_hold(): void {
		WP_Mock::userFunction( 'wc_get_order' )->with( 123 )->andReturn( $this->mock_venmo_order( 'brianhenryie' ) );
		WP_Mock::userFunction( 'wp_next_scheduled' )->with( Cron::FETCH_CUSTOMER_VENMO_PROFILE_CRON_HOOK, array( 123 ) )->andReturn( false );
		WP_Mock::userFunction( 'wp_schedule_single_event' )
			->once()
			->with( WP_Mock\Functions::type( 'int' ), Cron::FETCH_CUSTOMER_VENMO_PROFILE_CRON_HOOK, array( 123 ) )
			->andReturn( true );

		/** @var API_Interface&MockInterface $api */
		$api = Mockery::mock( API_Interface::class );

		$sut = new Order( $api, $this->get_settings(), $this->logger );

		$sut->schedule_fetch_customer_venmo_profile( 123, 'pending', 'on-hold' );
	}

	/**
	 * @return array<string, array{status_to:string, username:string, display_name:string}>
	 */
	public function not_scheduled_provider(): array {
		return array(
			'not on-hold'           => array(
				'status_to'    => 'processing',
				'username'     => 'brianhenryie',
				'display_name' => '',
			),
			'no username'           => array(
				'status_to'    => 'on-hold',
				'username'     => '',
				'display_name' => '',
			),
			'display name is known' => array(
				'status_to'    => 'on-hold',
				'username'     => 'brianhenryie',
				'display_name' => 'Brian Henry',
			),
		);
	}

	/**
	 * @covers ::schedule_fetch_customer_venmo_profile
	 *
	 * @dataProvider not_scheduled_provider
	 *
	 * @param string $status_to    The order's new status.
	 * @param string $username     The customer's Venmo username meta.
	 * @param string $display_name The already-recorded display name meta, if any.
	 */
	public function test_does_not_schedule( string $status_to, string $username, string $display_name ): void {
		WP_Mock::userFunction( 'wc_get_order' )->andReturn( $this->mock_venmo_order( $username, $display_name ) );
		WP_Mock::userFunction( 'wp_next_scheduled' )->andReturn( false );
		WP_Mock::userFunction( 'wp_schedule_single_event' )->never();

		/** @var API_Interface&MockInterface $api */
		$api = Mockery::mock( API_Interface::class );

		$sut = new Order( $api, $this->get_settings(), $this->logger );

		$sut->schedule_fetch_customer_venmo_profile( 123, 'pending', $status_to );
	}

	/**
	 * @covers ::schedule_fetch_customer_venmo_profile
	 */
	public function test_does_not_schedule_twice(): void {
		WP_Mock::userFunction( 'wc_get_order' )->andReturn( $this->mock_venmo_order( 'brianhenryie' ) );
		WP_Mock::userFunction( 'wp_next_scheduled' )->andReturn( time() + 10 );
		WP_Mock::userFunction( 'wp_schedule_single_event' )->never();

		/** @var API_Interface&MockInterface $api */
		$api = Mockery::mock( API_Interface::class );

		$sut = new Order( $api, $this->get_settings(), $this->logger );

		$sut->schedule_fetch_customer_venmo_profile( 123, 'pending', 'on-hold' );
	}

	/**
	 * @covers ::fetch_customer_venmo_profile
	 */
	public function test_fetch_saves_display_name_meta_and_note(): void {
		$order = $this->mock_venmo_order( 'brianhenryie' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( Venmo_Gateway::CUSTOMER_VENMO_DISPLAY_NAME_META_KEY, 'Brian Henry' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Venmo profile @brianhenryie is Brian Henry.' );
		$order->shouldReceive( 'save' )->once();

		WP_Mock::userFunction( 'wc_get_order' )->with( 123 )->andReturn( $order );
		WP_Mock::passthruFunction( '__' );

		/** @var API_Interface&MockInterface $api */
		$api = Mockery::mock( API_Interface::class );
		$api->shouldReceive( 'get_venmo_profile' )->once()->with( 'brianhenryie' )->andReturn( new Venmo_Profile( 'BrianHenryIE', 'Brian Henry', 'Brian', 'Henry' ) );

		$sut = new Order( $api, $this->get_settings(), $this->logger );

		$sut->fetch_customer_venmo_profile( 123 );
	}

	/**
	 * @covers ::fetch_customer_venmo_profile
	 */
	public function test_fetch_adds_note_when_profile_not_found(): void {
		$order = $this->mock_venmo_order( 'nobody' );
		$order->shouldNotReceive( 'update_meta_data' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Could not find a Venmo profile for @nobody.' );

		WP_Mock::userFunction( 'wc_get_order' )->with( 123 )->andReturn( $order );
		WP_Mock::passthruFunction( '__' );

		/** @var API_Interface&MockInterface $api */
		$api = Mockery::mock( API_Interface::class );
		$api->shouldReceive( 'get_venmo_profile' )->once()->with( 'nobody' )->andReturn( null );

		$sut = new Order( $api, $this->get_settings(), $this->logger );

		$sut->fetch_customer_venmo_profile( 123 );
	}
}
