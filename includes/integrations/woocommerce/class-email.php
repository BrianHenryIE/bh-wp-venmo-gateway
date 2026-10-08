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

		if ( $sent_to_admin || ! $order->has_status( 'on-hold' ) ) {
			return;
		}

		// Your order has been received.

		$payment_url_helper   = new Venmo_Payment_Url( $order );
		$venmo_payment_url    = $payment_url_helper->get_browser_url();
		$venmo_payment_qr_url = $payment_url_helper->get_qr_url();

		$venmo_image_url     = plugins_url( 'assets/woocommerce/images/venmo-logo-25.png', 'bh-wp-venmo-gateway/bh-wp-venmo-gateway.php' );
		$qr_code_data_base64 = ( new QR_Code() )->get_data_uri( $venmo_payment_qr_url, QROutputInterface::GDIMAGE_PNG );

		// Show from/to usernames if customer username is available.
		if ( ! empty( $customer_venmo_username ) ) {
			printf(
				'<p><strong>Payment from @%s to @%s</strong></p>' . PHP_EOL,
				esc_html( $customer_venmo_username ),
				esc_html( $store_venmo_username )
			);
		}

		printf(
			'<p>Please send payment of %s via Venmo to <a href="%s">@%s</a></p>' . PHP_EOL . PHP_EOL,
			esc_html( (float) wc_price( $order->get_total() ) ),
			esc_url_raw( $venmo_payment_url ),
			esc_html( $store_venmo_username )
		);

		printf(
			'<p>Please pay the precise amount – <b>%s</b> and include the order number – <b>%d</b> in the note.</p>' . PHP_EOL . PHP_EOL,
			esc_html( (float) wc_price( $order->get_total() ) ),
			absint( $order->get_id() )
		);

		// Venmo logo image.
		printf(
			'<p><a href="%s"><img src="%s" /></a></p>' . PHP_EOL . PHP_EOL,
			esc_url_raw( $venmo_payment_url ),
			esc_url_raw( $venmo_image_url )
		);

		// QR Code.
		printf(
			'<p><a href="%s"><img style="display:block; max-width: 90vw; max-height: 500px;" src="%s" alt="Payment QR code" /></a></p>' . PHP_EOL . PHP_EOL,
			esc_url_raw( $venmo_payment_qr_url, array( 'venmo' ) ),
			esc_url_raw( $qr_code_data_base64, array( 'data' ) )
		);

		printf(
			'<p><a href="%s">Open Venmo</a></p>' . PHP_EOL . PHP_EOL,
			esc_url_raw( $venmo_payment_url )
		);
	}
}
