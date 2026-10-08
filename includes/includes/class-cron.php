<?php
/**
 *
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Includes;

use BrianHenryIE\WP_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\API\API_Interface;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerAwareTrait;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerInterface;


class Cron {
	use LoggerAwareTrait;

	const CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK = 'bh_wp_venmo_gateway_check_for_payment_emails';

	/**
	 * Single event, scheduled when a Venmo order is placed, to fetch the customer's name from their public
	 * Venmo profile. Takes the order id as its argument.
	 *
	 * @see \BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Order::schedule_fetch_customer_venmo_profile()
	 * @see \BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Order::fetch_customer_venmo_profile()
	 */
	const FETCH_CUSTOMER_VENMO_PROFILE_CRON_HOOK = 'bh_wp_venmo_gateway_fetch_customer_venmo_profile';

	/**
	 * Single event, scheduled when a Venmo donation is created, to fetch the donor's name from their public
	 * Venmo profile. Takes the donation id as its argument.
	 *
	 * @see \BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\Venmo_Gateway::createPayment()
	 * @see \BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\Donor_Venmo_Profile::fetch_donor_venmo_profile()
	 */
	const FETCH_DONOR_VENMO_PROFILE_CRON_HOOK = 'bh_wp_venmo_gateway_fetch_donor_venmo_profile';

	/**
	 * Cron_Jobs constructor.
	 *
	 * @param API_Interface      $api
	 * @param Settings_Interface $settings
	 * @param LoggerInterface    $logger
	 */
	public function __construct(
		protected API_Interface $api,
		protected Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->logger = $logger;
	}

	/**
	 * Schedules or deletes the cron as per the settings.
	 *
	 * @hooked plugins_loaded
	 */
	public function add_cron_jon(): void {

		$should_be_enabled = $this->settings->is_imap_reconcile_enabled();

		if ( $should_be_enabled ) {

			if ( ! wp_next_scheduled( self::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK ) ) {
				wp_schedule_event( time(), 'hourly', self::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK );
			}
		} else {
			$timestamp = wp_next_scheduled( self::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK );

			if ( false === $timestamp ) {
				return;
			}

			wp_unschedule_event( $timestamp, self::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK );

		}
	}

	/**
	 * @hooked self::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK
	 */
	public function check_for_payment_emails(): void {

		$this->api->check_for_payment_emails();
	}
}
