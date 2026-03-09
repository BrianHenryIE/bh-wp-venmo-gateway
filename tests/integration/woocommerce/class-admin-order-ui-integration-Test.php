<?php
/**
 * Integration/e2e tests for the Admin_Order_UI metabox.
 *
 * These tests run with WooCommerce fully activated, verifying the full plugin
 * integration: hook registration, metabox presence, and output on a real order.
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WC_Venmo_Gateway\WPUnit_Testcase;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WC_Venmo_Gateway\WooCommerce\Admin_Order_UI
 */
class Admin_Order_UI_Integration_Test extends WPUnit_Testcase {

	/**
	 * Confirms that `add_meta_boxes` has a callback registered by the plugin for the metabox.
	 *
	 * @covers ::add_venmo_payment_metabox
	 */
	public function test_add_meta_boxes_hook_is_registered(): void {
		$this->assertGreaterThan(
			0,
			has_action( 'add_meta_boxes', array( new Admin_Order_UI(), 'add_venmo_payment_metabox' ) )
		);
	}

	/**
	 * Confirms the metabox is registered on the shop_order screen after the hook fires.
	 *
	 * @covers ::add_venmo_payment_metabox
	 */
	public function test_metabox_registered_on_shop_order_screen(): void {
		global $wp_meta_boxes;

		do_action( 'add_meta_boxes' );

		$this->assertArrayHasKey( 'shop_order', $wp_meta_boxes );
		$this->assertArrayHasKey( 'bh-wc-venmo-payment', $wp_meta_boxes['shop_order']['side']['high'] );
	}

	/**
	 * Confirms the metabox is registered on the HPOS (wc-orders) screen.
	 *
	 * @covers ::add_venmo_payment_metabox
	 */
	public function test_metabox_registered_on_hpos_screen(): void {
		global $wp_meta_boxes;

		do_action( 'add_meta_boxes' );

		$this->assertArrayHasKey( 'woocommerce_page_wc-orders', $wp_meta_boxes );
		$this->assertArrayHasKey( 'bh-wc-venmo-payment', $wp_meta_boxes['woocommerce_page_wc-orders']['side']['high'] );
	}

	/**
	 * With a real Venmo order, the rendered metabox contains all expected elements:
	 * - customer Venmo @username
	 * - store Venmo @username
	 * - Venmo payment URL
	 * - QR code data URI image
	 *
	 * @covers ::render_venmo_payment_metabox
	 */
	public function test_render_outputs_payment_details_for_venmo_order(): void {

		$order = new WC_Order();
		$order->set_payment_method( 'venmo' );
		$order->add_meta_data( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'integrationcustomer', true );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'integrationstore', true );
		$order->save();

		$sut = new Admin_Order_UI();

		ob_start();
		$sut->render_venmo_payment_metabox( $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'integrationstore', $output, 'Store username should appear in metabox output.' );
		$this->assertStringContainsString( 'integrationcustomer', $output, 'Customer username should appear in metabox output.' );
		$this->assertStringContainsString( 'https://venmo.com/integrationstore', $output, 'Payment URL should appear in metabox output.' );
		$this->assertStringContainsString( 'data:image/', $output, 'QR code data URI should appear in metabox output.' );
	}

	/**
	 * The payment URL embedded in the metabox encodes the order amount and note correctly.
	 *
	 * @covers ::render_venmo_payment_metabox
	 */
	public function test_render_payment_url_encodes_amount_and_note(): void {

		$order = new WC_Order();
		$order->set_payment_method( 'venmo' );
		$order->set_total( '42.00' );
		$order->add_meta_data( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'acustomer', true );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'astore', true );
		$order_id = $order->save();

		$sut = new Admin_Order_UI();

		ob_start();
		$sut->render_venmo_payment_metabox( $order );
		$output = ob_get_clean();

		$expected_url = sprintf(
			'https://venmo.com/%s?txn=pay&amount=%s&note=%s',
			rawurlencode( 'astore' ),
			rawurlencode( '42.00' ),
			rawurlencode( 'order ' . $order_id )
		);

		// Expected: `<a href="https://venmo.com/astore?txn=pay&#038;amount=42.00&#038;note=order%2011">`.
		// Actual: `<a href="https://venmo.com/astore?txn=pay&#038;amount=42.00&#038;note=order%2011">`.
		$this->assertStringContainsString( $expected_url, $output );
	}

	/**
	 * When the payment method is not Venmo the metabox renders nothing.
	 *
	 * @covers ::render_venmo_payment_metabox
	 */
	public function test_render_outputs_nothing_for_non_venmo_order(): void {

		$order = new WC_Order();
		$order->set_payment_method( 'bacs' );
		$order->save();

		$sut = new Admin_Order_UI();

		ob_start();
		$sut->render_venmo_payment_metabox( $order );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * A WP_Post object (legacy order) is also accepted by the render callback.
	 *
	 * @covers ::render_venmo_payment_metabox
	 */
	public function test_render_accepts_wp_post_for_legacy_order_screen(): void {

		$order = new WC_Order();
		$order->set_payment_method( 'venmo' );
		$order->add_meta_data( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'postcustomer', true );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'poststore', true );
		$order_id = $order->save();

		// Simulate legacy post object passed by add_meta_box callback.
		$post = get_post( $order_id );

		if ( is_null( $post ) ) {
			$this->markTestSkipped( 'Could not retrieve post for order – HPOS may be active without CPT storage.' );
		}

		$sut = new Admin_Order_UI();

		ob_start();
		$sut->render_venmo_payment_metabox( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'poststore', $output );
	}
}
