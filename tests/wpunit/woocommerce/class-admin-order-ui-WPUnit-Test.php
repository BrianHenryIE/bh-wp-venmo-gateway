<?php
/**
 * WP unit tests for the Admin_Order_UI metabox.
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WC_Venmo_Gateway\WPUnit_Testcase;
use WC_Order;
use WC_Payment_Gateways;

/**
 * @coversDefaultClass \BrianHenryIE\WC_Venmo_Gateway\WooCommerce\Admin_Order_UI
 */
class Admin_Order_UI_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * @covers ::add_venmo_payment_metabox
	 */
	public function test_add_venmo_payment_metabox_registers_metabox_for_shop_order_screen(): void {
		global $wp_meta_boxes;

		$sut = new Admin_Order_UI();
		$sut->add_venmo_payment_metabox();

		$this->assertArrayHasKey( 'bh-wc-venmo-payment', $wp_meta_boxes['shop_order']['side']['high'] );
	}

	/**
	 * @covers ::add_venmo_payment_metabox
	 */
	public function test_add_venmo_payment_metabox_registers_metabox_for_hpos_screen(): void {
		global $wp_meta_boxes;

		$sut = new Admin_Order_UI();
		$sut->add_venmo_payment_metabox();

		$this->assertArrayHasKey( 'bh-wc-venmo-payment', $wp_meta_boxes['woocommerce_page_wc-orders']['side']['high'] );
	}

	/**
	 * Rendering should produce no output for non-Venmo orders.
	 *
	 * @covers ::render_venmo_payment_metabox
	 */
	public function test_render_venmo_payment_metabox_outputs_nothing_for_non_venmo_order(): void {
		$order = new WC_Order();
		$order->set_payment_method( 'cheque' );
		$order->save();

		$sut = new Admin_Order_UI();

		ob_start();
		$sut->render_venmo_payment_metabox( $order );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * When the order has no store Venmo username meta, a fallback message is shown.
	 *
	 * @covers ::render_venmo_payment_metabox
	 */
	public function test_render_venmo_payment_metabox_shows_fallback_when_no_store_username(): void {

		// Register a minimal Venmo gateway so payment method validation passes.
		add_filter(
			'woocommerce_payment_gateways',
			function ( array $gateways ): array {
				$gateways[] = Venmo_Gateway::class;
				return $gateways;
			}
		);
		WC_Payment_Gateways::instance()->init();

		$order = new WC_Order();
		$order->set_payment_method( 'venmo' );
		$order->save();

		$sut = new Admin_Order_UI();

		ob_start();
		$sut->render_venmo_payment_metabox( $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No store Venmo username recorded', $output );
	}

	/**
	 * When the order has valid Venmo meta, the QR code image and payment link are rendered.
	 *
	 * @covers ::render_venmo_payment_metabox
	 */
	public function test_render_venmo_payment_metabox_outputs_qr_code_and_payment_link(): void {

		add_filter(
			'woocommerce_payment_gateways',
			function ( array $gateways ): array {
				$gateways[] = Venmo_Gateway::class;
				return $gateways;
			}
		);
		WC_Payment_Gateways::instance()->init();

		$order = new WC_Order();
		$order->set_payment_method( 'venmo' );
		$order->add_meta_data( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'testcustomer', true );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'teststore', true );
		$order->save();

		$sut = new Admin_Order_UI();

		ob_start();
		$sut->render_venmo_payment_metabox( $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'https://venmo.com/teststore', $output );
		$this->assertStringContainsString( 'data:image/', $output );
		$this->assertStringContainsString( '@testcustomer', $output );
		$this->assertStringContainsString( '@teststore', $output );
	}

	/**
	 * The payment URL should include the order ID in the note parameter.
	 *
	 * @covers ::render_venmo_payment_metabox
	 */
	public function test_render_venmo_payment_metabox_payment_url_contains_order_id(): void {

		add_filter(
			'woocommerce_payment_gateways',
			function ( array $gateways ): array {
				$gateways[] = Venmo_Gateway::class;
				return $gateways;
			}
		);
		WC_Payment_Gateways::instance()->init();

		$order = new WC_Order();
		$order->set_payment_method( 'venmo' );
		$order->add_meta_data( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, 'testcustomer', true );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'teststore', true );
		$order_id = $order->save();

		$sut = new Admin_Order_UI();

		ob_start();
		$sut->render_venmo_payment_metabox( $order );
		$output = ob_get_clean();

		$expected_note = rawurlencode( 'order ' . $order_id );
		$this->assertStringContainsString( $expected_note, $output );
	}
}
