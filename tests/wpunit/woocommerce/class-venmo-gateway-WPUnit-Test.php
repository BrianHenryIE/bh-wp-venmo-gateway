<?php
/**
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WP_Venmo_Gateway\WPUnit_Testcase;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\WooCommerce\Venmo_Gateway
 */
class Venmo_Gateway_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * @covers ::process_payment
	 */
	public function test_process_payment_adds_payment_link_comment_to_order(): void {
		$sut = new Venmo_Gateway();

		$order = new WC_Order();
		$order->add_meta_data( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'brianhenryie', true );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'sackavs', true );
		$order_id = $order->save();

		// ACT.
		$sut->process_payment( $order_id );

		$order = wc_get_order( $order );

		$this->assertEquals( 'on-hold', $order->get_status() );

		$notes        = wc_get_order_notes( array( 'order_id' => $order_id ) );
		$comment_html = $notes[0]->content;

		$expected_payment_link = "https://venmo.com/sackavs?txn=pay&amount=0.00&note=order%20{$order_id}";

		$this->assertStringContainsString( $expected_payment_link, $comment_html );
	}
}
