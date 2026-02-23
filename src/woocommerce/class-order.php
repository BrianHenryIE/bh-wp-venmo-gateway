<?php

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WC_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\Includes\Cron;
use BrianHenryIE\WC_Venmo_Gateway\Psr\Log\LoggerAwareTrait;
use WC_Order;
use WC_Payment_Gateways;

class Order {

	use LoggerAwareTrait;

	protected Settings_Interface $settings;

	public function __construct( $settings, $logger ) {
		$this->settings = $settings;
		$this->setLogger( $logger );
	}

	/**
	 * When an order is  created (set to on-hold), schedule to check for emails in five mintues.
	 *
	 * @hooked woocommerce_order_status_changed
	 *
	 * @param int    $order_id
	 * @param string $status_from
	 * @param string $status_to
	 */
	public function schedule_email_check( $order_id, $status_from, $status_to ): void {

		if ( ! $this->settings->is_imap_reconcile_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( ! in_array( $payment_method, $this->settings->get_payment_method_ids() ) ) {
			return;
		}

		if ( 'on-hold' === $status_to ) {

			$timestamp = wp_next_scheduled( Cron::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK );
			if ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, Cron::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK );
			}

			wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), Cron::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK );
		}
	}

	/**
	 * Displays the user's Venmo username instead of their billing address.
	 *
	 * @hooked woocommerce_order_get_formatted_billing_address
	 * @see WC_Order::get_formatted_billing_address()
	 *
	 * @param string   $formatted_address
	 * @param string[] $raw_address
	 * @param WC_Order $order
	 * @return mixed
	 */
	public function admin_view_billing_address( string $formatted_address, array $raw_address, WC_Order $order ) {

		global $post;

		// e.g. admin order screen will have $post.
		if ( is_null( $post ) ) {
			return $formatted_address;
		}

		// TODO: On the Thank You page this fails.
		if ( ! is_admin() ) {
			return $formatted_address;
		}

		$order = wc_get_order( $post->ID );

		if ( ! ( $order instanceof WC_Order ) ) {
			return $formatted_address;
		}

		$payment_gateways = WC_Payment_Gateways::instance()->payment_gateways();

		if ( ! isset( $payment_gateways[ $order->get_payment_method() ] ) ) {
			return $formatted_address;
		}

		$payment_method_instance = $payment_gateways[ $order->get_payment_method() ];

		if ( ! ( $payment_method_instance instanceof Venmo_Gateway ) ) {
			return $formatted_address;
		}

		$venmo_username = $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY );
		$address        = 'Venmo: ' . $venmo_username;

		return $address;
	}
}
