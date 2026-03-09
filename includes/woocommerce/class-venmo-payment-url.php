<?php
/**
 * Generate URLs/HREFs releveant to the order's payment username.
 *
 * @package brianhenryie/bh-wc-venmo-gateeway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use WC_Order;

/**
 * Mostly just some `sprintf()` functions
 */
class Venmo_Payment_Url {

	/**
	 * The payment address for the order.
	 */
	protected string $store_venmo_username;

	/**
	 * Given an order, provide functions for URLs relevant to its payments.
	 *
	 * @param WC_Order $order The order, presuming a STORE_VENMO_USERNAME_META_KEY.
	 */
	public function __construct(
		protected WC_Order $order
	) {
		$this->store_venmo_username = $order->get_meta( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY );
	}

	/**
	 * Get the QR code URL – starts with `venmo://` for iOS/Android.
	 *
	 * Template: `venmo://paycharge?txn=pay&recipients=~MYUSERNAME~&note=~PRE-FILLEDCOMMENT~&amount=~PREFILLEDAMOUNT~`.
	 */
	public function get_qr_url(): string {

		$venmo_payment_url = sprintf(
			'venmo://paycharge?txn=pay&recipients=%s&note=%s&amount=%s',
			$this->store_venmo_username,
			rawurlencode( 'order ' . $this->order->get_id() ),
			rawurlencode( $this->order->get_total() ),
		);

		return $venmo_payment_url;
	}

	/**
	 * Get the browser payment URL.
	 *
	 * Template: `https://venmo.com/{username}?txn=pay&amount={amount}&note={note}`.
	 */
	public function get_browser_url(): string {

		$venmo_payment_url = sprintf(
			'https://venmo.com/%s?txn=pay&amount=%s&note=%s',
			rawurlencode( $this->store_venmo_username ),
			rawurlencode( $this->order->get_total() ),
			rawurlencode( 'order ' . $this->order->get_id() )
		);

		return $venmo_payment_url;
	}

	/**
	 * Get the browser payment URL for printing. Returns a string split with `<span>` and `&ZeroWidthSpace;` for line-breaks.
	 */
	public function get_html() {

		$venmo_payment_url_display = sprintf(
			'<span>venmo.com/</span>&ZeroWidthSpace;<span>%s?</span>&ZeroWidthSpace;<span>txn=pay&</span>&ZeroWidthSpace;<span>amount=%s&</span>&ZeroWidthSpace;<span>note=%s</span>',
			rawurlencode( $this->store_venmo_username ),
			rawurlencode( $this->order->get_total() ),
			rawurlencode( 'order ' . $this->order->get_id() )
		);

		return $venmo_payment_url_display;
	}
}
