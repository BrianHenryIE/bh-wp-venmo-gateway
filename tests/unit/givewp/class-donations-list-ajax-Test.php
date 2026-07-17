<?php
/**
 * Unit tests for Donations_List::ajax_mark_paid() — the capability, gateway,
 * pending-status and status-update guard branches that the E2E happy-path test
 * cannot reach.
 *
 * The wp_send_json_error()/wp_send_json_success() calls terminate the request in
 * production (they call wp_die()); here they are mocked to throw so the branch
 * that was taken can be asserted and execution stops as it would live.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

require_once __DIR__ . '/class-json-response-exception.php';

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\Donations_List
 */
class Donations_List_Ajax_Test extends Unit_TestCase {

	protected function setup(): void {
		parent::setup();

		\WP_Mock::userFunction( 'check_ajax_referer' )->andReturn( true );
		\WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		\WP_Mock::passthruFunction( 'wp_unslash' );
		\WP_Mock::userFunction( 'absint' )->andReturnUsing( fn( $value ) => (int) $value );
		\WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );

		\WP_Mock::userFunction( 'wp_send_json_error' )->andReturnUsing(
			function ( $data = null, $status = null ): void {
				throw new Json_Response_Exception( false, $data, $status );
			}
		);
		\WP_Mock::userFunction( 'wp_send_json_success' )->andReturnUsing(
			function ( $data = null, $status = null ): void {
				throw new Json_Response_Exception( true, $data, $status );
			}
		);
	}

	protected function tearDown(): void {
		unset( $_POST['donation_id'], $_POST['venmo_username'], $_POST['transaction_id'], $_POST['payment_date'], $_POST['payment_time'] );
		parent::tearDown();
	}

	/**
	 * @return Json_Response_Exception
	 */
	private function run_and_capture(): Json_Response_Exception {
		try {
			( new Donations_List() )->ajax_mark_paid();
		} catch ( Json_Response_Exception $e ) {
			return $e;
		}
		$this->fail( 'Expected a JSON response to terminate ajax_mark_paid().' );
	}

	/**
	 * @covers ::ajax_mark_paid
	 */
	public function test_without_capability_returns_403(): void {
		\WP_Mock::userFunction( 'current_user_can' )->with( 'edit_give_payments' )->andReturn( false );

		$response = $this->run_and_capture();

		$this->assertFalse( $response->success );
		$this->assertSame( 403, $response->status );
	}

	/**
	 * @covers ::ajax_mark_paid
	 */
	public function test_wrong_gateway_returns_404(): void {
		\WP_Mock::userFunction( 'current_user_can' )->andReturn( true );
		\WP_Mock::userFunction( 'give_get_payment_gateway' )->with( 55 )->andReturn( 'paypal' );

		$_POST['donation_id'] = '55';

		$response = $this->run_and_capture();

		$this->assertFalse( $response->success );
		$this->assertSame( 404, $response->status );
	}

	/**
	 * @covers ::ajax_mark_paid
	 */
	public function test_non_pending_donation_returns_400(): void {
		\WP_Mock::userFunction( 'current_user_can' )->andReturn( true );
		\WP_Mock::userFunction( 'give_get_payment_gateway' )->with( 55 )->andReturn( 'venmo' );
		\WP_Mock::userFunction( 'give_get_payment_status' )->with( 55 )->andReturn( 'publish' );

		$_POST['donation_id'] = '55';

		$response = $this->run_and_capture();

		$this->assertFalse( $response->success );
		$this->assertSame( 400, $response->status );
	}

	/**
	 * @covers ::ajax_mark_paid
	 */
	public function test_failed_status_update_returns_500(): void {
		\WP_Mock::userFunction( 'current_user_can' )->andReturn( true );
		\WP_Mock::userFunction( 'give_get_payment_gateway' )->andReturn( 'venmo' );
		\WP_Mock::userFunction( 'give_get_payment_status' )->andReturn( 'pending' );
		\WP_Mock::userFunction( 'give_update_meta' );
		\WP_Mock::userFunction( 'give_insert_payment_note' );
		\WP_Mock::userFunction( 'give_update_payment_status' )->with( 55, 'publish' )->andReturn( false );

		$_POST['donation_id'] = '55';

		$response = $this->run_and_capture();

		$this->assertFalse( $response->success );
		$this->assertSame( 500, $response->status );
	}

	/**
	 * @covers ::ajax_mark_paid
	 */
	public function test_successful_mark_paid_completes_the_donation(): void {
		\WP_Mock::userFunction( 'current_user_can' )->andReturn( true );
		\WP_Mock::userFunction( 'give_get_payment_gateway' )->andReturn( 'venmo' );
		\WP_Mock::userFunction( 'give_get_payment_status' )->andReturn( 'pending' );
		\WP_Mock::userFunction( 'give_insert_payment_note' )->once();

		// The recorded payment details are written as meta.
		\WP_Mock::userFunction( 'give_update_meta' )
			->once()
			->with( 55, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'donor' );
		\WP_Mock::userFunction( 'give_update_meta' )
			->once()
			->with( 55, Venmo_Gateway::VENMO_TRANSACTION_ID_META_KEY, 'TXN1' );

		\WP_Mock::userFunction( 'give_update_payment_status' )
			->once()
			->with( 55, 'publish' )
			->andReturn( true );

		$_POST['donation_id']    = '55';
		$_POST['venmo_username'] = '@donor';
		$_POST['transaction_id'] = 'TXN1';

		$response = $this->run_and_capture();

		$this->assertTrue( $response->success );
	}
}
