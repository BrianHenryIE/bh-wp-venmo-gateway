<?php
/**
 * The SVG image was not displaying in Gmail.
 *
 * @see https://www.codegenes.net/blog/is-there-a-trick-to-display-svg-images-in-gmail/
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce;

use JuicedPlugins\Venmo_Gateway_Pro\WPUnit_Testcase;
use WC_Order;
use WC_Payment_Gateways;

/**
 * @coversDefaultClass \JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Email
 */
class Email_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * @covers ::email_instructions
	 */
	public function test_email_instructions(): void {

		$gateway = new Venmo_Gateway();
		WC_Payment_Gateways::instance()->payment_gateways[] = $gateway;

		$order = new WC_Order();
		$order->set_status( 'on-hold' );
		$order->set_payment_method( $gateway->id );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'brianhenryie', true );
		$order->save();

		$sut = new Email();

		ob_start();

		$sut->email_instructions( $order, false );

		$result = ob_get_flush();

		$this->assertStringNotContainsString( 'data:image/svg+xml;base64', $result );
		$this->assertStringContainsString( 'data:image/png;base64', $result );
	}
}
