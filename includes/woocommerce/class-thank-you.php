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
	public function print_instructions( string $thank_you_text, ?WC_Order $order = null ): string {

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

		// Thank you. Your order has been received.

		$customer_venmo_username = $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY );
		$store_venmo_username    = $order->get_meta( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY );

		$order_id = $order->get_id();

		$payment_url_helper   = new Venmo_Payment_Url( $order );
		$venmo_payment_qr_url = $payment_url_helper->get_qr_url();
		$venmo_payment_url    = $payment_url_helper->get_browser_url();

		$qr_options           = new class() extends QROptions {
			protected int $quietzoneSize = 1;
		};
		$venmo_logo_image_url = plugins_url( 'assets/woocommerce/images/venmo-logo-25.png', 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php' );
		$qr_code_data_base64  = ( new QRCode( $qr_options ) )->render( $venmo_payment_qr_url );

		$order_total = " \${$order->get_total()}";

		$instructions = <<<EOD
<br/>

<p><strong>Payment from @{$customer_venmo_username} to @{$store_venmo_username}</strong></p>

<p>Please send payment of <b>{$order_total}</b> via Venmo to <b><a target="_blank" href="{$venmo_payment_url}">@{$store_venmo_username}</b></a>.</p>

<p>Please pay the precise amount and include "<b>order {$order_id}</b>" in the note.</p>

<div style="text-align: center;">

	<div>
		<a target="_blank" href="{$venmo_payment_url}">
			<img src="{$venmo_logo_image_url}" />
		</a>
	</div>

	<div>
		<a target="_blank" href="{$venmo_payment_url}">
			<img style="display:block; max-width: 90vw; max-height: 500px;" src="{$qr_code_data_base64}" alt="Payment QR code" />
		</a>
	</div>

	<div>
		<a target="_blank" href="{$venmo_payment_url}">\${$order->get_total()} to @{$store_venmo_username}</a>
	</div>

</div>
EOD;

		$instructions .= '</p>';

		// Remove the last </p> because it is already contained in the HTML this string will be printed in.
		$instructions = substr( $instructions, 0, -4 );

		return $thank_you_text . '</p>' . wptexturize( $instructions );
	}
}
