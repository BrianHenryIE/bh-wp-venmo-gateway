<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       example.com
 * @since      1.0.0
 *
 * @package    BrianHenryIE\WC_Venmo_Gateway
 * @subpackage BrianHenryIE\WC_Venmo_Gateway/admin
 */

namespace BrianHenryIE\WC_Venmo_Gateway\Includes;

use BrianHenryIE\WC_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\API\API_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;


class Cron {

	use LoggerAwareTrait;

	const CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK = 'bh_wc_venmo_gateway_check_for_payment_emails';

	/**
	 * @var Settings_Interface
	 */
	protected Settings_Interface $settings;

	/**
	 * @var API_Interface
	 */
	protected API_Interface $api;

	/**
	 * Cron_Jobs constructor.
	 *
	 * @param API_Interface      $api
	 * @param Settings_Interface $settings
	 * @param LoggerInterface    $logger
	 */
	public function __construct( API_Interface $api, Settings_Interface $settings, LoggerInterface $logger ) {

		$this->logger   = $logger;
		$this->settings = $settings;
		$this->api      = $api;
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
