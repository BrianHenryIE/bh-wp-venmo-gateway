<?php
/**
 * The admin order screen links the transaction id to the transaction on venmo.com.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Venmo_Gateway\WPUnit_Testcase;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Venmo_Gateway
 */
class Venmo_Gateway_Transaction_URL_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * The url bh-wp-order-email-reconcile records when the payment email is matched, prefixed with the gateway id.
	 *
	 * @covers ::get_transaction_url
	 */
	public function test_returns_the_reconciled_transaction_url(): void {
		$order = new WC_Order();
		$order->set_transaction_id( '4242424242424242424' );
		$order->update_meta_data( ( new Venmo_Gateway() )->id . '_transaction_url', 'https://venmo.com/story/4242424242424242424' );
		$order->save();

		$this->assertSame( 'https://venmo.com/story/4242424242424242424', ( new Venmo_Gateway() )->get_transaction_url( $order ) );
	}

	/**
	 * Without a recorded url there is nothing to link to (WooCommerce then prints the id as plain text).
	 *
	 * @covers ::get_transaction_url
	 */
	public function test_returns_empty_string_when_no_url_was_recorded(): void {
		$order = new WC_Order();
		$order->set_transaction_id( '4242424242424242424' );
		$order->save();

		$this->assertSame( '', ( new Venmo_Gateway() )->get_transaction_url( $order ) );
	}

	/**
	 * Meta from before the keys were prefixed is not used.
	 *
	 * @covers ::get_transaction_url
	 */
	public function test_ignores_unprefixed_legacy_meta(): void {
		$order = new WC_Order();
		$order->update_meta_data( 'transaction_id_href', 'https://venmo.com/story/1' );
		$order->update_meta_data( 'transaction_url', 'https://venmo.com/story/1' );
		$order->save();

		$this->assertSame( '', ( new Venmo_Gateway() )->get_transaction_url( $order ) );
	}
}
