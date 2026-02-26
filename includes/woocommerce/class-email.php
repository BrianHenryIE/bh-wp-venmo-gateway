<?php
/**
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WC_Venmo_Gateway\chillerlan\QRCode\QRCode;
use BrianHenryIE\WC_Venmo_Gateway\chillerlan\QRCode\QROptions;
use WC_Order;
use WC_Payment_Gateways;

class Email {

	// TODO: Don't send order received email unless the order has not been paid in 60 minutes.

	/**
	 * Adds instructions to the order confirmation emails.
	 *
	 * This runs for every order, on every email (received, complete, etc.).
	 *
	 * @hooked woocommerce_email_before_order_table
	 *
	 * @param WC_Order $order
	 * @param bool     $sent_to_admin
	 * @param bool     $plain_text
	 */
	public function email_instructions( WC_Order $order, bool $sent_to_admin, bool $plain_text = false ): void {

		$payment_gateways = WC_Payment_Gateways::instance()->payment_gateways();

		if ( ! isset( $payment_gateways[ $order->get_payment_method() ] ) ) {
			return;
		}

		$payment_gateway_instance = $payment_gateways[ $order->get_payment_method() ];

		if ( ! ( $payment_gateway_instance instanceof Venmo_Gateway ) ) {
			return;
		}

		$store_venmo_username = $payment_gateway_instance->get_option( 'store_venmo_username' );

		if ( empty( $store_venmo_username ) ) {
			return;
		}

		$payment_url_helper   = new Venmo_Payment_Url( $order );
		$venmo_payment_url    = $payment_url_helper->get_browser_url();
		$venmo_payment_qr_url = $payment_url_helper->get_qr_url();

		$qr_options          = new class() extends QROptions {
			protected int $quietzoneSize = 1;
		};
		$venmo_image_url     = plugins_url( 'assets/woocommerce/images/venmo-logo-25.png', 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php' );
		$qr_code_data_base64 = ( new QRCode( $qr_options ) )->render( $venmo_payment_qr_url );

		// Your order has been received.

		$instructions = "<p>Please send payment of \${$order->get_total()} via Venmo to <a href=\"{$venmo_payment_url}\">@{$store_venmo_username}</a></p>";

		$instructions .= "<p>Please pay the precise amount – <b> \${$order->get_total()}</b> and include the order number – <b>{$order->get_id()}</b> in the note.</p>";

		// Venmo logo image.
		$instructions .= "<p><a href=\"{$venmo_payment_url}\"><img src=\"{$venmo_image_url}\" /></a></p>";

		// QR Code.
		$instructions .= "<p><a href=\"{$venmo_payment_qr_url}\"><img style=\"display:block; max-width: 90vw; max-height: 500px;\" src=\"{$qr_code_data_base64}\" alt=\"Payment QR code\" /></a></p>";

		$instructions .= "<p><a href=\"{$venmo_payment_url}\">Open Venmo</a></p>";

		// TODO: escape output.
		if ( $instructions && ! $sent_to_admin && $order->has_status( 'on-hold' ) ) {
			echo wptexturize( $instructions ) . PHP_EOL;
		}
	}
}
