<?php
/**
 * Instructions shown on the Thank You page immediately after the order is created.
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WC_Venmo_Gateway\chillerlan\QRCode\QRCode;
use BrianHenryIE\WC_Venmo_Gateway\chillerlan\QRCode\QROptions;
use WC_Order;
use WC_Payment_Gateways;

class Thank_You {


	public function __construct() {
		add_action( 'init', array( $this, 'allow_data_protocol_for_inline_qr_code' ) );
	}

	/**
	 * @hooked init
	 * @see wp_kses()
	 * @see wp_allowed_protocols()
	 * Must be hooked before wp_loaded.
	 */
	public function allow_data_protocol_for_inline_qr_code() {
		if ( ! $this->is_thank_you_order_confirmation_page() ) {
			return;
		}

		add_filter(
			'kses_allowed_protocols',
			fn( array $protocols ): array => array_merge( $protocols, array( 'data' ) )
		);
	}

	/**
	 * Check for `/order-received/` in the URL.
	 * TODO: How to confirm it without permalinks.
	 */
	protected function is_thank_you_order_confirmation_page(): bool {

		return isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) &&
			str_contains( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/order-received/' );
	}

	/**
	 * Prints the HTML on the Thank You (post checkout) page.
	 *
	 * This hook is above the order details table.
	 *
	 * @hooked woocommerce_thankyou_order_received_text
	 *
	 * @param string $thank_you_text "Your order has been received".
	 * @param mixed  $order_id
	 * @return string
	 */
	public function print_instructions( string $thank_you_text, ?WC_Order $order_id = null ): string {

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			return $thank_you_text;
		}

		$payment_gateways = WC_Payment_Gateways::instance()->payment_gateways();

		if ( ! isset( $payment_gateways[ $order->get_payment_method() ] ) ) {
			return $thank_you_text;
		}

		$payment_gateway_instance = $payment_gateways[ $order->get_payment_method() ];

		if ( ! ( $payment_gateway_instance instanceof Venmo_Gateway ) ) {
			return $thank_you_text;
		}

		$customer_venmo_username = $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY );
		$store_venmo_username    = $order->get_meta( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY );

		// Thank you. Your order has been received.

		$instructions = '<br/>';

		/**
		 * Template: `https://venmo.com/{username}?txn=pay&amount={amount}&note={note}`.
		 */
		$venmo_url = sprintf(
			'https://venmo.com/%s?txn=pay&amount=%s&note=%s',
			rawurlencode( $store_venmo_username ),
			rawurlencode( $order->get_total() ),
			rawurlencode( $order->get_id() )
		);

		$instructions .= "<p>Please send payment of <b>{$order->get_formatted_order_total()}</b> via Venmo to <b><a href=\"{$venmo_url}\">@{$store_venmo_username}</b></a></p>";

		$instructions .= "<p>Please include the order number – <b>{$order->get_id()}</b> – in the message.</p>";
		// $instructions .= "<p>* Pay the precise amount – <b>{$order->get_formatted_order_total()}</b> – so the payment can be automatically matched to the order.";

		$instructions .= '<div style="text-align: center;">';

		$instructions   .= '<div>';
		$instructions   .= "<a href=\"{$venmo_url}\">";
		$venmo_image_url = plugins_url( 'assets/woocommerce/images/venmo-logo-25.png', 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php' );
		$instructions   .= "<img src=\"{$venmo_image_url}\" />";
		$instructions   .= '</a>';
		$instructions   .= '</div>';

		$instructions .= '<div>';
		$instructions .= "<a href=\"{$venmo_url}\">";
		// Reduce the paddding around the QR code.
		$qr_options    = new class() extends QROptions {
			protected int $quietzoneSize = 1;
		};
		$instructions .= '<img style="display:block; max-width: 90vw; max-height: 500px;" src="' . ( new QRCode( $qr_options ) )->render( $venmo_url ) . '" alt="Payment QR code" />';
		$instructions .= '</a>';
		$instructions .= '</div>';

		$instructions .= '<div>';
		$instructions .= "<a href=\"{$venmo_url}\">{$order->get_formatted_order_total()} to @{$store_venmo_username}</a>";
		$instructions .= '</div>';

		$instructions .= '</div>';

		$instructions .= '</p>';

		// Remove the last </p> because it is already contained in the HTML this string will be printed in.
		$instructions = substr( $instructions, 0, -4 );

		return $thank_you_text . '</p>' . wptexturize( $instructions );
	}
}
