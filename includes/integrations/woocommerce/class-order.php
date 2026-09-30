<?php
/**
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Venmo_Gateway\API\API_Interface;
use BrianHenryIE\WP_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\Includes\Cron;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerAwareTrait;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerInterface;
use BrianHenryIE\WP_Venmo_Gateway\Venmo_Username;
use WC_Order;
use WC_Payment_Gateways;

/**
 * WooCommerce order hooks: admin display, and background tasks scheduled when a Venmo order is placed.
 */
class Order {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param API_Interface      $api      The plugin's API, to fetch Venmo profiles.
	 * @param Settings_Interface $settings The plugin settings.
	 * @param LoggerInterface    $logger   PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * When a Venmo order is placed (set to on-hold), schedule a background task to look up the customer's
	 * full name from their public Venmo profile.
	 *
	 * The customer only enters their username at checkout, but Venmo's payment emails name the payer, so
	 * the name is what a payment email can later be matched against.
	 *
	 * @hooked woocommerce_order_status_changed
	 * @see WC_Order::status_transition()
	 *
	 * @param int    $order_id    The order id.
	 * @param string $status_from The previous status.
	 * @param string $status_to   The new status.
	 */
	public function schedule_fetch_customer_venmo_profile( $order_id, $status_from, $status_to ): void {

		if ( 'on-hold' !== $status_to ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			return;
		}

		if ( ! in_array( $order->get_payment_method(), $this->settings->get_payment_method_ids(), true ) ) {
			return;
		}

		if ( '' === $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true ) ) {
			return;
		}

		if ( '' !== $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_DISPLAY_NAME_META_KEY, true ) ) {
			return;
		}

		$args = array( (int) $order_id );

		if ( false !== wp_next_scheduled( Cron::FETCH_CUSTOMER_VENMO_PROFILE_CRON_HOOK, $args ) ) {
			return;
		}

		wp_schedule_single_event( time(), Cron::FETCH_CUSTOMER_VENMO_PROFILE_CRON_HOOK, $args );

		$this->logger->debug( 'Scheduled fetch of customer Venmo profile for order ' . $order_id . '.', array( 'order_id' => $order_id ) );
	}

	/**
	 * Background task: fetch the customer's public Venmo profile and record their full name in the order meta.
	 *
	 * @hooked bh_wp_venmo_gateway_fetch_customer_venmo_profile
	 * @see Cron::FETCH_CUSTOMER_VENMO_PROFILE_CRON_HOOK
	 * @see self::schedule_fetch_customer_venmo_profile()
	 *
	 * @param int $order_id The order id.
	 */
	public function fetch_customer_venmo_profile( int $order_id ): void {

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			$this->logger->debug( 'Order ' . $order_id . ' not found when fetching customer Venmo profile.', array( 'order_id' => $order_id ) );
			return;
		}

		$username = $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true );

		if ( ! is_string( $username ) || '' === $username ) {
			$this->logger->debug( 'Order ' . $order_id . ' has no customer Venmo username.', array( 'order_id' => $order_id ) );
			return;
		}

		$profile = $this->api->get_venmo_profile( $username );

		if ( is_null( $profile ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: the customer's Venmo username. */
					__( 'Could not find a Venmo profile for @%s.', 'bh-wp-venmo-gateway' ),
					$username
				)
			);
			$this->logger->info(
				'No Venmo profile found for @' . $username . ' (order ' . $order_id . ').',
				array(
					'order_id' => $order_id,
					'username' => $username,
				)
			);
			return;
		}

		$order->update_meta_data( Venmo_Gateway::CUSTOMER_VENMO_DISPLAY_NAME_META_KEY, $profile->display_name );
		$order->add_order_note(
			sprintf(
				/* translators: 1: the customer's Venmo username, 2: the customer's name on their Venmo profile. */
				__( 'Venmo profile @%1$s is %2$s.', 'bh-wp-venmo-gateway' ),
				$username,
				$profile->display_name
			)
		);
		$order->save();

		$this->logger->info(
			'Recorded Venmo profile name "' . $profile->display_name . '" for @' . $username . ' on order ' . $order_id . '.',
			array(
				'order_id'     => $order_id,
				'username'     => $username,
				'display_name' => $profile->display_name,
			)
		);
	}

	/**
	 * When an order is  created (set to on-hold), schedule to check for emails in five minutes.
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

		$customer_venmo_username = $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY );
		$address                 = 'Venmo: ' . Venmo_Username::for_display( $customer_venmo_username );

		return $address;
	}
}
