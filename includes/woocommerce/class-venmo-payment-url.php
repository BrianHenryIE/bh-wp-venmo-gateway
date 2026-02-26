<?php

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use WC_Order;

class Venmo_Payment_Url {

	/**
	 * TODO: This information should be taken from the order meta, not via constructor.
	 *
	 * @param string $store_venmo_username
	 * @param string $store_venmo_user_uuid
	 */
	public function __construct(
		protected string $store_venmo_username,
		protected string $store_venmo_user_uuid,
	) {
	}

	public function get_qr_url( WC_Order $order ): string {

		/**
		 * Template: https://www.paypal.com/qrcodes/venmocs/b30b21d4-aa4a-4b1d-be4d-a9136b376d21?amount=76.63&currency_code=USD&created=1753234807.228478
		 */
		$venmo_payment_url = sprintf(
			'https://www.paypal.com/qrcodes/venmocs/%s?amount=%s&currency_code=%s&created=%s',
			$this->store_venmo_user_uuid,
			$order->get_total(),
			'USD', // $order->get_currency()
			$order->get_date_created()->format( 'U.123456' ) // '1753234807.228478',
		);

		/**
		 * venmo://paycharge?txn=pay&recipients=~MYUSERNAME~&note=~PRE-FILLEDCOMMENT~&amount=~PREFILLEDAMOUNT~
		 */
		$venmo_payment_url = sprintf(
			'venmo://paycharge?txn=pay&recipients=%s&note=%s&amount=%s',
			$this->store_venmo_username,
			rawurlencode( 'order ' . $order->get_id() ),
			rawurlencode( $order->get_total() ),
		);

		return $venmo_payment_url;
	}

	public function get_browser_url( WC_Order $order ): string {

		/**
		 * Template: `https://venmo.com/{username}?txn=pay&amount={amount}&note={note}`.
		 */
		$venmo_payment_url = sprintf(
			'https://venmo.com/%s?txn=pay&amount=%s&note=%s',
			rawurlencode( $this->store_venmo_username ),
			rawurlencode( $order->get_total() ),
			rawurlencode( 'order ' . $order->get_id() )
		);

		return $venmo_payment_url;
	}

	public function get_html( WC_Order $order ) {

		/**
		 * Template: https://www.paypal.com/qrcodes/venmocs/b30b21d4-aa4a-4b1d-be4d-a9136b376d21?amount=76.63&currency_code=USD&created=1753234807.228478
		 */
		$venmo_payment_url_display = sprintf(
			'<span>paypal.com/</span>&ZeroWidthSpace;<span>qrcodes/</span>&ZeroWidthSpace;<span>venmocs/</span>&ZeroWidthSpace;<span>%s?</span>&ZeroWidthSpace;<span>amount=%s&</span>&ZeroWidthSpace;<span>currency_code=%s&</span>&ZeroWidthSpace;<span>created=%s</span>',
			$this->store_venmo_user_uuid,
			$order->get_total(),
			'USD', // $order->get_currency()
			$order->get_date_created()->format( 'U.123456' ) // '1753234807.228478',
		);

		$venmo_payment_url_display = sprintf(
			'<span>venmo.com/</span>&ZeroWidthSpace;<span>%s?</span>&ZeroWidthSpace;<span>txn=pay&</span>&ZeroWidthSpace;<span>amount=%s&</span>&ZeroWidthSpace;<span>note=%s</span>',
			rawurlencode( $this->store_venmo_username ),
			rawurlencode( $order->get_total() ),
			rawurlencode( 'order ' . $order->get_id() )
		);

		return $venmo_payment_url_display;
	}
}
