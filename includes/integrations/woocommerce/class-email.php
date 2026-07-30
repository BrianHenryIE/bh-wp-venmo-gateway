<?php
/**
 * Add instructions to the customer on-hold email.
 *
 * Inline images do not display in gmail. Potential fix at:
 *
 * @see https://gist.github.com/thomasfw/5df1a041fd8f9c939ef9d88d887ce023
 * @see https://stackoverflow.com/questions/9110091/base64-encoded-images-in-email-signatures/9110164#9110164
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Venmo_Gateway\chillerlan\QRCode\Output\QROutputInterface;
use BrianHenryIE\WP_Venmo_Gateway\QR\QR_Code;
use WC_Order;
use WC_Payment_Gateways;

/**
 * @see wp-content/plugins/woocommerce/templates/emails/email-order-details.php
 * @see wp-content/plugins/woocommerce/templates/emails/plain/email-order-details.php
 */
class Email {

	// TODO: Delay the email so it only sends if they do not pay immediately.

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

		$store_venmo_username    = $order->get_meta( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY );
		$customer_venmo_username = $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY );

		if ( empty( $store_venmo_username ) ) {
			return;
		}

		$payment_url_helper   = new Venmo_Payment_Url( $order );
		$venmo_payment_url    = $payment_url_helper->get_browser_url();
		$venmo_payment_qr_url = $payment_url_helper->get_qr_url();

		$venmo_image_url     = plugins_url( 'assets/woocommerce/images/venmo-logo-25.png', 'bh-wp-venmo-gateway/bh-wp-venmo-gateway.php' );
		$qr_code_data_base64 = ( new QR_Code() )->get_data_uri( $venmo_payment_qr_url, QROutputInterface::GDIMAGE_PNG );

		// Your order has been received.

		$instructions = '';

		// Show from/to usernames if customer username is available
		if ( ! empty( $customer_venmo_username ) ) {
			$instructions .= "<p><strong>Payment from @{$customer_venmo_username} to @{$store_venmo_username}</strong></p>";
		}

		$instructions .= "<p>Please send payment of \${$order->get_total()} via Venmo to <a href=\"{$venmo_payment_url}\">@{$store_venmo_username}</a></p>";

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
